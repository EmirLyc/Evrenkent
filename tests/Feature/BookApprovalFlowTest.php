<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kitap onay akışı (Yazar → Süper Admin). 2026-09-28'e kadar Filament'in tablo aksiyonları
 * üzerinden yazılmıştı; Filament kaldırılınca aynı senaryolar Süper Admin paneline taşındı.
 */
class BookApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function yazar(): User
    {
        $user = User::factory()->create();
        $user->assignRole('yazar');

        return $user;
    }

    public function test_author_submits_and_super_admin_approves_and_publishes_now(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.gonder', $book))->assertRedirect();
        $this->assertSame(ContentStatus::Gonderildi, $book->refresh()->status);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), ['price' => 75, 'publish_mode' => 'simdi'])
            ->assertSessionHasNoErrors();

        $book->refresh();
        $this->assertSame(ContentStatus::Yayinda, $book->status);
        $this->assertNotNull($book->published_at);
        $this->assertSame(['gonderildi', 'onaylandi', 'yayinda'], $book->reviews()->orderBy('id')->pluck('action')->all());
    }

    public function test_super_admin_can_set_a_scheduled_publish_date_while_approving(): void
    {
        $book = Book::factory()->for($this->yazar(), 'author')->create(['status' => ContentStatus::Gonderildi]);
        $target = now()->addDays(10)->startOfMinute();

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), [
                'price' => 60,
                'publish_mode' => 'ileri',
                'scheduled_publish_at' => $target->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasNoErrors();

        $book->refresh();
        $this->assertSame(ContentStatus::Onaylandi, $book->status);
        $this->assertTrue($target->equalTo($book->scheduled_publish_at));
    }

    public function test_a_draft_book_cannot_be_approved_and_is_not_listed(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->for($this->yazar(), 'author')->create(['status' => ContentStatus::Taslak, 'title' => 'Taslaktaki Kitap']);

        $this->actingAs($admin)->get(route('panel.adminpanel.onaylar.kitap.onayla-form', $book))->assertForbidden();
        $this->actingAs($admin)->get(route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar']))->assertDontSee('Taslaktaki Kitap');
    }

    public function test_super_admin_can_reject_a_book_permanently(): void
    {
        $book = Book::factory()->for($this->yazar(), 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'ret', 'note' => 'Kapsam dışı.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ContentStatus::Reddedildi, $book->refresh()->status);
        $this->assertSame('reddedildi', $book->reviews()->latest('id')->first()->action);
    }

    public function test_author_can_resubmit_after_a_revision_request(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'revizyon', 'note' => 'Kapak görseli eksik.']);

        $this->assertSame(ContentStatus::RevizyonIstendi, $book->refresh()->status);

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.gonder', $book))->assertRedirect();
        $this->assertSame(ContentStatus::Gonderildi, $book->refresh()->status);
    }

    public function test_status_cannot_be_tampered_via_the_admin_edit_form(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak, 'price' => 40]);

        $this->actingAs($this->superAdmin())
            ->put(route('panel.adminpanel.kitaplar.guncelle', $book), [
                'author_id' => $author->id,
                'title' => $book->title,
                'slug' => $book->slug,
                'price' => 40,
                'status' => ContentStatus::Yayinda->value,
            ])
            ->assertSessionHasNoErrors();

        // Durum sadece İçerik Onayları akışıyla değişir; formdan gelen değer yok sayılır.
        $this->assertSame(ContentStatus::Taslak, $book->fresh()->status);
    }
}
