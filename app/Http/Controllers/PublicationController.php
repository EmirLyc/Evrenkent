<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\MagazineIssue;
use App\Rules\RichTextContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicationController extends Controller
{
    public function index(): View
    {
        return $this->listView('Yayınlarım', null, 'panel.yayinlarim.index');
    }

    public function taslaklarim(): View
    {
        return $this->listView('Taslaklarım', ContentStatus::Taslak, 'panel.yayinlarim.taslaklarim', showActions: true);
    }

    public function gonderilenler(): View
    {
        return $this->listView(
            'Gönderilenler',
            [ContentStatus::Gonderildi, ContentStatus::Incelemede],
            'panel.yayinlarim.gonderilenler'
        );
    }

    public function geriDonenler(): View
    {
        // Kalıcı reddedilenler de burada (kırmızı rozet + gerekçe); düzenleme/gönderme kapalı, silinebilir.
        return $this->listView('Geri Dönenler', [ContentStatus::RevizyonIstendi, ContentStatus::Reddedildi], 'panel.yayinlarim.geri-donenler', showActions: true);
    }

    public function yayinlananlar(): View
    {
        return $this->listView('Yayınlananlar', ContentStatus::Yayinda, 'panel.yayinlarim.yayinlananlar');
    }

    public function istatistiklerim(): View
    {
        return view('panel.placeholder', [
            'title' => 'İstatistiklerim',
            'message' => 'Yayın istatistikleri (okunma, satış, gelir) yakında burada olacak.',
        ]);
    }

    public function yeniTaslakForm(): View
    {
        return view('panel.yayinlarim.yeni', ['magazineIssues' => $this->assignableMagazineIssues()]);
    }

    /**
     * Bir makalenin gönderilebileceği dergi sayıları — yazarın atandığı dergilerin
     * (Faz E: "dergide yazarı süper admin yapacak") henüz yayınlanmamış sayıları.
     * Önceden her yazar açık olan her sayıya gönderebiliyordu.
     *
     * @return Collection<int, MagazineIssue>
     */
    private function assignableMagazineIssues()
    {
        // Yayındaki ve kalıcı reddedilmiş sayılara makale gönderilemez.
        return MagazineIssue::whereNotIn('status', [ContentStatus::Yayinda, ContentStatus::Reddedildi])
            ->whereIn('magazine_id', auth()->user()->authoredMagazines()->pluck('magazines.id'))
            ->with('magazine')
            ->orderBy('title')
            ->get();
    }

    public function editBook(Book $book): View
    {
        $this->authorize('update', $book);

        $categories = Category::orderBy('name')->get();

        return view('panel.yayinlarim.kitap-duzenle', compact('book', 'categories'));
    }

    public function updateBook(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            // Fiyat bilerek yok: yazar fiyat belirlemez/önermez (2026-09-27 revizesi),
            // Süper Admin onay aşamasında belirler (bkz. ContentApprovalController::approveBook).
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'page_count' => ['nullable', 'integer', 'min:0'],
            // document_count / video_count bilerek yok: metinden otomatik (Book::refreshContentCounts).
            'map_count' => ['nullable', 'integer', 'min:0'],
            'author_note_count' => ['nullable', 'integer', 'min:0'],
            'source_count' => ['nullable', 'integer', 'min:0'],
            // Yazarın önerdiği hedef yayın tarihi — kesinleşmiş bir taahhüt değil,
            // admin onaylarken bunu görüp değiştirebilir/kesinleştirebilir.
            'scheduled_publish_at' => ['nullable', 'date', 'after:now'],
        ]);

        $book->categories()->sync($data['categories'] ?? []);

        $book->update([
            'title' => $data['title'],
            'description' => $data['body'],
            'page_count' => $data['page_count'] ?? null,
            'map_count' => $data['map_count'] ?? null,
            'author_note_count' => $data['author_note_count'] ?? null,
            'source_count' => $data['source_count'] ?? null,
            'scheduled_publish_at' => $data['scheduled_publish_at'] ?? null,
        ]);

        return redirect()->route($this->listRouteFor($book->status))->with('status', 'Kitap güncellendi.');
    }

    public function editArticle(Article $article): View
    {
        $this->authorize('update', $article);

        $magazineIssues = $this->assignableMagazineIssues();

        // Makalenin şu an bağlı olduğu sayı arada Yayında'ya geçmiş olsa bile
        // (nadir ama mümkün) seçim listesinde kaybolmasın diye ekleniyor.
        if ($article->magazineIssue && ! $magazineIssues->contains('id', $article->magazine_issue_id)) {
            $magazineIssues = $magazineIssues->push($article->magazineIssue)->sortBy('title')->values();
        }

        return view('panel.yayinlarim.makale-duzenle', compact('article', 'magazineIssues'));
    }

    public function updateArticle(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('update', $article);

        // Mevcut sayısı listede olmasa da (arada yayına girmiş olabilir) korunabilsin.
        $allowedIssueIds = $this->assignableMagazineIssues()->pluck('id')->push($article->magazine_issue_id)->filter();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', new RichTextContent],
            'magazine_issue_id' => ['required', Rule::in($allowedIssueIds)],
        ], $this->issueMessages());

        $article->update([
            'title' => $data['title'],
            'content' => $data['body'],
            'magazine_issue_id' => $data['magazine_issue_id'],
        ]);

        return redirect()->route($this->listRouteFor($article->status))->with('status', 'Makale güncellendi.');
    }

    /**
     * @return array<string, string>
     */
    private function issueMessages(): array
    {
        return ['magazine_issue_id.in' => 'Bu sayıya makale gönderemezsiniz — sadece atandığınız dergilerin açık sayılarına gönderebilirsiniz.'];
    }

    /**
     * Düzenleme sonrası hangi listeye dönüleceğini kaydın durumuna göre belirler
     * (Taslak -> Taslaklarım, Revizyon İstendi -> Geri Dönenler).
     */
    private function listRouteFor(ContentStatus $status): string
    {
        return $status === ContentStatus::RevizyonIstendi
            ? 'panel.yayinlarim.geri-donenler'
            : 'panel.yayinlarim.taslaklarim';
    }

    public function storeTaslak(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:kitap,makale'],
            'title' => ['required', 'string', 'max:255'],
            // Kitapta düz metin açıklama, makalede zengin metin içerik (Faz F1).
            'body' => ['required', 'string', ...($request->input('type') === 'makale' ? [new RichTextContent] : [])],
            // Sadece yazarın atandığı dergilerin açık sayıları — istek elle düzenlenip başka
            // bir derginin sayısına gönderilemesin.
            'magazine_issue_id' => ['required_if:type,makale', 'nullable', Rule::in($this->assignableMagazineIssues()->pluck('id'))],
        ], $this->issueMessages());

        $user = $request->user();
        $slug = Str::slug($data['title']).'-'.Str::random(6);

        if ($data['type'] === 'kitap') {
            $this->authorize('create', Book::class);

            Book::create([
                'author_id' => $user->id,
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['body'],
                // Fiyat Süper Admin'in onayına kadar 0 kalır (sütun varsayılanı); onay
                // aşamasında fiyat zorunlu olduğu için bu hâliyle yayına çıkamaz.
                'status' => ContentStatus::Taslak,
            ]);
        } else {
            $this->authorize('create', Article::class);

            Article::create([
                'author_id' => $user->id,
                'title' => $data['title'],
                'slug' => $slug,
                'content' => $data['body'],
                'magazine_issue_id' => $data['magazine_issue_id'],
                'status' => ContentStatus::Taslak,
            ]);
        }

        return redirect()->route('panel.yayinlarim.taslaklarim')->with('status', 'Taslak oluşturuldu.');
    }

    /**
     * Yazar kendi taslağını siler (policy: sadece Taslak durumundaki kendi
     * kitabı — onaya gönderilmiş/yayındaki bir kitap silinemez). Taslak
     * aşamasındaki bir kitabın kapak görseli olması beklenmez (kapak yükleme
     * sadece Süper Admin formunda var) ama savunmacı olarak yine de temizleniyor.
     */
    public function destroyBook(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        if ($book->cover_image) {
            Storage::disk(config('filesystems.covers_disk'))->delete($book->cover_image);
        }

        $book->delete();

        return redirect()->route('panel.yayinlarim.taslaklarim')->with('status', 'Kitap silindi.');
    }

    /**
     * Yazar kendi taslak makalesini siler (policy: sadece Taslak durumundaki
     * kendi makalesi).
     */
    public function destroyArticle(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return redirect()->route('panel.yayinlarim.taslaklarim')->with('status', 'Makale silindi.');
    }

    public function submitBook(Book $book): RedirectResponse
    {
        $this->authorize('submit', $book);

        $book->update(['status' => ContentStatus::Gonderildi]);

        $book->reviews()->create([
            'reviewer_id' => auth()->id(),
            'action' => 'gonderildi',
            'note' => 'Yazar tarafından Süper Admin onayına gönderildi.',
        ]);

        return back()->with('status', 'Kitap onaya gönderildi.');
    }

    public function submitArticle(Article $article): RedirectResponse
    {
        $this->authorize('submit', $article);

        $article->update(['status' => ContentStatus::Gonderildi]);

        $article->reviews()->create([
            'reviewer_id' => auth()->id(),
            'action' => 'gonderildi',
            'note' => $article->magazine_issue_id
                ? 'Yazar tarafından Dergi Editörüne gönderildi.'
                : 'Yazar tarafından gönderildi.',
        ]);

        return back()->with('status', 'Makale gönderildi.');
    }

    /**
     * @param  ContentStatus|array<ContentStatus>|null  $status
     */
    private function listView(string $title, ContentStatus|array|null $status, string $view, bool $showActions = false): View
    {
        $user = auth()->user();

        $books = $user->books()
            ->with(['reviews' => fn ($query) => $query->latest()])
            ->when($status, fn ($query) => $query->whereIn('status', is_array($status) ? $status : [$status]))
            ->latest()
            ->get();

        $articles = $user->articles()
            ->with(['reviews' => fn ($query) => $query->latest()])
            ->when($status, fn ($query) => $query->whereIn('status', is_array($status) ? $status : [$status]))
            ->latest()
            ->get();

        return view($view, compact('title', 'books', 'articles', 'showActions'));
    }
}
