<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_dashboard_is_only_accessible_to_super_admin(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('panel.adminpanel.index'))->assertOk();

        $yazar = User::factory()->create();
        $yazar->assignRole('yazar');
        $this->actingAs($yazar)->get(route('panel.adminpanel.index'))->assertForbidden();

        $okur = User::factory()->create();
        $okur->assignRole('okur');
        $this->actingAs($okur)->get(route('panel.adminpanel.index'))->assertForbidden();
    }

    public function test_dashboard_shows_real_totals(): void
    {
        $admin = $this->superAdmin();

        Book::factory()->count(3)->create();
        MagazineIssue::factory()->for($admin, 'editor')->count(2)->create();

        $response = $this->actingAs($admin)->get(route('panel.adminpanel.index'))->assertOk();

        $response->assertSee('Toplam Kitap');
        $response->assertSeeText((string) User::count());
    }

    public function test_pending_approvals_counts_only_submitted_or_in_review_content(): void
    {
        $admin = $this->superAdmin();

        Book::factory()->create(['status' => ContentStatus::Gonderildi]);
        Book::factory()->create(['status' => ContentStatus::Incelemede]);
        Book::factory()->create(['status' => ContentStatus::Taslak]);
        Book::factory()->create(['status' => ContentStatus::Yayinda]);

        $response = $this->actingAs($admin)->get(route('panel.adminpanel.index'))->assertOk();

        $response->assertSee('Kitap Onayları');
        $response->assertSeeInOrder(['Kitap Onayları', '2']);
    }

    public function test_live_activity_shows_newest_events_first(): void
    {
        $admin = $this->superAdmin();

        $reader = User::factory()->create(['name' => 'Eski Kullanıcı', 'created_at' => now()->subDays(5)]);
        $book = Book::factory()->create(['title' => 'Yeni Satılan Kitap']);
        Purchase::factory()->for($reader)->for($book)->create([
            'purchased_at' => now()->subMinute(),
        ]);

        User::factory()->create(['name' => 'Az Önce Kayıt Olan', 'created_at' => now()]);

        $response = $this->actingAs($admin)->get(route('panel.adminpanel.index'))->assertOk();

        $response->assertSeeInOrder(['Az Önce Kayıt Olan', 'Yeni Satılan Kitap']);
    }

    public function test_placeholder_page_renders_for_a_known_section(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('panel.adminpanel.placeholder', 'sozlukler'))
            ->assertOk()
            ->assertSee('Sözlükler');
    }

    public function test_placeholder_page_404s_for_an_unknown_section(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('panel.adminpanel.placeholder', 'olmayan-bolum'))
            ->assertNotFound();
    }

    public function test_super_admin_redirect_path_points_to_own_dashboard(): void
    {
        $admin = $this->superAdmin();

        $this->assertSame('/panel/admin-panel', $admin->panelPath());
        $this->assertSame('/', $admin->redirectPath());
    }
}
