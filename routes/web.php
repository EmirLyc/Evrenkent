<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\BookCatalogController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MagazineCatalogController;
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
Route::get('/makaleler/{article:slug}', [ArticleController::class, 'show'])->name('makaleler.show');
Route::get('/abonelik', [SubscriptionController::class, 'index'])->name('abonelik');

Route::get('/dashboard', function () {
    return redirect(auth()->user()?->redirectPath() ?? '/');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/panel.php';
