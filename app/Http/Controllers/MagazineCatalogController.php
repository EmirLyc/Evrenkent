<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use Illuminate\View\View;

class MagazineCatalogController extends Controller
{
    public function index(): View
    {
        $issues = MagazineIssue::published()
            ->with(['editor', 'magazine'])
            ->withCount(['articles' => fn ($q) => $q->where('status', ContentStatus::Yayinda)])
            ->latest('publish_date')
            ->paginate(18);

        return view('magazines.index', [
            'issues' => $issues,
            // Zamanlanmış sayılar (Faz D) — geri sayımla ayrı bir satırda.
            'upcomingIssues' => MagazineIssue::upcoming()->with('magazine')->get(),
            // Dergiler (Faz E) — en az bir yayınlanmış ya da zamanlanmış sayısı olanlar;
            // henüz hiçbir şey yayınlamamış bir dergiyi okura göstermek anlamsız.
            // orWhere iç içe grupta: whereHas'ın "magazine_id = magazines.id" koşuluyla OR'lanıp
            // başka dergilerin sayılarıyla eşleşmesin.
            'magazines' => Magazine::whereHas('issues', fn ($q) => $q->where(fn ($q) => $q
                ->where('status', ContentStatus::Yayinda)
                ->orWhere(fn ($q) => $q->where('status', ContentStatus::Onaylandi)->whereNotNull('scheduled_publish_at'))))
                ->withCount(['issues' => fn ($q) => $q->where('status', ContentStatus::Yayinda)])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
