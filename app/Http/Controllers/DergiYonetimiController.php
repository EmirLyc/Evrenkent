<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\MagazineIssue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DergiYonetimiController extends Controller
{
    /**
     * Makale Havuzu sekmeleri — mockup'taki 5 sekmeyle birebir eşleşir. "Kabul Edilen"
     * hem Onaylandı hem Yayında olanları kapsar (mockup'ta ikisi ayrı değil).
     *
     * @return array<string, array{label: string, statuses: array<ContentStatus>|null}>
     */
    private function articleTabs(): array
    {
        return [
            'tumu' => ['label' => 'Tümü', 'statuses' => null],
            'incelenmeyi-bekleyen' => ['label' => 'İncelenmeyi Bekleyen', 'statuses' => [ContentStatus::Gonderildi, ContentStatus::Incelemede]],
            'revizyon-istenen' => ['label' => 'Revizyon İstenen', 'statuses' => [ContentStatus::RevizyonIstendi]],
            'kabul-edilen' => ['label' => 'Kabul Edilen', 'statuses' => [ContentStatus::Onaylandi, ContentStatus::Yayinda]],
            'reddedilen' => ['label' => 'Reddedilen', 'statuses' => [ContentStatus::Reddedildi]],
        ];
    }

    /**
     * Editörün kendi sayılarına bağlı tüm makaleler (başka editörünkiler hiç görünmez).
     */
    private function articlesQuery()
    {
        return Article::whereHas('magazineIssue', fn ($q) => $q->where('editor_id', auth()->id()))
            ->with(['author', 'magazineIssue', 'categories']);
    }

    public function index(): View
    {
        $editor = auth()->user();

        // Aktif Sayı: henüz yayında olmayan, en son güncellenen sayı. Editörün elinde
        // birden fazla "hazırlanan" sayı olabilir (taslak+gönderilmiş+onaylanmış vb.) —
        // hangisiyle en son ilgilenmişse o gösterilir.
        $activeIssue = $editor->editedMagazineIssues()
            ->where('status', '!=', ContentStatus::Yayinda)
            ->with('articles')
            ->latest('updated_at')
            ->first();

        $checklist = [];
        $progress = 0;

        if ($activeIssue) {
            $hasArticles = $activeIssue->articles->isNotEmpty();
            // Hiç makale yokken "bekleyen inceleme yok" vakumsal olarak doğru olur (boş
            // kümede her koşul sağlanır) — bu yüzden makale olmadan bu madde işaretlenmez.
            $pendingReview = $hasArticles && $activeIssue->articles->whereIn('status', [ContentStatus::Gonderildi, ContentStatus::Incelemede])->isEmpty();

            $checklist = [
                ['label' => 'Kapak', 'done' => filled($activeIssue->cover_image)],
                ['label' => 'Editör Yazısı', 'done' => filled($activeIssue->editor_note)],
                ['label' => 'Makaleler', 'done' => $hasArticles, 'meta' => $activeIssue->articles->count().' makale'],
                ['label' => 'İnceleme', 'done' => $pendingReview],
                ['label' => 'Onaya Gönderildi', 'done' => in_array($activeIssue->status, [ContentStatus::Gonderildi, ContentStatus::Onaylandi, ContentStatus::Yayinda], true)],
            ];

            $progress = (int) round(collect($checklist)->where('done', true)->count() / count($checklist) * 100);
        }

        $pendingReviewCount = $this->articlesQuery()->whereIn('status', [ContentStatus::Gonderildi, ContentStatus::Incelemede])->count();
        $revisionRequestedCount = $this->articlesQuery()->where('status', ContentStatus::RevizyonIstendi)->count();

        $recentArticles = $this->articlesQuery()->latest('created_at')->take(5)->get();

        $recentIssues = $editor->editedMagazineIssues()->latest('publish_date')->take(5)->get();

        return view('panel.dergi.index', compact(
            'activeIssue', 'checklist', 'progress',
            'pendingReviewCount', 'revisionRequestedCount',
            'recentArticles', 'recentIssues'
        ));
    }

    public function sayilarim(Request $request): View
    {
        $status = ContentStatus::tryFrom((string) $request->query('durum'));

        $issues = auth()->user()->editedMagazineIssues()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('updated_at')
            ->get();

        return view('panel.dergi.sayilarim', compact('issues', 'status'));
    }

    public function makaleHavuzu(Request $request): View
    {
        $tabs = $this->articleTabs();
        $activeTab = $request->query('durum', 'tumu');

        if (! array_key_exists($activeTab, $tabs)) {
            $activeTab = 'tumu';
        }

        $statuses = $tabs[$activeTab]['statuses'];

        $articles = $this->articlesQuery()
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('panel.dergi.makale-havuzu', compact('articles', 'tabs', 'activeTab'));
    }

    public function yayinTakvimi(): View
    {
        $issues = auth()->user()->editedMagazineIssues()
            ->orderByDesc('publish_date')
            ->orderByDesc('created_at')
            ->get();

        return view('panel.dergi.yayin-takvimi', compact('issues'));
    }

    /**
     * Sayı oluşturma/düzenleme formunun validasyon kuralları — ikisi de aynı alanları kullanır.
     *
     * @return array<string, array<mixed>>
     */
    private function sayiValidationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'issue_number' => ['required', 'integer', 'min:1'],
            'editor_note' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'publish_date' => ['nullable', 'date'],
        ];
    }

    public function yeniSayiForm(): View
    {
        $this->authorize('create', MagazineIssue::class);

        // Faz E: sayı, editörün Süper Admin tarafından atandığı dergilerden birine açılır.
        return view('panel.dergi.sayi-form', [
            'magazineIssue' => null,
            'magazines' => auth()->user()->editedMagazines()->orderBy('name')->get(),
        ]);
    }

    public function storeSayi(Request $request): RedirectResponse
    {
        $this->authorize('create', MagazineIssue::class);

        $data = $request->validate([
            ...$this->sayiValidationRules(),
            // Sadece editörü olduğu dergiler — başka bir derginin sayısını açamaz.
            'magazine_id' => ['required', Rule::in(auth()->user()->editedMagazines()->pluck('id'))],
        ], ['magazine_id.in' => 'Sadece editörü olduğunuz bir dergiye sayı açabilirsiniz.']);

        if ($request->hasFile('cover_image')) {
            // x-magazine-cover bileşeni bu disk/dizinden okuyor. Disk adı config'ten (covers_disk).
            $data['cover_image'] = $request->file('cover_image')->store('covers/magazine-issues', config('filesystems.covers_disk'));
        }

        $issue = auth()->user()->editedMagazineIssues()->create([
            ...$data,
            'status' => ContentStatus::Taslak,
        ]);

        return redirect()->route('panel.dergi.sayilarim.duzenle', $issue)->with('status', 'Sayı oluşturuldu.');
    }

    public function sayiDuzenleForm(MagazineIssue $magazineIssue): View
    {
        $this->authorize('update', $magazineIssue);

        return view('panel.dergi.sayi-form', ['magazineIssue' => $magazineIssue]);
    }

    public function updateSayi(Request $request, MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('update', $magazineIssue);

        $data = $request->validate($this->sayiValidationRules());

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('covers/magazine-issues', config('filesystems.covers_disk'));
        } else {
            unset($data['cover_image']);
        }

        $magazineIssue->update($data);

        return redirect()->route('panel.dergi.sayilarim.duzenle', $magazineIssue)->with('status', 'Sayı güncellendi.');
    }

    /**
     * Editör kendi taslak sayısını siler (policy: sadece Taslak durumundaki kendi
     * sayısı — onaya gönderilmiş/yayındaki bir sayı silinemez). Süper Admin'in
     * AdminMagazineIssueController::destroy'daki aynı deseniyle tutarlı: kapak
     * görseli de (varsa) diskten temizlenir.
     */
    public function destroySayi(MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('delete', $magazineIssue);

        if ($magazineIssue->cover_image) {
            Storage::disk(config('filesystems.covers_disk'))->delete($magazineIssue->cover_image);
        }

        $magazineIssue->delete();

        return redirect()->route('panel.dergi.sayilarim')->with('status', 'Sayı silindi.');
    }

    public function gonderSayi(MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('submit', $magazineIssue);

        $magazineIssue->update(['status' => ContentStatus::Gonderildi]);

        $magazineIssue->reviews()->create([
            'reviewer_id' => auth()->id(),
            'action' => 'gonderildi',
            'note' => 'Dergi Editörü tarafından Süper Admin onayına gönderildi.',
        ]);

        return back()->with('status', 'Sayı Süper Admin onayına gönderildi.');
    }

    public function makaleGoster(Article $article): View
    {
        $this->authorize('view', $article);

        $article->load(['author', 'magazineIssue', 'categories']);

        return view('panel.dergi.makale-goster', compact('article'));
    }

    public function inceleMakale(Article $article): RedirectResponse
    {
        $this->authorize('review', $article);

        $article->update(['status' => ContentStatus::Incelemede]);

        $article->reviews()->create([
            'reviewer_id' => auth()->id(),
            'action' => 'incelemede',
            'note' => 'Dergi Editörü tarafından incelendi, Süper Admin onayına hazır.',
        ]);

        return back()->with('status', 'Makale incelendi, Süper Admin onayına gönderildi.');
    }
}
