<?php

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Metne gömülü belgeler (Faz F2) — kitap ve makale. İçerik silinince belgeler de
 * tek tek siliniyor (morph ilişkisinde yabancı anahtar yok; Document::deleted dosyayı
 * diskten de kaldırıyor).
 */
trait HasDocuments
{
    public static function bootHasDocuments(): void
    {
        static::deleting(fn ($model) => $model->documents()->get()->each->delete());
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->orderBy('title');
    }
}
