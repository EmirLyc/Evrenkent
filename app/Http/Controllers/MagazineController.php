<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use Illuminate\View\View;

/**
 * Herkese açık dergi sayfası (Faz E) — derginin tanıtımı, editörü, yakında çıkacak ve
 * yayınlanmış sayıları. Taslak/onay bekleyen sayılar burada görünmez.
 */
class MagazineController extends Controller
{
    public function show(Magazine $magazine): View
    {
        $magazine->load('editor');

        return view('magazines.magazine', [
            'magazine' => $magazine,
            'issues' => $magazine->issues()->published()->with('magazine')
                ->withCount(['articles' => fn ($q) => $q->where('status', ContentStatus::Yayinda)])
                ->latest('publish_date')->get(),
            'upcomingIssues' => MagazineIssue::upcoming()->where('magazine_id', $magazine->id)->with('magazine')->get(),
        ]);
    }
}
