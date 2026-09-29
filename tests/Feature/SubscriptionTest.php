<?php

namespace Tests\Feature;

use App\Enums\SubscriptionPlan;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function reader(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('okur');

        return $user;
    }

    public function test_subscription_page_is_public_and_shows_admin_configured_prices(): void
    {
        PlatformSettings::set(['premium_monthly_price' => 79, 'premium_yearly_price' => 790, 'premium_discount_percent' => 25]);

        $this->get(route('abonelik'))
            ->assertOk()
            ->assertSee('Premium Hesap')
            ->assertSee('79')
            ->assertSee('790')
            ->assertSee('%25 indirim')
            // 12 x 79 = 948 → 790 yaklaşık %17 tasarruf
            ->assertSee('%17 tasarruf')
            // Ziyaretçide satın alma formu değil, girişe götüren link.
            ->assertSee('Giriş Yap ve Premium')
            ->assertSee(route('panel.aboneligim'), false);
    }

    public function test_guest_cannot_subscribe(): void
    {
        $this->post(route('panel.abonelik.satin-al'), ['plan' => 'aylik'])->assertRedirect(route('login'));
    }

    public function test_monthly_subscription_activates_premium_for_a_month_and_records_the_payment(): void
    {
        $user = $this->reader();
        $this->travelTo(now()->startOfDay());

        $this->actingAs($user)
            ->post(route('panel.abonelik.satin-al'), ['plan' => 'aylik'])
            ->assertRedirect(route('panel.aboneligim'));

        $user->refresh();
        $this->assertTrue($user->isPremium());
        $this->assertTrue($user->premium_until->equalTo(now()->addMonthNoOverflow()));

        $subscription = $user->subscriptions()->first();
        $this->assertSame(SubscriptionPlan::Aylik, $subscription->plan);
        $this->assertSame('99.00', $subscription->amount);
    }

    public function test_yearly_subscription_uses_the_yearly_price_and_length(): void
    {
        $user = $this->reader();

        $this->actingAs($user)->post(route('panel.abonelik.satin-al'), ['plan' => 'yillik']);

        $user->refresh();
        $this->assertTrue($user->premium_until->between(now()->addYear()->subMinute(), now()->addYear()->addMinute()));
        $this->assertSame('990.00', $user->subscriptions()->first()->amount);
    }

    public function test_renewing_early_extends_from_the_current_end_date(): void
    {
        $currentEnd = now()->addDays(10)->startOfMinute();
        $user = $this->reader(['is_premium' => true, 'premium_until' => $currentEnd]);

        $this->actingAs($user)->post(route('panel.abonelik.satin-al'), ['plan' => 'aylik']);

        $this->assertTrue($user->refresh()->premium_until->equalTo($currentEnd->copy()->addMonthNoOverflow()));
    }

    public function test_lifetime_premium_is_not_turned_into_a_timed_subscription(): void
    {
        $user = $this->reader(['is_premium' => true, 'premium_until' => null]);

        $this->actingAs($user)->post(route('panel.abonelik.satin-al'), ['plan' => 'aylik']);

        $this->assertNull($user->refresh()->premium_until);
        $this->assertSame(0, $user->subscriptions()->count());
    }

    public function test_invalid_plan_is_rejected(): void
    {
        $this->actingAs($this->reader())
            ->post(route('panel.abonelik.satin-al'), ['plan' => 'haftalik'])
            ->assertSessionHasErrors('plan');
    }

    public function test_aboneligim_shows_quota_usage_for_free_accounts_and_payment_history_for_members(): void
    {
        $free = $this->reader();
        $free->notes()->create(['type' => 'defter', 'content' => 'x']);

        // Faz H5: ücretsiz hesapta 1 defter ("Bazı Prensipler").
        $this->actingAs($free)->get(route('panel.aboneligim'))
            ->assertOk()
            ->assertSee('Ücretsiz hesap')
            ->assertSee('1 / 1')
            ->assertSee("Premium'a Geç");

        $member = $this->reader();
        $member->subscribe(SubscriptionPlan::Aylik);

        $this->actingAs($member)->get(route('panel.aboneligim'))
            ->assertOk()
            ->assertSee('Premium üyesiniz')
            ->assertSee('Aylık')
            ->assertSee('99,00 TL')
            ->assertSee('Süreyi Uzat');
    }
}
