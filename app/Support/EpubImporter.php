<?php

namespace App\Support;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/**
 * EPUB'dan kitap bölümleri (2026-09-27 format kararı: "metin için DOCX, ikinci seçenek EPUB").
 *
 * EPUB bir zip: META-INF/container.xml → paket dosyası (OPF) → okuma sırası (spine). Spine'daki
 * her XHTML dosyası bir bölüm olur; başlık dosyadaki ilk başlıktan (h1–h3) alınır. İçindekiler
 * (nav) ve metinsiz sayfalar (kapak, tam sayfa görsel) atlanır.
 *
 * Dipnotlar: EPUB 3 işaretleri (epub:type="noteref" / role="doc-noteref" → hedefteki
 * footnote/endnote) bizim satır içi dipnot biçimine çevrilir, not blokları metinden çıkarılır.
 * İşaretsiz eski (EPUB 2) dipnotlar sıradan metin olarak kalır.
 *
 * Görseller: withImages() ile kaydedici verilirse PNG/JPG görseller gömülü belge olur (Word
 * aktarmayla aynı), verilmezse atlanır. HTML sonunda RichText::normalize'dan geçer.
 */
class EpubImporter
{
    private const MAX_ENTRY_BYTES = 30 * 1024 * 1024;

    private ?Closure $imageHandler = null;

    private int $importedImages = 0;

    private int $skippedImages = 0;

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
     * @return list<array{title: ?string, html: string}>
     */
    public function importChapters(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Dosya okunamadı. Geçerli bir EPUB dosyası yükleyin.');
        }

        $this->importedImages = $this->skippedImages = 0;

        try {
            $opfPath = $this->rootFile($zip);
            $spine = $this->spine($zip, $opfPath);

            // Dipnot hedefleri başka bir dosyada olabilir (ör. notes.xhtml): önce hepsini topla.
            $documents = [];
            foreach ($spine as $file) {
                $xhtml = $this->read($zip, $file);
                if ($xhtml !== null) {
                    $documents[$file] = $this->parseHtml($xhtml);
                }
            }
            $notes = $this->collectNotes($documents);

            $chapters = [];
            foreach ($documents as $file => $dom) {
                $chapter = $this->chapter($zip, $file, $dom, $notes);
                if ($chapter) {
                    $chapters[] = $chapter;
                }
            }

            return $chapters;
        } finally {
            $zip->close();
        }
    }

    private function rootFile(ZipArchive $zip): string
    {
        $container = $this->read($zip, 'META-INF/container.xml');
        if ($container && preg_match('/full-path="([^"]+)"/', $container, $match)) {
            return $match[1];
        }

        throw new InvalidArgumentException('Dosya okunamadı. Geçerli bir EPUB dosyası yükleyin.');
    }

    /**
     * Okuma sırasındaki XHTML dosyaları (zip içi yollar); içindekiler (nav) hariç.
     *
     * @return list<string>
     */
    private function spine(ZipArchive $zip, string $opfPath): array
    {
        $opf = $this->read($zip, $opfPath);
        $dom = new DOMDocument;
        if (! $opf || ! $dom->loadXML($opf, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new InvalidArgumentException('Dosya okunamadı. Geçerli bir EPUB dosyası yükleyin.');
        }

        $base = str_contains($opfPath, '/') ? dirname($opfPath).'/' : '';
        $manifest = [];
        foreach ($dom->getElementsByTagName('item') as $item) {
            $manifest[$item->getAttribute('id')] = [
                'href' => $this->resolve($base, rawurldecode($item->getAttribute('href'))),
                'type' => $item->getAttribute('media-type'),
                'nav' => str_contains(' '.$item->getAttribute('properties').' ', ' nav '),
            ];
        }

        $files = [];
        foreach ($dom->getElementsByTagName('itemref') as $itemref) {
            $item = $manifest[$itemref->getAttribute('idref')] ?? null;
            if ($item && ! $item['nav'] && in_array($item['type'], ['application/xhtml+xml', 'text/html'], true)) {
                $files[] = $item['href'];
            }
        }

        if ($files === []) {
            throw new InvalidArgumentException('EPUB dosyasında okunacak bölüm bulunamadı.');
        }

        return $files;
    }

    /**
     * Tüm dosyalardaki dipnot / sonnot bloklarının metni: "dosya#id" → metin.
     *
     * @param  array<string, DOMDocument>  $documents
     * @return array<string, string>
     */
    private function collectNotes(array $documents): array
    {
        $notes = [];
        foreach ($documents as $file => $dom) {
            foreach ($this->noteElements($dom) as $note) {
                if ($note->getAttribute('id') === '') {
                    continue;
                }
                $clone = $note->cloneNode(true);
                // Geri dönüş bağlantısını ("↩", "[1]") metne katma.
                foreach (iterator_to_array((new DOMXPath($dom))->query('.//a[contains(@*[name()="epub:type"], "backlink") or contains(@role, "doc-backlink")]', $clone) ?: []) as $back) {
                    $back->parentNode->removeChild($back);
                }
                $text = trim(preg_replace('/\s+/u', ' ', $clone->textContent));
                $notes[$file.'#'.$note->getAttribute('id')] = preg_replace('/^\[?\d+\]?[.)]?\s+/u', '', $text);
            }
        }

        return $notes;
    }

    /**
     * @return list<DOMElement>
     */
    private function noteElements(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[contains(concat(" ", @*[name()="epub:type"], " "), " footnote ") or contains(concat(" ", @*[name()="epub:type"], " "), " endnote ") or contains(concat(" ", @*[name()="epub:type"], " "), " rearnote ") or @role="doc-footnote" or @role="doc-endnote" or contains(concat(" ", @*[name()="epub:type"], " "), " footnotes ") or contains(concat(" ", @*[name()="epub:type"], " "), " endnotes ") or @role="doc-endnotes"]');

        return $nodes ? iterator_to_array($nodes) : [];
    }

    /**
     * @param  array<string, string>  $notes
     * @return array{title: ?string, html: string}|null
     */
    private function chapter(ZipArchive $zip, string $file, DOMDocument $dom, array $notes): ?array
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            return null;
        }
        $xpath = new DOMXPath($dom);

        // 1) Dipnot işaretleri → <span data-footnote>.
        foreach (iterator_to_array($xpath->query('//a[contains(concat(" ", @*[name()="epub:type"], " "), " noteref ") or @role="doc-noteref"]') ?: []) as $ref) {
            /** @var DOMElement $ref */
            $href = rawurldecode($ref->getAttribute('href'));
            $target = str_starts_with($href, '#')
                ? $file.$href
                : $this->resolve(dirname($file) === '.' ? '' : dirname($file).'/', strtok($href, '#')).'#'.substr((string) strstr($href, '#'), 1);
            $text = $notes[$target] ?? null;

            $replaceNode = $ref->parentNode instanceof DOMElement && $ref->parentNode->nodeName === 'sup' && trim($ref->parentNode->textContent) === trim($ref->textContent)
                ? $ref->parentNode
                : $ref;

            if ($text) {
                $marker = $dom->createElement('span');
                $marker->setAttribute('data-footnote', $text);
                $replaceNode->parentNode->replaceChild($marker, $replaceNode);
            } else {
                $replaceNode->parentNode->removeChild($replaceNode);
            }
        }

        // 2) Not blokları metinden çıkar (içerikleri işaretlere taşındı).
        foreach ($this->noteElements($dom) as $note) {
            $note->parentNode?->removeChild($note);
        }

        // 3) Başlık: ilk h1–h3; metinden çıkarılır (bölüm başlığı olarak ayrıca gösteriliyor).
        $title = null;
        $heading = $xpath->query('//body//h1 | //body//h2 | //body//h3')->item(0);
        if ($heading && trim($heading->textContent) !== '') {
            $title = mb_substr(trim(preg_replace('/\s+/u', ' ', $heading->textContent)), 0, 250);
            $heading->parentNode->removeChild($heading);
        }

        // Metinsiz sayfa (kapak, künye görseli, tam sayfa görsel): bölüm olmaz — görselleri de
        // belge olarak kaydedilmeden önce elenir (yoksa kapak gereksiz bir belge olurdu).
        if (trim(preg_replace('/[\s\x{A0}]+/u', ' ', $body->textContent)) === '') {
            return null;
        }

        // 4) Görseller → gömülü belge ya da atla.
        foreach (iterator_to_array($dom->getElementsByTagName('img')) as $img) {
            /** @var DOMElement $img */
            $id = $this->storeImage($zip, $file, $img);
            if ($id) {
                $marker = $dom->createElement('span');
                $marker->setAttribute('data-document', (string) $id);
                $img->parentNode->replaceChild($marker, $img);
            } else {
                $img->parentNode->removeChild($img);
            }
        }

        $html = '';
        foreach ($body->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }
        $html = RichText::normalize($html);

        // Metinsiz sayfa (kapak, künye görseli): bölüm olmaz.
        if (trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '') {
            return null;
        }

        return ['title' => $title, 'html' => $html];
    }

    private function storeImage(ZipArchive $zip, string $file, DOMElement $img): ?int
    {
        if (! $this->imageHandler) {
            $this->skippedImages++;

            return null;
        }

        $src = rawurldecode($img->getAttribute('src'));
        $path = $this->resolve(dirname($file) === '.' ? '' : dirname($file).'/', $src);
        $contents = $this->read($zip, $path);
        $info = $contents !== null ? @getimagesizefromstring($contents) : false;
        $mime = $info['mime'] ?? null;

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            $this->skippedImages++;

            return null;
        }

        $title = trim($img->getAttribute('alt')) ?: 'Görsel '.($this->importedImages + 1);
        $id = ($this->imageHandler)($contents, $mime, basename($path), mb_substr($title, 0, 200));

        $id ? $this->importedImages++ : $this->skippedImages++;

        return $id ?: null;
    }

    private function parseHtml(string $xhtml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        // XML bildirimi/DOCTYPE'ı at, HTML ayrıştırıcıyla oku (XHTML'deki &nbsp; gibi varlıklar
        // XML ayrıştırıcıda hata verir); encoding ipucu Türkçe karakterler için.
        $xhtml = preg_replace(['/<\?xml[^>]*\?>/i', '/<!DOCTYPE[^>]*>/i'], '', $xhtml);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$xhtml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return $dom;
    }

    private function read(ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > self::MAX_ENTRY_BYTES) {
            return null;
        }

        $contents = $zip->getFromName($name);

        return $contents === false ? null : $contents;
    }

    /** "OEBPS/" + "../Images/a.png" → "Images/a.png" */
    private function resolve(string $base, string $href): string
    {
        $parts = [];
        foreach (explode('/', $base.$href) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '.' && $segment !== '') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }
}
