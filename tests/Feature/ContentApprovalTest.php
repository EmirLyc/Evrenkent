<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContentApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_only_super_admin_can_access_the_approvals_page(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('panel.adminpanel.onaylar.index'))->assertOk();

        $yazar = User::factory()->create();
        $yazar->assignRole('yazar');
        $this->actingAs($yazar)->get(route('panel.adminpanel.onaylar.index'))->assertForbidden();
    }

    public function test_index_lists_only_actionable_content_per_tab(): void
    {
        $admin = $this->superAdmin();

        Book::factory()->create(['title' => 'Bekleyen Kitap', 'status' => ContentStatus::Gonderildi]);
        Book::factory()->create(['title' => 'Taslak Kitap', 'status' => ContentStatus::Taslak]);

        $response = $this->actingAs($admin)->get(route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar']))->assertOk();

        $response->assertSee('Bekleyen Kitap')->assertDontSee('Taslak Kitap');
    }

    public function test_super_admin_can_approve_a_submitted_book(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), [
                'price' => 120,
                'publish_mode' => 'ileri',
                'scheduled_publish_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar']));

        $book->refresh();
        $this->assertSame(ContentStatus::Onaylandi, $book->status);
        $this->assertSame('120.00', $book->price);
        $this->assertNotNull($book->scheduled_publish_at);
        $this->assertSame(1, $book->reviews()->count());
        $this->assertSame('onaylandi', $book->reviews()->first()->action);
        Notification::assertSentTo($book->author, \App\Notifications\ContentApproved::class);
    }

    public function test_super_admin_can_reject_a_submitted_book_with_a_note(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'revizyon', 'note' => 'Kapak eksik.'])
            ->assertRedirect(route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar']));

        $book->refresh();
        $this->assertSame(ContentStatus::RevizyonIstendi, $book->status);
        $this->assertSame('Kapak eksik.', $book->reviews()->first()->note);
        Notification::assertSentTo($book->author, \App\Notifications\ContentRevisionRequested::class);
    }

    public function test_reject_requires_a_note(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'revizyon'])
            ->assertSessionHasErrors('note');
    }

    public function test_super_admin_can_publish_an_approved_book(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $book = Book::factory()->create(['status' => ContentStatus::Onaylandi]);

        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.kitap.yayinla', $book));

        $book->refresh();
        $this->assertSame(ContentStatus::Yayinda, $book->status);
        Notification::assertSentTo($book->author, \App\Notifications\ContentPublished::class);
    }

    public function test_cannot_approve_a_book_that_is_not_submitted(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book))
            ->assertForbidden();
    }

    public function test_super_admin_can_approve_reject_and_publish_a_magazine_issue(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();

        // Sayı en az bir onaylı makale olmadan onaylanamaz (Faz D kuralı 2).
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi]);
        Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.dergi.onayla', $issue), [
            'publish_mode' => 'ileri',
            'scheduled_publish_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ]);
        $this->assertSame(ContentStatus::Onaylandi, $issue->refresh()->status);

        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.dergi.yayinla', $issue));
        $this->assertSame(ContentStatus::Yayinda, $issue->refresh()->status);

        $issue2 = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.dergi.reddet', $issue2), ['decision' => 'revizyon', 'note' => 'Eksik makale.']);
        $this->assertSame(ContentStatus::RevizyonIstendi, $issue2->refresh()->status);
    }

    public function test_super_admin_can_approve_reject_and_publish_an_article(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();

        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi]);
        $article = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Incelemede]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.makale.onayla', $article));
        $this->assertSame(ContentStatus::Onaylandi, $article->refresh()->status);

        // Kural 3: sayısı yayında değilken makale tek başına yayınlanamaz.
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.makale.yayinla', $article))->assertForbidden();
        $this->assertSame(ContentStatus::Onaylandi, $article->refresh()->status);

        // Kural 4: sayı yayındaysa yayınlanabilir.
        $issue->update(['status' => ContentStatus::Yayinda]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.makale.yayinla', $article));
        $this->assertSame(ContentStatus::Yayinda, $article->refresh()->status);

        $article2 = Article::factory()->create(['status' => ContentStatus::Incelemede]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.makale.reddet', $article2), ['decision' => 'revizyon', 'note' => 'Kaynak eksik.']);
        $this->assertSame(ContentStatus::RevizyonIstendi, $article2->refresh()->status);
    }

    public function test_super_admin_can_preview_unpublished_content_but_others_cannot(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($admin)->get(route('kitaplar.show', $book))->assertOk();

        $stranger = User::factory()->create();
        $stranger->assignRole('okur');
        $this->actingAs($stranger)->get(route('kitaplar.show', $book))->assertNotFound();
    }
}
