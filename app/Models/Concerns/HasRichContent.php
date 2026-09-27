<?php

namespace App\Models\Concerns;

use App\Support\RichText;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * `content` sütunu zengin metin (Faz F1): her kayıtta RichText::normalize'dan geçiyor —
 * bizim editör, DOCX içe aktarma, Filament formu, factory/seeder fark etmeksizin DB'ye
 * sadece temizlenmiş HTML giriyor.
 */
trait HasRichContent
{
    protected function content(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => RichText::normalize($value));
    }

    /** Okuma sayfası HTML'i (dipnotlar numaralı). */
    public function renderedContent(): string
    {
        return RichText::render($this->content, $this->footnotePrefix());
    }

    protected function footnotePrefix(): string
    {
        return 'dn';
    }
}
