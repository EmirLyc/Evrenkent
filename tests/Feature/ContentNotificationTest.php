<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\BookResource\Pages\ListBooks;
use App\Filament\Resources\MagazineIssueResource\Pages\ListMagazineIssues;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Notifications\ContentApproved;
use App\Notifications\ContentPublished;
use App\Notifications\ContentRevisionRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filament'teki Onayla/Reddet/Yayınla aksiyonlarının gerçekten bildirim gönderdiğini
 * doğrular — header'daki zilin (x-notifications-bell) beslendiği yer burası.
 */
class ContentNotificationTest extends TestCase
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

    private function dergiEditoru(): User
    {
        $user = User::factory()->create();
        $user->assignRole('dergi_editoru');

        return $user;
    }

    public function test_approving_a_book_notifies_its_author(): void
    {
        Notification::fake();

        $admin = $this->superAdmin();
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        Livewire::actingAs($admin)->test(ListBooks::class)->callTableAction('approve', $book);

        Notification::assertSentTo($author, ContentApproved::class);
    }

    public function test_rejecting_a_book_notifies_its_author_with_the_note(): void
    {
        Notification::fake();

        $admin = $this->superAdmin();
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        Livewire::actingAs($admin)->test(ListBooks::class)
            ->callTableAction('reject', $book, data: ['note' => 'Kapak eksik.']);

        Notification::assertSentTo(
            $author,
            ContentRevisionRequested::class,
            fn ($notification, $channels) => $notification->toArray($author)['body'] === "\"{$book->title}\" için revizyon istendi: Kapak eksik."
        );
    }

    public function test_publishing_a_book_notifies_its_author(): void
    {
        Notification::fake();

        $admin = $this->superAdmin();
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create([
            'status' => ContentStatus::Onaylandi,
            'published_at' => null,
        ]);

        Livewire::actingAs($admin)->test(ListBooks::class)->callTableAction('publish', $book);

        Notification::assertSentTo($author, ContentPublished::class);
    }

    public function test_approving_an_article_notifies_its_author(): void
    {
        Notification::fake();

        $admin = $this->superAdmin();
        $author = $this->yazar();
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Incelemede]);

        Livewire::actingAs($admin)->test(ListArticles::class)->callTableAction('approve', $article);

        Notification::assertSentTo($author, ContentApproved::class);
    }

    public function test_approving_a_magazine_issue_notifies_its_editor_not_the_admin(): void
    {
        Notification::fake();

        $admin = $this->superAdmin();
        $editor = $this->dergiEditoru();
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create(['status' => ContentStatus::Gonderildi]);
        // Onaylı makalesi olmayan sayı onaylanamıyor (Faz D kuralı 2).
        Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi]);

        Livewire::actingAs($admin)->test(ListMagazineIssues::class)->callTableAction('approve', $issue);

        Notification::assertSentTo($editor, ContentApproved::class);
        Notification::assertNotSentTo($admin, ContentApproved::class);
    }

    public function test_notification_links_to_the_authors_own_edit_page(): void
    {
        Notification::fake();

        $admin = $this->superAdmin();
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        Livewire::actingAs($admin)->test(ListBooks::class)->callTableAction('approve', $book);

        Notification::assertSentTo(
            $author,
            ContentApproved::class,
            fn ($notification) => $notification->toArray($author)['url'] === route('panel.yayinlarim.kitap.duzenle', $book)
        );
    }
}
