<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

/**
 * Faz F1: bölüm/makale içeriği temizlenmiş HTML olarak saklanıyor.
 */
class RichTextTest extends TestCase
{
    public function test_plain_text_becomes_escaped_paragraphs(): void
    {
        $this->assertSame(
            "<p>Birinci satır<br>\nikinci 3 &lt; 5 &amp; satır</p><p>İkinci paragraf</p>",
            RichText::normalize("Birinci satır\nikinci 3 < 5 & satır\n\n\nİkinci paragraf")
        );
    }

    public function test_dangerous_markup_is_removed(): void
    {
        $html = RichText::normalize('<p onclick="x()">Metin <a href="javascript:alert(1)">kötü</a></p><script>alert(1)</script><img src=x onerror=alert(1)><iframe src="https://kotu.example"></iframe>');

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('<p>Metin', $html);
    }

    public function test_other_editors_tags_are_mapped_and_formatting_kept(): void
    {
        // Faz G2: h1 içerikte geçerli ("Başlık 1"), h5/h6 → h4; stil (renk) atılır.
        $html = RichText::normalize('<h1 style="color:red">Başlık</h1><h6>Derin</h6><div>Trix <b>kalın</b> <i>eğik</i></div><p><a href="https://ornek.com">bağlantı</a></p><p></p>');

        $this->assertSame(
            '<h1>Başlık</h1><h4>Derin</h4><p>Trix <strong>kalın</strong> <em>eğik</em></p><p><a href="https://ornek.com" rel="noopener noreferrer nofollow">bağlantı</a></p>',
            $html
        );
    }

    public function test_editor_markers_are_whitelisted(): void
    {
        $html = RichText::normalize(
            '<p data-align="center" style="text-align:center">Orta <span data-font="garamond" data-size="20" style="font-family:x">yazı</span><span data-cite="Kaynak"></span></p>'
            .'<nav data-toc="true"></nav><hr data-page-break="true"><table style="min-width:50px"><colgroup><col></colgroup><tbody><tr><td colspan="2" data-colwidth="9" onclick="x()"><p>h</p></td></tr></tbody></table>'
            .'<figure data-image="3" data-caption="Alt yazı" onload="x()"></figure><section data-bibliography="true"></section>'
        );

        $this->assertSame(
            '<p data-align="center">Orta <span data-font="garamond" data-size="20">yazı</span><span data-cite="Kaynak"></span></p>'
            .'<nav data-toc="true"></nav><hr data-page-break="true" /><table><tbody><tr><td colspan="2"><p>h</p></td></tr></tbody></table>'
            .'<figure data-image="3" data-caption="Alt yazı"></figure><section data-bibliography="true"></section>',
            $html
        );
    }

    public function test_render_resolves_fonts_alignment_and_rejects_unknown_values(): void
    {
        $html = RichText::render('<p data-align="justify">A <span data-font="garamond" data-size="20">B</span> <span data-font="comic" data-size="400">C</span></p>');

        $this->assertSame('<p class="rt-align-justify">A <span style="font-family: \'EB Garamond\', Garamond, serif; font-size: 1.25em">B</span> <span>C</span></p>', $html);
    }

    public function test_heading_numbers_follow_the_mockup(): void
    {
        $html = RichText::render('<h1>Modern Devlet</h1><h2>Egemenlik</h2><h3>İç Egemenlik</h3><h1>Dönüşüm</h1><h2>Yeni</h2>', 'dn', null, ['numbering' => true]);

        $this->assertStringContainsString('<span class="rt-num">I.</span>Modern Devlet', $html);
        $this->assertStringContainsString('<span class="rt-num">1.1.</span>Egemenlik', $html);
        $this->assertStringContainsString('<span class="rt-num">1.1.1.</span>İç Egemenlik', $html);
        $this->assertStringContainsString('<span class="rt-num">II.</span>Dönüşüm', $html);
        $this->assertStringContainsString('<span class="rt-num">2.1.</span>Yeni', $html);
    }

    public function test_footnotes_render_as_lettered_references_with_a_list(): void
    {
        // Faz H2 ("Okuma moduna dair"): dipnot harfle, kaynak rakamla; işaret pencere için metnini taşır.
        $html = RichText::render('<p>Bir<span data-footnote="İlk &lt;kaynak&gt;"></span> iki<span data-footnote="İkinci"></span></p>', 'bolum-2');

        $this->assertStringContainsString('<sup class="footnote-ref"><a href="#bolum-2-1" id="bolum-2-ref-1" aria-label="Dipnot a" data-ref="dipnot" data-ref-label="a"', $html);
        $this->assertStringContainsString('<a href="#bolum-2-2" id="bolum-2-ref-2" aria-label="Dipnot b" data-ref="dipnot" data-ref-label="b" data-ref-text="İkinci">b</a>', $html);
        $this->assertStringContainsString('<li id="bolum-2-1">İlk &lt;kaynak&gt; <a href="#bolum-2-ref-1"', $html);
        $this->assertStringNotContainsString('data-footnote', $html);
    }

    public function test_footnote_letters_continue_after_z_like_css_lower_alpha(): void
    {
        $this->assertSame(['a', 'b', 'z', 'aa', 'az', 'ba'], array_map(RichText::footnoteLetter(...), [1, 2, 26, 27, 52, 53]));
    }

    public function test_work_title_in_asterisks_is_italic_in_the_bibliography(): void
    {
        $this->assertSame('Krasner, Stephen D., <em>Sovereignty</em>, 1999 &amp; &lt;b&gt;', RichText::citationHtml('Krasner, Stephen D., *Sovereignty*, 1999 & <b>'));

        $source = 'Krasner, *Sovereignty: Organized Hypocrisy*, Princeton, 1999.';
        $html = RichText::render('<p>Egemenlik<span data-cite="'.e($source).'"></span></p><section data-bibliography="true"></section>', 'dn', null, ['sources' => [$source], 'bibliographyUrl' => '']);

        $this->assertStringContainsString('<li id="kaynak-1">Krasner, <em>Sovereignty: Organized Hypocrisy</em>, Princeton, 1999.</li>', $html);
        // Okumadaki kart ve pencere metni ham hâliyle alıyor (satırlara tarayıcı ayırıyor).
        $this->assertStringContainsString('data-ref-text="Krasner, *Sovereignty: Organized Hypocrisy*, Princeton, 1999."', $html);
    }

    public function test_empty_editor_output_has_no_text(): void
    {
        $this->assertFalse(RichText::hasText('<p></p><p>&nbsp;</p><p><br></p>'));
        $this->assertTrue(RichText::hasText('<p>a</p>'));
        $this->assertTrue(RichText::hasText('düz metin'));
    }
}
