<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\NoteType;
use App\Models\Book;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz C: çalışma alanı kotası — ücretsiz hesapta Defter/Not/Alıntı her biri ayrı sınırlı
 * (Süper Admin ayarlar), premiumda sınırsız, favoriler herkese sınırsız. Faz H5 ("Bazı
 * Prensipler"): ücretsiz hesapta 1 defter (defter uzunluğu ayrıca ~1.000 kelime — NotebookTest).
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
            $user->notes()->create(['type' => 'defter', 'title' => "Defter {$i}", 'content' => "<p>Girdi {$i}</p>"]);
        }
    }

    public function test_free_account_has_one_notebook_and_each_area_counts_separately(): void
    {
        $user = $this->reader();
        $this->fillDefter($user, 1);

        $this->actingAs($user)
            ->post(route('panel.defterim.yeni'))
            ->assertSessionHas('quota', "Ücretsiz hesapta en fazla 1 defter tutabilirsiniz. Sınırsız defter için Premium'a geçebilirsiniz.");

        $this->assertSame(1, $user->notes()->where('type', 'defter')->count());

        // Defter dolu olsa da Not alanı ayrı sayılıyor.
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda]);
        $this->actingAs($user)
            ->post(route('panel.notlar.ekle'), ['type' => 'not', 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'content' => 'Not'])
            ->assertSessionHasNoErrors();
    }

    public function test_premium_member_has_no_quota(): void
    {
        $user = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);
        $this->fillDefter($user, 3);

        $this->actingAs($user)->post(route('panel.defterim.yeni'))->assertSessionMissing('quota');

        $this->assertSame(4, $user->notes()->count());
    }

    public function test_when_premium_expires_existing_notebooks_stay_but_new_ones_are_blocked(): void
    {
        $user = $this->reader(['is_premium' => true, 'premium_until' => now()->subDay()]);
        $this->fillDefter($user, 3);

        $this->actingAs($user)->post(route('panel.defterim.yeni'))->assertSessionHas('quota');
        $this->assertSame(3, $user->notes()->count());

        // Mevcut defterler düzenlenip silinebiliyor.
        $note = $user->notes()->first();
        $this->actingAs($user)->putJson(route('panel.defterim.kaydet', $note), ['title' => 'Güncel', 'content' => '<p>Yeni metin</p>'])->assertOk();
        $this->actingAs($user)->delete(route('panel.defterim.sil', $note))->assertRedirect(route('panel.defterim'));
        $this->assertSame(2, $user->notes()->count());
    }

    public function test_quota_comes_from_admin_settings(): void
    {
        PlatformSettings::set(['quota_defter' => 2]);
        $user = $this->reader();
        $this->fillDefter($user, 1);

        $this->actingAs($user)->post(route('panel.defterim.yeni'))->assertSessionMissing('quota');
        $this->actingAs($user)->post(route('panel.defterim.yeni'))->assertSessionHas('quota');
        $this->assertSame(2, $user->notes()->count());
    }

    public function test_pages_show_the_quota_for_free_accounts_only(): void
    {
        $free = $this->reader();
        $this->fillDefter($free, 1);
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda]);
        foreach (range(1, 3) as $i) {
            $free->notes()->create(['type' => 'not', 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'content' => "Not {$i}"]);
        }

        $this->actingAs($free)->get(route('panel.notlarim'))->assertOk()->assertSee('3 / 10 kayıt');
        $this->actingAs($free)->followingRedirects()->get(route('panel.defterim'))->assertOk()
            ->assertSee('Ücretsiz hesapta 1 defter tutabilirsiniz.')
            ->assertSee('/ 1.000');

        $premium = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);
        $this->fillDefter($premium, 1);
        $this->actingAs($premium)->followingRedirects()->get(route('panel.defterim'))->assertOk()
            ->assertDontSee('Ücretsiz hesapta')
            ->assertDontSee('/ 1.000');
    }

    public function test_account_specific_limits_replace_the_general_ones_and_are_still_shown(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $user = $this->reader();

        // Süper Admin → Kullanıcılar → Düzenle: boş bırakılan alan genel ayarda kalır.
        $this->actingAs($admin)->put(route('panel.adminpanel.kullanicilar.guncelle', $user), [
            'name' => $user->name, 'email' => $user->email, 'roles' => ['okur'],
            'quota' => ['defter' => 5, 'not' => 30, 'alinti' => '', 'defter_words' => 3000],
        ])->assertSessionHasNoErrors();

        $user->refresh();
        // MySQL JSON sütunu anahtar sırasını kendi düzenine çeviriyor; sıra değil değerler önemli.
        $overrides = $user->quota_overrides;
        ksort($overrides);
        $this->assertSame(['defter' => 5, 'defter_words' => 3000, 'not' => 30], $overrides);
        $this->assertSame(5, $user->noteQuota(NoteType::Defter));
        $this->assertSame(30, $user->noteQuota(NoteType::Not));
        $this->assertSame(10, $user->noteQuota(NoteType::Alinti));
        $this->assertSame(3000, $user->notebookWordLimit());

        // Sınır yine yazıyor — sadece daha yüksek.
        $this->fillDefter($user, 2);
        $this->actingAs($user)->post(route('panel.defterim.yeni'))->assertSessionMissing('quota');
        $this->actingAs($user)->get(route('panel.notlarim'))->assertSee('0 / 30 kayıt');
        $this->actingAs($user)->get(route('panel.aboneligim'))->assertSee('3 / 5');
        $this->actingAs($user)->followingRedirects()->get(route('panel.defterim'))->assertSee('/ 3.000');

        // Premium üyede özel sınır da yok.
        $user->update(['is_premium' => true]);
        $this->assertNull($user->noteQuota(NoteType::Not));
        $this->assertNull($user->notebookWordLimit());

        // Hepsi boşaltılınca alan temizlenir.
        $this->actingAs($admin)->put(route('panel.adminpanel.kullanicilar.guncelle', $user), [
            'name' => $user->name, 'email' => $user->email, 'roles' => ['okur'], 'quota' => ['defter' => '', 'not' => ''],
        ])->assertSessionHasNoErrors();
        $this->assertNull($user->refresh()->quota_overrides);
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
