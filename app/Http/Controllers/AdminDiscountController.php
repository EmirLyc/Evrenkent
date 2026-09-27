<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Süper Admin'in İndirimler sayfası (2026-09-27 revizesi: "ücretler ve indirimler süper
 * admin tarafından yapılmalı"). Tek tek kitap indirimi zaten kitap formunda var — burada
 * indirimdeki kitapların toplu görünümü ve yüzde bazlı toplu kampanya uygulama.
 *
 * Kampanya fiyatı yine books.discount_price'a yazılıyor (+ isteğe bağlı discount_ends_at),
 * yani kart/sayfa/sepet/satın alma hepsi Book::priceFor() üzerinden otomatik görüyor.
 */
class AdminDiscountController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Book::class);

        return view('panel.admin.indirimler.index', [
            'discountedBooks' => Book::whereNotNull('discount_price')
                ->with('author')
                ->orderBy('title')
                ->get(),
            'categories' => Category::orderBy('name')->get(),
            // Toplu kampanyaya sadece yayındaki, ücretli kitaplar girebilir.
            'eligibleBooks' => Book::published()->where('price', '>', 0)->orderBy('title')->get(['id', 'title', 'price']),
        ]);
    }

    public function applyBulk(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Book::class);

        $data = $request->validate([
            'target' => ['required', 'in:tumu,kategori,secili'],
            'category_id' => ['required_if:target,kategori', 'nullable', 'exists:categories,id'],
            'book_ids' => ['required_if:target,secili', 'nullable', 'array'],
            'book_ids.*' => ['integer', 'exists:books,id'],
            'percent' => ['required', 'integer', 'min:1', 'max:90'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ], [
            'category_id.required_if' => 'Bir kategori seçin.',
            'book_ids.required_if' => 'En az bir kitap seçin.',
        ]);

        $books = Book::published()
            ->where('price', '>', 0)
            ->when($data['target'] === 'kategori', fn ($query) => $query->whereHas('categories', fn ($q) => $q->where('categories.id', $data['category_id'])))
            ->when($data['target'] === 'secili', fn ($query) => $query->whereIn('id', $data['book_ids']))
            ->get();

        foreach ($books as $book) {
            $book->update([
                'discount_price' => round((float) $book->price * (100 - $data['percent']) / 100, 2),
                'discount_ends_at' => $data['ends_at'] ?? null,
            ]);
        }

        return redirect()->route('panel.adminpanel.indirimler.index')->with('status', $books->isEmpty()
            ? 'Seçilen hedefte yayında ve ücretli bir kitap yok, indirim uygulanmadı.'
            : "{$books->count()} kitaba %{$data['percent']} indirim uygulandı.");
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $book->update(['discount_price' => null, 'discount_ends_at' => null]);

        return redirect()->route('panel.adminpanel.indirimler.index')->with('status', "\"{$book->title}\" kitabının indirimi kaldırıldı.");
    }
}
