<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Support\ContentPublisher;
use App\Support\ContentReviewer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Süper Admin'in içerik onay akışı (Kitap/Dergi Sayısı/Makale onayla/reddet/yayınla),
 * kendi panelimizde. Yayınlama ve zamanlama ContentPublisher'dan geçiyor (zamanlayıcı da
 * aynı sınıfı kullanıyor).
 *
 * Faz D (2026-09-27 revizesi — "süper admin yayınla dediğinde vakit seçebilmeli"): onay
 * ekranında iki seçenek var — "Şimdi Yayınla" ya da "İleri Tarihte Yayınla" (Yakında
 * Çıkacaklar'da geri sayımla görünür). Önceki "önce Onayla, sonra ayrıca Yayınla" iki
 * adımı sadeleşti; "Yayınla" butonu sadece zaten Onaylandı durumunda bekleyenler için duruyor.
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
        $articles = Article::whereIn('status', self::ACTIONABLE_STATUSES)->with(['author', 'magazineIssue'])->latest('updated_at')->get();

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
            'showPublishMode' => true,
            // Yazarın önerdiği hedef tarih varsa "İleri tarihte" seçeneği onunla dolu gelir.
            'scheduledPublishAt' => $book->scheduled_publish_at?->isFuture() ? $book->scheduled_publish_at : null,
            // Fiyatı sadece Süper Admin belirler (Faz B) — yazar girmediği için yeni kitaplarda
            // 0 gelir, form o durumda boş açılır ki bilinçli doldurulsun.
            'showPrice' => true,
            'price' => (float) $book->price > 0 ? $book->price : null,
            'discountPrice' => $book->discount_price,
        ]);
    }

    public function approveBook(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('approve', $book);

        $data = $request->validate(array_merge($this->publishModeRules(), [
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_free' => ['nullable', 'boolean'],
        ]));
        $data = $this->resolveBookPrice($request, $data);

        // Kitabın zaten bir kampanya indirimi varsa yeni fiyat ondan yüksek olmalı.
        if ($book->discount_price !== null && ! $request->boolean('is_free') && (float) $data['price'] <= (float) $book->discount_price) {
            throw ValidationException::withMessages([
                'price' => 'Fiyat, kitabın mevcut indirimli fiyatından ('.number_format($book->discount_price, 2, ',', '.').' TL) yüksek olmalı.',
            ]);
        }

        $book->update(array_intersect_key($data, array_flip(['price', 'discount_price', 'discount_ends_at'])));

        return $this->publishOrSchedule($book, $data, 'kitaplar', 'Kitap');
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

        return $this->decide($request, $book, 'kitaplar', 'Kitap');
    }

    /** Zaten "Onaylandı" durumunda bekleyen (ör. zamanlanmış) bir kitabı hemen yayına alır. */
    public function publishBook(Book $book): RedirectResponse
    {
        $this->authorize('publish', $book);

        ContentPublisher::publishBook($book, auth()->user());

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar'])
            ->with('status', 'Kitap yayınlandı.');
    }

    // --- Dergi Sayısı ----------------------------------------------------

    public function approveIssueForm(MagazineIssue $magazineIssue): View
    {
        $this->authorize('approve', $magazineIssue);

        $approvedCount = $magazineIssue->articles()->where('status', ContentStatus::Onaylandi)->count();

        return view('panel.admin.onaylar.onayla', [
            'title' => $magazineIssue->title,
            'backRoute' => route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler']),
            'submitRoute' => route('panel.adminpanel.onaylar.dergi.onayla', $magazineIssue),
            'showPublishMode' => true,
            'scheduledPublishAt' => null,
            'publishNote' => "Sayıdaki {$approvedCount} onaylı makale de sayıyla aynı anda yayına girecek. Henüz onaylanmamış makaleler bu sayıyla yayınlanmaz; sonradan onaylanırsa tek başına yayınlanabilir.",
        ]);
    }

    public function approveIssue(Request $request, MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('approve', $magazineIssue);

        $data = $request->validate($this->publishModeRules());

        return $this->publishOrSchedule($magazineIssue, $data, 'dergiler', 'Sayı');
    }

    public function rejectIssueForm(MagazineIssue $magazineIssue): View
    {
        $this->authorize('reject', $magazineIssue);

        return view('panel.admin.onaylar.reddet', [
            'title' => $magazineIssue->title,
            'backRoute' => route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler']),
            'submitRoute' => route('panel.adminpanel.onaylar.dergi.reddet', $magazineIssue),
            'rejectNote' => $magazineIssue->articles()->whereNotIn('status', [ContentStatus::Yayinda, ContentStatus::Taslak, ContentStatus::Reddedildi])->exists()
                ? 'Kalıcı retle birlikte bu sayıya gönderilmiş makaleler revizyona döner; yazarları başka bir sayıya taşıyabilir.'
                : null,
        ]);
    }

    public function rejectIssue(Request $request, MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('reject', $magazineIssue);

        return $this->decide($request, $magazineIssue, 'dergiler', 'Sayı');
    }

    public function publishIssue(MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('publish', $magazineIssue);

        ContentPublisher::publishIssue($magazineIssue, auth()->user());

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler'])
            ->with('status', 'Sayı, onaylı makaleleriyle birlikte yayınlandı.');
    }

    // --- Makale ----------------------------------------------------------

    public function approveArticleForm(Article $article): View
    {
        $this->authorize('approve', $article);

        $issueIsLive = $article->magazineIssue?->status === ContentStatus::Yayinda;

        return view('panel.admin.onaylar.onayla', [
            'title' => $article->title,
            'backRoute' => route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler']),
            'submitRoute' => route('panel.adminpanel.onaylar.makale.onayla', $article),
            // Kural 3/4: sayısı yayında değilse makale tek başına yayınlanamaz, sadece onaylanır
            // ve sayıyla birlikte yayına girer; sayı zaten yayındaysa şimdi/ileri tarih seçilir.
            'showPublishMode' => $issueIsLive,
            'scheduledPublishAt' => null,
            'publishNote' => $issueIsLive
                ? null
                : 'Bu makalenin sayısı ("'.($article->magazineIssue?->title ?? '—').'") henüz yayında değil. Makale onaylanır ve sayı yayınlandığında onunla birlikte yayına girer.',
        ]);
    }

    public function approveArticle(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('approve', $article);

        if ($article->magazineIssue?->status === ContentStatus::Yayinda) {
            return $this->publishOrSchedule($article, $request->validate($this->publishModeRules()), 'makaleler', 'Makale');
        }

        ContentReviewer::approve($article, auth()->user(), 'Sayıyla birlikte yayınlanacak.');

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler'])
            ->with('status', 'Makale onaylandı — sayısı yayınlandığında birlikte yayına girecek.');
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

        return $this->decide($request, $article, 'makaleler', 'Makale');
    }

    public function publishArticle(Article $article): RedirectResponse
    {
        $this->authorize('publish', $article);

        ContentPublisher::publishArticle($article, auth()->user());

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler'])
            ->with('status', 'Makale yayınlandı.');
    }

    // --- Ortak ---------------------------------------------------------------

    /**
     * @return array<string, array<mixed>>
     */
    private function publishModeRules(): array
    {
        return [
            'publish_mode' => ['required', 'in:simdi,ileri'],
            'scheduled_publish_at' => ['nullable', 'required_if:publish_mode,ileri', 'date', 'after:now'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function publishOrSchedule(Book|MagazineIssue|Article $record, array $data, string $tab, string $label): RedirectResponse
    {
        if ($data['publish_mode'] === 'ileri') {
            $at = Carbon::parse($data['scheduled_publish_at']);
            ContentReviewer::approveAndPublish($record, auth()->user(), $at);
            $message = "{$label} onaylandı — {$at->translatedFormat('j F Y H:i')} tarihinde yayına girecek ve o zamana kadar Yakında Çıkacaklar'da görünecek.";
        } else {
            ContentReviewer::approveAndPublish($record, auth()->user());
            $message = $record instanceof MagazineIssue
                ? 'Sayı onaylandı ve onaylı makaleleriyle birlikte yayınlandı.'
                : "{$label} onaylandı ve yayınlandı.";
        }

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => $tab])->with('status', $message);
    }

    /**
     * Onay ekranındaki olumsuz karar (2026-09-27, karar A): "Revizyon iste" içeriği sahibine
     * geri gönderir, "Kalıcı olarak reddet" kapatır. İkisi de ContentReviewer'dan geçer.
     */
    private function decide(Request $request, Book|Article|MagazineIssue $content, string $tab, string $label): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:revizyon,ret'],
            'note' => ['required', 'string', 'max:2000'],
        ], [
            'decision.required' => 'Revizyon mu istiyorsunuz, kalıcı olarak mı reddediyorsunuz? Birini seçin.',
            'note.required' => 'Sahibinin gerekçeyi bilmesi için bir not yazın.',
        ]);

        if ($data['decision'] === 'ret') {
            ContentReviewer::reject($content, $request->user(), $data['note']);
            $message = "{$label} kalıcı olarak reddedildi. Reddedilenler sayfasından görebilirsiniz.";
        } else {
            ContentReviewer::requestRevision($content, $request->user(), $data['note']);
            $message = "{$label} revizyona gönderildi.";
        }

        return redirect()->route('panel.adminpanel.onaylar.index', ['tur' => $tab])->with('status', $message);
    }
}
