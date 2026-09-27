<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bir makalenin hangi dergi sayısına gönderildiği daha önce hiçbir yerde
 * seçilemiyordu (magazine_issue_id hep NULL kalıyordu) — bu yüzden gönderilen
 * her makale hiçbir Dergi Editörü'nün Makale Havuzu'nda görünmeden,
 * Süper Admin de onaylayamadan çıkmaza giriyordu. Bu testler o boşluğun
 * kapandığını doğruluyor.
 */
class ArticleMagazineIssueAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function yazar(): User
    {
        $user = User::factory()->create();
        $user->assignRole('yazar');

        return $user;
    }

    /** Faz E: yazar sadece Süper Admin'in kendisini atadığı dergilerin sayılarına gönderebilir. */
    private function assignTo(User $author, MagazineIssue ...$issues): void
    {
        foreach ($issues as $issue) {
            $issue->magazine->authors()->syncWithoutDetaching([$author->id]);
        }
    }

    public function test_new_draft_form_lists_only_unpublished_issues_of_assigned_magazines(): void
    {
        $author = $this->yazar();
        $open = MagazineIssue::factory()->create(['title' => 'Açık Sayı', 'status' => ContentStatus::Taslak]);
        $published = MagazineIssue::factory()->create(['title' => 'Yayında Sayı', 'status' => ContentStatus::Yayinda]);
        MagazineIssue::factory()->create(['title' => 'Atanmadığı Dergi Sayısı', 'status' => ContentStatus::Taslak]);
        $this->assignTo($author, $open, $published);

        $response = $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim.yeni'))->assertOk();

        $response->assertSee('Açık Sayı')
            ->assertDontSee('Yayında Sayı')
            ->assertDontSee('Atanmadığı Dergi Sayısı');
    }

    public function test_author_cannot_submit_to_an_issue_of_a_magazine_they_are_not_assigned_to(): void
    {
        $author = $this->yazar();
        $foreignIssue = MagazineIssue::factory()->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'makale',
            'title' => 'Sızma Makalesi',
            'body' => 'İçerik.',
            'magazine_issue_id' => $foreignIssue->id,
        ])->assertSessionHasErrors('magazine_issue_id');

        $this->assertDatabaseMissing('articles', ['title' => 'Sızma Makalesi']);
    }

    public function test_magazine_issue_is_required_when_creating_an_article(): void
    {
        $author = $this->yazar();

        $this->actingAs($author)
            ->post(route('panel.yayinlarim.taslaklarim.store'), [
                'type' => 'makale',
                'title' => 'Yeni Makale',
                'body' => 'İçerik.',
            ])
            ->assertSessionHasErrors('magazine_issue_id');

        $this->assertDatabaseMissing('articles', ['title' => 'Yeni Makale']);
    }

    public function test_creating_an_article_assigns_it_to_the_chosen_magazine_issue(): void
    {
        $author = $this->yazar();
        $issue = MagazineIssue::factory()->create();
        $this->assignTo($author, $issue);

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'makale',
            'title' => 'Yeni Makale',
            'body' => 'İçerik.',
            'magazine_issue_id' => $issue->id,
        ])->assertRedirect(route('panel.yayinlarim.taslaklarim'));

        $article = Article::where('title', 'Yeni Makale')->firstOrFail();
        $this->assertSame($issue->id, $article->magazine_issue_id);
    }

    public function test_book_drafts_do_not_require_a_magazine_issue(): void
    {
        $author = $this->yazar();

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'kitap',
            'title' => 'Yeni Kitap',
            'body' => 'Açıklama.',
            'price' => 10,
        ])->assertRedirect(route('panel.yayinlarim.taslaklarim'));

        $this->assertDatabaseHas('books', ['title' => 'Yeni Kitap']);
    }

    public function test_an_assigned_article_becomes_visible_to_its_editor_after_submission(): void
    {
        $author = $this->yazar();
        $editor = User::factory()->create();
        $editor->assignRole('dergi_editoru');
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create();

        $article = Article::factory()->for($author, 'author')->create([
            'status' => ContentStatus::Taslak,
            'magazine_issue_id' => $issue->id,
            'title' => 'Görünür Makale',
        ]);

        $this->actingAs($author)->post(route('panel.yayinlarim.makale.gonder', $article))->assertRedirect();
        $this->assertSame(ContentStatus::Gonderildi, $article->refresh()->status);

        $this->actingAs($editor)
            ->get(route('panel.dergi.makale-havuzu'))
            ->assertOk()
            ->assertSee('Görünür Makale');
    }

    public function test_editing_an_article_can_reassign_its_magazine_issue(): void
    {
        $author = $this->yazar();
        $originalIssue = MagazineIssue::factory()->create();
        $newIssue = MagazineIssue::factory()->create();
        $this->assignTo($author, $originalIssue, $newIssue);
        $article = Article::factory()->for($author, 'author')->create([
            'status' => ContentStatus::Taslak,
            'magazine_issue_id' => $originalIssue->id,
        ]);

        $this->actingAs($author)->put(route('panel.yayinlarim.makale.guncelle', $article), [
            'title' => $article->title,
            'body' => $article->content,
            'magazine_issue_id' => $newIssue->id,
        ]);

        $this->assertSame($newIssue->id, $article->refresh()->magazine_issue_id);
    }
}
