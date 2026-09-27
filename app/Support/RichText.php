<?php

namespace App\Support;

use App\Models\Document;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
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
        return (bool) preg_match('/<(p|h[1-6]|ul|ol|li|blockquote|br|div|span|strong|em|b|i|figure)\b/i', (string) $value);
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

    /**
     * Arama için düz metin (content_text sütunu): etiketler boşluğa, HTML varlıkları karakterlere
     * çevrilir ("Paşa&#039;nın" → "Paşa'nın"); dipnot metinleri ve video başlıkları da dahil.
     * Arama HTML üzerinde yapılınca "span" gibi etiket adları eşleşiyor, kesme işaretli kelimeler
     * bulunamıyordu.
     */
    public static function plainText(?string $html): string
    {
        $html = (string) $html;
        preg_match_all('/data-(?:footnote|title)="([^"]*)"/', $html, $extras);

        $text = preg_replace('/<[^>]+>/', ' ', $html).' '.implode(' ', $extras[1]);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** İçerikte okunacak bir metin var mı ("<p></p>" gibi boş editör çıktısı sayılmaz). */
    public static function hasText(?string $value): bool
    {
        $html = self::normalize($value);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") !== ''
            || str_contains($html, 'data-footnote')
            || str_contains($html, 'data-document')
            || str_contains($html, 'data-video');
    }

    /**
     * Okuma sayfası HTML'i. $idPrefix aynı sayfada birden fazla içerik olursa dipnot
     * bağlantıları çakışmasın diye (ör. "bolum-3").
     *
     * $documents: içeriğin sahibine (kitap/makale) ait belgeler (Faz F2). Gömülü belge
     * işareti sadece bu listedeki bir belgeye çözülür; listede olmayan (silinmiş ya da
     * başka bir içeriğin) belge işareti sessizce atılır.
     *
     * @param  Collection<int, Document>|null  $documents
     */
    public static function render(?string $value, string $idPrefix = 'dn', ?Collection $documents = null): string
    {
        $html = self::normalize($value);

        if (! str_contains($html, 'data-footnote') && ! str_contains($html, 'data-document') && ! str_contains($html, 'data-video')) {
            return $html;
        }

        [$dom, $root] = self::parse($html);
        $documents = ($documents ?? collect())->keyBy('id');

        // Video satırı (Faz F3): tanınmayan (YouTube/Vimeo dışı, bozuk) adres atılır.
        foreach (iterator_to_array((new DOMXPath($dom))->query('//figure[@data-video]')) as $marker) {
            /** @var DOMElement $marker */
            $video = VideoEmbed::parse($marker->getAttribute('data-video'));

            if (! $video) {
                $marker->parentNode->removeChild($marker);

                continue;
            }

            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML(self::videoLink($video, $marker->getAttribute('data-title'), $marker->getAttribute('data-duration')));
            $marker->parentNode->replaceChild($fragment, $marker);
        }

        foreach (iterator_to_array((new DOMXPath($dom))->query('//span[@data-document]')) as $marker) {
            /** @var DOMElement $marker */
            $document = $documents->get((int) $marker->getAttribute('data-document'));

            if (! $document) {
                $marker->parentNode->removeChild($marker);

                continue;
            }

            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML(self::documentMarker($document));
            $marker->parentNode->replaceChild($fragment, $marker);
        }

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

        if ($notes === []) {
            return $body;
        }

        $list = collect($notes)->map(fn ($text, $number) => sprintf(
            '<li id="%1$s-%2$d">%3$s <a href="#%1$s-ref-%2$d" class="footnote-back" aria-label="Metne dön">↑</a></li>',
            e($idPrefix), $number, e($text)
        ))->implode('');

        return $body.'<section class="footnotes" aria-label="Dipnotlar"><ol>'.$list.'</ol></section>';
    }

    /**
     * Mockup 3 / 3.1: kar tanesi ikonu; üstüne gelince (ya da klavyeyle odaklanınca)
     * "ad (tarih / N sayfa)", tıklayınca site içi görüntüleyici açılır (x-document-viewer).
     * Görüntüleyici olmayan bir sayfada da bağlantı çalışsın diye düğme değil <a>.
     */
    private static function documentMarker(Document $document): string
    {
        $caption = e($document->caption());

        return sprintf(
            '<a href="%s" class="document-marker" data-turbo="false" data-document-viewer="%s" data-document-title="%s" aria-label="Belge: %s">%s<span class="document-tooltip" aria-hidden="true">%s</span></a>',
            e($document->viewUrl()),
            $document->isPdf() ? 'pdf' : 'image',
            $caption,
            $caption,
            self::SNOWFLAKE_SVG,
            $caption,
        );
    }

    /**
     * Mockup 3: "▶ Video: Osmanlı Diplomasisinde Yazışma Usulü (12:45 dk.)" — paragraflar
     * arasında ayrı satır. Tıklayınca site içi oynatıcı (x-document-viewer, type=video);
     * JS yoksa bağlantı videoyu sağlayıcının sitesinde açar.
     *
     * @param  array{provider: string, id: string, watch: string, embed: string}  $video
     */
    private static function videoLink(array $video, string $title, string $duration): string
    {
        $title = trim($title) !== '' ? trim($title) : ($video['provider'] === 'youtube' ? 'YouTube videosu' : 'Vimeo videosu');
        $duration = trim($duration);
        $label = e('Video: '.$title);

        return sprintf(
            '<p class="video-link"><a href="%s" target="_blank" rel="noopener noreferrer" data-turbo="false" data-document-viewer="video" data-embed-url="%s" data-document-title="%s">%s<span class="video-link-text"><span class="video-link-title">%s</span>%s</span></a></p>',
            e($video['watch']),
            e($video['embed']),
            $label,
            self::PLAY_SVG,
            $label,
            $duration !== '' ? '<span class="video-link-duration">('.e($duration).' dk.)</span>' : '',
        );
    }

    /** Mockup 3'teki turuncu halkalı oynat simgesi. */
    public const PLAY_SVG = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="video-link-icon"><circle cx="12" cy="12" r="10.25" stroke="currentColor" stroke-width="1.5"/><path d="M10 8.2v7.6a.5.5 0 0 0 .77.42l5.9-3.8a.5.5 0 0 0 0-.84l-5.9-3.8a.5.5 0 0 0-.77.42Z" fill="currentColor"/></svg>';

    /** Kar tanesi (editör araç çubuğu da aynı çizimi kullanıyor: x-snowflake-icon). */
    public const SNOWFLAKE_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="document-marker-icon"><path d="M12 2v20M3.34 7l17.32 10M3.34 17 20.66 7"/><path d="m9 3.8 3 2.4 3-2.4M9 20.2l3-2.4 3 2.4"/><path d="m3.6 10.4 3.6-.5-1.3-3.4M20.4 13.6l-3.6.5 1.3 3.4"/><path d="m5.9 17.5 1.3-3.4-3.6-.5M18.1 6.5l-1.3 3.4 3.6.5"/></svg>';

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
            ->allowElement('span', ['data-footnote', 'data-document'])
            ->allowElement('figure', ['data-video', 'data-title', 'data-duration']);

        foreach (['script', 'style', 'template', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title', 'form', 'select', 'textarea', 'button', 'img', 'video', 'audio'] as $element) {
            $config = $config->dropElement($element);
        }

        return $sanitizer = new HtmlSanitizer($config);
    }
}
