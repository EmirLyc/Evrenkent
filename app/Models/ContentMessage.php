<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Esere bağlı yazışma (Faz G1, Taslaklarım'daki "Sohbet / Mesajlar"): yazar ile eseri
 * inceleyenler (Süper Admin; makalede sayının dergi editörü) arasında.
 */
class ContentMessage extends Model
{
    protected $fillable = ['user_id', 'body'];

    public function messageable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
