<?php

namespace App\Notifications\Concerns;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use Illuminate\Database\Eloquent\Model;

/**
 * Book/Article/MagazineIssue durum bildirimlerinin ortak kısmı: içerik türünün
 * etiketi ("Kitabınız"/"Makaleniz"/"Sayınız") ve alıcının gidebileceği bağlantı.
 */
trait DescribesContent
{
    protected function contentLabel(Model $content): string
    {
        return match (true) {
            $content instanceof Book => 'Kitabınız',
            $content instanceof Article => 'Makaleniz',
            $content instanceof MagazineIssue => 'Sayınız',
            default => 'İçeriğiniz',
        };
    }

    /**
     * Bildirimin gönderildiği andaki duruma göre: yayındaysa herkese açık sayfası, sahibi
     * düzenleyebiliyorsa (taslak / revizyon) düzenleme sayfası, diğer durumlarda (onaylandı,
     * zamanlandı) sahibinin listesi. Önceden hep düzenleme sayfasına gidiyordu — onaylanmış
     * ya da yayındaki içerikte o sayfa 403 veriyordu; sayıda ise Filament'e gidiyordu.
     */
    protected function contentUrl(Model $content): string
    {
        $status = $content->status ?? null;

        if ($status === ContentStatus::Yayinda) {
            return match (true) {
                $content instanceof Book => route('kitaplar.show', $content),
                $content instanceof Article => route('makaleler.show', $content),
                $content instanceof MagazineIssue => route('dergiler.show', $content),
                default => route('home'),
            };
        }

        $editable = in_array($status, [ContentStatus::Taslak, ContentStatus::RevizyonIstendi], true);

        return match (true) {
            $content instanceof Book => $editable ? route('panel.yayinlarim.kitap.duzenle', $content) : route('panel.yayinlarim.index'),
            $content instanceof Article => $editable ? route('panel.yayinlarim.makale.duzenle', $content) : route('panel.yayinlarim.index'),
            $content instanceof MagazineIssue => $editable ? route('panel.dergi.sayilarim.duzenle', $content) : route('panel.dergi.sayilarim'),
            default => route('home'),
        };
    }
}
