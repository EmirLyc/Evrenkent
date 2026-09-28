<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ücretsiz eser "Kitaplığıma Ekle" (Epic Games'teki gibi): 0 TL'lik bir satın alma kaydı; fiyatı
 * sonradan artsa da alan kullanıcının kitaplığında kalır ve okunabilir.
 */
class FreeBookClaimTest extends TestCase
{
    use RefreshDatabase;

    private function freeBook(array $attributes = []): Book
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'published_at' => now(), 'price' => 0, ...$attributes]);
        Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'İlk Bölüm']);

        return $book;
    }

    public function test_free_book_offers_add_to_library_instead_of_buying(): void
    {
        $book = $this->freeBook();

        $this->actingAs(User::factory()->create())->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('Ücretsiz')
            ->assertSee('Kitaplığıma Ekle')
            ->assertDontSee('0,00 TL')
            ->assertDontSee('Sepete Ekle')
            ->assertSee(route('kitaplar.oku', $book), false);

        $this->app['auth']->forgetGuards();
        $this->get(route('kitaplar.show', $book))->assertSee('Giriş Yap ve Kitaplığına Ekle');
        $this->get(route('kitaplar.index'))->assertSee('Ücretsiz');
    }

    public function test_claimed_book_stays_in_the_library_after_its_price_rises(): void
    {
        $reader = User::factory()->create();
        $other = User::factory()->create();
        $book = $this->freeBook();

        $this->actingAs($reader)->post(route('panel.satin-al', $book))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'kitaplığınıza eklendi'));
        $this->assertSame('0.00', $reader->purchases()->sole()->amount);

        $book->update(['price' => 120]);

        $this->assertTrue($book->fresh()->isReadableBy($reader));
        $this->actingAs($reader)->get(route('kitaplar.oku', $book))->assertOk();
        $this->actingAs($reader)->get(route('kitaplar.show', $book))
            ->assertSee('Kitaplığınızda')
            ->assertSee('ücretsiz olarak kitaplığınıza eklediniz');
        $this->actingAs($reader)->get(route('panel.satin-aldiklarim'))->assertSee('Ücretsiz');

        // Almayan kullanıcı artık satın almak zorunda.
        $this->actingAs($other)->get(route('kitaplar.oku', $book))->assertRedirect(route('kitaplar.show', $book));
        $this->actingAs($other)->get(route('kitaplar.show', $book))->assertSee('Satın Al')->assertDontSee('Kitaplığıma Ekle');
    }

    public function test_limited_time_free_campaign_can_be_claimed(): void
    {
        $reader = User::factory()->create();
        $book = $this->freeBook(['price' => 150, 'discount_price' => 0, 'discount_ends_at' => now()->addDays(3)]);

        $this->actingAs($reader)->get(route('kitaplar.show', $book))
            ->assertSee('150,00 TL')
            ->assertSee('tarihine kadar ücretsiz')
            ->assertSee('Kitaplığıma Ekle');

        $this->actingAs($reader)->post(route('panel.satin-al', $book));
        $this->travel(5)->days();

        $this->assertTrue($book->fresh()->isReadableBy($reader));
    }

    public function test_adding_a_free_book_to_the_cart_claims_it_directly(): void
    {
        $reader = User::factory()->create();
        $book = $this->freeBook();

        $this->actingAs($reader)->postJson(route('panel.sepet.kitap.ekle', $book))
            ->assertJson(['added' => false, 'reason' => 'claimed']);

        $this->assertTrue($reader->hasPurchased($book));
        $this->assertSame(0, $reader->cartItems()->count());
    }
}
