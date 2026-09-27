<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Notifications\ContentRejected;
use App\Notifications\ContentRevisionRequested;

/**
 * Onay ekranındaki olumsuz kararların tek adresi (2026-09-27, "Reddedilen" sekmesi kararı A):
 *
 *  - requestRevision(): "Revizyon iste" — içerik sahibine geri döner, düzeltip tekrar gönderebilir.
 *  - reject(): "Kalıcı olarak reddet" — içerik kapanır; sahibi görür ve silebilir, düzenleyip
 *    tekrar gönderemez. Süper Admin tüm reddedilenleri "Reddedilenler" sayfasında görür.
 *  - reopen(): Süper Admin'in yanlışlıkla reddettiği içeriği revizyona geri açması.
 *
 * Önceden "Reddet" her yerde revizyona düşürüyordu; Reddedildi durumu hiç atanmıyor, Dergi
 * Editörü'nün "Reddedilen" sekmesi hep boş kalıyordu. Kendi panelimiz ve Filament aynı
 * yoldan geçer (bkz. ContentPublisher — yayınlama tarafının eşi).
 */
class ContentReviewer
{
    public static function requestRevision(Book|Article|MagazineIssue $content, ?User $by, string $note): void
    {
        $content->update(['status' => ContentStatus::RevizyonIstendi]);
        self::record($content, 'revizyon_istendi', $by, $note);
        self::owner($content)?->notify(new ContentRevisionRequested($content, $note));
    }

    public static function reject(Book|Article|MagazineIssue $content, ?User $by, string $note): void
    {
        $content->update(['status' => ContentStatus::Reddedildi, 'scheduled_publish_at' => null]);
        self::record($content, 'reddedildi', $by, $note);
        self::owner($content)?->notify(new ContentRejected($content, $note));

        // Reddedilen sayının henüz yayınlanmamış makaleleri o sayıda mahsur kalmasın:
        // revizyona dönüp yazarları tarafından başka bir sayıya atanabilirler.
        if ($content instanceof MagazineIssue) {
            $content->articles()
                ->whereNotIn('status', [ContentStatus::Yayinda, ContentStatus::Reddedildi, ContentStatus::Taslak])
                ->get()
                ->each(fn (Article $article) => self::requestRevision(
                    $article,
                    $by,
                    "\"{$content->title}\" sayısı reddedildi. Makalenizi atandığınız başka bir sayıya taşıyıp yeniden gönderebilirsiniz."
                ));
        }
    }

    public static function reopen(Book|Article|MagazineIssue $content, User $by, ?string $note = null): void
    {
        self::requestRevision($content, $by, $note ?: 'Ret kararı geri alındı; içeriği düzenleyip yeniden gönderebilirsiniz.');
    }

    private static function owner(Book|Article|MagazineIssue $content): ?User
    {
        return $content instanceof MagazineIssue ? $content->editor : $content->author;
    }

    private static function record(Book|Article|MagazineIssue $content, string $action, ?User $by, ?string $note): void
    {
        $content->reviews()->create([
            'reviewer_id' => $by?->id,
            'action' => $action,
            'note' => $note,
        ]);
    }
}
