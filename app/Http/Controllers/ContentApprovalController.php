<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Notifications\ContentApproved;
use App\Notifications\ContentPublished;
use App\Notifications\ContentRevisionRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Süper Admin'in içerik onay akışı (Kitap/Dergi Sayısı/Makale onayla/reddet/yayınla) —
 * Filament'teki BookResource/ArticleResource/MagazineIssueResource'daki approve/reject/
 * publish action'larının birebir aynısı (aynı policy'ler, aynı 3 adım: durum güncelle +
 * ContentReview kaydı + bildirim), sadece kendi panelimizde. Filament resource'ları
 * silinmedi/değişmedi — bu, onlarla paralel çalışan ikinci bir arayüz.
 */
class ContentApprovalController extends Controller
{
    use Concerns\ResolvesBookPrice;

    private const ACTIONABLE_STATUSES = [
        ContentStatus::Gonderildi,
        ContentStatus::Incelemede,
        ContentStatus::Onaylandi,
    ];

    public function index(Request $request): View
    {
        $tab = $request->query('tur', 'kitaplar');

        $books = Book::whereIn('status', self::ACTIONABLE_STATUSES)->with('author')->latest('updated_at')->get();
        $issues = MagazineIssue::whereIn('status', self::ACTIONABLE_STATUSES)->with('editor')->latest('updated_at')->get();
        $articles = Article::whereIn('status', self::ACTIONABLE_STATUSES)->with('author')->latest('updated_at')->get();

        return view('panel.admin.onaylar.index', [
            'tab' => in_array($tab, ['kitaplar', 'dergiler', 'makaleler'], true) ? $tab : 'kitaplar',
            'books' => $books,
            'issues' => $issues,
            'articles' => $articles,
        ]);
    }

    // --- Kitap ---------------------------------------------------------

    public function approveBookForm(Book $book): View
    {
        $this->authorize('approve', $book);

        return view('panel.admin.onaylar.onayla', [
            'title' => $book->title,
            'backRoute' => route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar']),
            'submitRoute' => route('panel.adminpanel.onaylar.kitap.onayla', $book),
            'showScheduledPublishAt' => true,
            'scheduledPublishAt' => $book->scheduled_publish_at,
            // Fiyatı sadece Süper Admin belirler (2026-09-27 revizesi) — yazar girmediği için
            // yeni kitaplarda 0 gelir, form o durumda boş açılır ki bilinçli doldurulsun.
            'showPrice' => true,
            'price' => (float) $book->price > 0 ? $book->price : null,
            'discountPrice' => $book->discount_price,
        ]);
    }

    public function approveBook(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('approve', $book);

        $data = $request->validate([
            'scheduled_publish_at' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_free' => ['nullable', 'boolean'],
        ]);
        $data = $this->resolveBookPrice($request, $data);

        // Kitabın zaten bir kampanya indirimi varsa yeni fiyat ondan yüksek olmalı.
        if ($book->discount_price !== null && ! $request->boolean('is_free') && (float) $data['price'] <= (float) $book->discount_price) {
            throw ValidationException::withMessages([
                'price' => 'Fiyat, kitabın mevcut indirimli fiyatından ('.number_format($book->discount_price, 2, ',', '.').' TL) yüksek olmalı.',
            ]);
        }

        $book->update(array_merge(
            ['status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => $data['scheduled_publish_at'] ?? null],
            array_intersect_key($data, array_flip(['price', 'discount_price', 'discount_ends_at'])),
        ));
        $this->recordReview($book, 'onaylandi');
        $book->author->notify(new ContentApproved($book));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar'])
            ->with('status', 'Kitap onaylandı.');
    }

    public function rejectBookForm(Book $book): View
    {
        $this->authorize('reject', $book);

        return view('panel.admin.onaylar.reddet', [
            'title' => $book->title,
            'backRoute' => route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar']),
            'submitRoute' => route('panel.adminpanel.onaylar.kitap.reddet', $book),
        ]);
    }

    public function rejectBook(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('reject', $book);

        $data = $request->validate(['note' => ['required', 'string']]);

        $book->update(['status' => ContentStatus::RevizyonIstendi]);
        $this->recordReview($book, 'revizyon_istendi', $data['note']);
        $book->author->notify(new ContentRevisionRequested($book, $data['note']));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar'])
            ->with('status', 'Kitap revizyona gönderildi.');
    }

    public function publishBook(Book $book): RedirectResponse
    {
        $this->authorize('publish', $book);

        $book->update(['status' => ContentStatus::Yayinda, 'published_at' => now()]);
        $this->recordReview($book, 'yayinda');
        $book->author->notify(new ContentPublished($book));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar'])
            ->with('status', 'Kitap yayınlandı.');
    }

    // --- Dergi Sayısı ----------------------------------------------------

    public function approveIssue(MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('approve', $magazineIssue);

        $magazineIssue->update(['status' => ContentStatus::Onaylandi]);
        $this->recordReview($magazineIssue, 'onaylandi');
        $magazineIssue->editor->notify(new ContentApproved($magazineIssue));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler'])
            ->with('status', 'Sayı onaylandı.');
    }

    public function rejectIssueForm(MagazineIssue $magazineIssue): View
    {
        $this->authorize('reject', $magazineIssue);

        return view('panel.admin.onaylar.reddet', [
            'title' => $magazineIssue->title,
            'backRoute' => route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler']),
            'submitRoute' => route('panel.adminpanel.onaylar.dergi.reddet', $magazineIssue),
        ]);
    }

    public function rejectIssue(Request $request, MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('reject', $magazineIssue);

        $data = $request->validate(['note' => ['required', 'string']]);

        $magazineIssue->update(['status' => ContentStatus::RevizyonIstendi]);
        $this->recordReview($magazineIssue, 'revizyon_istendi', $data['note']);
        $magazineIssue->editor->notify(new ContentRevisionRequested($magazineIssue, $data['note']));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler'])
            ->with('status', 'Sayı revizyona gönderildi (Dergi Editörüne döndü).');
    }

    public function publishIssue(MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('publish', $magazineIssue);

        $magazineIssue->update([
            'status' => ContentStatus::Yayinda,
            'publish_date' => $magazineIssue->publish_date ?? now()->toDateString(),
        ]);
        $this->recordReview($magazineIssue, 'yayinda');
        $magazineIssue->editor->notify(new ContentPublished($magazineIssue));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler'])
            ->with('status', 'Sayı yayınlandı.');
    }

    // --- Makale ----------------------------------------------------------

    public function approveArticle(Article $article): RedirectResponse
    {
        $this->authorize('approve', $article);

        $article->update(['status' => ContentStatus::Onaylandi]);
        $this->recordReview($article, 'onaylandi');
        $article->author->notify(new ContentApproved($article));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler'])
            ->with('status', 'Makale onaylandı.');
    }

    public function rejectArticleForm(Article $article): View
    {
        $this->authorize('reject', $article);

        return view('panel.admin.onaylar.reddet', [
            'title' => $article->title,
            'backRoute' => route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler']),
            'submitRoute' => route('panel.adminpanel.onaylar.makale.reddet', $article),
        ]);
    }

    public function rejectArticle(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('reject', $article);

        $data = $request->validate(['note' => ['required', 'string']]);

        $article->update(['status' => ContentStatus::RevizyonIstendi]);
        $this->recordReview($article, 'revizyon_istendi', $data['note']);
        $article->author->notify(new ContentRevisionRequested($article, $data['note']));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler'])
            ->with('status', 'Makale revizyona gönderildi.');
    }

    public function publishArticle(Article $article): RedirectResponse
    {
        $this->authorize('publish', $article);

        $article->update(['status' => ContentStatus::Yayinda, 'published_at' => now()]);
        $this->recordReview($article, 'yayinda');
        $article->author->notify(new ContentPublished($article));

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler'])
            ->with('status', 'Makale yayınlandı.');
    }

    private function recordReview(Book|Article|MagazineIssue $record, string $action, ?string $note = null): void
    {
        $record->reviews()->create([
            'reviewer_id' => auth()->id(),
            'action' => $action,
            'note' => $note,
        ]);
    }
}
