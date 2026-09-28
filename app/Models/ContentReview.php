<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentReview extends Model
{
    protected $fillable = ['reviewable_type', 'reviewable_id', 'reviewer_id', 'action', 'note'];

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Yazarın gördüğü adım adı (Taslaklarım detayı ve yazışma zaman çizelgesi, Faz G1).
     *
     * @return array{label: string, icon: string, tone: string}
     */
    public function step(): array
    {
        return match ($this->action) {
            'gonderildi' => ['label' => 'Gönderildi', 'icon' => 'paper-airplane', 'tone' => 'sky'],
            'incelemede' => ['label' => 'Dergi editörü inceledi, Süper Admin onayına iletildi', 'icon' => 'magnifying-glass', 'tone' => 'sky'],
            'onaylandi' => ['label' => 'Kabul edildi', 'icon' => 'check-circle', 'tone' => 'emerald'],
            'revizyon_istendi' => ['label' => 'Düzeltme istendi', 'icon' => 'arrow-uturn-left', 'tone' => 'orange'],
            'reddedildi' => ['label' => 'Kalıcı olarak reddedildi', 'icon' => 'no-symbol', 'tone' => 'red'],
            'yayinda' => ['label' => 'Yayınlandı', 'icon' => 'globe-alt', 'tone' => 'slate'],
            default => ['label' => $this->action, 'icon' => 'information-circle', 'tone' => 'slate'],
        };
    }
}
