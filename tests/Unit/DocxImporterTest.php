<?php

namespace Tests\Unit;

use App\Support\DocxImporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeDocx;

/**
 * Faz F1: Word dosyasından içerik. Faz G2: tek belge — Word'deki başlık katmanları aynen
 * (Başlık 1 → h1 …), tablolar, sayfa sonları, hizalama; "Konu Başlığı" eserin adı.
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
        $result = (new DocxImporter)->importDocument($path);

        // "Başlık 1" artık eserin adı değil bir bölüm başlığı; ad sadece "Konu Başlığı"ndan gelir.
        $this->assertNull($result['title']);
        $this->assertSame(
            '<h1>Deneme</h1>'
            .'<p>Düz <strong>kalın</strong> ve <em>eğik</em> değil<span data-footnote="Kaynak: Şeyh Galip"></span></p>'
            .'<h2>Alt Bölüm</h2>'
            .'<ul><li>madde bir</li><li>madde iki</li></ul><ol><li>sıra bir</li></ol>'
            .'<blockquote><p>Bir alıntı</p></blockquote>'
            .'<p><a href="https://ornek.com" rel="noopener noreferrer nofollow">site</a></p>',
            $result['html']
        );
    }

    public function test_heading_levels_are_kept_and_derived_styles_count(): void
    {
        $body = FakeDocx::p('Önsöz metni')
            .FakeDocx::p('Birinci Bölüm', 'Balk1')
            .FakeDocx::p('Ara başlık', 'Balk2')
            .FakeDocx::p('Daha alt', 'Balk3')
            // Özel stil "heading 1"den türetilmiş — o da Başlık 1.
            .FakeDocx::p('İkinci Bölüm', 'BolumBasligi');

        $this->assertSame(
            '<p>Önsöz metni</p><h1>Birinci Bölüm</h1><h2>Ara başlık</h2><h3>Daha alt</h3><h1>İkinci Bölüm</h1>',
            (new DocxImporter)->importDocument(FakeDocx::make($body))['html']
        );
    }

    public function test_title_tables_page_breaks_and_alignment(): void
    {
        $body = FakeDocx::p('Eserin Adı', 'KonuBal')
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Ortalı</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Önce</w:t></w:r><w:r><w:br w:type="page"/></w:r><w:r><w:t>Sonra</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:pageBreakBefore/></w:pPr><w:r><w:t>Yeni sayfada</w:t></w:r></w:p>'
            .'<w:tbl><w:tr><w:trPr><w:tblHeader/></w:trPr><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>Başlık</w:t></w:r></w:p></w:tc></w:tr>'
            .'<w:tr><w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>B</w:t></w:r></w:p></w:tc></w:tr></w:tbl>';

        $result = (new DocxImporter)->importDocument(FakeDocx::make($body));

        $this->assertSame('Eserin Adı', $result['title']);
        $this->assertSame(
            '<p data-align="center">Ortalı</p>'
            .'<p>Önce</p><hr data-page-break="true" /><p>Sonra</p>'
            .'<hr data-page-break="true" /><p>Yeni sayfada</p>'
            .'<table><tbody><tr><th colspan="2"><p>Başlık</p></th></tr><tr><td><p><strong>A</strong></p></td><td><p>B</p></td></tr></tbody></table>',
            $result['html']
        );
    }

    public function test_images_become_inline_figures_through_the_handler(): void
    {
        $path = FakeDocx::make(
            FakeDocx::p('Önce').FakeDocx::imageParagraph('rIdImg1', 'Harita'),
            images: ['rIdImg1' => ['media/image1.png', FakeDocx::png()]]
        );

        $html = (new DocxImporter)->withImages(fn () => 7)->importDocument($path)['html'];

        $this->assertSame('<p>Önce</p><figure data-image="7" data-caption="Harita"></figure>', $html);
    }

    public function test_non_docx_file_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($path, 'bu bir word dosyası değil');

        $this->expectException(InvalidArgumentException::class);
        (new DocxImporter)->importDocument($path);
    }
}
