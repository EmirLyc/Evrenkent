<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\ReadingStatus;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PanelController extends Controller
{
    /**
     * Bu alanların (Yardım, İletişim) henüz gerçek bir veri modeli yok —
     * sadece sayfa iskeleti/boş-durum olarak kuruluyor.
     */
    private function placeholder(string $title, string $message): View
    {
        return view('panel.placeholder', compact('title', 'message'));
    }

    public const LIBRARY_SORTS = [
        'eklenen' => 'Son Eklenen',
        'okunan' => 'Son Okunan',
        'ad' => 'Kitap Adı',
        'yazar' => 'Yazar',
    ];

    /**
     * Kitaplığım (mockup 4.1) — satın alınan / kitaplığa eklenen, favorilenen ve okuma
     * listesindeki eserler tek listede. Her eserde okuma durumu: "%45 okundu" (sayfalı okumada
     * ulaşılan yer), "Okundu · tarih", yoksa kitaplığa nasıl girdiği (satın alındı, favori…).
     * Izgara / Liste görünümü ve sıralama.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $purchases = $user->purchases()->get()->keyBy('book_id');
        $favorites = $user->favorites()->where('favoritable_type', Book::class)->get()->keyBy('favoritable_id');
        $readingItems = $user->readingListItems()->where('readable_type', Book::class)->get()->keyBy('readable_id');

        $bookIds = $purchases->keys()->merge($favorites->keys())->merge($readingItems->keys())->unique();

        $items = Book::whereIn('id', $bookIds)->with(['author', 'categories'])->get()
            ->map(function (Book $book) use ($user, $purchases, $favorites, $readingItems) {
                $purchase = $purchases->get($book->id);
                $favorite = $favorites->get($book->id);
                $readingItem = $readingItems->get($book->id);

                $item = (object) [
                    'book' => $book,
                    'purchase' => $purchase,
                    'favorited' => $favorite !== null,
                    'readingItem' => $readingItem,
                    // Okuma sayfasıyla aynı kural (Book::isReadableBy) — satın almalar zaten elde.
                    'readable' => $book->author_id === $user->id
                        || ($book->status === ContentStatus::Yayinda && ($book->price <= 0 || $purchase !== null)),
                    // Kitaplığa ilk giriş anı: satın alma, favori ya da okuma listesi — hangisi önceyse.
                    'addedAt' => collect([$purchase?->purchased_at, $favorite?->created_at, $readingItem?->created_at])->filter()->min(),
                    'lastReadAt' => $readingItem?->updated_at,
                ];

                return $this->withLibraryStatus($item);
            });

        $sort = array_key_exists($request->query('sirala'), self::LIBRARY_SORTS) ? $request->query('sirala') : 'eklenen';
        $items = match ($sort) {
            'okunan' => $items->sortByDesc(fn ($item) => $item->lastReadAt?->getTimestamp() ?? 0),
            'ad' => $items->sortBy(fn ($item) => Str::lower($item->book->title), SORT_LOCALE_STRING),
            'yazar' => $items->sortBy(fn ($item) => Str::lower($item->book->author?->name ?? ''), SORT_LOCALE_STRING),
            default => $items->sortByDesc(fn ($item) => $item->addedAt?->getTimestamp() ?? 0),
        };

        return view('panel.kitapligim', [
            'items' => $items->values(),
            'sort' => $sort,
            'view' => $request->query('gorunum') === 'izgara' ? 'izgara' : 'liste',
        ]);
    }

    /**
     * Satırdaki okuma durumu ve birincil düğme (mockup 4.1): "%45 okundu" + çubuk, "Okundu ·
     * tarih", yoksa kitaplığa nasıl girdiği. Okunamıyorsa (satın alınmamış, yayından kalkmış)
     * düğme tanıtım sayfasına gider.
     */
    private function withLibraryStatus(object $item): object
    {
        $reading = $item->readingItem;
        $date = fn ($value) => $value?->translatedFormat('j F Y');

        [$icon, $text, $sub, $percent, $label] = match (true) {
            $reading?->status === ReadingStatus::Tamamlandi => ['check-circle', 'Okundu', $date($reading->completed_at), null, 'Tekrar Oku'],
            $reading && $reading->progress > 0 => ['book-open', '%'.$reading->progress.' okundu', null, $reading->progress, 'Okumaya Devam Et'],
            $reading !== null => ['queue-list', 'Okuma listende', $date($reading->created_at), null, 'Okumaya Başla'],
            $item->purchase && (float) $item->purchase->amount <= 0 => ['plus-circle', 'Kitaplığa eklendi', $date($item->purchase->purchased_at), null, 'Oku'],
            $item->purchase !== null => ['check-badge', 'Satın alındı', $date($item->purchase->purchased_at), null, 'Oku'],
            default => ['heart', 'Favorilerde', $date($item->addedAt), null, 'İncele'],
        };

        $item->status = ['icon' => $icon, 'text' => $text, 'sub' => $sub, 'percent' => $percent];
        $item->actionLabel = $item->readable ? $label : 'İncele';
        $item->actionUrl = $item->readable ? route('kitaplar.oku', $item->book) : route('kitaplar.show', $item->book);

        return $item;
    }

    public function yardim(): View
    {
        return $this->placeholder('Yardım Merkezi', 'Yardım içerikleri yakında burada olacak.');
    }

    public function iletisim(): View
    {
        return $this->placeholder('İletişim', 'İletişim formu yakında eklenecek.');
    }
}
