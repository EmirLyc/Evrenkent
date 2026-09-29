<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookReadingTest extends TestCase
{
    use RefreshDatabase;

    private function okur(): User
    {
        $user = User::factory()->create();
        $user->assignRole('okur');

        return $user;
    }

    public function test_draft_book_reading_page_returns_404(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Taslak, 'price' => 0]);
        Chapter::factory()->for($book)->create(['order' => 1]);

        $this->get(route('kitaplar.oku', $book))->assertNotFound();
    }

    public function test_free_published_book_is_readable_without_purchase(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        $chapter = Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'Serbest Bölüm']);

        $this->get(route('kitaplar.oku', $book))
            ->assertOk()
            ->assertSee('Serbest Bölüm');
    }

    public function test_paid_book_redirects_to_show_page_when_not_purchased(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 99]);
        Chapter::factory()->for($book)->create(['order' => 1]);

        $this->actingAs($user)
            ->get(route('kitaplar.oku', $book))
            ->assertRedirect(route('kitaplar.show', $book));
    }

    public function test_purchased_book_is_readable(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 99]);
        $chapter = Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'Satın Alınan Bölüm']);
        $user->purchases()->create([
            'book_id' => $book->id,
            'amount' => 99,
            'purchased_at' => now(),
            'payment_status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get(route('kitaplar.oku', $book))
            ->assertOk()
            ->assertSee('Satın Alınan Bölüm');
    }

    public function test_author_can_always_read_own_book(): void
    {
        $author = User::factory()->create();
        $author->assignRole('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak, 'price' => 99]);
        Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'Yazarın Bölümü']);

        $this->actingAs($author)
            ->get(route('kitaplar.oku', $book))
            ->assertOk()
            ->assertSee('Yazarın Bölümü');
    }

    public function test_viewing_a_chapter_creates_reading_list_item_and_tracks_progress(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create(['order' => 1]);
        $chapter2 = Chapter::factory()->for($book)->create(['order' => 2]);

        $this->actingAs($user)->get(route('kitaplar.oku', [$book, $chapter2->order]))->assertOk();

        $item = $user->readingListItemFor($book);
        $this->assertNotNull($item);
        $this->assertSame(ReadingStatus::Listede, $item->status);
        $this->assertSame(2, $item->last_chapter_number);
    }

    public function test_saved_position_in_a_removed_chapter_falls_back_to_first_chapter(): void
    {
        $user = $this->okur();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'İlk Bölüm']);
        $user->readingListItems()->create([
            'readable_type' => Book::class,
            'readable_id' => $book->id,
            'status' => ReadingStatus::Listede,
            'last_chapter_number' => 5,
        ]);

        $this->actingAs($user)->get(route('kitaplar.oku', $book))->assertOk()->assertSee('İlk Bölüm');

        $this->assertSame(1, $user->readingListItemFor($book)->last_chapter_number);
    }

    public function test_author_can_preview_book_without_chapters_despite_stale_position(): void
    {
        $author = User::factory()->create();
        $author->assignRole('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);
        $author->readingListItems()->create([
            'readable_type' => Book::class,
            'readable_id' => $book->id,
            'status' => ReadingStatus::Listede,
            'last_chapter_number' => 2,
        ]);

        $this->actingAs($author)->get(route('kitaplar.oku', $book))->assertOk();
    }

    public function test_nonexistent_chapter_number_returns_404(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create(['order' => 1]);

        $this->get(route('kitaplar.oku', [$book, 99]))->assertNotFound();
    }
}
