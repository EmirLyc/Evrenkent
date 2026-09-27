<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function show(Article $article): View
    {
        abort_unless($article->isVisibleTo(auth()->user()), 404);

        $article->load(['author', 'magazineIssue']);

        return view('articles.show', compact('article'));
    }
}
