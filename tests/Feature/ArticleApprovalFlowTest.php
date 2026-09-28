<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Makale onay akışı (Yazar → Dergi Editörü → Süper Admin). 2026-09-28'e kadar Filament'in
 * tablo aksiyonları üzerinden yazılmıştı; Filament kaldırılınca panellere taşındı.
 */
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

        $this->actingAs($editor)->post(route('panel.dergi.makale-havuzu.incele', $article))->assertRedirect();

        $this->assertSame(ContentStatus::Incelemede, $article->fresh()->status);
    }

    public function test_a_different_editor_cannot_review_someone_elses_issue_article(): void
    {
        $issue = MagazineIssue::factory()->for($this->dergiEditoru(), 'editor')->create();
        $article = Article::factory()
            ->for($this->yazar(), 'author')
            ->for($issue, 'magazineIssue')
            ->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->dergiEditoru())->post(route('panel.dergi.makale-havuzu.incele', $article))->assertForbidden();

        $this->assertSame(ContentStatus::Gonderildi, $article->fresh()->status);
    }

    public function test_super_admin_can_approve_and_publish_a_reviewed_article(): void
    {
        // Makale tek başına ancak sayısı yayındayken yayınlanabilir (Faz D kuralı 3/4).
        $article = Article::factory()
            ->for($this->yazar(), 'author')
            ->for(MagazineIssue::factory()->create(['status' => ContentStatus::Yayinda]), 'magazineIssue')
            ->create(['status' => ContentStatus::Incelemede]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.makale.onayla', $article), ['publish_mode' => 'simdi'])
            ->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame(ContentStatus::Yayinda, $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame(['onaylandi', 'yayinda'], $article->reviews()->orderBy('id')->pluck('action')->all());
    }

    public function test_an_approved_article_in_a_live_issue_can_be_published_later(): void
    {
        $article = Article::factory()
            ->for(MagazineIssue::factory()->create(['status' => ContentStatus::Yayinda]), 'magazineIssue')
            ->create(['status' => ContentStatus::Onaylandi, 'published_at' => null]);

        $this->actingAs($this->superAdmin())->post(route('panel.adminpanel.onaylar.makale.yayinla', $article))->assertRedirect();

        $this->assertSame(ContentStatus::Yayinda, $article->refresh()->status);
        $this->assertNotNull($article->published_at);
    }
}
