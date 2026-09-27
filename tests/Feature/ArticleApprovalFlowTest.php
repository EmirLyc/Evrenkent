<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Models\Article;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function dergiEditoru(): User
    {
        $user = User::factory()->create();
        $user->assignRole('dergi_editoru');

        return $user;
    }

    private function yazar(): User
    {
        $user = User::factory()->create();
        $user->assignRole('yazar');

        return $user;
    }

    public function test_owning_editor_can_review_a_submitted_article(): void
    {
        $editor = $this->dergiEditoru();
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create();
        $article = Article::factory()
            ->for($this->yazar(), 'author')
            ->for($issue, 'magazineIssue')
            ->create(['status' => ContentStatus::Gonderildi]);

        Livewire::actingAs($editor)
            ->test(ListArticles::class)
            ->callTableAction('review', $article)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ContentStatus::Incelemede, $article->fresh()->status);
    }

    public function test_a_different_editor_cannot_review_someone_elses_issue_article(): void
    {
        $owningEditor = $this->dergiEditoru();
        $otherEditor = $this->dergiEditoru();
        $issue = MagazineIssue::factory()->for($owningEditor, 'editor')->create();
        $article = Article::factory()
            ->for($this->yazar(), 'author')
            ->for($issue, 'magazineIssue')
            ->create(['status' => ContentStatus::Gonderildi]);

        Livewire::actingAs($otherEditor)
            ->test(ListArticles::class)
            ->assertTableActionHidden('review', $article);
    }

    public function test_super_admin_can_approve_and_publish_a_reviewed_article(): void
    {
        $admin = $this->superAdmin();
        // Makale tek başına ancak sayısı yayındayken yayınlanabilir (Faz D kuralı 3/4).
        $article = Article::factory()
            ->for($this->yazar(), 'author')
            ->for(MagazineIssue::factory()->create(['status' => ContentStatus::Yayinda]), 'magazineIssue')
            ->create(['status' => ContentStatus::Incelemede]);

        Livewire::actingAs($admin)
            ->test(ListArticles::class)
            ->callTableAction('approve', $article)
            ->assertHasNoTableActionErrors();

        $article->refresh();
        $this->assertSame(ContentStatus::Onaylandi, $article->status);

        Livewire::actingAs($admin)
            ->test(ListArticles::class)
            ->callTableAction('publish', $article)
            ->assertHasNoTableActionErrors();

        $article->refresh();
        $this->assertSame(ContentStatus::Yayinda, $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame(2, $article->reviews()->count());
    }
}
