<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\MagazineIssue;
use Illuminate\View\View;

class MagazineIssueController extends Controller
{
    public function show(MagazineIssue $magazineIssue): View
    {
        $user = auth()->user();
        $isOwnerOrAdmin = $user && ($user->id === $magazineIssue->editor_id || $user->hasRole('super_admin'));

        // Zamanlanmış sayı (Onaylandı + yayın tarihi) kitaplardaki gibi herkese açık bir
        // tanıtım (Yakında Çıkacak) sayfası — makaleleri ise yayın anına kadar gizli.
        $isUpcoming = $magazineIssue->status === ContentStatus::Onaylandi && $magazineIssue->scheduled_publish_at !== null;

        abort_unless($magazineIssue->status === ContentStatus::Yayinda || $isUpcoming || $isOwnerOrAdmin, 404);

        $magazineIssue->load(['editor', 'magazine']);

        // Sahibi/Süper Admin önizlerken sayının içindeki taslak makaleleri de görebilir
        // (aksi halde onay bekleyen bir sayı hep boş görünürdü) — herkes için hâlâ sadece
        // yayınlanmış makaleler.
        $articles = $magazineIssue->articles()
            ->when(! $isOwnerOrAdmin, fn ($query) => $query->where('status', ContentStatus::Yayinda))
            ->with('author')
            ->get();

        return view('magazines.show', ['issue' => $magazineIssue, 'articles' => $articles, 'isUpcoming' => $isUpcoming]);
    }
}
