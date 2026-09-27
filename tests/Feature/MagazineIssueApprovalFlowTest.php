<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\MagazineIssueResource\Pages\ListMagazineIssues;
use App\Models\Article;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MagazineIssueApprovalFlowTest extends TestCase
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

    public function test_owning_editor_can_submit_a_draft_issue_for_approval(): void
    {
        $editor = $this->dergiEditoru();
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create([
            'status' => ContentStatus::Taslak,
        ]);

        Livewire::actingAs($editor)
            ->test(ListMagazineIssues::class)
            ->callTableAction('submit', $issue)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ContentStatus::Gonderildi, $issue->fresh()->status);
    }

    public function test_a_different_editor_cannot_submit_someone_elses_issue(): void
    {
        $owningEditor = $this->dergiEditoru();
        $otherEditor = $this->dergiEditoru();
        $issue = MagazineIssue::factory()->for($owningEditor, 'editor')->create([
            'status' => ContentStatus::Taslak,
        ]);

        Livewire::actingAs($otherEditor)
            ->test(ListMagazineIssues::class)
            ->assertTableActionHidden('submit', $issue);
    }

    public function test_full_flow_submit_approve_publish(): void
    {
        $editor = $this->dergiEditoru();
        $admin = $this->superAdmin();
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create([
            'status' => ContentStatus::Taslak,
            'publish_date' => null,
        ]);

        Livewire::actingAs($editor)
            ->test(ListMagazineIssues::class)
            ->callTableAction('submit', $issue)
            ->assertHasNoTableActionErrors();

        // callTableAction'a geçirilen $issue, o an belleğindeki (o adımdan önceki) attribute'ları
        // taşır — bir sonraki action'ın visible() kontrolünün doğru değerlendirilmesi için
        // her adımdan sonra veritabanından tazelenmesi gerekiyor.
        $issue->refresh();

        // Sayı en az bir onaylı makale olmadan onaylanamaz/yayınlanamaz (Faz D kuralı 2);
        // yayınlanınca o makale de sayıyla birlikte yayına girmeli (kural 1).
        $article = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi]);

        Livewire::actingAs($admin)
            ->test(ListMagazineIssues::class)
            ->callTableAction('approve', $issue)
            ->assertHasNoTableActionErrors();

        $issue->refresh();

        Livewire::actingAs($admin)
            ->test(ListMagazineIssues::class)
            ->callTableAction('publish', $issue)
            ->assertHasNoTableActionErrors();

        $issue->refresh();
        $this->assertSame(ContentStatus::Yayinda, $issue->status);
        $this->assertNotNull($issue->publish_date);
        $this->assertSame(3, $issue->reviews()->count());
        $this->assertSame(ContentStatus::Yayinda, $article->refresh()->status);
    }

    public function test_issue_without_an_approved_article_cannot_be_approved(): void
    {
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi]);
        Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Incelemede]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListMagazineIssues::class)
            ->assertTableActionHidden('approve', $issue);
    }

    public function test_super_admin_reject_sends_issue_back_to_revizyon_istendi(): void
    {
        $editor = $this->dergiEditoru();
        $admin = $this->superAdmin();
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create([
            'status' => ContentStatus::Gonderildi,
        ]);

        Livewire::actingAs($admin)
            ->test(ListMagazineIssues::class)
            ->callTableAction('reject', $issue, data: ['note' => 'Kapak eksik.'])
            ->assertHasNoTableActionErrors();

        $issue->refresh();
        $this->assertSame(ContentStatus::RevizyonIstendi, $issue->status);

        // Editör revizyon sonrası tekrar gönderebilmeli.
        Livewire::actingAs($editor)
            ->test(ListMagazineIssues::class)
            ->assertTableActionVisible('submit', $issue);
    }
}
