<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rol PDF'i: Dergi Editörü "Yazar'ın paneline ek olarak" Dergi Yönetimi'ne sahip (2026-09-28).
 * Önceden editöre sadece dergi_editoru rolü verildiği için Yayın Yönetimi'ne giremiyor, makale
 * yazamıyordu. Editör ayrıca yazar rolü almadan yazabiliyor; kendi dergisine atanmadan da.
 */
class EditorAuthorshipTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_editor_sees_publication_management_in_the_sidebar_and_can_open_it(): void
    {
        $editor = $this->user('dergi_editoru');

        $this->actingAs($editor)->get(route('panel.dergi.index'))
            ->assertOk()
            ->assertSee('Yayın Yönetimi')
            ->assertSee('Dergi Yönetimi')
            ->assertSee(route('panel.yayinlarim.taslaklarim'), false);

        foreach (['taslaklarim', 'taslaklarim.yeni', 'yayinlananlar', 'istatistiklerim', 'cop-kutusu'] as $page) {
            $this->actingAs($editor)->get(route('panel.yayinlarim.'.$page))->assertOk();
        }
    }

    public function test_reader_still_cannot_open_publication_management(): void
    {
        $this->actingAs($this->user('okur'))->get(route('panel.yayinlarim.index'))->assertForbidden();
    }

    public function test_editor_can_write_an_article_for_their_own_magazine_without_being_assigned_as_author(): void
    {
        $editor = $this->user('dergi_editoru');
        $ownIssue = MagazineIssue::factory()->for($editor, 'editor')->create(['title' => 'Kendi Sayım']);
        MagazineIssue::factory()->create(['title' => 'Başka Derginin Sayısı']);

        $this->actingAs($editor)->get(route('panel.yayinlarim.taslaklarim.yeni'))
            ->assertOk()
            ->assertSee('Kendi Sayım')
            ->assertDontSee('Başka Derginin Sayısı');

        $this->actingAs($editor)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'makale',
            'title' => 'Editörün Yazısı',
            'page_ratio' => '21x27.5',
            'magazine_issue_id' => $ownIssue->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('articles', ['title' => 'Editörün Yazısı', 'author_id' => $editor->id, 'magazine_issue_id' => $ownIssue->id]);
    }

    public function test_editor_cannot_send_an_article_to_a_magazine_they_neither_edit_nor_write_for(): void
    {
        $editor = $this->user('dergi_editoru');
        $foreignIssue = MagazineIssue::factory()->create();

        $this->actingAs($editor)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'makale',
            'title' => 'Yanlış Dergi',
            'page_ratio' => '21x27.5',
            'magazine_issue_id' => $foreignIssue->id,
        ])->assertSessionHasErrors('magazine_issue_id');

        // Süper Admin başka bir dergiye yazar olarak atarsa gönderebilir.
        $foreignIssue->magazine->authors()->attach($editor->id);

        $this->actingAs($editor)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'makale',
            'title' => 'Atandığı Dergi',
            'page_ratio' => '21x27.5',
            'magazine_issue_id' => $foreignIssue->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_editor_can_create_a_book_draft(): void
    {
        $editor = $this->user('dergi_editoru');

        $this->actingAs($editor)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'kitap',
            'title' => 'Editörün Kitabı',
            'page_ratio' => '13x20',
        ])->assertSessionHasNoErrors();

        $this->assertSame($editor->id, Book::where('title', 'Editörün Kitabı')->value('author_id'));
    }

    /** Mockup 2.3: editörün kendi makalesi havuzda etiketli, normal akıştan geçip Süper Admin'e gider. */
    public function test_editors_own_article_goes_through_the_pool_with_a_badge(): void
    {
        $editor = $this->user('dergi_editoru');
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create();
        $article = Article::factory()->for($editor, 'author')->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($editor)->post(route('panel.yayinlarim.makale.gonder', $article))->assertRedirect();
        $this->assertSame(ContentStatus::Gonderildi, $article->refresh()->status);

        $this->actingAs($editor)->get(route('panel.dergi.makale-havuzu'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Editörün Makalesi');

        $this->actingAs($editor)->post(route('panel.dergi.makale-havuzu.incele', $article))->assertRedirect();
        $this->assertSame(ContentStatus::Incelemede, $article->refresh()->status);
    }

    public function test_badge_is_not_shown_for_other_authors_articles(): void
    {
        $editor = $this->user('dergi_editoru');
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create();
        Article::factory()->for($this->user('yazar'), 'author')->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($editor)->get(route('panel.dergi.makale-havuzu'))->assertOk()->assertDontSee('Editörün Makalesi');
    }

    public function test_super_admin_can_pick_editors_as_book_and_magazine_authors(): void
    {
        $admin = $this->user('super_admin');
        $editor = $this->user('dergi_editoru');
        $editor->update(['name' => 'Editör Ayşe']);
        $magazine = Magazine::factory()->create();

        $this->actingAs($admin)->get(route('panel.adminpanel.kitaplar.yeni'))->assertOk()->assertSee('Editör Ayşe');

        $this->actingAs($admin)->put(route('panel.adminpanel.dergiler.guncelle', $magazine), [
            'name' => $magazine->name,
            'slug' => $magazine->slug,
            'editor_id' => $magazine->editor_id,
            'author_ids' => [$editor->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($magazine->authors()->whereKey($editor->id)->exists());
    }
}
