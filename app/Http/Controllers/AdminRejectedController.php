<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Support\ContentReviewer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Süper Admin: kalıcı reddedilen tüm içerik (2026-09-27, karar A — "süper admin
 * reddettiklerini totalde görebilsin"). Kitap, dergi sayısı ve makale tek listede, en son
 * reddedilen üstte; her satırda ret gerekçesi, reddeden ve tarih. Yanlışlıkla reddedilen
 * içerik "Revizyona geri aç" ile sahibine döner.
 */
class AdminRejectedController extends Controller
{
    public function index(Request $request): View
    {
        $type = in_array($request->query('tur'), ['kitaplar', 'dergiler', 'makaleler'], true) ? $request->query('tur') : null;

        $load = fn ($query) => $query->where('status', ContentStatus::Reddedildi)
            ->with(['reviews' => fn ($reviews) => $reviews->where('action', 'reddedildi')->latest()->with('reviewer')])
            ->get();

        $items = collect()
            ->concat(! $type || $type === 'kitaplar' ? $load(Book::with('author')) : [])
            ->concat(! $type || $type === 'dergiler' ? $load(MagazineIssue::with(['editor', 'magazine'])) : [])
            ->concat(! $type || $type === 'makaleler' ? $load(Article::with(['author', 'magazineIssue'])) : [])
            ->map(fn (Model $item) => [
                'model' => $item,
                'kind' => match (true) {
                    $item instanceof Book => 'Kitap',
                    $item instanceof MagazineIssue => 'Dergi Sayısı',
                    default => 'Makale',
                },
                'owner' => $item instanceof MagazineIssue ? $item->editor : $item->author,
                'review' => $item->reviews->first(),
                'viewUrl' => match (true) {
                    $item instanceof Book => route('kitaplar.show', $item),
                    $item instanceof MagazineIssue => route('dergiler.show', $item),
                    default => route('makaleler.show', $item),
                },
                'reopenUrl' => match (true) {
                    $item instanceof Book => route('panel.adminpanel.reddedilenler.kitap.geri-ac', $item),
                    $item instanceof MagazineIssue => route('panel.adminpanel.reddedilenler.dergi.geri-ac', $item),
                    default => route('panel.adminpanel.reddedilenler.makale.geri-ac', $item),
                },
            ])
            ->sortByDesc(fn (array $row) => $row['review']?->created_at ?? $row['model']->updated_at)
            ->values();

        $counts = [
            'kitaplar' => Book::where('status', ContentStatus::Reddedildi)->count(),
            'dergiler' => MagazineIssue::where('status', ContentStatus::Reddedildi)->count(),
            'makaleler' => Article::where('status', ContentStatus::Reddedildi)->count(),
        ];

        return view('panel.admin.reddedilenler.index', compact('items', 'counts', 'type'));
    }

    public function reopenBook(Request $request, Book $book): RedirectResponse
    {
        return $this->reopen($request, $book, 'Kitap');
    }

    public function reopenIssue(Request $request, MagazineIssue $magazineIssue): RedirectResponse
    {
        return $this->reopen($request, $magazineIssue, 'Sayı');
    }

    public function reopenArticle(Request $request, Article $article): RedirectResponse
    {
        return $this->reopen($request, $article, 'Makale');
    }

    private function reopen(Request $request, Book|MagazineIssue|Article $content, string $label): RedirectResponse
    {
        abort_unless($content->status === ContentStatus::Reddedildi, 404);

        ContentReviewer::reopen($content, $request->user());

        return back()->with('status', "{$label} revizyona geri açıldı; sahibi düzenleyip yeniden gönderebilir.");
    }
}
