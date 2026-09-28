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
    /**
     * Editörün yazı tipi menüsü ("Yazarın Gözünden" 1.1.4) — saklanan anahtar → CSS yığını.
     * Metinde sadece anahtar saklanır (<span data-font="georgia">); bilinmeyen anahtar atılır.
     */
    public const FONTS = [
        'georgia' => ['Georgia', "Georgia, 'Times New Roman', serif"],
        'times' => ['Times New Roman', "'Times New Roman', Times, serif"],
        'palatino' => ['Palatino', "'Palatino Linotype', Palatino, 'Book Antiqua', serif"],
        'garamond' => ['Garamond', "'EB Garamond', Garamond, serif"],
        'arial' => ['Arial', 'Arial, Helvetica, sans-serif'],
        'helvetica' => ['Helvetica', 'Helvetica, Arial, sans-serif'],
        'lato' => ['Lato', 'Lato, sans-serif'],
        'source-serif' => ['Source Serif', "'Source Serif 4', 'Source Serif Pro', serif"],
        // "Daha fazla yazı tipi…"
        'merriweather' => ['Merriweather', 'Merriweather, serif'],
        'libre-baskerville' => ['Libre Baskerville', "'Libre Baskerville', serif"],
        'crimson-pro' => ['Crimson Pro', "'Crimson Pro', serif"],
        'open-sans' => ['Open Sans', "'Open Sans', sans-serif"],
        'roboto' => ['Roboto', 'Roboto, sans-serif'],
    ];

    /** Varsayılan yazı tipi ve punto (mockup'ta editör açılınca seçili olan: Georgia, 16). */
    public const DEFAULT_FONT = 'georgia';

    public const DEFAULT_SIZE = 16;

    /** Web yazı tipleri (Bunny Fonts) — sistemde olmayanlar; editörde ve okumada yüklenir. */
    public const WEB_FONTS_URL = 'https://fonts.bunny.net/css?family=eb-garamond:400,400i,500,600|lato:400,400i,700|source-serif-4:400,400i,600|merriweather:400,400i,700|libre-baskerville:400,400i,700|crimson-pro:400,400i,600|open-sans:400,400i,600|roboto:400,400i,500&display=swap';

    public const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /** Eski düz metin kaydı mı, HTML mi — blok etiketi yoksa düz metin sayılır. */
    public static function isHtml(?string $value): bool
    {
        return (bool) preg_match('/<(p|h[1-6]|ul|ol|li|blockquote|br|div|span|strong|em|b|i|figure|table|nav|section|hr)\b/i', (string) $value);
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
        preg_match_all('/data-(?:footnote|title|caption|cite)="([^"]*)"/', $html, $extras);

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
            || str_contains($html, 'data-video')
            || str_contains($html, 'data-image');
    }

    /**
     * Başlıklar (h1–h4) sırasıyla: düzey + düz metin — içindekiler ve numaralandırma için
     * (bkz. WorkOutline).
     *
     * @return list<array{level: int, text: string}>
     */
    public static function headings(?string $html): array
    {
        if (! preg_match('/<h[1-4]\b/i', (string) $html)) {
            return [];
        }

        [$dom] = self::parse((string) $html);
        $headings = [];
        foreach ((new DOMXPath($dom))->query('//h1 | //h2 | //h3 | //h4') as $heading) {
            $headings[] = ['level' => (int) substr($heading->nodeName, 1), 'text' => trim(preg_replace('/\s+/u', ' ', $heading->textContent))];
        }

        return $headings;
    }

    /**
     * Kaynak numaraları ("1 Kaynak No.") — içerikteki atıflar, ilk geçiş sırasıyla, tekrarsız.
     *
     * @return list<string>
     */
    public static function citations(?string $html): array
    {
        preg_match_all('/data-cite="([^"]*)"/', (string) $html, $matches);

        return collect($matches[1])
            ->map(fn ($text) => trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Başlık numarası: Başlık 1 → "I.", altları "1.1.", "1.1.1." (mockup 1.1.1). */
    public static function headingNumber(array $counters, int $level): string
    {
        if ($level === 1) {
            return self::roman(max(1, $counters[1])).'.';
        }

        return implode('.', array_slice($counters, 0, $level, true)).'.';
    }

    public static function roman(int $number): string
    {
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $roman = '';
        foreach ($map as $symbol => $value) {
            while ($number >= $value) {
                $roman .= $symbol;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * Okuma sayfası HTML'i. $idPrefix aynı sayfada birden fazla içerik olursa dipnot
     * bağlantıları çakışmasın diye (ör. "bolum-3").
     *
     * $documents: içeriğin sahibine (kitap/makale) ait belgeler (Faz F2). Gömülü belge
     * işareti sadece bu listedeki bir belgeye çözülür; listede olmayan (silinmiş ya da
     * başka bir içeriğin) belge işareti sessizce atılır.
     *
     * $context (Faz G2 — kitap bütün bir eser; bkz. WorkOutline::contextFor):
     *  - numbering: başlıklara numara (I., 1.1., 1.1.1.) — eserin ayarı
     *  - counters: başlık sayaçlarının başlangıcı (bölüm sayfasında [1 => bölüm no])
     *  - anchor: başlık çapalarının öneki (içindekiler bağlantıları için)
     *  - sources: eserin bütün kaynakları sırasıyla (kaynak numaraları bölümler arasında sürsün)
     *  - bibliographyUrl: kaynakçanın bulunduğu sayfa (başka bölümdeyse), aynı sayfadaysa ''
     *  - toc: içindekiler satırları [{level, number, text, url}]
     *
     * @param  Collection<int, Document>|null  $documents
     * @param  array<string, mixed>  $context
     */
    public static function render(?string $value, string $idPrefix = 'dn', ?Collection $documents = null, array $context = []): string
    {
        $html = self::normalize($value);

        if ($html === '') {
            return '';
        }

        [$dom, $root] = self::parse($html);
        $xpath = new DOMXPath($dom);
        $documents = ($documents ?? collect())->keyBy('id');
        // Tek parça içerikte (makale) kaynaklar bu içerikten; kitapta eser geneli (WorkOutline).
        $context['sources'] ??= self::citations($html);

        self::renderTypography($xpath);
        self::renderHeadings($dom, $xpath, $context);
        self::renderImages($dom, $xpath, $documents);
        self::renderCitations($dom, $xpath, $context);
        self::renderBlocks($dom, $xpath, $context);

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

    /** Yazı tipi / punto (span[data-font|data-size]) ve hizalama (data-align) → stil ve sınıf. */
    private static function renderTypography(DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//span[@data-font or @data-size]')) as $span) {
            /** @var DOMElement $span */
            $styles = [];
            $font = self::FONTS[$span->getAttribute('data-font')] ?? null;
            if ($font) {
                $styles[] = 'font-family: '.$font[1];
            }
            $size = (int) $span->getAttribute('data-size');
            if ($size >= 8 && $size <= 96) {
                // em: okuma sayfasının taban puntosuna göre (16 = taban), yakınlaştırmayla birlikte ölçeklenir.
                $styles[] = 'font-size: '.round($size / self::DEFAULT_SIZE, 4).'em';
            }
            $span->removeAttribute('data-font');
            $span->removeAttribute('data-size');
            $styles ? $span->setAttribute('style', implode('; ', $styles)) : null;
        }

        foreach (iterator_to_array($xpath->query('//*[@data-align]')) as $element) {
            /** @var DOMElement $element */
            $align = $element->getAttribute('data-align');
            $element->removeAttribute('data-align');
            if (in_array($align, self::ALIGNMENTS, true) && $align !== 'left') {
                $element->setAttribute('class', trim($element->getAttribute('class').' rt-align-'.$align));
            }
        }
    }

    /**
     * Başlık çapaları (içindekiler bağlantıları) ve numaraları. Bölüm sayfasında sayaç bölüm
     * numarasından başlar (bölümün kendi başlığı "Başlık 1", içerikte h2–h4 var).
     *
     * @param  array<string, mixed>  $context
     */
    private static function renderHeadings(DOMDocument $dom, DOMXPath $xpath, array $context): void
    {
        $counters = ($context['counters'] ?? []) + [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        ksort($counters);
        $anchor = $context['anchor'] ?? 'b';
        $index = 0;

        foreach ($xpath->query('//h1 | //h2 | //h3 | //h4') as $heading) {
            /** @var DOMElement $heading */
            $level = (int) substr($heading->nodeName, 1);
            $counters[$level]++;
            for ($deeper = $level + 1; $deeper <= 4; $deeper++) {
                $counters[$deeper] = 0;
            }

            $heading->setAttribute('id', $anchor.'-'.(++$index));

            if (! empty($context['numbering'])) {
                $number = $dom->createElement('span', self::headingNumber($counters, $level));
                $number->setAttribute('class', 'rt-num');
                $heading->insertBefore($number, $heading->firstChild);
            }
        }
    }

    /**
     * Metin içi görsel (Ekle → Görsel): <figure data-image="id" data-caption=".."> — sadece
     * içeriğin kendi görsellerine çözülür; dosya belge gibi erişim kontrollü adresten gelir.
     *
     * @param  Collection<int, Document>  $documents
     */
    private static function renderImages(DOMDocument $dom, DOMXPath $xpath, Collection $documents): void
    {
        foreach (iterator_to_array($xpath->query('//figure[@data-image]')) as $marker) {
            /** @var DOMElement $marker */
            $document = $documents->get((int) $marker->getAttribute('data-image'));

            if (! $document || ! $document->isImage()) {
                $marker->parentNode->removeChild($marker);

                continue;
            }

            $caption = trim($marker->getAttribute('data-caption'));
            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML(sprintf(
                '<figure class="rt-image"><img src="%s" alt="%s" loading="lazy"/>%s</figure>',
                e($document->viewUrl()),
                e($caption !== '' ? $caption : $document->title),
                $caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '',
            ));
            $marker->parentNode->replaceChild($fragment, $marker);
        }
    }

    /**
     * "1 Kaynak No.": <span data-cite="kaynak metni"> → [n]; aynı kaynak aynı numarayı alır.
     * Eser genelinde numaralanır (context.sources), kaynakçaya bağlanır.
     *
     * @param  array<string, mixed>  $context
     */
    private static function renderCitations(DOMDocument $dom, DOMXPath $xpath, array $context): void
    {
        $markers = iterator_to_array($xpath->query('//span[@data-cite]'));
        if ($markers === []) {
            return;
        }

        $sources = array_values($context['sources']);
        $bibliographyUrl = $context['bibliographyUrl'] ?? '';

        foreach ($markers as $marker) {
            /** @var DOMElement $marker */
            $position = array_search(trim($marker->getAttribute('data-cite')), $sources, true);
            if ($position === false) {
                $marker->parentNode->removeChild($marker);

                continue;
            }

            $number = $position + 1;
            $sup = $dom->createElement('sup');
            $sup->setAttribute('class', 'cite-ref');
            $link = $dom->createElement('a', '['.$number.']');
            $link->setAttribute('href', $bibliographyUrl.'#kaynak-'.$number);
            $link->setAttribute('title', $sources[$position]);
            $sup->appendChild($link);
            $marker->parentNode->replaceChild($sup, $marker);
        }
    }

    /**
     * Blok işaretleri: İçindekiler (nav[data-toc]), Kaynakça (section[data-bibliography]),
     * Sayfa Sonu (hr[data-page-break]) ve tablolar (dar ekranda yatay kayan kutu).
     *
     * @param  array<string, mixed>  $context
     */
    private static function renderBlocks(DOMDocument $dom, DOMXPath $xpath, array $context): void
    {
        foreach (iterator_to_array($xpath->query('//nav[@data-toc]')) as $marker) {
            /** @var DOMElement $marker */
            $entries = $context['toc'] ?? null;
            if ($entries === null) {
                // Tek parça içerik (makale): başlıklar bu sayfada.
                $entries = [];
                foreach ($xpath->query('//h1 | //h2 | //h3 | //h4') as $heading) {
                    $number = $heading->firstChild instanceof DOMElement && $heading->firstChild->getAttribute('class') === 'rt-num' ? $heading->firstChild->textContent : '';
                    $entries[] = ['level' => (int) substr($heading->nodeName, 1), 'number' => $number, 'text' => trim(substr($heading->textContent, strlen($number))), 'url' => '#'.$heading->getAttribute('id')];
                }
            }

            $items = collect($entries)->map(fn ($entry) => sprintf(
                '<li class="rt-toc-l%d"><a href="%s">%s<span>%s</span></a></li>',
                $entry['level'], e($entry['url']),
                $entry['number'] !== '' ? '<span class="rt-toc-num">'.e($entry['number']).'</span>' : '',
                e($entry['text']),
            ))->implode('');

            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML('<nav class="rt-toc" aria-label="İçindekiler"><p class="rt-toc-title">İçindekiler</p><ol>'.$items.'</ol></nav>');
            $marker->parentNode->replaceChild($fragment, $marker);
        }

        foreach (iterator_to_array($xpath->query('//section[@data-bibliography]')) as $marker) {
            /** @var DOMElement $marker */
            $sources = array_values($context['sources']);
            $items = collect($sources)->map(fn ($text, $i) => '<li id="kaynak-'.($i + 1).'">'.e($text).'</li>')->implode('');

            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML('<section class="rt-bibliography" aria-label="Kaynakça"><p class="rt-bibliography-title">Kaynakça</p>'
                .($items !== '' ? '<ol>'.$items.'</ol>' : '<p class="rt-empty">Henüz kaynak yok.</p>').'</section>');
            $marker->parentNode->replaceChild($fragment, $marker);
        }

        foreach (iterator_to_array($xpath->query('//hr[@data-page-break]')) as $marker) {
            $break = $dom->createElement('div');
            $break->setAttribute('class', 'rt-page-break');
            $break->setAttribute('aria-hidden', 'true');
            $marker->parentNode->replaceChild($break, $marker);
        }

        foreach (iterator_to_array($xpath->query('//table')) as $table) {
            $wrap = $dom->createElement('div');
            $wrap->setAttribute('class', 'rt-table-wrap');
            $table->parentNode->replaceChild($wrap, $table);
            $wrap->appendChild($table);
        }
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
     * Başka editörlerin (Trix, Word'den yapıştırma) etiketlerini bizimkilere eşler: div → p,
     * h5/h6 → h4, b → strong, i → em. Güvenlik sınırı bundan sonraki sanitizer; bu adım sadece
     * yapıyı (paragraf, kalın, eğik) kaybetmemek için. Faz G2'den beri h1 içerikte geçerli
     * ("Başlık 1"; kitapta bölüm başlığı) — eskiden h2'ye çevriliyordu.
     */
    private static function mapForeignTags(string $html): string
    {
        if (! preg_match('/<(div|h5|h6|b|i)\b/i', $html)) {
            return $html;
        }

        [$dom, $root] = self::parse($html);
        $map = ['div' => 'p', 'h5' => 'h4', 'h6' => 'h4', 'b' => 'strong', 'i' => 'em'];

        foreach (iterator_to_array((new DOMXPath($dom))->query('//div[not(@id="rt-root")] | //h5 | //h6 | //b | //i')) as $element) {
            /** @var DOMElement $element */
            $replacement = $dom->createElement($map[strtolower($element->tagName)]);
            if ($element->hasAttribute('data-align')) {
                $replacement->setAttribute('data-align', $element->getAttribute('data-align'));
            }
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

        foreach (['br', 'strong', 'em', 'u', 's', 'sup', 'sub', 'blockquote', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr'] as $element) {
            $config = $config->allowElement($element);
        }

        // Faz G2: hizalama (data-align) paragraf ve başlıklarda; değerleri render'da beyaz listeyle.
        foreach (['p', 'h1', 'h2', 'h3', 'h4'] as $element) {
            $config = $config->allowElement($element, ['data-align']);
        }

        $config = $config
            ->allowElement('a', ['href'])
            // Dipnot, kar taneli belge (F2), yazı tipi / punto ve kaynak numarası (G2).
            ->allowElement('span', ['data-footnote', 'data-document', 'data-font', 'data-size', 'data-cite'])
            // Video (F3) ve metin içi görsel (G2).
            ->allowElement('figure', ['data-video', 'data-title', 'data-duration', 'data-image', 'data-caption'])
            // Süs ayırıcı (F4) ve Sayfa Sonu (G2).
            ->allowElement('hr', ['data-page-break'])
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            // İçindekiler ve Kaynakça yer tutucuları (G2) — içerikleri render'da üretiliyor.
            ->allowElement('nav', ['data-toc'])
            ->allowElement('section', ['data-bibliography']);

        foreach (['script', 'style', 'template', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title', 'form', 'select', 'textarea', 'button', 'img', 'video', 'audio'] as $element) {
            $config = $config->dropElement($element);
        }

        return $sanitizer = new HtmlSanitizer($config);
    }
}
