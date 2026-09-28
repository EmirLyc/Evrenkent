<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Document;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Süper Admin → Makaleler (2026-09-28). Filament kaldırılmadan önce makalelerin admin
 * tarafında düzenlenebildiği tek yer Filament'ti.
 */
class AdminArticleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_only_super_admin_can_open_the_articles_page(): void
    {
        $this->actingAs($this->user('super_admin'))->get(route('panel.adminpanel.makaleler.index'))->assertOk();
        $this->actingAs($this->user('dergi_editoru'))->get(route('panel.adminpanel.makaleler.index'))->assertForbidden();
        $this->actingAs($this->user('yazar'))->get(route('panel.adminpanel.makaleler.index'))->assertForbidden();
    }

    public function test_index_searches_by_title_or_author_and_filters_by_status_and_magazine(): void
    {
        $admin = $this->user('super_admin');
        $author = $this->user('yazar');
        $author->update(['name' => 'Zeynep Kaya']);

        $magazine = Magazine::factory()->create(['name' => 'Astronomi Dergisi']);
        $issue = MagazineIssue::factory()->create(['magazine_id' => $magazine->id]);
        Article::factory()->for($author, 'author')->for($issue, 'magazineIssue')->create(['title' => 'Kara Delikler', 'status' => ContentStatus::Gonderildi]);
        Article::factory()->for(MagazineIssue::factory(), 'magazineIssue')->create(['title' => 'Osmanlı Diplomasisi', 'status' => ContentStatus::Yayinda]);

        $this->actingAs($admin)->get(route('panel.adminpanel.makaleler.index'))
            ->assertSee('Kara Delikler')->assertSee('Osmanlı Diplomasisi')->assertSee('Astronomi Dergisi');

        $this->actingAs($admin)->get(route('panel.adminpanel.makaleler.index', ['q' => 'Zeynep']))
            ->assertSee('Kara Delikler')->assertDontSee('Osmanlı Diplomasisi');

        $this->actingAs($admin)->get(route('panel.adminpanel.makaleler.index', ['durum' => 'yayinda']))
            ->assertDontSee('Kara Delikler')->assertSee('Osmanlı Diplomasisi');

        $this->actingAs($admin)->get(route('panel.adminpanel.makaleler.index', ['dergi' => $magazine->id]))
            ->assertSee('Kara Delikler')->assertDontSee('Osmanlı Diplomasisi');
    }

    public function test_super_admin_can_create_an_article_for_an_author(): void
    {
        $author = $this->user('yazar');
        $issue = MagazineIssue::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($this->user('super_admin'))->post(route('panel.adminpanel.makaleler.store'), [
            'author_id' => $author->id,
            'magazine_issue_id' => $issue->id,
            'title' => 'Yeni Makale',
            'slug' => 'yeni-makale',
            'body' => '<p>Metin<span data-footnote="Kaynak"></span></p>',
            'status' => ContentStatus::Incelemede->value,
            'categories' => [$category->id],
        ])->assertSessionHasNoErrors();

        $article = Article::where('slug', 'yeni-makale')->firstOrFail();
        $this->assertSame($author->id, $article->author_id);
        $this->assertSame(ContentStatus::Incelemede, $article->status);
        $this->assertStringContainsString('data-footnote="Kaynak"', $article->content);
        $this->assertSame([$category->id], $article->categories->pluck('id')->all());
    }

    public function test_an_article_cannot_be_created_as_published_in_an_unpublished_issue(): void
    {
        $admin = $this->user('super_admin');
        $data = [
            'author_id' => $this->user('yazar')->id,
            'title' => 'Erken Yayın',
            'slug' => 'erken-yayin',
            'body' => '<p>Metin</p>',
            'status' => ContentStatus::Yayinda->value,
        ];

        $this->actingAs($admin)->post(route('panel.adminpanel.makaleler.store'), [
            ...$data, 'magazine_issue_id' => MagazineIssue::factory()->create()->id,
        ])->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('articles', ['slug' => 'erken-yayin']);

        $this->actingAs($admin)->post(route('panel.adminpanel.makaleler.store'), [
            ...$data, 'magazine_issue_id' => MagazineIssue::factory()->create(['status' => ContentStatus::Yayinda])->id,
        ])->assertSessionHasNoErrors();
        $this->assertNotNull(Article::where('slug', 'erken-yayin')->value('published_at'));
    }

    public function test_edit_form_uses_the_rich_editor_with_the_articles_documents(): void
    {
        $article = Article::factory()->for(MagazineIssue::factory(), 'magazineIssue')->create();
        Document::factory()->for($article, 'documentable')->create(['title' => 'Ek Harita']);

        $this->actingAs($this->user('super_admin'))->get(route('panel.adminpanel.makaleler.duzenle', $article))
            ->assertOk()
            ->assertSee('richEditor', false)
            ->assertSee('Belge ekle (kar tanesi)')
            ->assertSee('Ek Harita')
            ->assertDontSee('Belge yükle / yönet');
    }

    public function test_update_changes_content_but_status_only_changes_through_approvals(): void
    {
        $author = $this->user('yazar');
        $issue = MagazineIssue::factory()->create();
        $article = Article::factory()->for($author, 'author')->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Incelemede]);
        $admin = $this->user('super_admin');
        $payload = [
            'author_id' => $author->id,
            'magazine_issue_id' => $issue->id,
            'title' => 'Düzeltilmiş Başlık',
            'slug' => $article->slug,
            'body' => '<h2>Giriş</h2><p>Yeni metin</p>',
        ];

        $this->actingAs($admin)->put(route('panel.adminpanel.makaleler.guncelle', $article), [...$payload, 'status' => 'yayinda'])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)->put(route('panel.adminpanel.makaleler.guncelle', $article), $payload)
            ->assertRedirect(route('panel.adminpanel.makaleler.duzenle', $article));

        $article->refresh();
        $this->assertSame('Düzeltilmiş Başlık', $article->title);
        $this->assertStringContainsString('<h2>Giriş</h2>', $article->content);
        $this->assertSame(ContentStatus::Incelemede, $article->status);
    }

    public function test_super_admin_can_delete_an_article(): void
    {
        $article = Article::factory()->create();

        $this->actingAs($this->user('super_admin'))->delete(route('panel.adminpanel.makaleler.sil', $article))
            ->assertRedirect(route('panel.adminpanel.makaleler.index'));

        $this->assertModelMissing($article);
    }

    /** Filament paneli kaldırıldı — eski /admin yer imleri kullanıcının kendi paneline düşer. */
    public function test_old_filament_address_redirects_to_the_users_own_panel(): void
    {
        $this->actingAs($this->user('super_admin'))->get('/admin/books/1/edit')->assertRedirect('/panel/admin-panel');
        $this->actingAs($this->user('dergi_editoru'))->get('/admin')->assertRedirect('/panel/dergi');
        auth()->logout();
        $this->get('/admin')->assertRedirect(route('login'));
    }
}
