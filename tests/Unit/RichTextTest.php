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
        $html = RichText::normalize('<h1 style="color:red">Başlık</h1><div>Trix <b>kalın</b> <i>eğik</i></div><p><a href="https://ornek.com">bağlantı</a></p><p></p>');

        $this->assertSame(
            '<h2>Başlık</h2><p>Trix <strong>kalın</strong> <em>eğik</em></p><p><a href="https://ornek.com" rel="noopener noreferrer nofollow">bağlantı</a></p>',
            $html
        );
    }

    public function test_footnotes_render_as_numbered_references_with_a_list(): void
    {
        $html = RichText::render('<p>Bir<span data-footnote="İlk &lt;kaynak&gt;"></span> iki<span data-footnote="İkinci"></span></p>', 'bolum-2');

        $this->assertStringContainsString('<sup class="footnote-ref"><a href="#bolum-2-1" id="bolum-2-ref-1"', $html);
        $this->assertStringContainsString('<a href="#bolum-2-2" id="bolum-2-ref-2"', $html);
        $this->assertStringContainsString('<li id="bolum-2-1">İlk &lt;kaynak&gt; <a href="#bolum-2-ref-1"', $html);
        $this->assertStringNotContainsString('data-footnote', $html);
    }

    public function test_empty_editor_output_has_no_text(): void
    {
        $this->assertFalse(RichText::hasText('<p></p><p>&nbsp;</p><p><br></p>'));
        $this->assertTrue(RichText::hasText('<p>a</p>'));
        $this->assertTrue(RichText::hasText('düz metin'));
    }
}
