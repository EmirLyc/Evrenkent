<?php

namespace App\Http\Controllers;

use App\Enums\BookShelf;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookCatalogController extends Controller
{
    public function index(Request $request): View
    {
        // Kategori bağlantısı (kitap tanıtım sayfasındaki tıklanabilir etiketler)
        // rafları geçersiz kılar — "Roman" etiketine tıklayan biri o kategorideki
        // tüm kitapları görmek ister, belirli bir rafı değil.
        if ($request->filled('kategori')) {
            $category = Category::where('slug', $request->query('kategori'))->firstOrFail();

            $books = $category->books()
                ->books()
                ->published()
                ->with('author')
                ->latest('published_at')
                ->paginate(18)
                ->withQueryString();

            return view('books.index', ['books' => $books, 'shelf' => null, 'category' => $category]);
        }

        $shelf = BookShelf::tryFrom((string) $request->query('raf')) ?? BookShelf::YeniCikanlar;

        $books = $shelf->query()->paginate(18)->withQueryString();

        return view('books.index', ['books' => $books, 'shelf' => $shelf, 'category' => null]);
    }
}
