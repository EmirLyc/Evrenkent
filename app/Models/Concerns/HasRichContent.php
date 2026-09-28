<?php

namespace App\Models\Concerns;

use App\Models\Document;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;

/**
 * `content` sütunu zengin metin (Faz F1): her kayıtta RichText::normalize'dan geçiyor —
 * bizim editör, DOCX/EPUB içe aktarma, factory/seeder fark etmeksizin DB'ye
 * sadece temizlenmiş HTML giriyor.
 */
trait HasRichContent
{
    protected function content(): Attribute
    {
        return Attribute::make(set: function (?string $value) {
            $html = RichText::normalize($value);

            // Arama düz metin üzerinde (bkz. RichText::plainText, SearchController).
            return ['content' => $html, 'content_text' => RichText::plainText($html)];
        });
    }

    /** Okuma sayfası HTML'i (dipnotlar numaralı, gömülü belgeler kar tanesi ikonu). */
    public function renderedContent(): string
    {
        return RichText::render($this->content, $this->footnotePrefix(), $this->contentDocuments(), $this->renderContext());
    }

    /**
     * Eser geneli bağlam (Faz G2): başlık numaraları, içindekiler, kaynak numaraları —
     * bkz. WorkOutline. Varsayılan boş (içerik kendi başına render edilir).
     *
     * @return array<string, mixed>
     */
    protected function renderContext(): array
    {
        return [];
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
