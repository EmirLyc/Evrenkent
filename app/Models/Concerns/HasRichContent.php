<?php

namespace App\Models\Concerns;

use App\Models\Document;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;

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

    /** Okuma sayfası HTML'i (dipnotlar numaralı, gömülü belgeler kar tanesi ikonu). */
    public function renderedContent(): string
    {
        return RichText::render($this->content, $this->footnotePrefix(), $this->contentDocuments());
    }

    /**
     * İçerikte yerleştirilebilecek belgeler (Faz F2) — bölümde kitabın, makalede
     * makalenin belgeleri. Başka bir içeriğin belgesi işaretlense bile çözülmez.
     *
     * @return Collection<int, Document>
     */
    protected function contentDocuments(): Collection
    {
        return collect();
    }

    protected function footnotePrefix(): string
    {
        return 'dn';
    }
}
