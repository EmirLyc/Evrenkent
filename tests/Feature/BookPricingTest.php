<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz B (2026-09-27 revizesi): fiyatı yazar değil Süper Admin belirler; 0 TL ancak açık
 * "Ücretsiz" işaretiyle; kampanya indiriminin bitiş tarihi geçince her yerde devre dışı kalır.
 */
class BookPricingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    // --- Yazar fiyat belirlemez -------------------------------------------------

    public function test_author_forms_no_longer_have_a_price_field(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim.yeni'))
            ->assertOk()
            ->assertDontSee('name="price"', false)
            ->assertSee('Süper Admin tarafından belirlenir');

        foreach (['bilgiler', 'icerik', 'kapak', 'gonder'] as $step) {
            $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', [$book, $step]))
                ->assertOk()
                ->assertDontSee('name="price"', false);
        }
    }

    public function test_a_price_sent_by_the_author_is_ignored(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak, 'price' => 0]);

        $this->actingAs($author)->put(route('panel.yayinlarim.kitap.guncelle', $book), [
            'title' => $book->title,
            'page_ratio' => '13x20',
            'price' => 999,
        ])->assertRedirect();

        $this->assertSame('0.00', $book->refresh()->price);

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'kitap',
            'title' => 'Yeni Kitap',
            'page_ratio' => '13x20',
            'price' => 555,
        ])->assertRedirect();

        $this->assertSame('0.00', Book::where('title', 'Yeni Kitap')->first()->price);
    }

    // --- Onayda fiyat zorunlu ---------------------------------------------------

    public function test_approval_without_a_price_is_rejected_and_the_book_stays_submitted(): void
    {
        $admin = $this->user('super_admin');
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi, 'price' => 0]);

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), ['price' => 0, 'publish_mode' => 'simdi'])
            ->assertSessionHasErrors('price');

        $this->assertSame(ContentStatus::Gonderildi, $book->refresh()->status);
    }

    public function test_approval_as_free_sets_price_to_zero_explicitly(): void
    {
        $admin = $this->user('super_admin');
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi, 'price' => 0]);

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), ['is_free' => 1, 'publish_mode' => 'simdi'])
            ->assertSessionHasNoErrors();

        $book->refresh();
        $this->assertSame(ContentStatus::Yayinda, $book->status);
        $this->assertSame('0.00', $book->price);
    }

    public function test_approval_form_shows_an_empty_required_price_for_a_new_book(): void
    {
        $admin = $this->user('super_admin');
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi, 'price' => 0]);

        $this->actingAs($admin)
            ->get(route('panel.adminpanel.onaylar.kitap.onayla-form', $book))
            ->assertOk()
            ->assertSee('Satış Fiyatı')
            ->assertSee('Bu kitap ücretsiz');
    }

    public function test_admin_book_form_requires_the_free_flag_for_a_zero_price(): void
    {
        $admin = $this->user('super_admin');
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 50]);

        $payload = [
            'author_id' => $book->author_id,
            'title' => $book->title,
            'slug' => $book->slug,
        ];

        $this->actingAs($admin)
            ->put(route('panel.adminpanel.kitaplar.guncelle', $book), $payload + ['price' => 0])
            ->assertSessionHasErrors('price');

        $this->actingAs($admin)
            ->put(route('panel.adminpanel.kitaplar.guncelle', $book), $payload + ['is_free' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', $book->refresh()->price);
    }

    // --- Kampanya bitiş tarihi --------------------------------------------------

    public function test_expired_discount_is_ignored_everywhere(): void
    {
        $reader = $this->user('okur');
        $book = Book::factory()->create([
            'status' => ContentStatus::Yayinda,
            'title' => 'Süresi Geçmiş Kampanya',
            'price' => 100,
            'discount_price' => 60,
            'discount_ends_at' => now()->subDay(),
        ]);

        $this->assertNull($book->activeDiscountPrice());
        $this->assertSame('100.00', $book->priceFor($reader));

        $this->get(route('kitaplar.show', $book))->assertOk()->assertDontSee('60,00 TL');
        $this->get(route('kitaplar.index', ['raf' => 'firsatlar']))->assertOk()->assertDontSee('Süresi Geçmiş Kampanya');

        $this->actingAs($reader)->post(route('panel.satin-al', $book));
        $this->assertSame('100.00', $reader->purchases()->first()->amount);
    }

    public function test_active_timed_discount_is_applied_and_shows_its_end_date(): void
    {
        $reader = $this->user('okur');
        $book = Book::factory()->create([
            'status' => ContentStatus::Yayinda,
            'price' => 100,
            'discount_price' => 70,
            'discount_ends_at' => now()->addDays(10),
        ]);

        $this->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('70,00 TL')
            ->assertSee('tarihine kadar');

        $this->actingAs($reader)->post(route('panel.sepet.kitap.ekle', $book));
        $this->actingAs($reader)->get(route('panel.sepetim'))->assertSee('70,00 TL');

        $this->actingAs($reader)->post(route('panel.sepet.checkout'));
        $this->assertSame('70.00', $reader->purchases()->first()->amount);
    }
}
