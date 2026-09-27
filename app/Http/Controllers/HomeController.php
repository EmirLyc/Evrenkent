<?php

namespace App\Http\Controllers;

use App\Enums\BookShelf;
use App\Models\Category;
use App\Models\MagazineIssue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Anasayfa: her tür içeriği tanıtan bir keşif sayfası (2026-09-27 toplantı revizesi —
     * önceden doğrudan Kitaplar sekmesi seçili açılıyordu). Mockup 1'deki tip anahtarı +
     * raf pilleri görünümü /kitaplar'a taşındı (bkz. BookCatalogController).
     */
    public function index(Request $request): View|RedirectResponse
    {
        // Eski tip anahtarı adresleri (/?tur=dergiler, /?raf=cok-satanlar) paylaşılmış ya da
        // yer imine eklenmiş olabilir — kırılmasınlar diye yeni yerlerine yönlendiriliyor.
        if ($request->query('tur') === 'dergiler') {
            return redirect()->route('dergiler.index');
        }

        if ($request->has('tur') || $request->has('raf')) {
            return redirect()->route('kitaplar.index', array_filter(['raf' => $request->query('raf')]));
        }

        // Yakında Çıkacaklar: zamanlanmış kitaplar ve dergi sayıları (Faz D) tarih sırasıyla karışık.
        $upcoming = BookShelf::YakindaCikacaklar->query()->take(6)->get()
            ->concat(MagazineIssue::upcoming()->with('magazine')->take(6)->get())
            ->sortBy('scheduled_publish_at')
            ->take(6)
            ->values();

        return view('home', [
            'newBooks' => BookShelf::YeniCikanlar->query()->take(6)->get(),
            'upcoming' => $upcoming,
            'editorsPicks' => BookShelf::EditorunSeckisi->query()->take(6)->get(),
            'newIssues' => MagazineIssue::published()->with('magazine')->latest('publish_date')->take(6)->get(),
            // Sadece en az bir yayındaki kitabı olan kategoriler — boş bir etikete tıklayıp
            // "bu kategoride kitap yok" sayfasına düşmek anlamsız olurdu.
            'categories' => Category::whereHas('books', fn ($query) => $query->published())
                ->withCount(['books' => fn ($query) => $query->published()])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
