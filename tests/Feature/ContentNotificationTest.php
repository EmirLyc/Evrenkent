<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Notifications\ContentApproved;
use App\Notifications\ContentPublished;
use App\Notifications\ContentRevisionRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Onay ekranındaki Onayla/Reddet/Yayınla kararlarının gerçekten bildirim gönderdiğini ve
 * bildirimin doğru sayfaya bağlandığını doğrular — header'daki zilin (x-notifications-bell)
 * beslendiği yer burası.
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
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->superAdmin())->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), [
            'price' => 50,
            'publish_mode' => 'ileri',
            'scheduled_publish_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ]);

        Notification::assertSentTo($author, ContentApproved::class);
    }

    public function test_requesting_a_revision_notifies_the_author_with_the_note(): void
    {
        Notification::fake();
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'revizyon', 'note' => 'Kapak eksik.']);

        Notification::assertSentTo(
            $author,
            ContentRevisionRequested::class,
            fn ($notification) => $notification->toArray($author)['body'] === "\"{$book->title}\" için revizyon istendi: Kapak eksik."
        );
    }

    public function test_publishing_a_book_notifies_its_author(): void
    {
        Notification::fake();
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Onaylandi, 'published_at' => null]);

        $this->actingAs($this->superAdmin())->post(route('panel.adminpanel.onaylar.kitap.yayinla', $book));

        Notification::assertSentTo($author, ContentPublished::class);
    }

    public function test_approving_an_article_notifies_its_author(): void
    {
        Notification::fake();
        $author = $this->yazar();
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Incelemede]);

        $this->actingAs($this->superAdmin())->post(route('panel.adminpanel.onaylar.makale.onayla', $article));

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

        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.dergi.onayla', $issue), [
            'publish_mode' => 'ileri',
            'scheduled_publish_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ]);

        Notification::assertSentTo($editor, ContentApproved::class);
        Notification::assertNotSentTo($admin, ContentApproved::class);
    }

    /**
     * Bağlantı içeriğin durumuna göre: revizyonda düzenleme sayfası, onaylı/zamanlı içerikte
     * sahibinin listesi (düzenleme sayfası orada 403 verirdi), yayında herkese açık sayfa.
     */
    public function test_notification_links_depend_on_the_content_status(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $author = $this->yazar();

        $revised = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.kitap.reddet', $revised), ['decision' => 'revizyon', 'note' => 'Düzeltin.']);
        Notification::assertSentTo($author, ContentRevisionRequested::class,
            fn ($n) => $n->toArray($author)['url'] === route('panel.yayinlarim.kitap.duzenle', $revised));

        $scheduled = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.kitap.onayla', $scheduled), [
            'price' => 50, 'publish_mode' => 'ileri', 'scheduled_publish_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ]);
        Notification::assertSentTo($author, ContentApproved::class,
            fn ($n) => $n->toArray($author)['url'] === route('panel.yayinlarim.index'));

        $published = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.kitap.onayla', $published), ['price' => 50, 'publish_mode' => 'simdi']);
        Notification::assertSentTo($author, ContentPublished::class,
            fn ($n) => $n->toArray($author)['url'] === route('kitaplar.show', $published));

        // Sayının bağlantısı önceden Filament'in düzenleme sayfasına gidiyordu.
        $editor = $this->dergiEditoru();
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create(['status' => ContentStatus::Gonderildi]);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.dergi.reddet', $issue), ['decision' => 'revizyon', 'note' => 'Kapak eksik.']);
        Notification::assertSentTo($editor, ContentRevisionRequested::class,
            fn ($n) => $n->toArray($editor)['url'] === route('panel.dergi.sayilarim.duzenle', $issue));
    }
}
