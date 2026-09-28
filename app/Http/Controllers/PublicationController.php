<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicationController extends Controller
{
    /**
     * Taslaklarım sekmeleri ("Yazarın Gözünden" 1.1). Yazarın gördüğü adlar: Gönderildi +
     * İncelemede = "İncelemede", Revizyon İstendi = "Düzeltme İstendi", Onaylandı = "Kabul
     * Edildi". Kalıcı reddedilenlerin ayrı sekmesi yok (mockup'ta yok) — "Tümü"nde görünür.
     */
    public const TABS = [
        'tumu' => 'Tümü',
        'taslak' => 'Taslak',
        'incelemede' => 'İncelemede',
        'duzeltme' => 'Düzeltme İstendi',
        'kabul' => 'Kabul Edildi',
    ];

    public const SORTS = [
        'guncel' => 'Son güncellenen',
        'yeni' => 'Son oluşturulan',
        'baslik' => 'Başlık (A–Z)',
    ];

    private const PER_PAGE = 6;

    /**
     * Yayın Yönetimi 3 başlığa indi (2026-09-28): Taslaklarım / Yayınlananlar / İstatistiklerim.
     * "Yayınlarım" Yayınlananlar'la çakıştığı için kalktı; Gönderilenler ve Geri Dönenler
     * Taslaklarım'da durum sekmesi oldu. Eski adresler (yer imleri, eski bildirimler) yönleniyor.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('panel.yayinlarim.taslaklarim');
    }

    public function gonderilenler(): RedirectResponse
    {
        return redirect()->route('panel.yayinlarim.taslaklarim', ['durum' => 'incelemede']);
    }

    public function geriDonenler(): RedirectResponse
    {
        return redirect()->route('panel.yayinlarim.taslaklarim', ['durum' => 'duzeltme']);
    }

    /**
     * Taslaklarım: yayına girmemiş bütün eserler — kitap ve dergi yazısı tek listede, durum
     * sekmeleri (sayılarıyla), arama (başlık, kategori, tür), sıralama, ızgara/liste, sayfalama.
     */
    public function taslaklarim(Request $request): View
    {
        $all = $this->publications()->reject(fn (Model $item) => $item->status === ContentStatus::Yayinda);

        $counts = collect(self::TABS)->map(fn ($label, $key) => $key === 'tumu'
            ? $all->count()
            : $all->filter(fn (Model $item) => $item->authorStatusKey() === $key)->count());

        $tab = array_key_exists($request->query('durum'), self::TABS) ? $request->query('durum') : 'tumu';
        $items = $tab === 'tumu' ? $all : $all->filter(fn (Model $item) => $item->authorStatusKey() === $tab);

        return view('panel.yayinlarim.taslaklarim', [
            ...$this->listing($request, $items),
            'tab' => $tab,
            'counts' => $counts,
            'trashCount' => auth()->user()->books()->onlyTrashed()->count() + auth()->user()->articles()->onlyTrashed()->count(),
        ]);
    }

    public function yayinlananlar(Request $request): View
    {
        $items = $this->publications()->filter(fn (Model $item) => $item->status === ContentStatus::Yayinda);

        return view('panel.yayinlarim.yayinlananlar', $this->listing($request, $items));
    }

    /**
     * Kullanıcının bütün eserleri (kitap + makale), kartlarda gereken ilişkilerle.
     *
     * @return Collection<int, Book|Article>
     */
    private function publications(): Collection
    {
        $user = auth()->user();

        return $user->books()->with(['reviews', 'categories', 'latestChapter'])->get()
            ->concat($user->articles()->with(['reviews', 'categories', 'magazineIssue.magazine'])->get());
    }

    /**
     * Arama, sıralama, görünüm ve sayfalama — Taslaklarım ve Yayınlananlar ortak.
     *
     * @param  Collection<int, Book|Article>  $items
     * @return array<string, mixed>
     */
    private function listing(Request $request, Collection $items): array
    {
        $q = trim((string) $request->query('q'));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $items = $items->filter(fn (Model $item) => str_contains(mb_strtolower(implode(' ', [
                $item->title,
                $item->categories->pluck('name')->implode(' '),
                $item instanceof Book ? 'kitap' : 'dergi yazısı makale '.$item->magazineIssue?->magazine?->name,
            ])), $needle));
        }

        $sort = array_key_exists($request->query('sirala'), self::SORTS) ? $request->query('sirala') : 'guncel';
        $items = match ($sort) {
            'yeni' => $items->sortByDesc('created_at'),
            'baslik' => $items->sortBy(fn (Model $item) => mb_strtolower($item->title), SORT_LOCALE_STRING),
            default => $items->sortByDesc('updated_at'),
        };

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $items->values()->forPage($page, self::PER_PAGE)->values(),
            $items->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return [
            'items' => $paginator,
            'q' => $q,
            'sort' => $sort,
            'view' => $request->query('gorunum') === 'liste' ? 'liste' : 'izgara',
        ];
    }

    /** Gönderimi Gör / Detayları Gör / Yayın Sürecini Takip Et — eserin durumu ve yayın süreci. */
    public function bookDetail(Book $book): View
    {
        $this->authorize('converse', $book);

        return $this->detail($book->load(['reviews.reviewer', 'categories', 'latestChapter']));
    }

    public function articleDetail(Article $article): View
    {
        $this->authorize('converse', $article);

        return $this->detail($article->load(['reviews.reviewer', 'categories', 'magazineIssue.magazine']));
    }

    private function detail(Book|Article $item): View
    {
        return view('panel.yayinlarim.detay', ['item' => $item]);
    }

    /** Çöp kutusu: yazarın sildiği eserler — geri alınabilir ya da kalıcı silinebilir. */
    public function copKutusu(): View
    {
        $user = auth()->user();

        $items = $user->books()->onlyTrashed()->get()
            ->concat($user->articles()->onlyTrashed()->with('magazineIssue.magazine')->get())
            ->sortByDesc('deleted_at')
            ->values();

        return view('panel.yayinlarim.cop-kutusu', ['items' => $items]);
    }

    public function restoreBook(Book $book): RedirectResponse
    {
        return $this->restore($book);
    }

    public function restoreArticle(Article $article): RedirectResponse
    {
        return $this->restore($article);
    }

    private function restore(Book|Article $item): RedirectResponse
    {
        $this->authorize('restore', $item);

        $item->restore();

        return redirect()->route('panel.yayinlarim.cop-kutusu')->with('status', "\"{$item->title}\" Taslaklarım'a geri alındı.");
    }

    public function forceDeleteBook(Book $book): RedirectResponse
    {
        return $this->forceDelete($book);
    }

    public function forceDeleteArticle(Article $article): RedirectResponse
    {
        return $this->forceDelete($article);
    }

    /** Kalıcı silme: kapak ve belgeler (dosyalarıyla) model olaylarında temizleniyor. */
    private function forceDelete(Book|Article $item): RedirectResponse
    {
        $this->authorize('forceDelete', $item);

        $item->forceDelete();

        return redirect()->route('panel.yayinlarim.cop-kutusu')->with('status', "\"{$item->title}\" kalıcı olarak silindi.");
    }

    public function istatistiklerim(): View
    {
        return view('panel.placeholder', [
            'title' => 'İstatistiklerim',
            'message' => 'Yayın istatistikleri (okunma, satış, gelir) yakında burada olacak.',
        ]);
    }

    /**
     * Yazar kendi taslağını siler (policy: Taslak ya da kalıcı reddedilmiş kendi eseri).
     * Silme iki kademeli (2026-09-28): eser önce çöp kutusuna gider, oradan geri alınabilir
     * ya da kalıcı silinebilir — yanlışlıkla silinen bir eser kaybolmasın.
     */
    public function destroyBook(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()->route('panel.yayinlarim.taslaklarim')->with('status', "\"{$book->title}\" çöp kutusuna taşındı.");
    }

    public function destroyArticle(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return redirect()->route('panel.yayinlarim.taslaklarim')->with('status', "\"{$article->title}\" çöp kutusuna taşındı.");
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
}
