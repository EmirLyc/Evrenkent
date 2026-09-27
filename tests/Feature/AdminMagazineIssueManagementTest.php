<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMagazineIssueManagementTest extends TestCase
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

    public function test_only_super_admin_can_access_the_issue_list(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('panel.adminpanel.sayilar.index'))->assertOk();

        $editor = $this->dergiEditoru();
        $this->actingAs($editor)->get(route('panel.adminpanel.sayilar.index'))->assertForbidden();
    }

    public function test_search_and_status_filter_narrow_the_list(): void
    {
        $admin = $this->superAdmin();
        MagazineIssue::factory()->create(['title' => 'Bilim Tarihi Dergisi', 'status' => ContentStatus::Yayinda]);
        MagazineIssue::factory()->create(['title' => 'Astronomi Dergisi', 'status' => ContentStatus::Taslak]);

        $response = $this->actingAs($admin)->get(route('panel.adminpanel.sayilar.index', ['q' => 'Bilim']));
        $response->assertSee('Bilim Tarihi Dergisi')->assertDontSee('Astronomi Dergisi');

        $response = $this->actingAs($admin)->get(route('panel.adminpanel.sayilar.index', ['durum' => 'taslak']));
        $response->assertSee('Astronomi Dergisi')->assertDontSee('Bilim Tarihi Dergisi');
    }

    public function test_super_admin_can_create_an_issue_with_a_cover(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();
        $editor = $this->dergiEditoru();
        // Faz E: sayının editörü seçilen derginin editöründen gelir.
        $magazine = Magazine::factory()->create(['editor_id' => $editor->id]);

        $response = $this->actingAs($admin)->post(route('panel.adminpanel.sayilar.store'), [
            'magazine_id' => $magazine->id,
            'title' => 'Yeni Sayı',
            'issue_number' => 12,
            'cover_image' => UploadedFile::fake()->image('kapak.jpg'),
            'status' => ContentStatus::Taslak->value,
        ]);

        $issue = MagazineIssue::where('title', 'Yeni Sayı')->firstOrFail();
        $response->assertRedirect(route('panel.adminpanel.sayilar.duzenle', $issue));
        $this->assertSame($editor->id, $issue->editor_id);
        $this->assertSame($magazine->id, $issue->magazine_id);
        Storage::disk('public')->assertExists($issue->cover_image);
    }

    public function test_issue_cannot_be_opened_in_a_magazine_without_an_editor(): void
    {
        $magazine = Magazine::factory()->create(['editor_id' => null]);

        $this->actingAs($this->superAdmin())->post(route('panel.adminpanel.sayilar.store'), [
            'magazine_id' => $magazine->id,
            'title' => 'Editörsüz Sayı',
            'issue_number' => 3,
            'status' => ContentStatus::Taslak->value,
        ])->assertSessionHasErrors('magazine_id');

        $this->assertDatabaseMissing('magazine_issues', ['title' => 'Editörsüz Sayı']);
    }

    public function test_status_cannot_be_changed_through_the_update_endpoint(): void
    {
        $admin = $this->superAdmin();
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Onaylandi]);

        $this->actingAs($admin)->put(route('panel.adminpanel.sayilar.guncelle', $issue), [
            'magazine_id' => $issue->magazine_id,
            'title' => $issue->title,
            'issue_number' => $issue->issue_number,
            'status' => ContentStatus::Yayinda->value,
        ]);

        $this->assertSame(ContentStatus::Onaylandi, $issue->refresh()->status);
    }

    public function test_super_admin_can_delete_an_issue_and_its_cover(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();
        $cover = UploadedFile::fake()->image('kapak.jpg')->store('covers/magazine-issues', 'public');
        $issue = MagazineIssue::factory()->create(['cover_image' => $cover]);

        $this->actingAs($admin)
            ->delete(route('panel.adminpanel.sayilar.sil', $issue))
            ->assertRedirect(route('panel.adminpanel.sayilar.index'));

        $this->assertModelMissing($issue);
        Storage::disk('public')->assertMissing($cover);
    }
}
