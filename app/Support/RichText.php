<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Kitap bölümü ve makale içeriği (Faz F1, 2026-09-27 revizesi: "başlık, italik, dipnot
 * taşıyabilsin, DOCX'ten aktarılabilsin"). İçerik temizlenmiş HTML olarak saklanıyor.
 *
 * - normalize(): kayıt öncesi — düz metni paragraflara çevirir, HTML'i izinli etiketlere
 *   indirger (editör, DOCX içe aktarma, Filament ve eski düz metin kayıtlar hep buradan geçer).
 * - render(): okuma sayfası — dipnot işaretlerini numaralı üst simgelere ve sayfa sonundaki
 *   dipnot listesine çevirir.
 *
 * Dipnot saklama biçimi: <span data-footnote="Dipnot metni"></span> — metin işaretin olduğu
 * yerde duruyor, numaralar render sırasında sırayla veriliyor (dipnot eklenip silinince
 * numaraları elle düzeltmek gerekmiyor).
 */
class RichText
{
    /** Eski düz metin kaydı mı, HTML mi — blok etiketi yoksa düz metin sayılır. */
    public static function isHtml(?string $value): bool
    {
        return (bool) preg_match('/<(p|h[1-6]|ul|ol|li|blockquote|br|div|span|strong|em|b|i)\b/i', (string) $value);
    }

    public static function fromPlainText(string $text): string
    {
        $paragraphs = preg_split('/\R\s*\R/u', trim(str_replace("\r\n", "\n", $text))) ?: [];

        return collect($paragraphs)
            ->map(fn ($paragraph) => trim($paragraph))
            ->filter(fn ($paragraph) => $paragraph !== '')
            ->map(fn ($paragraph) => '<p>'.nl2br(e($paragraph), false).'</p>')
            ->implode('');
    }

    public static function normalize(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        if (! self::isHtml($value)) {
            return self::fromPlainText($value);
        }

        $html = self::sanitizer()->sanitize(self::mapForeignTags($value));

        // Boş paragrafları (editörün bıraktığı <p></p>, Word'deki boş satırlar) at.
        return trim(preg_replace('/<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $html));
    }

    /** İçerikte okunacak bir metin var mı ("<p></p>" gibi boş editör çıktısı sayılmaz). */
    public static function hasText(?string $value): bool
    {
        $html = self::normalize($value);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") !== ''
            || str_contains($html, 'data-footnote');
    }

    /**
     * Okuma sayfası HTML'i. $idPrefix aynı sayfada birden fazla içerik olursa dipnot
     * bağlantıları çakışmasın diye (ör. "bolum-3").
     */
    public static function render(?string $value, string $idPrefix = 'dn'): string
    {
        $html = self::normalize($value);

        if (! str_contains($html, 'data-footnote')) {
            return $html;
        }

        [$dom, $root] = self::parse($html);

        $notes = [];
        foreach (iterator_to_array((new DOMXPath($dom))->query('//span[@data-footnote]')) as $marker) {
            /** @var DOMElement $marker */
            $number = count($notes) + 1;
            $notes[$number] = $marker->getAttribute('data-footnote');

            $sup = $dom->createElement('sup');
            $sup->setAttribute('class', 'footnote-ref');
            $link = $dom->createElement('a', (string) $number);
            $link->setAttribute('href', "#{$idPrefix}-{$number}");
            $link->setAttribute('id', "{$idPrefix}-ref-{$number}");
            $link->setAttribute('aria-label', "Dipnot {$number}");
            $sup->appendChild($link);
            $marker->parentNode->replaceChild($sup, $marker);
        }

        $body = self::innerHtml($dom, $root);

        $list = collect($notes)->map(fn ($text, $number) => sprintf(
            '<li id="%1$s-%2$d">%3$s <a href="#%1$s-ref-%2$d" class="footnote-back" aria-label="Metne dön">↑</a></li>',
            e($idPrefix), $number, e($text)
        ))->implode('');

        return $body.'<section class="footnotes" aria-label="Dipnotlar"><ol>'.$list.'</ol></section>';
    }

    /**
     * Başka editörlerin (Filament/Trix, Word'den yapıştırma) etiketlerini bizimkilere eşler:
     * div → p, h1 → h2 (sayfa başlığı h1), b → strong, i → em. Güvenlik sınırı bundan sonraki
     * sanitizer; bu adım sadece yapıyı (paragraf, kalın, eğik) kaybetmemek için.
     */
    private static function mapForeignTags(string $html): string
    {
        if (! preg_match('/<(div|h1|b|i)\b/i', $html)) {
            return $html;
        }

        [$dom, $root] = self::parse($html);
        $map = ['div' => 'p', 'h1' => 'h2', 'b' => 'strong', 'i' => 'em'];

        foreach (iterator_to_array((new DOMXPath($dom))->query('//div[not(@id="rt-root")] | //h1 | //b | //i')) as $element) {
            /** @var DOMElement $element */
            $replacement = $dom->createElement($map[strtolower($element->tagName)]);
            while ($element->firstChild) {
                $replacement->appendChild($element->firstChild);
            }
            $element->parentNode->replaceChild($replacement, $element);
        }

        return self::innerHtml($dom, $root);
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    private static function parse(string $html): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        // Kökü sarıp encoding'i belirtiyoruz; aksi hâlde libxml Türkçe karakterleri bozar.
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="rt-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

        return [$dom, $dom->getElementById('rt-root') ?? (new DOMXPath($dom))->query('//div')->item(0)];
    }

    private static function innerHtml(DOMDocument $dom, DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        static $sanitizer = null;

        if ($sanitizer) {
            return $sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            // Listede olmayan sarmalayıcı etiketler (section, font, span...) atılır ama içindeki metin kalır.
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            // Uzunluk sınırı istek doğrulamasında (bkz. RichTextContent kuralı); burada kırpmasın.
            ->withMaxInputLength(-1);

        foreach (['p', 'br', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's', 'sup', 'sub', 'blockquote', 'ul', 'ol', 'li', 'hr'] as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config
            ->allowElement('a', ['href'])
            ->allowElement('span', ['data-footnote']);

        foreach (['script', 'style', 'template', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title', 'form', 'select', 'textarea', 'button', 'img', 'video', 'audio'] as $element) {
            $config = $config->dropElement($element);
        }

        return $sanitizer = new HtmlSanitizer($config);
    }
}
