<?php

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Metne gömülü belgeler (Faz F2) — kitap ve makale. İçerik kalıcı olarak silinince
 * belgeler de tek tek siliniyor (morph ilişkisinde yabancı anahtar yok; Document::deleted
 * dosyayı diskten de kaldırıyor). Çöp kutusuna atılan (soft delete, Faz G1) eserin
 * belgeleri geri alınabilsin diye yerinde kalıyor.
 */
trait HasDocuments
{
    public static function bootHasDocuments(): void
    {
        static::deleting(function ($model) {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->documents()->get()->each->delete();
            }
        });
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->orderBy('title');
    }
}
