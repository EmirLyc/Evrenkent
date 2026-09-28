<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
use App\Models\DictionaryEntry;
use App\Models\MagazineIssue;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Kitap+Dergi+Makale üzerinde LIKE tabanlı arama (Scout/Meilisearch kurulu değil,
     * veri boyutu için şimdilik gerek yok). Başlık/yazar-editör adı/açıklama-içerik
     * üzerinde arar, sadece yayınlanmış içerikleri döner.
     */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        $books = collect();
        $dictionaries = collect();
        $entries = collect();
        $issues = collect();
        $articles = collect();

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';

            // Kitaplar ve sözlükler (Faz G3) aynı tabloda; sonuçta ayrı başlıklar.
            $works = Book::published()
                ->with('author')
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('author', fn ($q) => $q->where('name', 'like', $like));
                })
                ->latest('published_at')
                ->take(24)
                ->get();
            [$dictionaries, $books] = $works->partition(fn (Book $book) => $book->isDictionary());
            $books = $books->take(12)->values();
            $dictionaries = $dictionaries->take(12)->values();

            $entries = DictionaryEntry::availableTo(null)
                ->where('term_search', 'like', '%'.addcslashes(DictionaryEntry::searchable($q), '%_\\').'%')
                ->with('book.author')
                ->orderBy('term_search')
                ->take(12)
                ->get();

            $issues = MagazineIssue::published()
                ->with(['editor', 'magazine'])
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhereHas('editor', fn ($q) => $q->where('name', 'like', $like))
                        ->orWhereHas('magazine', fn ($q) => $q->where('name', 'like', $like));
                })
                ->latest('publish_date')
                ->take(12)
                ->get();

            $articles = Article::published()
                ->with(['author', 'magazineIssue'])
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        // HTML değil düz metin kopyası: etiket adları eşleşmesin, "Paşa'nın" bulunsun.
                        ->orWhere('content_text', 'like', $like)
                        ->orWhereHas('author', fn ($q) => $q->where('name', 'like', $like));
                })
                ->latest('published_at')
                ->take(12)
                ->get();
        }

        return view('search.index', compact('q', 'books', 'dictionaries', 'entries', 'issues', 'articles'));
    }
}
