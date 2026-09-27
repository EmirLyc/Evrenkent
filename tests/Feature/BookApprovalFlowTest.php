<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\BookResource\Pages\EditBook;
use App\Filament\Resources\BookResource\Pages\ListBooks;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

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

    public function test_super_admin_can_approve_a_submitted_book(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->for($this->yazar(), 'author')->create([
            'status' => ContentStatus::Gonderildi,
        ]);

        Livewire::actingAs($admin)
            ->test(ListBooks::class)
            ->callTableAction('approve', $book)
            ->assertHasNoTableActionErrors();

        $book->refresh();
        $this->assertSame(ContentStatus::Onaylandi, $book->status);
        $this->assertSame(1, $book->reviews()->count());
        $this->assertSame('onaylandi', $book->reviews()->first()->action);
    }

    public function test_super_admin_can_set_a_scheduled_publish_date_while_approving(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->for($this->yazar(), 'author')->create([
            'status' => ContentStatus::Gonderildi,
        ]);
        $target = now()->addDays(10)->startOfMinute();

        Livewire::actingAs($admin)
            ->test(ListBooks::class)
            ->callTableAction('approve', $book, data: ['scheduled_publish_at' => $target])
            ->assertHasNoTableActionErrors();

        $book->refresh();
        $this->assertSame(ContentStatus::Onaylandi, $book->status);
        $this->assertTrue($target->equalTo($book->scheduled_publish_at));
    }

    public function test_approve_action_is_hidden_for_a_draft_book(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->for($this->yazar(), 'author')->create([
            'status' => ContentStatus::Taslak,
        ]);

        Livewire::actingAs($admin)
            ->test(ListBooks::class)
            ->assertTableActionHidden('approve', $book);
    }

    public function test_super_admin_can_reject_a_submitted_book_with_a_note(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->for($this->yazar(), 'author')->create([
            'status' => ContentStatus::Gonderildi,
        ]);

        Livewire::actingAs($admin)
            ->test(ListBooks::class)
            ->callTableAction('reject', $book, data: ['note' => 'Kapak görseli eksik.'])
            ->assertHasNoTableActionErrors();

        $book->refresh();
        $this->assertSame(ContentStatus::RevizyonIstendi, $book->status);
        $this->assertSame('Kapak görseli eksik.', $book->reviews()->latest()->first()->note);
    }

    /** Filament (yedek panel) de kalıcı reddedebiliyor — kendi panelimizle aynı yol (ContentReviewer). */
    public function test_super_admin_can_reject_a_book_permanently_from_the_backup_panel(): void
    {
        $book = Book::factory()->for($this->yazar(), 'author')->create(['status' => ContentStatus::Gonderildi]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListBooks::class)
            ->callTableAction('reject', $book, data: ['decision' => 'ret', 'note' => 'Kapsam dışı.'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ContentStatus::Reddedildi, $book->refresh()->status);
        $this->assertSame('reddedildi', $book->reviews()->latest('id')->first()->action);
    }

    public function test_super_admin_can_publish_an_approved_book(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->for($this->yazar(), 'author')->create([
            'status' => ContentStatus::Onaylandi,
            'published_at' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(ListBooks::class)
            ->callTableAction('publish', $book)
            ->assertHasNoTableActionErrors();

        $book->refresh();
        $this->assertSame(ContentStatus::Yayinda, $book->status);
        $this->assertNotNull($book->published_at);
    }

    public function test_status_field_is_locked_on_edit_and_cannot_be_tampered_via_form(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->for($this->yazar(), 'author')->create([
            'status' => ContentStatus::Taslak,
        ]);

        Livewire::actingAs($admin)
            ->test(EditBook::class, ['record' => $book->getRouteKey()])
            ->set('data.status', ContentStatus::Yayinda->value)
            ->call('save');

        // status alanı disabled+dehydrated(false) olduğu için formdan gelen değer yok sayılır.
        $this->assertSame(ContentStatus::Taslak, $book->fresh()->status);
    }
}
