<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_lists_all_published_books_not_just_a_slice(): void
    {
        Book::factory()->count(20)->create(['status' => ContentStatus::Yayinda]);
        Book::factory()->create(['status' => ContentStatus::Taslak, 'title' => 'Taslak Kitap']);

        $response = $this->get(route('kitaplar.index'))->assertOk();

        $response->assertViewHas('books', fn ($books) => $books->total() === 20);
        $response->assertDontSee('Taslak Kitap');
    }

    public function test_catalog_paginates_at_eighteen_per_page(): void
    {
        Book::factory()->count(20)->create(['status' => ContentStatus::Yayinda]);

        $response = $this->get(route('kitaplar.index'))->assertOk();

        $response->assertViewHas('books', fn ($books) => $books->count() === 18 && $books->hasMorePages());
    }

    public function test_raf_filter_switches_which_books_are_listed(): void
    {
        $picked = Book::factory()->create(['status' => ContentStatus::Yayinda, 'is_editors_pick' => true, 'title' => 'Seçilmiş']);
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'is_editors_pick' => false, 'title' => 'Diğer Kitap']);

        $this->get(route('kitaplar.index', ['raf' => 'editorun-seckisi']))
            ->assertOk()
            ->assertSee('Seçilmiş')
            ->assertDontSee('Diğer Kitap');
    }

    public function test_catalog_shows_the_type_switcher_with_kitaplar_selected(): void
    {
        $this->get(route('kitaplar.index'))
            ->assertOk()
            ->assertSee(route('dergiler.index'), false)
            ->assertSee('Sözlükler');
    }

    public function test_cok_satanlar_raf_orders_books_by_purchase_count(): void
    {
        $lessPopular = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Az Satan']);
        $bestseller = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Çok Satan']);

        \App\Models\Purchase::factory()->for($bestseller)->create();
        \App\Models\Purchase::factory()->for($bestseller)->create();
        \App\Models\Purchase::factory()->for($lessPopular)->create();

        $this->get(route('kitaplar.index', ['raf' => 'cok-satanlar']))
            ->assertOk()
            ->assertSeeInOrder([$bestseller->title, $lessPopular->title]);
    }

    public function test_firsatlar_raf_only_shows_discounted_books_with_struck_through_price(): void
    {
        Book::factory()->create([
            'status' => ContentStatus::Yayinda,
            'price' => 100,
            'discount_price' => 75,
            'title' => 'İndirimli Kitap',
        ]);
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'discount_price' => null, 'title' => 'Normal Kitap']);

        $this->get(route('kitaplar.index', ['raf' => 'firsatlar']))
            ->assertOk()
            ->assertSee('İndirimli Kitap')
            ->assertDontSee('Normal Kitap')
            ->assertSee('75,00 TL');
    }

    public function test_empty_shelf_shows_its_own_empty_message_instead_of_an_error(): void
    {
        $this->get(route('kitaplar.index', ['raf' => 'firsatlar']))
            ->assertOk()
            ->assertSee('Şu an indirimde bir kitap yok.');
    }

    public function test_invalid_raf_falls_back_to_yeni_cikanlar_instead_of_erroring(): void
    {
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Görünen Kitap']);

        $this->get(route('kitaplar.index', ['raf' => 'olmayan-bir-deger']))
            ->assertOk()
            ->assertSee('Görünen Kitap');
    }

    public function test_kategori_filter_shows_only_published_books_in_that_category(): void
    {
        $roman = Category::factory()->create(['name' => 'Roman', 'slug' => 'roman']);
        $siir = Category::factory()->create(['name' => 'Şiir', 'slug' => 'siir']);

        $romanBook = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Roman Kitabı']);
        $romanBook->categories()->attach($roman);

        $siirBook = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Şiir Kitabı']);
        $siirBook->categories()->attach($siir);

        $draftInRoman = Book::factory()->create(['status' => ContentStatus::Taslak, 'title' => 'Taslak Roman']);
        $draftInRoman->categories()->attach($roman);

        $this->get(route('kitaplar.index', ['kategori' => 'roman']))
            ->assertOk()
            ->assertSee('Roman Kitabı')
            ->assertDontSee('Şiir Kitabı')
            ->assertDontSee('Taslak Roman');
    }

    public function test_kategori_filter_with_unknown_slug_returns_404(): void
    {
        $this->get(route('kitaplar.index', ['kategori' => 'olmayan-kategori']))->assertNotFound();
    }
}
