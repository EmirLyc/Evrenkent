<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\User;
use App\Notifications\ContentPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UpcomingBooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_yakinda_pili_only_shows_approved_books_with_a_scheduled_date(): void
    {
        $upcoming = Book::factory()->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => now()->addDays(5),
            'title' => 'Yakında Kitabı',
        ]);
        Book::factory()->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => null,
            'title' => 'Tarihsiz Onaylı',
        ]);
        Book::factory()->create([
            'status' => ContentStatus::Taslak,
            'title' => 'Taslak Kitap',
        ]);

        $response = $this->get(route('kitaplar.index', ['raf' => 'yakinda']))->assertOk();

        $response->assertSee('Yakında Kitabı')
            ->assertDontSee('Tarihsiz Onaylı')
            ->assertDontSee('Taslak Kitap');
    }

    public function test_upcoming_book_show_page_hides_purchase_and_read_buttons(): void
    {
        $reader = User::factory()->create();
        $reader->assignRole('okur');
        $book = Book::factory()->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => now()->addDays(5),
        ]);

        $response = $this->actingAs($reader)->get(route('kitaplar.show', $book))->assertOk();

        // Not: "Satın Al" burada kontrol edilmiyor çünkü sidebar'daki "Satın Aldıklarım"
        // linki de bu alt string'i içeriyor (girişli her kullanıcıda görünür) — asıl
        // satın alma butonu için daha spesifik "Sepete Ekle"/"Favorilere Ekle" yeterli.
        $response->assertSee('Yakında Çıkacak')
            ->assertDontSee('Sepete Ekle')
            ->assertDontSee('Favorilere Ekle');
    }

    public function test_approved_book_without_scheduled_date_is_not_publicly_visible(): void
    {
        $book = Book::factory()->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => null,
        ]);

        $this->get(route('kitaplar.show', $book))->assertNotFound();
    }

    public function test_author_can_still_preview_their_own_approved_book_without_a_scheduled_date(): void
    {
        $author = User::factory()->create();
        $author->assignRole('yazar');
        $book = Book::factory()->for($author, 'author')->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => null,
        ]);

        $this->actingAs($author)
            ->get(route('kitaplar.show', $book))
            ->assertOk();
    }

    public function test_publish_scheduled_books_command_publishes_due_books_and_notifies_author(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $author->assignRole('yazar');
        $book = Book::factory()->for($author, 'author')->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => now()->subMinute(),
        ]);

        Artisan::call('books:publish-scheduled');

        $book->refresh();
        $this->assertSame(ContentStatus::Yayinda, $book->status);
        $this->assertNotNull($book->published_at);
        Notification::assertSentTo($author, ContentPublished::class);
    }

    public function test_publish_scheduled_books_command_ignores_future_dates(): void
    {
        Notification::fake();

        $book = Book::factory()->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => now()->addDays(3),
        ]);

        Artisan::call('books:publish-scheduled');

        $this->assertSame(ContentStatus::Onaylandi, $book->fresh()->status);
    }
}
