<?php

namespace App\Notifications;

use App\Models\Book;
use App\Models\MagazineIssue;
use App\Notifications\Concerns\DescribesContent;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Kalıcı ret (2026-09-27) — içerik kapandı, düzenlenip tekrar gönderilemez. Bağlantı
 * düzenleme sayfasına değil (artık erişilemez) içeriğin listelendiği sayfaya gider.
 */
class ContentRejected extends Notification
{
    use DescribesContent, Queueable;

    public function __construct(protected Model $content, protected string $note) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->contentLabel($this->content).' reddedildi',
            'body' => "\"{$this->content->title}\" reddedildi: {$this->note}",
            // Eserde ret gerekçesinin göründüğü detay sayfası (Faz G1; önceden Geri Dönenler listesi).
            'url' => match (true) {
                $this->content instanceof MagazineIssue => route('panel.dergi.sayilarim'),
                $this->content instanceof Book => route('panel.yayinlarim.kitap.detay', $this->content),
                default => route('panel.yayinlarim.makale.detay', $this->content),
            },
        ];
    }
}
