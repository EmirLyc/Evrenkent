<?php

namespace App\Http\Controllers;

use App\Models\MagazineIssue;
use Illuminate\View\View;

class MagazineCatalogController extends Controller
{
    public function index(): View
    {
        $issues = MagazineIssue::published()
            ->with('editor')
            ->withCount(['articles' => fn ($q) => $q->where('status', \App\Enums\ContentStatus::Yayinda)])
            ->latest('publish_date')
            ->paginate(18);

        return view('magazines.index', [
            'issues' => $issues,
            // Zamanlanmış sayılar (Faz D) — geri sayımla ayrı bir satırda.
            'upcomingIssues' => MagazineIssue::upcoming()->get(),
        ]);
    }
}
