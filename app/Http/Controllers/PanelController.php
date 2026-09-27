<?php

namespace App\Http\Controllers;

use App\Models\Book;
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

    /**
     * Kitaplığım — kullanıcının satın aldığı, favorilediği ve okuma listesindeki
     * kitapların birleşik görünümü.
     */
    public function index(): View
    {
        $user = auth()->user();

        $purchasedIds = $user->purchases()->pluck('book_id');
        $favoritedIds = $user->favorites()->where('favoritable_type', Book::class)->pluck('favoritable_id');
        $readingItems = $user->readingListItems()->where('readable_type', Book::class)->get()->keyBy('readable_id');

        $bookIds = $purchasedIds->merge($favoritedIds)->merge($readingItems->keys())->unique();

        $items = Book::whereIn('id', $bookIds)->with(['author', 'categories'])->get()
            ->map(fn (Book $book) => (object) [
                'book' => $book,
                'purchased' => $purchasedIds->contains($book->id),
                'favorited' => $favoritedIds->contains($book->id),
                'readingItem' => $readingItems->get($book->id),
            ])
            ->sortByDesc(fn ($item) => optional($item->readingItem)->updated_at ?? $item->book->updated_at);

        return view('panel.kitapligim', compact('items'));
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
