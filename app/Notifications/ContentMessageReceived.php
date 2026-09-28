<?php

namespace App\Notifications;

use App\Models\ContentMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Esere bağlı yazışmada yeni mesaj (Faz G1) — header'daki zile düşer. */
class ContentMessageReceived extends Notification
{
    use Queueable;

    public function __construct(protected ContentMessage $message, protected string $url) {}

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
            'title' => "\"{$this->message->messageable->title}\" için yeni mesaj",
            'body' => $this->message->user->name.': '.Str::limit($this->message->body, 120),
            'url' => $this->url,
        ];
    }
}
