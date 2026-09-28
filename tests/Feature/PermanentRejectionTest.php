<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Notifications\ContentRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 2026-09-27, karar A: "Revizyon iste" ile "Kalıcı olarak reddet" ayrı. Reddedilen içerik
 * kapanır (sahibi görür/siler, tekrar gönderemez); Süper Admin tümünü Reddedilenler
 * sayfasında görür ve gerekirse revizyona geri açar.
 */
class PermanentRejectionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_book_can_be_rejected_permanently_and_its_author_is_notified(): void
    {
        Notification::fake();
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->user('super_admin'))
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'ret', 'note' => 'Yayın politikamıza uymuyor.'])
            ->assertRedirect(route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar']));

        $this->assertSame(ContentStatus::Reddedildi, $book->refresh()->status);
        $this->assertDatabaseHas('content_reviews', ['reviewable_id' => $book->id, 'action' => 'reddedildi', 'note' => 'Yayın politikamıza uymuyor.']);
        Notification::assertSentTo($author, ContentRejected::class);
    }

    public function test_a_decision_is_required(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->user('super_admin'))
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['note' => 'Not'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(ContentStatus::Gonderildi, $book->refresh()->status);
    }

    public function test_author_sees_the_rejection_reason_and_can_delete_but_not_edit_or_resubmit(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi, 'title' => 'Reddedilen Roman']);
        $this->actingAs($this->user('super_admin'))
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'ret', 'note' => 'Konu kapsam dışı.']);

        // Faz G1: kalıcı reddedilen ayrı sekmede değil, Taslaklarım "Tümü"nde; gerekçe detay sayfasında.
        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim'))
            ->assertOk()
            ->assertSee('Reddedilen Roman')
            ->assertSee('Reddedildi')
            ->assertDontSee(route('panel.yayinlarim.kitap.gonder', $book), false)
            ->assertDontSee(route('panel.yayinlarim.kitap.duzenle', $book), false);

        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.detay', $book))
            ->assertOk()
            ->assertSee('Ret gerekçesi')
            ->assertSee('Konu kapsam dışı.')
            ->assertDontSee(route('panel.yayinlarim.kitap.duzenle', $book), false);

        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', $book))->assertForbidden();
        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.gonder', $book))->assertForbidden();

        $this->actingAs($author)->delete(route('panel.yayinlarim.kitap.sil', $book));
        $this->assertSoftDeleted($book);
    }

    public function test_revision_request_still_returns_the_content_for_editing(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->user('super_admin'))
            ->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'revizyon', 'note' => 'Kapağı değiştirin.']);

        $this->assertSame(ContentStatus::RevizyonIstendi, $book->refresh()->status);
        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', $book))->assertOk();
    }

    public function test_rejecting_an_issue_releases_its_submitted_articles_and_closes_it_to_new_articles(): void
    {
        $editor = $this->user('dergi_editoru');
        $author = $this->user('yazar');
        $magazine = Magazine::factory()->create(['editor_id' => $editor->id]);
        $magazine->authors()->attach($author->id);
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create(['magazine_id' => $magazine->id, 'status' => ContentStatus::Gonderildi, 'title' => 'Kapanan Sayı']);
        $approved = Article::factory()->for($author, 'author')->create(['magazine_issue_id' => $issue->id, 'status' => ContentStatus::Onaylandi]);
        $draft = Article::factory()->for($author, 'author')->create(['magazine_issue_id' => $issue->id, 'status' => ContentStatus::Taslak]);

        $this->actingAs($this->user('super_admin'))
            ->post(route('panel.adminpanel.onaylar.dergi.reddet', $issue), ['decision' => 'ret', 'note' => 'Bu sayı çıkmayacak.']);

        $this->assertSame(ContentStatus::Reddedildi, $issue->refresh()->status);
        $this->assertSame(ContentStatus::RevizyonIstendi, $approved->refresh()->status);
        $this->assertSame(ContentStatus::Taslak, $draft->refresh()->status);

        // Reddedilen sayı yazarın sayı seçim listesinde artık yok (adı bildirimde geçiyor, o yüzden seçeneğe bakılıyor).
        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim.yeni'))->assertDontSee('>Kapanan Sayı</option>', false);
    }

    public function test_rejected_articles_fill_the_editors_rejected_tab(): void
    {
        $editor = $this->user('dergi_editoru');
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create(['status' => ContentStatus::Taslak]);
        $article = Article::factory()->create(['magazine_issue_id' => $issue->id, 'status' => ContentStatus::Incelemede, 'title' => 'Uygun Olmayan Makale']);

        $this->actingAs($this->user('super_admin'))
            ->post(route('panel.adminpanel.onaylar.makale.reddet', $article), ['decision' => 'ret', 'note' => 'Dergi konusu dışında.']);

        $this->actingAs($editor)
            ->get(route('panel.dergi.makale-havuzu', ['durum' => 'reddedilen']))
            ->assertOk()
            ->assertSee('Uygun Olmayan Makale');
    }

    public function test_super_admin_sees_all_rejected_content_and_can_filter(): void
    {
        $admin = $this->user('super_admin');
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi, 'title' => 'Ret Kitap']);
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi, 'title' => 'Ret Sayı']);
        $article = Article::factory()->create(['status' => ContentStatus::Incelemede, 'title' => 'Ret Makale']);
        Book::factory()->create(['status' => ContentStatus::Gonderildi, 'title' => 'Bekleyen Kitap']);

        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.kitap.reddet', $book), ['decision' => 'ret', 'note' => 'Kitap gerekçesi']);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.dergi.reddet', $issue), ['decision' => 'ret', 'note' => 'Sayı gerekçesi']);
        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.makale.reddet', $article), ['decision' => 'ret', 'note' => 'Makale gerekçesi']);

        $this->actingAs($admin)->get(route('panel.adminpanel.reddedilenler.index'))
            ->assertOk()
            ->assertSee(['Ret Kitap', 'Ret Sayı', 'Ret Makale', 'Kitap gerekçesi', 'Sayı gerekçesi', 'Makale gerekçesi', $admin->name])
            ->assertSee('Tümü (3)')
            ->assertDontSee('Bekleyen Kitap');

        $this->actingAs($admin)->get(route('panel.adminpanel.reddedilenler.index', ['tur' => 'makaleler']))
            ->assertSee('Ret Makale')
            ->assertDontSee('Ret Kitap');

        $this->actingAs($this->user('dergi_editoru'))->get(route('panel.adminpanel.reddedilenler.index'))->assertForbidden();
    }

    public function test_super_admin_can_reopen_a_rejected_item_for_revision(): void
    {
        $admin = $this->user('super_admin');
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Reddedildi]);
        $pending = Book::factory()->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($admin)->post(route('panel.adminpanel.reddedilenler.kitap.geri-ac', $book))->assertRedirect();

        $this->assertSame(ContentStatus::RevizyonIstendi, $book->refresh()->status);
        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', $book))->assertOk();

        // Sadece reddedilmiş içerik geri açılır.
        $this->actingAs($admin)->post(route('panel.adminpanel.reddedilenler.kitap.geri-ac', $pending))->assertNotFound();
    }
}
