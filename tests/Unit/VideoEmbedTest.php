<?php

namespace Tests\Unit;

use App\Support\RichText;
use App\Support\VideoEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Faz F3: metne eklenen YouTube / Vimeo bağlantısı.
 */
class VideoEmbedTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function validUrls(): array
    {
        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0'],
            'youtube kısa' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0'],
            'youtube shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0'],
            'youtube başlangıç' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&start=90'],
            'vimeo' => ['https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789?autoplay=1&dnt=1'],
            'vimeo liste dışı' => ['https://vimeo.com/123456789/abcdef1234', 'https://player.vimeo.com/video/123456789?autoplay=1&dnt=1&h=abcdef1234'],
        ];
    }

    #[DataProvider('validUrls')]
    public function test_supported_links_become_player_urls(string $url, string $embed): void
    {
        $this->assertSame($embed, VideoEmbed::parse($url)['embed']);
    }

    /** @return array<string, array{string}> */
    public static function invalidUrls(): array
    {
        return [
            'başka site' => ['https://evil.example/watch?v=dQw4w9WgXcQ'],
            'javascript' => ['javascript:alert(1)'],
            'bozuk kimlik' => ['https://www.youtube.com/watch?v=bad"><script>'],
            'youtube kanal' => ['https://www.youtube.com/@kanal'],
            'boş' => [''],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_unsupported_links_are_rejected(string $url): void
    {
        $this->assertNull(VideoEmbed::parse($url));
    }

    public function test_reading_html_has_the_video_line_and_drops_unknown_sources(): void
    {
        $html = RichText::render('<p>A</p><figure data-video="https://youtu.be/dQw4w9WgXcQ" data-title="Osmanlı Diplomasisinde Yazışma Usulü" data-duration="12:45"></figure><figure data-video="https://evil.example/x" data-title="Kötü"></figure>');

        $this->assertStringContainsString('class="video-link"', $html);
        $this->assertStringContainsString('data-embed-url="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&amp;rel=0"', $html);
        $this->assertStringContainsString('Video: Osmanlı Diplomasisinde Yazışma Usulü', $html);
        $this->assertStringContainsString('(12:45 dk.)', $html);
        $this->assertStringNotContainsString('Kötü', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('<figure', $html);
    }

    public function test_video_attributes_survive_sanitizing_and_count_as_content(): void
    {
        $saved = RichText::normalize('<figure data-video="https://vimeo.com/123456789" data-title="Belgesel" onclick="x()" style="color:red"></figure>');

        $this->assertSame('<figure data-video="https://vimeo.com/123456789" data-title="Belgesel"></figure>', $saved);
        $this->assertTrue(RichText::hasText($saved));
    }
}
