<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_published_book_by_title(): void
    {
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Sislerin Ardındaki Fener']);
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Alakasız Kitap']);

        $this->get(route('arama', ['q' => 'Fener']))
            ->assertOk()
            ->assertSee('Sislerin Ardındaki Fener')
            ->assertDontSee('Alakasız Kitap');
    }

    public function test_search_finds_published_book_by_author_name(): void
    {
        $author = User::factory()->create(['name' => 'Ahmet Yılmaz']);
        Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Yayinda, 'title' => 'Bir Roman']);

        $this->get(route('arama', ['q' => 'Yılmaz']))
            ->assertOk()
            ->assertSee('Bir Roman');
    }

    public function test_search_finds_published_book_by_description(): void
    {
        Book::factory()->create([
            'status' => ContentStatus::Yayinda,
            'title' => 'Başka Bir Başlık',
            'description' => 'Bu kitap gizemli bir hazineyi anlatır.',
        ]);

        $this->get(route('arama', ['q' => 'hazineyi']))
            ->assertOk()
            ->assertSee('Başka Bir Başlık');
    }

    public function test_search_does_not_return_draft_or_unpublished_content(): void
    {
        Book::factory()->create(['status' => ContentStatus::Taslak, 'title' => 'Gizli Taslak']);

        $this->get(route('arama', ['q' => 'Gizli']))
            ->assertOk()
            ->assertDontSee('Gizli Taslak');
    }

    public function test_search_finds_magazine_issue_by_title(): void
    {
        MagazineIssue::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Kış Sayısı']);
        MagazineIssue::factory()->create(['status' => ContentStatus::Taslak, 'title' => 'Kış Taslağı']);

        $response = $this->get(route('arama', ['q' => 'Kış']))->assertOk();

        $response->assertSee('Kış Sayısı')->assertDontSee('Kış Taslağı');
    }

    public function test_search_finds_article_by_content(): void
    {
        Article::factory()->create([
            'status' => ContentStatus::Yayinda,
            'title' => 'Bir Makale',
            'content' => 'Bu yazı deniz canlıları hakkındadır.',
        ]);

        $this->get(route('arama', ['q' => 'deniz canlıları']))
            ->assertOk()
            ->assertSee('Bir Makale');
    }

    public function test_search_escapes_wildcard_characters(): void
    {
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'alt_baslik']);
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'altXbaslik']);

        // "_" LIKE'ta joker karakter (tek karakter) — escape edilmezse ikisi de eşleşirdi.
        $response = $this->get(route('arama', ['q' => 'alt_baslik']))->assertOk();

        $response->assertSee('alt_baslik')->assertDontSee('altXbaslik');
    }

    public function test_empty_query_shows_no_results_and_no_error(): void
    {
        $this->get(route('arama'))
            ->assertOk()
            ->assertSee('Aramak için');
    }

    public function test_no_results_message_is_shown_for_an_unmatched_query(): void
    {
        $this->get(route('arama', ['q' => 'boyle-bir-sey-yok-12345']))
            ->assertOk()
            ->assertSee('bir sonuç bulunamadı');
    }

    /** İçerik HTML olarak saklanıyor; arama düz metin kopyasında (content_text). */
    public function test_article_content_search_uses_plain_text(): void
    {
        Article::factory()->create([
            'status' => ContentStatus::Yayinda,
            'title' => 'Londra Raporları',
            'content' => "<p>Fuad Paşa'nın raporu<span data-footnote=\"Hariciye arşivi\"></span></p>",
        ]);

        $this->get(route('arama', ['q' => "Paşa'nın"]))->assertSee('Londra Raporları');
        $this->get(route('arama', ['q' => 'Hariciye arşivi']))->assertSee('Londra Raporları');
        // Etiket ve öznitelik adları eşleşmemeli.
        $this->get(route('arama', ['q' => 'span']))->assertDontSee('Londra Raporları');
        $this->get(route('arama', ['q' => 'data-footnote']))->assertDontSee('Londra Raporları');
    }
}
