<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\BookCatalogController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MagazineCatalogController;
use App\Http\Controllers\MagazineController;
use App\Http\Controllers\MagazineIssueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/arama', [SearchController::class, 'index'])->name('arama');
Route::get('/kitaplar', [BookCatalogController::class, 'index'])->name('kitaplar.index');
Route::get('/kitaplar/{book:slug}', [BookController::class, 'show'])->name('kitaplar.show');
Route::get('/kitaplar/{book:slug}/oku/{chapterNumber?}', [BookController::class, 'read'])->name('kitaplar.oku');
Route::get('/dergiler', [MagazineCatalogController::class, 'index'])->name('dergiler.index');
Route::get('/dergiler/{magazineIssue}', [MagazineIssueController::class, 'show'])->name('dergiler.show');
Route::get('/dergi/{magazine:slug}', [MagazineController::class, 'show'])->name('dergi.show');
Route::get('/makaleler/{article:slug}', [ArticleController::class, 'show'])->name('makaleler.show');
// Faz G3: sözlükler ve maddeleri ("Sözlüğe Dair" — okurken tıklanan kavram buraya gelir).
Route::get('/sozlukler', [DictionaryController::class, 'index'])->name('sozlukler.index');
Route::get('/sozlukler/{book:slug}/{entry:slug}', [DictionaryController::class, 'entry'])->name('sozlukler.madde')->scopeBindings();
Route::get('/abonelik', [SubscriptionController::class, 'index'])->name('abonelik');
// Faz F2: gömülü belgeyi site içinde gösterme — erişim içeriği okuyabilmeye bağlı (ziyaretçi ücretsiz kitabı okuyabilir).
Route::get('/belge/{document}', [DocumentController::class, 'show'])->name('belgeler.goster');

Route::get('/dashboard', function () {
    return redirect(auth()->user()?->redirectPath() ?? '/');
})->middleware(['auth'])->name('dashboard');

// Filament paneli 2026-09-28'de kaldırıldı — eski /admin yer imleri kullanıcının kendi paneline düşsün.
Route::get('/admin/{path?}', fn () => redirect(auth()->user()?->redirectPath() ?? route('login')))
    ->where('path', '.*');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/panel.php';
