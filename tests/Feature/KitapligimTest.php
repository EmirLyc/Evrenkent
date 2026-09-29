<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitapligimTest extends TestCase
{
    use RefreshDatabase;

    private function okur(): User
    {
        $user = User::factory()->create();
        $user->assignRole('okur');

        return $user;
    }

    public function test_purchased_book_appears_with_purchased_badge(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Satın Alınan Kitap', 'price' => 50]);
        $user->purchases()->create([
            'book_id' => $book->id,
            'amount' => 50,
            'purchased_at' => now(),
            'payment_status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get(route('panel.index'))
            ->assertOk()
            ->assertSee('Satın Alınan Kitap')
            ->assertSee('Satın alındı')
            ->assertSee(now()->translatedFormat('j F Y'))
            ->assertSee(route('kitaplar.oku', $book), false)
            ->assertSee('Toplam 1 kitap');
    }

    public function test_favorited_book_appears_with_favori_badge(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Favori Kitap']);
        $user->favorites()->create([
            'favoritable_type' => Book::class,
            'favoritable_id' => $book->id,
        ]);

        $this->actingAs($user)
            ->get(route('panel.index'))
            ->assertOk()
            ->assertSee('Favori Kitap')
            ->assertSee('Favorilerde')
            ->assertSee('Favorilerden çıkar');
    }

    public function test_reading_progress_and_finished_books_show_like_the_mockup(): void
    {
        $user = $this->okur();
        $reading = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Okunan Kitap', 'price' => 0]);
        $finished = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Biten Kitap', 'price' => 0]);
        $user->readingListItems()->create(['readable_type' => Book::class, 'readable_id' => $reading->id, 'status' => ReadingStatus::Listede, 'progress' => 45]);
        $user->readingListItems()->create(['readable_type' => Book::class, 'readable_id' => $finished->id, 'status' => ReadingStatus::Tamamlandi, 'completed_at' => '2026-05-29 10:00', 'progress' => 100]);

        $this->actingAs($user)
            ->get(route('panel.index'))
            ->assertOk()
            ->assertSee('%45 okundu')
            ->assertSee('aria-valuenow="45"', false)
            ->assertSee('Okumaya Devam Et')
            ->assertSee('Okundu')
            ->assertSee('29 Mayıs 2026')
            ->assertSee('Tekrar Oku')
            ->assertSee('Okundu olarak işaretle')
            ->assertSee('Yeniden okumaya başla');
    }

    public function test_unreadable_book_links_to_its_page_instead_of_reading(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Ücretli Kitap', 'price' => 80]);
        $user->readingListItems()->create(['readable_type' => Book::class, 'readable_id' => $book->id, 'status' => ReadingStatus::Listede]);

        $this->actingAs($user)
            ->get(route('panel.index'))
            ->assertSee('Okuma listende')
            ->assertSee('İncele')
            ->assertDontSee(route('kitaplar.oku', $book), false);
    }

    public function test_sort_and_grid_view(): void
    {
        $user = $this->okur();
        foreach (['Beta' => 3, 'Alfa' => 1, 'Gama' => 2] as $title => $daysAgo) {
            $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => $title]);
            $user->favorites()->create(['favoritable_type' => Book::class, 'favoritable_id' => $book->id])
                ->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }

        // Varsayılan: son eklenen üstte, liste görünümü.
        $this->actingAs($user)->get(route('panel.index'))->assertSeeInOrder(['Alfa', 'Gama', 'Beta'])->assertSee('aria-current="true"', false);
        $this->actingAs($user)->get(route('panel.index', ['sirala' => 'ad']))->assertSeeInOrder(['Alfa', 'Beta', 'Gama']);
        $this->actingAs($user)->get(route('panel.index', ['gorunum' => 'izgara']))->assertOk()->assertSee('aspect-[2/3]', false);
    }

    public function test_page_turns_record_progress_and_only_move_forward(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create(['order' => 1]);
        Chapter::factory()->for($book)->create(['order' => 2]);

        $this->actingAs($user)->postJson(route('kitaplar.konum', $book), ['bolum' => 2, 'sayfa' => 8, 'toplam' => 20])->assertNoContent();
        $item = $user->readingListItemFor($book);
        $this->assertSame(45, $item->progress);
        $this->assertSame(2, $item->last_chapter_number);

        // İçindekiler'den başa dönmek ilerlemeyi geri almaz; bölüm konumu yine güncellenir.
        $this->actingAs($user)->postJson(route('kitaplar.konum', $book), ['bolum' => 1, 'sayfa' => 0, 'toplam' => 20])->assertNoContent();
        $item->refresh();
        $this->assertSame(45, $item->progress);
        $this->assertSame(1, $item->last_chapter_number);

        // Okundu işaretlenince %100, yeniden okumaya başlayınca sıfırdan.
        $this->actingAs($user)->patch(route('panel.okuma-listesi.tamamla', $item));
        $this->assertSame(100, $item->refresh()->progress);
        $this->actingAs($user)->patch(route('panel.okuma-listesi.listeye-al', $item));
        $this->assertNull($item->refresh()->progress);
    }

    public function test_author_previewing_own_draft_does_not_land_in_the_library(): void
    {
        $author = User::factory()->create();
        $author->assignRole('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak, 'title' => 'Önizlenen Taslak']);
        Chapter::factory()->for($book)->create(['order' => 1]);

        $this->actingAs($author)->get(route('kitaplar.oku', $book))->assertOk();
        $this->actingAs($author)->postJson(route('kitaplar.konum', $book), ['bolum' => 1, 'sayfa' => 3, 'toplam' => 10])->assertNoContent();

        $this->assertNull($author->readingListItemFor($book));
        $this->actingAs($author)->get(route('panel.index'))->assertDontSee('Önizlenen Taslak');
    }

    public function test_unrelated_book_does_not_appear(): void
    {
        $user = $this->okur();
        Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'İlgisiz Kitap']);

        $this->actingAs($user)
            ->get(route('panel.index'))
            ->assertOk()
            ->assertDontSee('İlgisiz Kitap');
    }

    public function test_book_entry_links_to_its_show_page(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Tıklanabilir Kitap']);
        $user->favorites()->create(['favoritable_type' => Book::class, 'favoritable_id' => $book->id]);

        $this->actingAs($user)
            ->get(route('panel.index'))
            ->assertOk()
            ->assertSee(route('kitaplar.show', $book), false);
    }

    public function test_empty_state_when_no_related_books(): void
    {
        $user = $this->okur();

        $this->actingAs($user)
            ->get(route('panel.index'))
            ->assertOk()
            ->assertSee('Henüz kitaplığınıza eklenmiş bir eser yok.');
    }
}
