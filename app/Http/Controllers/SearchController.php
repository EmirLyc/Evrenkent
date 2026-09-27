<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
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
        $issues = collect();
        $articles = collect();

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';

            $books = Book::published()
                ->with('author')
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('author', fn ($q) => $q->where('name', 'like', $like));
                })
                ->latest('published_at')
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
                        ->orWhere('content', 'like', $like)
                        ->orWhereHas('author', fn ($q) => $q->where('name', 'like', $like));
                })
                ->latest('published_at')
                ->take(12)
                ->get();
        }

        return view('search.index', compact('q', 'books', 'issues', 'articles'));
    }
}
