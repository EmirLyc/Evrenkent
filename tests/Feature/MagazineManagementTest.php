<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz E (2026-09-27 revizesi): "Dergi" varlığı — Süper Admin dergiye editör ve yazar atar,
 * sayılar dergiye bağlı, herkese açık dergi sayfası.
 */
class MagazineManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_only_super_admin_can_manage_magazines(): void
    {
        $this->actingAs($this->user('super_admin'))->get(route('panel.adminpanel.dergiler.index'))->assertOk();
        $this->actingAs($this->user('dergi_editoru'))->get(route('panel.adminpanel.dergiler.index'))->assertForbidden();
    }

    public function test_super_admin_creates_a_magazine_with_an_editor_and_authors(): void
    {
        $editor = $this->user('dergi_editoru');
        $authorA = $this->user('yazar');
        $authorB = $this->user('yazar');

        $this->actingAs($this->user('super_admin'))->post(route('panel.adminpanel.dergiler.store'), [
            'name' => 'Tarih Dergisi',
            'slug' => 'tarih-dergisi',
            'description' => 'Tarih üzerine.',
            'editor_id' => $editor->id,
            'author_ids' => [$authorA->id, $authorB->id],
        ])->assertSessionHasNoErrors();

        $magazine = Magazine::where('slug', 'tarih-dergisi')->firstOrFail();
        $this->assertSame($editor->id, $magazine->editor_id);
        $this->assertEqualsCanonicalizing([$authorA->id, $authorB->id], $magazine->authors->pluck('id')->all());
    }

    public function test_only_users_with_the_right_roles_can_be_assigned(): void
    {
        $reader = $this->user('okur');

        $this->actingAs($this->user('super_admin'))->post(route('panel.adminpanel.dergiler.store'), [
            'name' => 'Yanlış Roller',
            'slug' => 'yanlis-roller',
            'editor_id' => $reader->id,
            'author_ids' => [$reader->id],
        ])->assertSessionHasErrors(['editor_id', 'author_ids.0']);
    }

    public function test_changing_the_editor_hands_the_magazines_issues_over(): void
    {
        $oldEditor = $this->user('dergi_editoru');
        $newEditor = $this->user('dergi_editoru');
        $magazine = Magazine::factory()->create(['editor_id' => $oldEditor->id]);
        $issue = MagazineIssue::factory()->for($oldEditor, 'editor')->create(['magazine_id' => $magazine->id]);

        $this->actingAs($this->user('super_admin'))->put(route('panel.adminpanel.dergiler.guncelle', $magazine), [
            'name' => $magazine->name,
            'slug' => $magazine->slug,
            'editor_id' => $newEditor->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($newEditor->id, $issue->refresh()->editor_id);

        // Yeni editör artık sayıyı kendi panelinde görüyor, eski editör görmüyor.
        $this->actingAs($newEditor)->get(route('panel.dergi.sayilarim'))->assertSee($issue->title);
        $this->actingAs($oldEditor)->get(route('panel.dergi.sayilarim'))->assertDontSee($issue->title);
    }

    public function test_updating_authors_syncs_the_list(): void
    {
        $keep = $this->user('yazar');
        $remove = $this->user('yazar');
        $magazine = Magazine::factory()->create();
        $magazine->authors()->attach([$keep->id, $remove->id]);

        $this->actingAs($this->user('super_admin'))->put(route('panel.adminpanel.dergiler.guncelle', $magazine), [
            'name' => $magazine->name,
            'slug' => $magazine->slug,
            'author_ids' => [$keep->id],
        ]);

        $this->assertSame([$keep->id], $magazine->authors()->pluck('users.id')->all());
    }

    public function test_magazine_with_issues_cannot_be_deleted(): void
    {
        $admin = $this->user('super_admin');
        $withIssue = Magazine::factory()->create();
        MagazineIssue::factory()->create(['magazine_id' => $withIssue->id]);
        $empty = Magazine::factory()->create();

        $this->actingAs($admin)->delete(route('panel.adminpanel.dergiler.sil', $withIssue));
        $this->assertModelExists($withIssue);

        $this->actingAs($admin)->delete(route('panel.adminpanel.dergiler.sil', $empty));
        $this->assertModelMissing($empty);
    }

    // --- Herkese açık -------------------------------------------------------------

    public function test_public_magazine_page_lists_published_and_upcoming_issues_only(): void
    {
        $magazine = Magazine::factory()->create(['name' => 'Astronomi Dergisi']);
        MagazineIssue::factory()->create(['magazine_id' => $magazine->id, 'title' => 'Yayındaki Sayı', 'status' => ContentStatus::Yayinda]);
        MagazineIssue::factory()->create(['magazine_id' => $magazine->id, 'title' => 'Zamanlanmış Sayı', 'status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => now()->addDays(2)]);
        MagazineIssue::factory()->create(['magazine_id' => $magazine->id, 'title' => 'Taslak Sayı', 'status' => ContentStatus::Taslak]);

        $this->get(route('dergi.show', $magazine))
            ->assertOk()
            ->assertSee('Astronomi Dergisi')
            ->assertSee('Yayındaki Sayı')
            ->assertSee('Zamanlanmış Sayı')
            ->assertDontSee('Taslak Sayı');
    }

    public function test_magazines_catalog_lists_only_magazines_with_public_content_and_links_to_them(): void
    {
        $withContent = Magazine::factory()->create(['name' => 'Dolu Dergi']);
        MagazineIssue::factory()->create(['magazine_id' => $withContent->id, 'status' => ContentStatus::Yayinda]);
        $onlyDraft = Magazine::factory()->create(['name' => 'Boş Dergi']);
        MagazineIssue::factory()->create(['magazine_id' => $onlyDraft->id, 'status' => ContentStatus::Taslak]);

        $this->get(route('dergiler.index'))
            ->assertOk()
            ->assertSee('Dolu Dergi')
            ->assertSee(route('dergi.show', $withContent), false)
            ->assertDontSee('Boş Dergi');
    }

    public function test_issue_cards_and_pages_show_the_magazine_name_and_search_finds_by_it(): void
    {
        $magazine = Magazine::factory()->create(['name' => 'Felsefe Dergisi']);
        $issue = MagazineIssue::factory()->create(['magazine_id' => $magazine->id, 'title' => 'Kış Sayısı', 'status' => ContentStatus::Yayinda, 'issue_number' => 7]);

        $this->get(route('dergiler.index'))->assertSeeInOrder(['Felsefe Dergisi', 'Kış Sayısı', 'Sayı 7']);
        $this->get(route('dergiler.show', $issue))->assertSee(route('dergi.show', $magazine), false);
        $this->get(route('arama', ['q' => 'Felsefe']))->assertSee('Kış Sayısı');
    }
}
