<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Support\WorkOutline;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function show(Article $article): View
    {
        abort_unless($article->isVisibleTo(auth()->user()), 404);

        $article->load(['author', 'magazineIssue.magazine', 'documents']);
        // Okuma modunun İçindekiler çekmecesi (Faz H1).
        $contents = WorkOutline::for($article)->contents;

        return view('articles.show', compact('article', 'contents'));
    }
}
