<?php

namespace Tests\Unit;

use App\Support\DocxImporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeDocx;

/**
 * Faz F1: Word dosyasından bölüm/makale içeriği.
 */
class DocxImporterTest extends TestCase
{
    public function test_formatting_footnotes_lists_quotes_and_links_are_carried_over(): void
    {
        $body = FakeDocx::p('Deneme', 'Balk1')
            .'<w:p><w:r><w:t xml:space="preserve">Düz </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>kalın</w:t></w:r><w:r><w:t xml:space="preserve"> ve </w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>eğik</w:t></w:r><w:r><w:rPr><w:b w:val="0"/></w:rPr><w:t xml:space="preserve"> değil</w:t></w:r><w:r><w:footnoteReference w:id="1"/></w:r></w:p>'
            .FakeDocx::p('Alt Bölüm', 'Balk2')
            .'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>madde bir</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>madde iki</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>sıra bir</w:t></w:r></w:p>'
            .FakeDocx::p('Bir alıntı', 'Alnt')
            .'<w:p><w:hyperlink r:id="rId9"><w:r><w:t>site</w:t></w:r></w:hyperlink><w:del><w:r><w:delText>silinen</w:delText></w:r></w:del></w:p>'
            .'<w:p/>';

        $path = FakeDocx::make($body, ['1' => 'Kaynak: Şeyh Galip'], ['rId9' => 'https://ornek.com']);
        $result = (new DocxImporter)->importSingle($path);

        $this->assertSame('Deneme', $result['title']);
        $this->assertSame(
            '<p>Düz <strong>kalın</strong> ve <em>eğik</em> değil<span data-footnote="Kaynak: Şeyh Galip"></span></p>'
            .'<h2>Alt Bölüm</h2>'
            .'<ul><li>madde bir</li><li>madde iki</li></ul><ol><li>sıra bir</li></ol>'
            .'<blockquote><p>Bir alıntı</p></blockquote>'
            .'<p><a href="https://ornek.com" rel="noopener noreferrer nofollow">site</a></p>',
            $result['html']
        );
    }

    public function test_book_is_split_into_chapters_at_top_level_headings(): void
    {
        $body = FakeDocx::p('Önsöz metni')
            .FakeDocx::p('Birinci Bölüm', 'Balk1')
            .FakeDocx::p('Birinci metin')
            .FakeDocx::p('Ara başlık', 'Balk2')
            // Özel stil "heading 1"den türetilmiş — o da bölüm başlığı sayılır.
            .FakeDocx::p('İkinci Bölüm', 'BolumBasligi')
            .FakeDocx::p('İkinci metin');

        $chapters = (new DocxImporter)->importChapters(FakeDocx::make($body));

        $this->assertCount(3, $chapters);
        $this->assertSame([null, 'Birinci Bölüm', 'İkinci Bölüm'], array_column($chapters, 'title'));
        $this->assertSame('<p>Önsöz metni</p>', $chapters[0]['html']);
        $this->assertSame('<p>Birinci metin</p><h2>Ara başlık</h2>', $chapters[1]['html']);
        $this->assertSame('<p>İkinci metin</p>', $chapters[2]['html']);
    }

    public function test_document_without_headings_is_a_single_untitled_chapter(): void
    {
        $chapters = (new DocxImporter)->importChapters(FakeDocx::make(FakeDocx::p('Tek paragraf')));

        $this->assertSame([['title' => null, 'html' => '<p>Tek paragraf</p>']], $chapters);
    }

    public function test_non_docx_file_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($path, 'bu bir word dosyası değil');

        $this->expectException(InvalidArgumentException::class);
        (new DocxImporter)->importSingle($path);
    }
}
