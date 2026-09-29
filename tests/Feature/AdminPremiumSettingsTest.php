<?php

namespace Tests\Feature;

use App\Enums\NoteType;
use App\Enums\SubscriptionPlan;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPremiumSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'premium_monthly_price' => 120,
            'premium_yearly_price' => 1100,
            'premium_discount_percent' => 20,
            'quota_defter' => 5,
            'quota_defter_words' => 2500,
            'quota_not' => 15,
            'quota_alinti' => 25,
        ], $overrides);
    }

    public function test_only_super_admin_can_manage_premium_settings(): void
    {
        $this->actingAs($this->superAdmin())->get(route('panel.adminpanel.premium.edit'))->assertOk()->assertSee('Premium Sistemi');

        $reader = User::factory()->create();
        $reader->assignRole('okur');
        $this->actingAs($reader)->get(route('panel.adminpanel.premium.edit'))->assertForbidden();
        $this->actingAs($reader)->put(route('panel.adminpanel.premium.guncelle'), $this->validPayload())->assertForbidden();
    }

    public function test_settings_are_saved_and_used_everywhere(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('panel.adminpanel.premium.guncelle'), $this->validPayload())
            ->assertRedirect(route('panel.adminpanel.premium.edit'));

        PlatformSettings::flush();
        $this->assertSame(120.0, SubscriptionPlan::Aylik->price());
        $this->assertSame(1100.0, SubscriptionPlan::Yillik->price());
        $this->assertSame(20, PlatformSettings::get('premium_discount_percent'));
        $this->assertSame(5, PlatformSettings::noteQuota(NoteType::Defter));
        $this->assertSame(25, PlatformSettings::noteQuota(NoteType::Alinti));
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('panel.adminpanel.premium.guncelle'), $this->validPayload(['premium_discount_percent' => 95, 'quota_not' => 0]))
            ->assertSessionHasErrors(['premium_discount_percent', 'quota_not']);
    }

    public function test_page_shows_active_member_count_and_recent_subscriptions(): void
    {
        $member = User::factory()->create(['name' => 'Abone Okur']);
        $member->subscribe(SubscriptionPlan::Yillik);
        User::factory()->create(['is_premium' => true, 'premium_until' => now()->subDay()]); // süresi dolmuş

        $this->actingAs($this->superAdmin())
            ->get(route('panel.adminpanel.premium.edit'))
            ->assertOk()
            ->assertSee('Abone Okur')
            ->assertSee('Yıllık')
            ->assertViewHas('activePremiumCount', 1);
    }
}
