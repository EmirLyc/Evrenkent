<?php

namespace App\Support;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use ZipArchive;

/**
 * Word (.docx) dosyasını bölüm/makale içeriğine (RichText HTML) çevirir — Faz F1,
 * 2026-09-27 toplantı kararı: metin içerikte ana format DOCX.
 *
 * Kütüphane (PhpWord) yerine OOXML doğrudan okunuyor: Türkçe Word'de başlık stillerinin
 * kimliği "Heading1" değil "Balk1" oluyor ve PhpWord bunları başlık saymıyor; dipnotları da
 * bizim satır içi biçimimize çevirmek gerekiyordu. Başlık tespiti stil kimliğine değil
 * styles.xml'deki dile bağımsız stil adına ("heading 1") ve anahat düzeyine bakıyor.
 *
 * Taşınanlar: paragraf, başlık (Başlık 1–4 aynen), hizalama, kalın/eğik/altı çizili/üstü çizili,
 * üst/alt simge, dipnot ve sonnot, madde işaretli/numaralı liste, alıntı stili, dış bağlantı,
 * tablo, sayfa sonu. Görseller: withImages() ile bir kaydedici verilirse her PNG/JPG görsel
 * kaydedilir ve yerinde metin içi görsel olur (Faz G2); verilmezse atlanır.
 */
class DocxImporter
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    private const VML = 'urn:schemas-microsoft-com:vml';

    /** Tek bir görsel için üst sınır (sıkıştırılmamış). */
    private const MAX_IMAGE_BYTES = 15 * 1024 * 1024;

    /** Sıkıştırılmamış document.xml için üst sınır — zip bombasına karşı. */
    private const MAX_XML_BYTES = 60 * 1024 * 1024;

    /** @var array<string, array{name: string, basedOn: ?string, outline: ?int}> */
    private array $styles = [];

    /** @var array<string, string> footnote id → metin */
    private array $footnotes = [];

    /** @var array<string, string> endnote id → metin */
    private array $endnotes = [];

    /** @var array<string, array<string, string>> numId → ilvl → numFmt */
    private array $numbering = [];

    /** @var array<string, string> rel id → dış URL */
    private array $links = [];

    /** @var array<string, string> rel id → zip içindeki görsel yolu */
    private array $images = [];

    /** @var (Closure(string, string, string, string): ?int)|null */
    private ?Closure $imageHandler = null;

    private ?ZipArchive $zip = null;

    private int $importedImages = 0;

    private int $skippedImages = 0;

    /**
     * Görselleri gömülü belge yapacak kaydedici: içerik, MIME, dosya adı ve başlık alır,
     * oluşturulan belgenin kimliğini döner (null → görsel atlanır).
     */
    public function withImages(callable $handler): static
    {
        $this->imageHandler = Closure::fromCallable($handler);

        return $this;
    }

    /** @return array{imported: int, skipped: int} */
    public function imageStats(): array
    {
        return ['imported' => $this->importedImages, 'skipped' => $this->skippedImages];
    }

    /**
     * Bütün dosya tek belge olarak (Faz G2, "Yazarın Gözünden": platform docx başlık
     * katmanlarıyla uyumlu). Word'deki Başlık 1–4 editörde de Başlık 1–4 olur — kitapta her
     * Başlık 1 bir bölüm (bkz. BookDocument). Dosyanın başındaki "Konu Başlığı" (Title) stili
     * eserin adı önerisi olarak döner ve metinden çıkarılır.
     *
     * @return array{title: ?string, html: string}
     */
    public function importDocument(string $path): array
    {
        $blocks = $this->blocks($path);
        $title = null;

        if (($blocks[0]['type'] ?? null) === 'title') {
            $title = $blocks[0]['text'];
            array_shift($blocks);
        }

        return ['title' => $title, 'html' => $this->toHtml($blocks)];
    }

    /**
     * @return list<array{type: string, html?: string, text?: string, level?: int, list?: string}>
     */
    private function blocks(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Dosya okunamadı. Geçerli bir Word (.docx) dosyası yükleyin.');
        }

        $this->zip = $zip;
        $this->importedImages = $this->skippedImages = 0;

        try {
            $document = $this->readXml($zip, 'word/document.xml');
            if (! $document) {
                throw new InvalidArgumentException('Dosya okunamadı. Geçerli bir Word (.docx) dosyası yükleyin.');
            }

            $this->readStyles($this->readXml($zip, 'word/styles.xml'));
            $this->footnotes = $this->readNotes($this->readXml($zip, 'word/footnotes.xml'), 'footnote');
            $this->endnotes = $this->readNotes($this->readXml($zip, 'word/endnotes.xml'), 'endnote');
            $this->readNumbering($this->readXml($zip, 'word/numbering.xml'));
            $this->readLinks($this->readXml($zip, 'word/_rels/document.xml.rels'));

            $body = $document->getElementsByTagNameNS(self::W, 'body')->item(0);

            // Zip, görseller okunabilsin diye blokların sonuna kadar açık kalıyor.
            return $body ? $this->blockChildren($body) : [];
        } finally {
            $zip->close();
            $this->zip = null;
        }
    }

    private function readXml(ZipArchive $zip, string $name): ?DOMDocument
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > self::MAX_XML_BYTES) {
            return null;
        }

        $xml = $zip->getFromName($name);
        if ($xml === false) {
            return null;
        }

        $dom = new DOMDocument;

        return $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE) ? $dom : null;
    }

    /**
     * Body / tablo hücresi / içerik denetimi altındaki blokları sırayla toplar.
     *
     * @return list<array<string, mixed>>
     */
    private function blockChildren(DOMNode $parent): array
    {
        $blocks = [];

        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
                continue;
            }

            match ($node->localName) {
                'p' => array_push($blocks, ...$this->paragraph($node)),
                // Faz G2: tablo editörde de tablo ("Ekle → Tablo"); okumada dar ekranda yatay kayar.
                'tbl' => $blocks[] = ['type' => 'table', 'html' => $this->table($node)],
                'sdt', 'sdtContent', 'customXml' => array_push($blocks, ...$this->blockChildren($node)),
                default => null,
            };
        }

        return $blocks;
    }

    /**
     * Paragraf bir ya da birkaç blok olur: satır içi görsel (G2'de metin içi görsel) ve sayfa
     * sonu blok düzeyinde olduğu için paragrafı böler.
     *
     * @return list<array<string, mixed>>
     */
    private function paragraph(DOMElement $p): array
    {
        $properties = $this->child($p, 'pPr');
        $blocks = [];

        if ($properties && $this->isOn($this->child($properties, 'pageBreakBefore'))) {
            $blocks[] = ['type' => 'pagebreak'];
        }

        // Görsel ve sayfa sonu işaretlerinde bölünüyor; aradaki metin parçaları aynı paragraf türünde.
        foreach (preg_split('/(\x01[^\x01]*\x01)/', $this->inline($p), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            if ($part === "\x01PB\x01") {
                $blocks[] = ['type' => 'pagebreak'];
            } elseif (preg_match('/^\x01FIG:(\d+):([^\x01]*)\x01$/', $part, $figure)) {
                $blocks[] = ['type' => 'figure', 'id' => (int) $figure[1], 'caption' => base64_decode($figure[2])];
            } elseif (($block = $this->textBlock(trim($part), $properties)) !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function textBlock(string $html, ?DOMElement $properties): ?array
    {
        $styleId = $properties ? $this->attr($this->child($properties, 'pStyle'), 'val') : null;
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));

        if ($text === '' && ! str_contains($html, 'data-footnote')) {
            return null;
        }

        // Hizalama (Word'de "ortala / sağa yasla / iki yana yasla").
        $align = match ($properties ? $this->attr($this->child($properties, 'jc'), 'val') : null) {
            'center' => 'center',
            'right', 'end' => 'right',
            'both', 'distribute' => 'justify',
            default => null,
        };

        // Word'ün "Konu Başlığı" (Title) stili eserin adıdır, bölüm başlığı değil.
        $styleName = $this->styleName($styleId);
        if ($styleName !== null && strcasecmp($styleName, 'title') === 0) {
            return ['type' => 'title', 'text' => $text, 'html' => $html];
        }

        $level = $this->headingLevel($styleId, $properties);
        if ($level !== null) {
            return ['type' => 'heading', 'level' => $level, 'text' => $text, 'html' => $html, 'align' => $align];
        }

        $numbering = $properties ? $this->child($properties, 'numPr') : null;
        if ($numbering) {
            $numId = $this->attr($this->child($numbering, 'numId'), 'val');
            $ilvl = $this->attr($this->child($numbering, 'ilvl'), 'val') ?? '0';

            // numId 0 = numaralandırma kaldırılmış paragraf.
            if ($numId !== null && $numId !== '0') {
                $format = $this->numbering[$numId][$ilvl] ?? 'bullet';

                return ['type' => 'li', 'list' => $format === 'bullet' ? 'ul' : 'ol', 'html' => $html];
            }
        }

        if ($styleName !== null && preg_match('/quote|alıntı/iu', $styleName)) {
            return ['type' => 'quote', 'html' => $html, 'align' => $align];
        }

        return ['type' => 'p', 'html' => $html, 'align' => $align];
    }

    /**
     * Word tablosu → HTML tablo. Birleştirilmiş sütunlar (gridSpan) colspan olur; dikey
     * birleştirmenin devam hücreleri boş hücre olarak kalır. Başlık satırı (tblHeader) th.
     */
    private function table(DOMElement $table): string
    {
        $rows = '';

        foreach ($table->childNodes as $row) {
            if (! $row instanceof DOMElement || $row->localName !== 'tr') {
                continue;
            }

            $rowProperties = $this->child($row, 'trPr');
            $cellTag = $rowProperties && $this->child($rowProperties, 'tblHeader') ? 'th' : 'td';
            $cells = '';

            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof DOMElement || $cell->localName !== 'tc') {
                    continue;
                }
                $cellProperties = $this->child($cell, 'tcPr');
                $span = (int) ($this->attr($this->child($cellProperties, 'gridSpan'), 'val') ?? 1);
                $inner = $this->toHtml(array_values(array_filter(
                    $this->blockChildren($cell),
                    fn ($block) => ! in_array($block['type'], ['pagebreak', 'figure', 'table', 'title'], true)
                )));

                $cells .= "<{$cellTag}".($span > 1 ? " colspan=\"{$span}\"" : '').'>'.($inner !== '' ? $inner : '<p></p>')."</{$cellTag}>";
            }

            if ($cells !== '') {
                $rows .= "<tr>{$cells}</tr>";
            }
        }

        return $rows === '' ? '' : "<table><tbody>{$rows}</tbody></table>";
    }

    /**
     * Paragraf / bağlantı / değişiklik izleme sarmalayıcısı içindeki satır içi metin (HTML).
     */
    private function inline(DOMElement $parent): string
    {
        $html = '';

        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
                continue;
            }

            $html .= match ($node->localName) {
                'r' => $this->run($node),
                'hyperlink' => $this->hyperlink($node),
                // Değişiklik izleme: eklenenler metne dahil, silinenler (w:del) değil.
                'ins', 'smartTag', 'fldSimple', 'sdt', 'sdtContent', 'customXml' => $this->inline($node),
                default => '',
            };
        }

        return $html;
    }

    private function hyperlink(DOMElement $link): string
    {
        $inner = $this->inline($link);
        $url = $this->links[$link->getAttributeNS(self::R, 'id')] ?? null;

        return $url && preg_match('#^(https?://|mailto:)#i', $url)
            ? '<a href="'.e($url).'">'.$inner.'</a>'
            : $inner;
    }

    private function run(DOMElement $run): string
    {
        $text = '';
        $footnotes = '';

        foreach ($run->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
                continue;
            }

            match ($node->localName) {
                't' => $text .= e($node->textContent),
                'tab' => $text .= ' ',
                'noBreakHyphen' => $text .= '-',
                // Faz G2: Word'deki sayfa sonu editörde de "Sayfa Sonu" (paragrafı böler, bkz. paragraph()).
                'br', 'cr' => $this->attr($node, 'type') === 'page' ? $footnotes .= "\x01PB\x01" : $text .= '<br>',
                'footnoteReference' => $footnotes .= $this->footnoteMarker($this->footnotes[$this->attr($node, 'id')] ?? null),
                'endnoteReference' => $footnotes .= $this->footnoteMarker($this->endnotes[$this->attr($node, 'id')] ?? null),
                // Görsel: modern çizim (w:drawing) ya da eski VML (w:pict).
                'drawing', 'pict' => $footnotes .= $this->image($node),
                default => null,
            };
        }

        if ($text !== '') {
            $properties = $this->child($run, 'rPr');
            if ($properties) {
                $vertical = $this->attr($this->child($properties, 'vertAlign'), 'val');
                $wrap = array_filter([
                    'strong' => $this->isOn($this->child($properties, 'b')),
                    'em' => $this->isOn($this->child($properties, 'i')),
                    'u' => ($underline = $this->child($properties, 'u')) && $this->attr($underline, 'val') !== 'none',
                    's' => $this->isOn($this->child($properties, 'strike')),
                    'sup' => $vertical === 'superscript',
                    'sub' => $vertical === 'subscript',
                ]);

                foreach (array_keys($wrap) as $tag) {
                    $text = "<{$tag}>{$text}</{$tag}>";
                }
            }
        }

        return $text.$footnotes;
    }

    /**
     * Word görselini kaydedip yerinde metin içi görsel yapar (Faz G2; önceden kar taneli belge).
     * Sadece içeriği gerçekten PNG/JPG olanlar (getimagesizefromstring); EMF/WMF/GIF vb. atlanır.
     * Dönen işaret paragrafı böler (görsel blok düzeyinde) — bkz. paragraph().
     */
    private function image(DOMElement $node): string
    {
        $blip = $node->getElementsByTagNameNS(self::A, 'blip')->item(0);
        $relId = $blip?->getAttributeNS(self::R, 'embed')
            ?: $node->getElementsByTagNameNS(self::VML, 'imagedata')->item(0)?->getAttributeNS(self::R, 'id');

        $path = $relId ? ($this->images[$relId] ?? null) : null;
        if (! $path) {
            return '';
        }

        if (! $this->imageHandler || ! $this->zip) {
            $this->skippedImages++;

            return '';
        }

        $stat = $this->zip->statName($path);
        $contents = $stat && $stat['size'] <= self::MAX_IMAGE_BYTES ? $this->zip->getFromName($path) : false;
        $info = $contents !== false ? @getimagesizefromstring($contents) : false;
        $mime = $info['mime'] ?? null;

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            $this->skippedImages++;

            return '';
        }

        $properties = $node->getElementsByTagNameNS(self::WP, 'docPr')->item(0);
        // Alternatif metin varsa görselin alt yazısı olur.
        $caption = mb_substr(trim((string) ($properties?->getAttribute('descr') ?: $properties?->getAttribute('title'))), 0, 200);
        $title = $caption !== '' ? $caption : 'Görsel '.($this->importedImages + 1);

        $id = ($this->imageHandler)($contents, $mime, basename($path), $title);
        if (! $id) {
            $this->skippedImages++;

            return '';
        }

        $this->importedImages++;

        return "\x01FIG:".(int) $id.':'.base64_encode($caption)."\x01";
    }

    private function footnoteMarker(?string $text): string
    {
        return $text === null || trim($text) === '' ? '' : '<span data-footnote="'.e(trim($text)).'"></span>';
    }

    private function headingLevel(?string $styleId, ?DOMElement $properties): ?int
    {
        // Paragrafın kendi anahat düzeyi (Word'de "Anahat düzeyi: Düzey 1").
        $outline = $properties ? $this->attr($this->child($properties, 'outlineLvl'), 'val') : null;
        if ($outline !== null && is_numeric($outline) && (int) $outline < 9) {
            return (int) $outline + 1;
        }

        // Stil zinciri: "heading 2" / "Title" ya da ondan türetilmiş özel stil.
        for ($id = $styleId, $depth = 0; $id !== null && $depth < 10; $depth++) {
            $style = $this->styles[$id] ?? null;
            if (! $style) {
                break;
            }
            if (preg_match('/^heading\s*(\d)$/i', $style['name'], $match)) {
                return (int) $match[1];
            }
            if (strcasecmp($style['name'], 'title') === 0) {
                return 1;
            }
            if ($style['outline'] !== null && $style['outline'] < 9) {
                return $style['outline'] + 1;
            }
            $id = $style['basedOn'];
        }

        // styles.xml yoksa (bazı dönüştürücüler) kimliğe göre son bir tahmin.
        return $styleId && preg_match('/^(heading|başlık|balk)(\d)$/iu', $styleId, $match) ? (int) $match[2] : null;
    }

    private function styleName(?string $styleId): ?string
    {
        return $styleId ? ($this->styles[$styleId]['name'] ?? $styleId) : null;
    }

    private function readStyles(?DOMDocument $dom): void
    {
        $this->styles = [];
        if (! $dom) {
            return;
        }

        foreach ($dom->getElementsByTagNameNS(self::W, 'style') as $style) {
            if ($style->getAttributeNS(self::W, 'type') !== 'paragraph') {
                continue;
            }
            $properties = $this->child($style, 'pPr');
            $outline = $properties ? $this->attr($this->child($properties, 'outlineLvl'), 'val') : null;

            $this->styles[$style->getAttributeNS(self::W, 'styleId')] = [
                'name' => (string) $this->attr($this->child($style, 'name'), 'val'),
                'basedOn' => $this->attr($this->child($style, 'basedOn'), 'val'),
                'outline' => is_numeric($outline) ? (int) $outline : null,
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function readNotes(?DOMDocument $dom, string $tag): array
    {
        if (! $dom) {
            return [];
        }

        $notes = [];
        foreach ($dom->getElementsByTagNameNS(self::W, $tag) as $note) {
            $paragraphs = [];
            foreach ($note->getElementsByTagNameNS(self::W, 'p') as $p) {
                $paragraphs[] = trim(html_entity_decode(strip_tags($this->inline($p)), ENT_QUOTES | ENT_HTML5));
            }
            $notes[$note->getAttributeNS(self::W, 'id')] = trim(implode(' ', array_filter($paragraphs)));
        }

        return $notes;
    }

    private function readNumbering(?DOMDocument $dom): void
    {
        $this->numbering = [];
        if (! $dom) {
            return;
        }

        $abstract = [];
        foreach ($dom->getElementsByTagNameNS(self::W, 'abstractNum') as $definition) {
            foreach ($definition->getElementsByTagNameNS(self::W, 'lvl') as $level) {
                $abstract[$definition->getAttributeNS(self::W, 'abstractNumId')][$level->getAttributeNS(self::W, 'ilvl')]
                    = (string) $this->attr($this->child($level, 'numFmt'), 'val');
            }
        }

        foreach ($dom->getElementsByTagNameNS(self::W, 'num') as $num) {
            $abstractId = $this->attr($this->child($num, 'abstractNumId'), 'val');
            $this->numbering[$num->getAttributeNS(self::W, 'numId')] = $abstract[$abstractId] ?? [];
        }
    }

    private function readLinks(?DOMDocument $dom): void
    {
        $this->links = [];
        $this->images = [];
        if (! $dom) {
            return;
        }

        foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
            $type = $relationship->getAttribute('Type');
            $target = $relationship->getAttribute('Target');

            if ($relationship->getAttribute('TargetMode') === 'External') {
                if (str_ends_with($type, '/hyperlink')) {
                    $this->links[$relationship->getAttribute('Id')] = $target;
                }

                continue;
            }

            // Görsel yolu document.xml'e göre ("media/image1.png") ya da köke göre ("/word/media/...").
            if (str_ends_with($type, '/image')) {
                $this->images[$relationship->getAttribute('Id')] = str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'word/'.ltrim($target, './');
            }
        }
    }

    /**
     * Blokları HTML'e çevirir. Başlıklar Word'deki düzeyiyle (Başlık 1 → h1 … Başlık 4 ve
     * altı → h4) — Faz G2; önceden göreli eşleniyor, en üst başlık h2 oluyordu.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function toHtml(array $blocks): string
    {
        $html = '';
        $openList = null;

        foreach ($blocks as $block) {
            if ($openList && ($block['type'] !== 'li' || $block['list'] !== $openList)) {
                $html .= "</{$openList}>";
                $openList = null;
            }

            if ($block['type'] === 'li') {
                if (! $openList) {
                    $openList = $block['list'];
                    $html .= "<{$openList}>";
                }
                $html .= "<li>{$block['html']}</li>";

                continue;
            }

            $align = ! empty($block['align']) ? ' data-align="'.$block['align'].'"' : '';

            $html .= match ($block['type']) {
                'heading' => '<h'.min(4, max(1, $block['level'])).$align.'>'.$block['html'].'</h'.min(4, max(1, $block['level'])).'>',
                'quote' => "<blockquote><p{$align}>{$block['html']}</p></blockquote>",
                'table' => $block['html'],
                'pagebreak' => '<hr data-page-break="true">',
                'figure' => '<figure data-image="'.$block['id'].'" data-caption="'.e($block['caption']).'"></figure>',
                'title' => '',
                default => "<p{$align}>{$block['html']}</p>",
            };
        }

        if ($openList) {
            $html .= "</{$openList}>";
        }

        return RichText::normalize($html);
    }

    private function child(?DOMElement $parent, string $localName): ?DOMElement
    {
        if (! $parent) {
            return null;
        }

        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->namespaceURI === self::W && $node->localName === $localName) {
                return $node;
            }
        }

        return null;
    }

    private function attr(?DOMElement $element, string $name): ?string
    {
        return $element && $element->hasAttributeNS(self::W, $name) ? $element->getAttributeNS(self::W, $name) : null;
    }

    /** <w:b/> açık, <w:b w:val="0"/> / "false" kapalı. */
    private function isOn(?DOMElement $element): bool
    {
        return $element !== null && ! in_array($this->attr($element, 'val'), ['0', 'false', 'off'], true);
    }
}
