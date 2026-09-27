<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_book_page_is_visible_to_guests(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Herkese Açık Kitap']);

        $this->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('Herkese Açık Kitap');
    }

    public function test_draft_book_page_returns_404(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Taslak]);

        $this->get(route('kitaplar.show', $book))->assertNotFound();
    }

    public function test_authenticated_reader_sees_favorite_and_purchase_actions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('okur');
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda]);

        $this->actingAs($user)
            ->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('Favorile')
            ->assertSee('Satın Al')
            ->assertSee('Sepete Ekle');
    }

    public function test_rating_is_shown_only_when_reviews_have_actually_been_entered(): void
    {
        $rated = Book::factory()->create([
            'status' => ContentStatus::Yayinda,
            'average_rating' => 4.5,
            'review_count' => 12,
        ]);
        $unrated = Book::factory()->create(['status' => ContentStatus::Yayinda]);

        $this->get(route('kitaplar.show', $rated))
            ->assertOk()
            ->assertSee('4.5')
            ->assertSee('12 değerlendirme');

        // Hiç değerlendirme girilmemişse sahte "0.0 (0 değerlendirme)" yazmamalı.
        $this->get(route('kitaplar.show', $unrated))
            ->assertOk()
            ->assertDontSee('değerlendirme');
    }

    public function test_category_tags_link_to_the_catalog_filtered_by_that_category(): void
    {
        $category = \App\Models\Category::factory()->create(['name' => 'Roman', 'slug' => 'roman']);
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda]);
        $book->categories()->attach($category);

        $this->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee(route('kitaplar.index', ['kategori' => 'roman']), false);
    }

    public function test_yayinda_badge_is_hidden_on_the_public_page_but_shown_to_the_author_previewing_a_draft(): void
    {
        $published = Book::factory()->create(['status' => ContentStatus::Yayinda]);
        $this->get(route('kitaplar.show', $published))->assertOk()->assertDontSee('Yayında');

        $author = User::factory()->create();
        $author->assignRole('yazar');
        $draft = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)
            ->get(route('kitaplar.show', $draft))
            ->assertOk()
            ->assertSee('Taslak');
    }

    public function test_discounted_book_shows_the_discounted_price_it_is_actually_sold_for(): void
    {
        $book = Book::factory()->create([
            'status' => ContentStatus::Yayinda,
            'price' => 120,
            'discount_price' => 89.90,
        ]);

        // Satın alma/sepet indirimli fiyatı ücretlendiriyor — sayfa da onu göstermeli,
        // eski fiyat sadece üstü çizili referans olarak.
        $this->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('89,90 TL')
            ->assertSee('120,00 TL');
    }

    public function test_related_books_strip_shows_discounted_prices(): void
    {
        $category = \App\Models\Category::factory()->create();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda]);
        $related = Book::factory()->create([
            'status' => ContentStatus::Yayinda,
            'published_at' => now(),
            'price' => 150,
            'discount_price' => 99.50,
        ]);
        $book->categories()->attach($category);
        $related->categories()->attach($category);

        $this->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('99,50 TL');
    }

    public function test_purchased_book_shows_purchase_date_and_read_and_library_actions_instead_of_buying(): void
    {
        $user = User::factory()->create();
        $user->assignRole('okur');
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 75]);
        $user->purchases()->create([
            'book_id' => $book->id,
            'amount' => 75,
            'purchased_at' => '2025-05-15 10:00:00',
            'payment_status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('Satın alındı')
            ->assertSee('15 Mayıs 2025')
            ->assertSee('Şimdi Oku')
            ->assertSee('Kütüphanemde Görüntüle')
            ->assertSee(route('panel.index'), false)
            ->assertDontSee(route('panel.satin-al', $book), false)
            ->assertDontSee('75,00 TL');
    }

    public function test_content_stats_only_show_the_fields_the_author_actually_filled_in(): void
    {
        $book = Book::factory()->create([
            'status' => ContentStatus::Yayinda,
            'page_count' => 310,
            'document_count' => null,
            'video_count' => null,
        ]);

        $this->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('310 sayfa')
            ->assertDontSee('belge')
            ->assertDontSee('video');
    }
}
