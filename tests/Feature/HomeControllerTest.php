<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\MagazineIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_the_newest_books_first_in_the_yeni_cikanlar_shelf(): void
    {
        $old = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Eski Kitap', 'published_at' => now()->subDays(10)]);
        $new = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Yeni Kitap', 'published_at' => now()]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Yeni Çıkanlar')
            ->assertSeeInOrder([$new->title, $old->title]);
    }

    public function test_home_mixes_books_upcoming_books_editors_picks_and_magazine_issues(): void
    {
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Yayındaki Kitap']);
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'is_editors_pick' => true, 'title' => 'Seçilmiş Kitap']);
        Book::factory()->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => now()->addDays(3),
            'title' => 'Yakında Kitap',
        ]);
        MagazineIssue::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Yayındaki Sayı']);
        MagazineIssue::factory()->create(['status' => ContentStatus::Taslak, 'title' => 'Taslak Sayı']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Yakında Çıkacaklar')
            ->assertSee('Yakında Kitap')
            ->assertSee('Editörün Seçkisi')
            ->assertSee('Seçilmiş Kitap')
            ->assertSee('Yeni Dergi Sayıları')
            ->assertSee('Yayındaki Sayı')
            ->assertDontSee('Taslak Sayı');
    }

    public function test_empty_shelves_are_hidden_instead_of_showing_empty_messages(): void
    {
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'is_editors_pick' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Editörün Seçkisi')
            ->assertDontSee('Yakında Çıkacaklar')
            ->assertDontSee('Yeni Dergi Sayıları');
    }

    public function test_completely_empty_site_shows_an_honest_empty_state(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Henüz yayınlanmış bir içerik yok.');
    }

    public function test_type_switcher_shows_the_real_published_totals(): void
    {
        Book::factory()->count(9)->create(['status' => ContentStatus::Yayinda, 'published_at' => now()]);
        Book::factory()->create(['status' => ContentStatus::Taslak]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('9 eser')
            ->assertSee(route('kitaplar.index'), false)
            ->assertSee(route('dergiler.index'), false);
    }

    public function test_categories_section_only_lists_categories_with_published_books(): void
    {
        $roman = Category::factory()->create(['name' => 'Roman', 'slug' => 'roman']);
        $bos = Category::factory()->create(['name' => 'Boş Kategori', 'slug' => 'bos-kategori']);
        $sadeceTaslak = Category::factory()->create(['name' => 'Sadece Taslak', 'slug' => 'sadece-taslak']);

        Book::factory()->create(['status' => ContentStatus::Yayinda])->categories()->attach($roman);
        Book::factory()->create(['status' => ContentStatus::Taslak])->categories()->attach($sadeceTaslak);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('kitaplar.index', ['kategori' => 'roman']), false)
            ->assertDontSee('Boş Kategori')
            ->assertDontSee('Sadece Taslak');
    }

    public function test_old_type_switch_urls_redirect_to_their_new_pages(): void
    {
        $this->get('/?tur=dergiler')->assertRedirect(route('dergiler.index'));
        $this->get('/?tur=kitaplar&raf=cok-satanlar')->assertRedirect(route('kitaplar.index', ['raf' => 'cok-satanlar']));
        $this->get('/?tur=kitaplar')->assertRedirect(route('kitaplar.index'));
    }
}
