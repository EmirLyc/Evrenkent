<?php

namespace Tests\Unit;

use App\Support\EpubImporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeDocx;
use Tests\Support\FakeEpub;

/**
 * EPUB'dan kitap bölümleri (DOCX'in ikinci seçeneği).
 */
class EpubImporterTest extends TestCase
{
    public function test_each_reading_file_becomes_a_chapter_and_cover_and_toc_are_skipped(): void
    {
        $path = FakeEpub::make([
            'bolum1.xhtml' => '<h1>Birinci Bölüm</h1><p>Sabah <em>sisi</em> limanı sardı&nbsp;ve kadırga yanaştı.</p><h2>Rıhtım</h2><p>Hamallar indi.</p>',
            'bolum2.xhtml' => '<section><h2>İkinci Bölüm</h2><blockquote><p>Deniz ne verdiyse geri istedi.</p></blockquote></section>',
        ]);

        $chapters = (new EpubImporter)->importChapters($path);

        $this->assertSame(['Birinci Bölüm', 'İkinci Bölüm'], array_column($chapters, 'title'));
        $this->assertStringContainsString('<p>Sabah <em>sisi</em> limanı sardı', $chapters[0]['html']);
        $this->assertStringContainsString('<h2>Rıhtım</h2>', $chapters[0]['html']);
        $this->assertStringNotContainsString('Birinci Bölüm', $chapters[0]['html']);
        $this->assertStringContainsString('<blockquote><p>Deniz ne verdiyse geri istedi.</p></blockquote>', $chapters[1]['html']);
    }

    public function test_epub3_footnotes_become_inline_footnotes_even_across_files(): void
    {
        $path = FakeEpub::make([
            'bolum1.xhtml' => '<h1>Bölüm</h1><p>Metin<sup><a epub:type="noteref" href="#n1">1</a></sup> ve devamı<a epub:type="noteref" href="notlar.xhtml#n2">2</a>.</p>'
                .'<aside epub:type="footnote" id="n1"><p>1. Aynı dosyadaki not.</p></aside>',
            'notlar.xhtml' => '<section epub:type="endnotes"><h2>Notlar</h2><ol><li epub:type="endnote" id="n2"><p>Başka dosyadaki not. <a epub:type="backlink" href="bolum1.xhtml">↩</a></p></li></ol></section>',
        ]);

        $chapters = (new EpubImporter)->importChapters($path);

        $this->assertCount(1, $chapters);
        $this->assertSame(
            '<p>Metin<span data-footnote="Aynı dosyadaki not."></span> ve devamı<span data-footnote="Başka dosyadaki not."></span>.</p>',
            $chapters[0]['html']
        );
    }

    public function test_images_become_documents_through_the_handler(): void
    {
        $stored = [];
        $importer = (new EpubImporter)->withImages(function (string $contents, string $mime, string $name, string $title) use (&$stored) {
            $stored[] = [$mime, $name, $title];

            return 42;
        });

        $chapters = $importer->importChapters(FakeEpub::make(
            ['bolum1.xhtml' => '<h1>Bölüm</h1><p>Harita:</p><p><img src="../Images/harita.png" alt="Eski İstanbul haritası"/></p>'],
            ['Images/harita.png' => FakeDocx::png(), 'Images/kapak.png' => FakeDocx::png()]
        ));

        $this->assertSame([['image/png', 'harita.png', 'Eski İstanbul haritası']], $stored);
        $this->assertStringContainsString('<span data-document="42"></span>', $chapters[0]['html']);
        $this->assertSame(['imported' => 1, 'skipped' => 0], $importer->imageStats());
    }

    public function test_non_epub_file_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EpubImporter)->importChapters(FakeDocx::make(FakeDocx::p('Word dosyası')));
    }
}
