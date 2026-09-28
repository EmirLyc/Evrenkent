<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\ReadingStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Okuma modu — Faz F4 (mockup 3: kâğıt sayfa, bölüm açılışı, bölümler çekmecesi) ve Faz G4
 * (sayfalı okuma: yazarın sayfa oranında sabit sayfalar, bütün kitap tek akışta, okur puntoyu
 * değil sayfayı büyütür).
 */
class ReadingModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_is_read_as_pages_in_the_authors_ratio_with_every_chapter(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0, 'title' => 'Diplomasi Tarihi', 'page_ratio' => '13x20', 'heading_numbering' => true]);
        foreach (['Giriş', 'Yazışmalar', 'Sonuç'] as $index => $title) {
            Chapter::factory()->for($book)->create(['order' => $index + 1, 'title' => $title, 'content' => "<p>{$title} metni.</p>"]);
        }

        $response = $this->get(route('kitaplar.oku', [$book, 2]));
        // İstenen bölümün açılış sayfasından başlar (bileşen ayarı, JSON içinde).
        $this->assertMatchesRegularExpression('/initialChapter\\\\u0022:2[,}]/', $response->getContent());

        $response
            ->assertOk()
            // Sayfa: 736 px genişlik, 13 × 20 oranında 1132 px; metin 624 px sütunlarda.
            ->assertSee('x-data="pagedReader(', false)
            ->assertSee('width: 736px; height: 1132px', false)
            ->assertSee('class="rt-flow"', false)
            // Bütün kitap tek akışta, bölüm açılışları numaralı (Başlık 1 → "I.").
            ->assertSeeInOrder(['data-chapter="1"', 'Giriş metni.', 'data-chapter="2"', 'Yazışmalar metni.', 'data-chapter="3"', 'Sonuç metni.'], false)
            ->assertSee('<div class="rt-opener-number">II.</div>', false)
            ->assertSee('<div class="rt-opener-title" role="heading" aria-level="1">Yazışmalar</div>', false)
            // Okur puntoyu değil sayfayı büyütür.
            ->assertSee('Sayfayı büyüt')
            ->assertDontSee('Yazıyı büyüt')
            // Bölümler çekmecesi, tanıtım sayfasına geri bağlantı; uygulama kabuğu yok.
            ->assertSee('href="'.route('kitaplar.oku', [$book, 3]).'"', false)
            ->assertSee(route('kitaplar.show', $book), false)
            ->assertDontSee('Kitap, yazar veya konu ara');
    }

    public function test_preface_has_no_opener_and_numbering_follows_the_book_setting(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0, 'page_ratio' => '16x24', 'heading_numbering' => false]);
        Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'Giriş', 'is_preface' => true, 'content' => '<p>Önsöz.</p>']);
        Chapter::factory()->for($book)->create(['order' => 2, 'title' => 'Birinci Bölüm', 'content' => '<p>Metin.</p>']);

        $response = $this->get(route('kitaplar.oku', $book))->assertOk()
            ->assertSee('height: 1104px', false)
            ->assertSee('Birinci Bölüm')
            ->assertDontSee('rt-opener-number', false);

        $this->assertSame(1, substr_count($response->getContent(), '<header class="rt-opener">'));
    }

    public function test_table_of_contents_before_the_first_heading_is_kept_as_the_opening_page(): void
    {
        // G4 denemesinde bulundu: ilk Başlık 1'den önce sadece İçindekiler varsa giriş bölümü
        // "metinsiz" sayılıp atılıyordu (mockup'ta İçindekiler kitabın başında).
        $sections = \App\Support\BookDocument::split('<nav data-toc="true"></nav><h1>Birinci</h1><p>Metin.</p>');

        $this->assertCount(2, $sections);
        $this->assertTrue($sections[0]['preface']);
        $this->assertStringContainsString('data-toc', $sections[0]['html']);
    }

    public function test_turning_into_another_chapter_updates_the_reading_position(): void
    {
        $reader = User::factory()->create();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create(['order' => 1]);
        Chapter::factory()->for($book)->create(['order' => 2]);

        $this->actingAs($reader)->postJson(route('kitaplar.konum', $book), ['bolum' => 2])->assertNoContent();
        $item = $reader->readingListItemFor($book);
        $this->assertSame([2, ReadingStatus::Listede], [$item->last_chapter_number, $item->status]);

        $this->actingAs($reader)->postJson(route('kitaplar.konum', $book), ['bolum' => 9])->assertStatus(422);

        $paid = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 90]);
        Chapter::factory()->for($paid)->create(['order' => 1]);
        $this->actingAs($reader)->postJson(route('kitaplar.konum', $paid), ['bolum' => 1])->assertForbidden();
    }

    public function test_article_is_read_as_pages_in_the_magazine_ratio(): void
    {
        $magazine = Magazine::factory()->create(['name' => 'Tarih Dergisi']);
        $issue = MagazineIssue::factory()->create(['magazine_id' => $magazine->id, 'issue_number' => 12, 'status' => ContentStatus::Yayinda]);
        $article = Article::factory()->create(['magazine_issue_id' => $issue->id, 'status' => ContentStatus::Yayinda, 'title' => 'Sefaretnameler', 'page_ratio' => '21x27.5']);

        $this->get(route('makaleler.show', $article))
            ->assertOk()
            ->assertSee('reader-paper', false)
            ->assertSee('width: 736px; height: 964px', false)
            ->assertSee('Tarih Dergisi · Sayı 12')
            ->assertSee('Sefaretnameler')
            ->assertSee($article->author->name)
            ->assertSee(route('dergiler.show', $issue), false);
    }
}
