<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz C: çalışma alanı kotası — ücretsiz hesapta Defter/Not/Alıntı her biri ayrı sınırlı
 * (varsayılan 10, Süper Admin ayarlar), premiumda sınırsız, favoriler herkese sınırsız.
 */
class WorkspaceQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function reader(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('okur');

        return $user;
    }

    private function fillDefter(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user->notes()->create(['type' => 'defter', 'content' => "Girdi {$i}"]);
        }
    }

    public function test_free_account_is_blocked_at_the_quota_and_each_area_counts_separately(): void
    {
        $user = $this->reader();
        $this->fillDefter($user, 10);

        $this->actingAs($user)
            ->post(route('panel.notlar.ekle'), ['type' => 'defter', 'content' => '11. girdi'])
            ->assertSessionHasErrors('quota');

        $this->assertSame(10, $user->notes()->where('type', 'defter')->count());

        // Defter dolu olsa da Not alanı ayrı sayılıyor.
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda]);
        $this->actingAs($user)
            ->post(route('panel.notlar.ekle'), ['type' => 'not', 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'content' => 'Not'])
            ->assertSessionHasNoErrors();
    }

    public function test_premium_member_has_no_quota(): void
    {
        $user = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);
        $this->fillDefter($user, 10);

        $this->actingAs($user)
            ->post(route('panel.notlar.ekle'), ['type' => 'defter', 'content' => '11. girdi'])
            ->assertSessionHasNoErrors();

        $this->assertSame(11, $user->notes()->count());
    }

    public function test_when_premium_expires_existing_notes_stay_but_new_ones_are_blocked(): void
    {
        $user = $this->reader(['is_premium' => true, 'premium_until' => now()->subDay()]);
        $this->fillDefter($user, 12);

        $this->actingAs($user)
            ->post(route('panel.notlar.ekle'), ['type' => 'defter', 'content' => 'Yeni'])
            ->assertSessionHasErrors('quota');

        $this->assertSame(12, $user->notes()->count());

        // Mevcut kayıtlar düzenlenip silinebiliyor.
        $note = $user->notes()->first();
        $this->actingAs($user)->put(route('panel.notlar.guncelle', $note), ['content' => 'Güncel'])->assertSessionHasNoErrors();
        $this->actingAs($user)->delete(route('panel.notlar.sil', $note))->assertRedirect();
        $this->assertSame(11, $user->notes()->count());
    }

    public function test_quota_comes_from_admin_settings(): void
    {
        PlatformSettings::set(['quota_defter' => 2]);
        $user = $this->reader();
        $this->fillDefter($user, 2);

        $this->actingAs($user)
            ->post(route('panel.notlar.ekle'), ['type' => 'defter', 'content' => '3. girdi'])
            ->assertSessionHasErrors('quota');
    }

    public function test_page_shows_the_quota_counter_for_free_accounts_only(): void
    {
        $free = $this->reader();
        $this->fillDefter($free, 3);

        $this->actingAs($free)->get(route('panel.defterim'))->assertOk()->assertSee('3 / 10 kayıt');

        $premium = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);
        $this->actingAs($premium)->get(route('panel.defterim'))->assertOk()->assertDontSee('/ 10 kayıt');
    }

    public function test_favorites_are_unlimited_for_free_accounts(): void
    {
        $user = $this->reader();
        $books = Book::factory()->count(15)->create(['status' => ContentStatus::Yayinda]);

        foreach ($books as $book) {
            $this->actingAs($user)->post(route('panel.favoriler.kitap.toggle', $book));
        }

        $this->assertSame(15, $user->favorites()->count());
    }
}
