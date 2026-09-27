<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz C: premium üyelik geçerliliği (bitiş tarihi) ve premium indirimi — kampanya
 * indirimiyle toplanmaz, hangisi avantajlıysa o (toplantı kararı).
 */
class PremiumPricingTest extends TestCase
{
    use RefreshDatabase;

    private function reader(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('okur');

        return $user;
    }

    public function test_premium_is_only_valid_until_its_end_date(): void
    {
        $this->assertTrue($this->reader(['is_premium' => true, 'premium_until' => now()->addDay()])->isPremium());
        $this->assertTrue($this->reader(['is_premium' => true, 'premium_until' => null])->isPremium(), 'süresiz premium');
        // Önceden süresi dolan üye premium sayılmaya devam ediyordu.
        $this->assertFalse($this->reader(['is_premium' => true, 'premium_until' => now()->subDay()])->isPremium());
        $this->assertFalse($this->reader(['is_premium' => false])->isPremium());

        $this->assertSame(2, User::premium()->count());
    }

    public function test_premium_member_gets_the_premium_discount(): void
    {
        $premium = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);
        $free = $this->reader();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 100]);

        $this->assertSame('70.00', $book->priceFor($premium));
        $this->assertSame('100.00', $book->priceFor($free));
        $this->assertSame('100.00', $book->priceFor(null));
    }

    public function test_campaign_and_premium_discounts_do_not_stack_the_better_one_wins(): void
    {
        $premium = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);

        $smallCampaign = Book::factory()->create(['price' => 100, 'discount_price' => 90]);
        $bigCampaign = Book::factory()->create(['price' => 100, 'discount_price' => 50]);

        $this->assertSame('70.00', $smallCampaign->priceFor($premium), 'premium %30 kampanyadan iyi');
        $this->assertSame('50.00', $bigCampaign->priceFor($premium), 'kampanya premiumdan iyi');
        $this->assertTrue($smallCampaign->isPremiumPriceFor($premium));
        $this->assertFalse($bigCampaign->isPremiumPriceFor($premium));
    }

    public function test_premium_discount_percent_comes_from_admin_settings(): void
    {
        PlatformSettings::set(['premium_discount_percent' => 50]);
        $premium = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);
        $book = Book::factory()->create(['price' => 80]);

        $this->assertSame('40.00', $book->priceFor($premium));
    }

    public function test_premium_member_is_charged_the_premium_price_on_purchase_and_checkout(): void
    {
        $premium = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);
        $single = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 100]);
        $inCart = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 200]);

        $this->actingAs($premium)->post(route('panel.satin-al', $single));
        $this->actingAs($premium)->post(route('panel.sepet.kitap.ekle', $inCart));
        $this->actingAs($premium)->get(route('panel.sepetim'))->assertSee('140,00 TL');
        $this->actingAs($premium)->post(route('panel.sepet.checkout'));

        $this->assertSame('70.00', $premium->purchases()->where('book_id', $single->id)->value('amount'));
        $this->assertSame('140.00', $premium->purchases()->where('book_id', $inCart->id)->value('amount'));
    }

    public function test_book_page_shows_premium_teaser_to_non_members_and_premium_label_to_members(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 100]);

        $this->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('Premium üyelere')
            ->assertSee('70,00 TL')
            ->assertSee(route('abonelik'), false);

        $premium = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);

        $this->actingAs($premium)->get(route('kitaplar.show', $book))
            ->assertOk()
            ->assertSee('Premium fiyatınız')
            ->assertDontSee('Premium üyelere');
    }

    public function test_expired_premium_member_pays_the_normal_price(): void
    {
        $expired = $this->reader(['is_premium' => true, 'premium_until' => now()->subDay()]);
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 100]);

        $this->assertSame('100.00', $book->priceFor($expired));
    }
}
