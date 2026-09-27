<?php

use App\Http\Controllers\AdminBookController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDiscountController;
use App\Http\Controllers\AdminMagazineController;
use App\Http\Controllers\AdminPremiumController;
use App\Http\Controllers\AdminMagazineIssueController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\ContentApprovalController;
use App\Http\Controllers\DergiYonetimiController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocxImportController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReadingListController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('panel')->as('panel.')->group(function () {
    Route::get('/', [PanelController::class, 'index'])->name('index');
    Route::get('/aboneligim', [SubscriptionController::class, 'mine'])->name('aboneligim');
    Route::post('/abonelik', [SubscriptionController::class, 'store'])->name('abonelik.satin-al');
    Route::get('/yardim', [PanelController::class, 'yardim'])->name('yardim');
    Route::get('/iletisim', [PanelController::class, 'iletisim'])->name('iletisim');

    // Bildirimler: header'daki zil ikonu için — tüm girişli kullanıcılar (rol farketmez).
    Route::post('/bildirimler/{notification}/oku', [NotificationController::class, 'read'])->name('bildirimler.oku');
    Route::post('/bildirimler/tumunu-oku', [NotificationController::class, 'readAll'])->name('bildirimler.tumunu-oku');

    Route::get('/favorilerim', [FavoriteController::class, 'index'])->name('favorilerim');
    Route::post('/favoriler/kitap/{book}/toggle', [FavoriteController::class, 'toggleBook'])->name('favoriler.kitap.toggle');
    Route::delete('/favoriler/{favorite}', [FavoriteController::class, 'destroy'])->name('favoriler.sil');

    Route::get('/okuma-listem', [ReadingListController::class, 'okumaListem'])->name('okuma-listem');
    Route::get('/okuduklarim', [ReadingListController::class, 'okuduklarim'])->name('okuduklarim');
    Route::post('/okuma-listesi/kitap/{book}', [ReadingListController::class, 'addBook'])->name('okuma-listesi.kitap.ekle');
    Route::patch('/okuma-listesi/{readingListItem}/tamamla', [ReadingListController::class, 'complete'])->name('okuma-listesi.tamamla');
    Route::patch('/okuma-listesi/{readingListItem}/listeye-al', [ReadingListController::class, 'reopen'])->name('okuma-listesi.listeye-al');
    Route::delete('/okuma-listesi/{readingListItem}', [ReadingListController::class, 'destroy'])->name('okuma-listesi.sil');

    Route::get('/defterim', [NoteController::class, 'defterim'])->name('defterim');
    Route::get('/notlarim', [NoteController::class, 'notlarim'])->name('notlarim');
    Route::get('/alintilarim', [NoteController::class, 'alintilarim'])->name('alintilarim');
    Route::post('/notlar', [NoteController::class, 'store'])->name('notlar.ekle');
    Route::put('/notlar/{note}', [NoteController::class, 'update'])->name('notlar.guncelle');
    Route::delete('/notlar/{note}', [NoteController::class, 'destroy'])->name('notlar.sil');

    Route::get('/satin-aldiklarim', [PurchaseController::class, 'index'])->name('satin-aldiklarim');
    Route::post('/satin-al/{book}', [PurchaseController::class, 'store'])->name('satin-al');

    Route::get('/sepetim', [CartController::class, 'index'])->name('sepetim');
    Route::post('/sepet/kitap/{book}', [CartController::class, 'store'])->name('sepet.kitap.ekle');
    Route::delete('/sepet/kitap/{book}', [CartController::class, 'destroy'])->name('sepet.kitap.sil');
    Route::post('/sepet/checkout', [CartController::class, 'checkout'])->name('sepet.checkout');

    // Yayın Yönetimi: sadece Yazar rolündeki kullanıcılar erişebilir.
    Route::middleware('role:yazar')->prefix('yayinlarim')->as('yayinlarim.')->group(function () {
        Route::get('/', [PublicationController::class, 'index'])->name('index');
        Route::get('/taslaklarim', [PublicationController::class, 'taslaklarim'])->name('taslaklarim');
        Route::get('/taslaklarim/yeni', [PublicationController::class, 'yeniTaslakForm'])->name('taslaklarim.yeni');
        Route::post('/taslaklarim', [PublicationController::class, 'storeTaslak'])->name('taslaklarim.store');
        Route::get('/gonderilenler', [PublicationController::class, 'gonderilenler'])->name('gonderilenler');
        Route::get('/geri-donenler', [PublicationController::class, 'geriDonenler'])->name('geri-donenler');
        Route::get('/yayinlananlar', [PublicationController::class, 'yayinlananlar'])->name('yayinlananlar');
        Route::get('/istatistiklerim', [PublicationController::class, 'istatistiklerim'])->name('istatistiklerim');
        Route::post('/kitap/{book}/gonder', [PublicationController::class, 'submitBook'])->name('kitap.gonder');
        Route::post('/makale/{article}/gonder', [PublicationController::class, 'submitArticle'])->name('makale.gonder');
        Route::get('/kitap/{book}/duzenle', [PublicationController::class, 'editBook'])->name('kitap.duzenle');
        Route::put('/kitap/{book}', [PublicationController::class, 'updateBook'])->name('kitap.guncelle');
        Route::delete('/kitap/{book}', [PublicationController::class, 'destroyBook'])->name('kitap.sil');
        Route::get('/makale/{article}/duzenle', [PublicationController::class, 'editArticle'])->name('makale.duzenle');
        Route::put('/makale/{article}', [PublicationController::class, 'updateArticle'])->name('makale.guncelle');
        Route::delete('/makale/{article}', [PublicationController::class, 'destroyArticle'])->name('makale.sil');

        Route::get('/kitap/{book}/bolumler', [ChapterController::class, 'index'])->name('kitap.bolumler');
        Route::get('/kitap/{book}/bolumler/yeni', [ChapterController::class, 'create'])->name('kitap.bolumler.yeni');
        Route::post('/kitap/{book}/bolumler', [ChapterController::class, 'store'])->name('kitap.bolumler.store');
        Route::get('/kitap/{book}/bolumler/{chapter}/duzenle', [ChapterController::class, 'edit'])->name('kitap.bolumler.duzenle');
        Route::put('/kitap/{book}/bolumler/{chapter}', [ChapterController::class, 'update'])->name('kitap.bolumler.guncelle');
        Route::delete('/kitap/{book}/bolumler/{chapter}', [ChapterController::class, 'destroy'])->name('kitap.bolumler.sil');

        // Faz F1: Word'den aktarma — editöre önizleme (kaydetmez) ve kitabı bölümlere ayırarak ekleme.
        Route::post('/word-aktar', [DocxImportController::class, 'preview'])->name('word-aktar');
        Route::post('/kitap/{book}/bolumler/word-aktar', [DocxImportController::class, 'chapters'])->name('kitap.bolumler.word-aktar');

        // Faz F2: metne gömülü belgeler (PDF/görsel) — kitabın ve makalenin Belgeler sayfası.
        Route::get('/kitap/{book}/belgeler', [DocumentController::class, 'bookIndex'])->name('kitap.belgeler');
        Route::post('/kitap/{book}/belgeler', [DocumentController::class, 'bookStore'])->name('kitap.belgeler.store');
        Route::get('/makale/{article}/belgeler', [DocumentController::class, 'articleIndex'])->name('makale.belgeler');
        Route::post('/makale/{article}/belgeler', [DocumentController::class, 'articleStore'])->name('makale.belgeler.store');
        Route::put('/belgeler/{document}', [DocumentController::class, 'update'])->name('belgeler.guncelle');
        Route::delete('/belgeler/{document}', [DocumentController::class, 'destroy'])->name('belgeler.sil');
    });

    // Dergi Yönetimi: sadece Dergi Editörü rolündeki kullanıcılar erişebilir. Sayı
    // oluşturma/düzenleme/onaya gönderme ve makale inceleme burada gerçek — sadece
    // Süper Admin'in onayla/reddet/yayınla aksiyonları (policy'de zaten sadece ona
    // açık) Filament'te kalıyor.
    Route::middleware('role:dergi_editoru')->prefix('dergi')->as('dergi.')->group(function () {
        Route::get('/', [DergiYonetimiController::class, 'index'])->name('index');

        Route::get('/sayilarim', [DergiYonetimiController::class, 'sayilarim'])->name('sayilarim');
        Route::get('/sayilarim/yeni', [DergiYonetimiController::class, 'yeniSayiForm'])->name('sayilarim.yeni');
        Route::post('/sayilarim', [DergiYonetimiController::class, 'storeSayi'])->name('sayilarim.store');
        Route::get('/sayilarim/{magazineIssue}/duzenle', [DergiYonetimiController::class, 'sayiDuzenleForm'])->name('sayilarim.duzenle');
        Route::put('/sayilarim/{magazineIssue}', [DergiYonetimiController::class, 'updateSayi'])->name('sayilarim.guncelle');
        Route::delete('/sayilarim/{magazineIssue}', [DergiYonetimiController::class, 'destroySayi'])->name('sayilarim.sil');
        Route::post('/sayilarim/{magazineIssue}/gonder', [DergiYonetimiController::class, 'gonderSayi'])->name('sayilarim.gonder');

        Route::get('/makale-havuzu', [DergiYonetimiController::class, 'makaleHavuzu'])->name('makale-havuzu');
        Route::get('/makale-havuzu/{article}', [DergiYonetimiController::class, 'makaleGoster'])->name('makale-havuzu.goster');
        Route::post('/makale-havuzu/{article}/incele', [DergiYonetimiController::class, 'inceleMakale'])->name('makale-havuzu.incele');

        Route::get('/yayin-takvimi', [DergiYonetimiController::class, 'yayinTakvimi'])->name('yayin-takvimi');
    });

    // Süper Admin dashboard'u: gerçek verili özet. Kullanıcı/rol yönetimi ve
    // Kitaplar/Dergiler tam listeleri gibi henüz taşınmamış bölümler (adım adım
    // taşınıyor, bkz. UI_RESTYLE_NOTES.md) hâlâ Filament'e link veriyor —
    // içerik onay akışı (bkz. altındaki "onaylar" grubu) artık taşındı.
    Route::middleware('role:super_admin')->prefix('admin-panel')->as('adminpanel.')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index'])->name('index');
        Route::get('/yakinda/{section}', [SuperAdminController::class, 'placeholder'])->name('placeholder');

        // İçerik Onayları: Filament'teki BookResource/ArticleResource/MagazineIssueResource
        // approve/reject/publish action'larının kendi panelimizdeki paraleli.
        Route::prefix('onaylar')->as('onaylar.')->group(function () {
            Route::get('/', [ContentApprovalController::class, 'index'])->name('index');

            Route::get('/kitap/{book}/onayla', [ContentApprovalController::class, 'approveBookForm'])->name('kitap.onayla-form');
            Route::post('/kitap/{book}/onayla', [ContentApprovalController::class, 'approveBook'])->name('kitap.onayla');
            Route::get('/kitap/{book}/reddet', [ContentApprovalController::class, 'rejectBookForm'])->name('kitap.reddet-form');
            Route::post('/kitap/{book}/reddet', [ContentApprovalController::class, 'rejectBook'])->name('kitap.reddet');
            Route::post('/kitap/{book}/yayinla', [ContentApprovalController::class, 'publishBook'])->name('kitap.yayinla');

            Route::get('/dergi/{magazineIssue}/onayla', [ContentApprovalController::class, 'approveIssueForm'])->name('dergi.onayla-form');
            Route::post('/dergi/{magazineIssue}/onayla', [ContentApprovalController::class, 'approveIssue'])->name('dergi.onayla');
            Route::get('/dergi/{magazineIssue}/reddet', [ContentApprovalController::class, 'rejectIssueForm'])->name('dergi.reddet-form');
            Route::post('/dergi/{magazineIssue}/reddet', [ContentApprovalController::class, 'rejectIssue'])->name('dergi.reddet');
            Route::post('/dergi/{magazineIssue}/yayinla', [ContentApprovalController::class, 'publishIssue'])->name('dergi.yayinla');

            Route::get('/makale/{article}/onayla', [ContentApprovalController::class, 'approveArticleForm'])->name('makale.onayla-form');
            Route::post('/makale/{article}/onayla', [ContentApprovalController::class, 'approveArticle'])->name('makale.onayla');
            Route::get('/makale/{article}/reddet', [ContentApprovalController::class, 'rejectArticleForm'])->name('makale.reddet-form');
            Route::post('/makale/{article}/reddet', [ContentApprovalController::class, 'rejectArticle'])->name('makale.reddet');
            Route::post('/makale/{article}/yayinla', [ContentApprovalController::class, 'publishArticle'])->name('makale.yayinla');
        });

        // Kitaplar: Filament'teki BookResource'un list/create/edit/delete'inin
        // birebir aynısı (Faz 2 — bkz. UI_RESTYLE_NOTES.md).
        Route::prefix('kitaplar')->as('kitaplar.')->group(function () {
            Route::get('/', [AdminBookController::class, 'index'])->name('index');
            Route::get('/yeni', [AdminBookController::class, 'create'])->name('yeni');
            Route::post('/', [AdminBookController::class, 'store'])->name('store');
            Route::get('/{book}/duzenle', [AdminBookController::class, 'edit'])->name('duzenle');
            Route::put('/{book}', [AdminBookController::class, 'update'])->name('guncelle');
            Route::delete('/{book}', [AdminBookController::class, 'destroy'])->name('sil');
        });

        // Dergiler (Faz E): dergi tanımı + Süper Admin'in atadığı editör ve yazarlar.
        Route::prefix('dergiler')->as('dergiler.')->group(function () {
            Route::get('/', [AdminMagazineController::class, 'index'])->name('index');
            Route::get('/yeni', [AdminMagazineController::class, 'create'])->name('yeni');
            Route::post('/', [AdminMagazineController::class, 'store'])->name('store');
            Route::get('/{magazine}/duzenle', [AdminMagazineController::class, 'edit'])->name('duzenle');
            Route::put('/{magazine}', [AdminMagazineController::class, 'update'])->name('guncelle');
            Route::delete('/{magazine}', [AdminMagazineController::class, 'destroy'])->name('sil');
        });

        // Dergi Sayıları: Filament'teki MagazineIssueResource'un list/create/edit/delete'inin
        // karşılığı (Faz 3). Faz E'de "dergiler"den "sayilar"a taşındı — "Dergiler" artık dergi
        // tanımlarını yönetiyor, sayılar ayrı bir menüde.
        Route::prefix('sayilar')->as('sayilar.')->group(function () {
            Route::get('/', [AdminMagazineIssueController::class, 'index'])->name('index');
            Route::get('/yeni', [AdminMagazineIssueController::class, 'create'])->name('yeni');
            Route::post('/', [AdminMagazineIssueController::class, 'store'])->name('store');
            Route::get('/{magazineIssue}/duzenle', [AdminMagazineIssueController::class, 'edit'])->name('duzenle');
            Route::put('/{magazineIssue}', [AdminMagazineIssueController::class, 'update'])->name('guncelle');
            Route::delete('/{magazineIssue}', [AdminMagazineIssueController::class, 'destroy'])->name('sil');
        });

        // Kategoriler: Filament'teki CategoryResource'un list/create/edit/delete'inin
        // birebir aynısı (Faz 4 — bkz. UI_RESTYLE_NOTES.md).
        Route::prefix('kategoriler')->as('kategoriler.')->group(function () {
            Route::get('/', [AdminCategoryController::class, 'index'])->name('index');
            Route::get('/yeni', [AdminCategoryController::class, 'create'])->name('yeni');
            Route::post('/', [AdminCategoryController::class, 'store'])->name('store');
            Route::get('/{category}/duzenle', [AdminCategoryController::class, 'edit'])->name('duzenle');
            Route::put('/{category}', [AdminCategoryController::class, 'update'])->name('guncelle');
            Route::delete('/{category}', [AdminCategoryController::class, 'destroy'])->name('sil');
        });

        // İndirimler: indirimdeki kitapların listesi + yüzde bazlı toplu kampanya
        // (2026-09-27 revizesi, Faz B — bkz. UI_RESTYLE_NOTES.md).
        Route::prefix('indirimler')->as('indirimler.')->group(function () {
            Route::get('/', [AdminDiscountController::class, 'index'])->name('index');
            Route::post('/toplu', [AdminDiscountController::class, 'applyBulk'])->name('toplu');
            Route::delete('/{book}', [AdminDiscountController::class, 'destroy'])->name('kaldir');
        });

        // Premium Sistemi: plan fiyatları, premium indirim oranı, çalışma alanı kotaları
        // (2026-09-27 revizesi, Faz C).
        Route::get('/premium', [AdminPremiumController::class, 'edit'])->name('premium.edit');
        Route::put('/premium', [AdminPremiumController::class, 'update'])->name('premium.guncelle');

        // Kullanıcılar/Yazarlar/Dergi Editörleri (?rol= filtresiyle aynı liste) + Roller
        // ve Yetkiler: Filament'teki UserResource'un list/create/edit/delete'inin
        // birebir aynısı (Faz 5 — bkz. UI_RESTYLE_NOTES.md).
        Route::prefix('kullanicilar')->as('kullanicilar.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('/roller', [AdminUserController::class, 'roles'])->name('roller');
            Route::get('/yeni', [AdminUserController::class, 'create'])->name('yeni');
            Route::post('/', [AdminUserController::class, 'store'])->name('store');
            Route::get('/{user}/duzenle', [AdminUserController::class, 'edit'])->name('duzenle');
            Route::put('/{user}', [AdminUserController::class, 'update'])->name('guncelle');
            Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('sil');
        });
    });
});
