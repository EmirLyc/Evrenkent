<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz F4 (mockup 3): okuma modu — kâğıt sayfa, bölüm numarası + başlık, bölüm konumu,
 * bölümler çekmecesi, klavyeyle geçiş için önceki/sonraki bağlantıları.
 */
class ReadingModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chapter_page_uses_the_reading_layout_with_position_and_navigation(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0, 'title' => 'Diplomasi Tarihi']);
        foreach (['Giriş', 'Yazışmalar', 'Sonuç'] as $index => $title) {
            Chapter::factory()->for($book)->create(['order' => $index + 1, 'title' => $title]);
        }

        $this->get(route('kitaplar.oku', [$book, 2]))
            ->assertOk()
            ->assertSee('reader-paper', false)
            ->assertSee('<h1 class="reader-title mt-2">Yazışmalar</h1>', false)
            ->assertSee('Bölüm 2 / 3')
            ->assertSee('href="'.route('kitaplar.oku', [$book, 1]).'" data-reader-prev', false)
            ->assertSee('href="'.route('kitaplar.oku', [$book, 3]).'" data-reader-next', false)
            // Bölümler çekmecesi tüm bölümleri listeliyor, geçerli bölüm işaretli.
            ->assertSeeInOrder(['Giriş', 'Yazışmalar', 'Sonuç'])
            ->assertSee('aria-current="page"', false)
            // Geri bağlantısı kitabın tanıtım sayfasına.
            ->assertSee(route('kitaplar.show', $book), false)
            // Uygulama kabuğu (arama kutusu) okuma modunda yok.
            ->assertDontSee('Kitap, yazar veya konu ara');
    }

    public function test_first_chapter_has_no_previous_link_and_last_has_no_next_link(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create(['order' => 1]);

        $this->get(route('kitaplar.oku', [$book, 1]))
            ->assertOk()
            ->assertDontSee('data-reader-prev', false)
            ->assertDontSee('data-reader-next', false)
            ->assertSee('Bölüm 1 / 1');
    }

    public function test_article_is_read_in_the_same_mode_with_magazine_and_author(): void
    {
        $magazine = Magazine::factory()->create(['name' => 'Tarih Dergisi']);
        $issue = MagazineIssue::factory()->create(['magazine_id' => $magazine->id, 'issue_number' => 12, 'status' => ContentStatus::Yayinda]);
        $article = Article::factory()->create(['magazine_issue_id' => $issue->id, 'status' => ContentStatus::Yayinda, 'title' => 'Sefaretnameler']);

        $this->get(route('makaleler.show', $article))
            ->assertOk()
            ->assertSee('reader-paper', false)
            ->assertSee('Tarih Dergisi · Sayı 12')
            ->assertSee('Sefaretnameler')
            ->assertSee($article->author->name)
            ->assertSee(route('dergiler.show', $issue), false);
    }
}
