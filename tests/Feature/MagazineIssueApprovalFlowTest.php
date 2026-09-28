<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dergi sayısı onay akışı (Dergi Editörü → Süper Admin). 2026-09-28'e kadar Filament'in tablo
 * aksiyonları üzerinden yazılmıştı; Filament kaldırılınca Dergi Yönetimi + Süper Admin paneline taşındı.
 */
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
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($editor)->post(route('panel.dergi.sayilarim.gonder', $issue))->assertRedirect();

        $this->assertSame(ContentStatus::Gonderildi, $issue->fresh()->status);
    }

    public function test_a_different_editor_cannot_submit_someone_elses_issue(): void
    {
        $issue = MagazineIssue::factory()->for($this->dergiEditoru(), 'editor')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($this->dergiEditoru())->post(route('panel.dergi.sayilarim.gonder', $issue))->assertForbidden();

        $this->assertSame(ContentStatus::Taslak, $issue->fresh()->status);
    }

    public function test_full_flow_submit_approve_publish(): void
    {
        $editor = $this->dergiEditoru();
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create([
            'status' => ContentStatus::Taslak,
            'publish_date' => null,
        ]);

        $this->actingAs($editor)->post(route('panel.dergi.sayilarim.gonder', $issue));

        // Sayı en az bir onaylı makale olmadan onaylanamaz/yayınlanamaz (Faz D kuralı 2);
        // yayınlanınca o makale de sayıyla birlikte yayına girmeli (kural 1).
        $article = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.dergi.onayla', $issue), ['publish_mode' => 'simdi'])
            ->assertSessionHasNoErrors();

        $issue->refresh();
        $this->assertSame(ContentStatus::Yayinda, $issue->status);
        $this->assertNotNull($issue->publish_date);
        $this->assertSame(['gonderildi', 'onaylandi', 'yayinda'], $issue->reviews()->orderBy('id')->pluck('action')->all());
        $this->assertSame(ContentStatus::Yayinda, $article->refresh()->status);
    }

    public function test_an_approved_issue_can_be_published_later_with_its_articles(): void
    {
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Onaylandi]);
        $article = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi]);

        $this->actingAs($this->superAdmin())->post(route('panel.adminpanel.onaylar.dergi.yayinla', $issue))->assertRedirect();

        $this->assertSame(ContentStatus::Yayinda, $issue->refresh()->status);
        $this->assertSame(ContentStatus::Yayinda, $article->refresh()->status);
    }

    public function test_issue_without_an_approved_article_cannot_be_approved(): void
    {
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi]);
        Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Incelemede]);

        $this->actingAs($this->superAdmin())
            ->get(route('panel.adminpanel.onaylar.dergi.onayla-form', $issue))
            ->assertForbidden();
    }

    public function test_super_admin_revision_request_lets_the_editor_resubmit(): void
    {
        $editor = $this->dergiEditoru();
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.dergi.reddet', $issue), ['decision' => 'revizyon', 'note' => 'Kapak eksik.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ContentStatus::RevizyonIstendi, $issue->refresh()->status);

        // Editör revizyon sonrası tekrar gönderebilmeli.
        $this->actingAs($editor)->post(route('panel.dergi.sayilarim.gonder', $issue))->assertRedirect();
        $this->assertSame(ContentStatus::Gonderildi, $issue->refresh()->status);
    }
}
