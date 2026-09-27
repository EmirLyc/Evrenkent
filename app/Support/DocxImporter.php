<?php

namespace App\Support;

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
 * Taşınanlar: paragraf, başlık, kalın/eğik/altı çizili/üstü çizili, üst/alt simge, dipnot ve
 * sonnot, madde işaretli/numaralı liste, alıntı stili, dış bağlantı, tablo hücreleri (paragraf
 * olarak). Görseller taşınmıyor — gömülü belgeler ayrı bir özellik (Faz F2).
 */
class DocxImporter
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

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

    /**
     * Tek içerik olarak (bir bölüm ya da makale). Dosya tek bir üst düzey başlıkla
     * başlıyorsa o başlık öneri olarak döner ve metinden çıkarılır.
     *
     * @return array{title: ?string, html: string}
     */
    public function importSingle(string $path): array
    {
        $blocks = $this->blocks($path);
        $title = null;

        $headingLevels = collect($blocks)->where('type', 'heading')->pluck('level');
        $first = collect($blocks)->first();

        if ($first && $first['type'] === 'heading' && $first['level'] === $headingLevels->min()
            && $headingLevels->filter(fn ($level) => $level === $first['level'])->count() === 1) {
            $title = $first['text'];
            array_shift($blocks);
        }

        return ['title' => $title, 'html' => $this->toHtml($blocks)];
    }

    /**
     * Kitap dosyasını bölümlere ayırır: en üst düzey başlık (genelde "Başlık 1") her
     * bölümün başlangıcı. Başlıktan önceki metin (önsöz vb.) ayrı bir bölüm olur; hiç
     * başlık yoksa tüm dosya tek bölüm.
     *
     * @return list<array{title: ?string, html: string}>
     */
    public function importChapters(string $path): array
    {
        $blocks = $this->blocks($path);
        $topLevel = collect($blocks)->where('type', 'heading')->min('level');

        $chapters = [];
        $current = ['title' => null, 'blocks' => []];

        foreach ($blocks as $block) {
            if ($block['type'] === 'heading' && $block['level'] === $topLevel) {
                if ($current['title'] !== null || $current['blocks'] !== []) {
                    $chapters[] = $current;
                }
                $current = ['title' => $block['text'], 'blocks' => []];

                continue;
            }
            $current['blocks'][] = $block;
        }
        $chapters[] = $current;

        return collect($chapters)
            ->map(fn ($chapter) => ['title' => $chapter['title'], 'html' => $this->toHtml($chapter['blocks'])])
            ->filter(fn ($chapter) => $chapter['title'] !== null || RichText::hasText($chapter['html']))
            ->values()
            ->all();
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
        } finally {
            $zip->close();
        }

        $body = $document->getElementsByTagNameNS(self::W, 'body')->item(0);

        return $body ? $this->blockChildren($body) : [];
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
                'p' => ($block = $this->paragraph($node)) ? $blocks[] = $block : null,
                // Tablo düzeni okuma ekranında taşmasın diye hücreler sırayla paragraf oluyor.
                'tbl', 'tr', 'tc', 'sdt', 'sdtContent', 'customXml' => array_push($blocks, ...$this->blockChildren($node)),
                default => null,
            };
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function paragraph(DOMElement $p): ?array
    {
        $properties = $this->child($p, 'pPr');
        $styleId = $properties ? $this->attr($this->child($properties, 'pStyle'), 'val') : null;
        $html = trim($this->inline($p));
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));

        if ($text === '' && ! str_contains($html, 'data-footnote')) {
            return null;
        }

        $level = $this->headingLevel($styleId, $properties);
        if ($level !== null) {
            return ['type' => 'heading', 'level' => $level, 'text' => $text, 'html' => $html];
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

        $styleName = $this->styleName($styleId);
        if ($styleName !== null && preg_match('/quote|alıntı/iu', $styleName)) {
            return ['type' => 'quote', 'html' => $html];
        }

        return ['type' => 'p', 'html' => $html];
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
                'br', 'cr' => $text .= $this->attr($node, 'type') === 'page' ? '' : '<br>',
                'footnoteReference' => $footnotes .= $this->footnoteMarker($this->footnotes[$this->attr($node, 'id')] ?? null),
                'endnoteReference' => $footnotes .= $this->footnoteMarker($this->endnotes[$this->attr($node, 'id')] ?? null),
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
        if (! $dom) {
            return;
        }

        foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
            if ($relationship->getAttribute('TargetMode') === 'External' && str_ends_with($relationship->getAttribute('Type'), '/hyperlink')) {
                $this->links[$relationship->getAttribute('Id')] = $relationship->getAttribute('Target');
            }
        }
    }

    /**
     * Blokları HTML'e çevirir. Başlıklar göreli eşleniyor: içerikteki en üst düzey başlık
     * h2, bir altı h3, geri kalanı h4 (sayfanın h1'i bölüm/makale başlığı).
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function toHtml(array $blocks): string
    {
        $levels = collect($blocks)->where('type', 'heading')->pluck('level')->unique()->sort()->values();
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

            $html .= match ($block['type']) {
                'heading' => (function () use ($block, $levels) {
                    $tag = 'h'.min(4, 2 + $levels->search($block['level']));

                    return "<{$tag}>{$block['html']}</{$tag}>";
                })(),
                'quote' => "<blockquote><p>{$block['html']}</p></blockquote>",
                default => "<p>{$block['html']}</p>",
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
