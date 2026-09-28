<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationDraftEditTest extends TestCase
{
    use RefreshDatabase;

    private function yazar(): User
    {
        $user = User::factory()->create();
        $user->assignRole('yazar');

        return $user;
    }

    public function test_yazar_can_view_and_update_own_draft_book(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create([
            'status' => ContentStatus::Taslak,
            'title' => 'Eski Başlık',
        ]);

        $this->actingAs($author)
            ->get(route('panel.yayinlarim.kitap.duzenle', $book))
            ->assertOk()
            ->assertSee('Eski Başlık');

        $this->actingAs($author)
            ->put(route('panel.yayinlarim.kitap.guncelle', $book), [
                'title' => 'Yeni Başlık',
                'page_ratio' => '13x20',
                'price' => 149.90,
            ])
            ->assertRedirect(route('panel.yayinlarim.kitap.duzenle', [$book, 'bilgiler']));

        $book->refresh();
        $this->assertSame('Yeni Başlık', $book->title);
        $this->assertSame(ContentStatus::Taslak, $book->status);
    }

    public function test_yazar_can_set_content_stats_and_only_filled_ones_are_saved(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)
            ->post(route('panel.yayinlarim.kitap.kapak', $book), [
                'description' => 'Tanıtım',
                'map_count' => 3,
                'author_note_count' => 15,
            ])
            ->assertRedirect();

        $book->refresh();
        $this->assertSame(3, $book->map_count);
        $this->assertSame(15, $book->author_note_count);
        $this->assertNull($book->document_count);
    }

    public function test_yazar_can_suggest_a_target_publish_date(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);
        $target = now()->addDays(20)->startOfMinute();

        $this->actingAs($author)
            ->put(route('panel.yayinlarim.kitap.guncelle', $book), [
                'title' => $book->title,
                'page_ratio' => '13x20',
                'scheduled_publish_at' => $target->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect();

        $this->assertTrue($target->equalTo($book->fresh()->scheduled_publish_at));
    }

    public function test_scheduled_publish_date_must_be_in_the_future(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)
            ->put(route('panel.yayinlarim.kitap.guncelle', $book), [
                'title' => $book->title,
                'page_ratio' => '13x20',
                'scheduled_publish_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('scheduled_publish_at');
    }

    public function test_yazar_can_assign_multiple_categories_and_can_also_remove_them(): void
    {
        $author = $this->yazar();
        $roman = Category::factory()->create();
        $siir = Category::factory()->create();
        $tarih = Category::factory()->create();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);
        $book->categories()->attach($tarih);

        $this->actingAs($author)
            ->put(route('panel.yayinlarim.kitap.guncelle', $book), [
                'title' => $book->title,
                'page_ratio' => '13x20',
                'categories' => [$roman->id, $siir->id],
            ])
            ->assertRedirect();

        $book->refresh();
        $this->assertEqualsCanonicalizing([$roman->id, $siir->id], $book->categories->pluck('id')->all());
    }

    public function test_another_yazar_cannot_edit_someone_elses_book(): void
    {
        $owner = $this->yazar();
        $intruder = $this->yazar();
        $book = Book::factory()->for($owner, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($intruder)
            ->get(route('panel.yayinlarim.kitap.duzenle', $book))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put(route('panel.yayinlarim.kitap.guncelle', $book), [
                'title' => 'Ele geçirme denemesi',
                'body' => 'x',
            ])
            ->assertForbidden();

        $this->assertNotSame('Ele geçirme denemesi', $book->fresh()->title);
    }

    public function test_editing_a_submitted_book_is_forbidden(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($author)
            ->get(route('panel.yayinlarim.kitap.duzenle', $book))
            ->assertForbidden();
    }

    /** Faz G1: Geri Dönenler kalktı — düzeltme notu eserin detay sayfasında ve mesajlarda. */
    public function test_revision_note_is_shown_on_the_detail_and_messages_pages(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::RevizyonIstendi]);
        $book->reviews()->create([
            'reviewer_id' => User::factory()->create()->id,
            'action' => 'revizyon_istendi',
            'note' => 'Kapak görseli eksik, lütfen ekleyin.',
        ]);

        $this->actingAs($author)
            ->get(route('panel.yayinlarim.kitap.detay', $book))
            ->assertOk()
            ->assertSee('Düzeltme notu')
            ->assertSee('Kapak görseli eksik, lütfen ekleyin.');

        $this->actingAs($author)
            ->get(route('panel.mesajlar.kitap', $book))
            ->assertOk()
            ->assertSee('Kapak görseli eksik, lütfen ekleyin.');
    }

    public function test_edit_link_is_not_shown_for_a_submitted_book(): void
    {
        $author = $this->yazar();
        Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($author)
            ->get(route('panel.yayinlarim.taslaklarim', ['durum' => 'incelemede']))
            ->assertOk()
            ->assertSee('Gönderimi Gör')
            ->assertDontSee('Düzenle');
    }

    public function test_yazar_can_view_and_update_own_draft_article(): void
    {
        $author = $this->yazar();
        $issue = MagazineIssue::factory()->create();
        $article = Article::factory()->for($author, 'author')->create([
            'status' => ContentStatus::Taslak,
            'title' => 'Eski Makale',
            'magazine_issue_id' => $issue->id,
        ]);

        $this->actingAs($author)
            ->put(route('panel.yayinlarim.makale.guncelle', $article), [
                'title' => 'Yeni Makale',
                'page_ratio' => '21x27.5',
                'magazine_issue_id' => $issue->id,
            ])
            ->assertRedirect(route('panel.yayinlarim.makale.duzenle', [$article, 'bilgiler']));

        $article->refresh();
        $this->assertSame('Yeni Makale', $article->title);
        $this->assertSame(ContentStatus::Taslak, $article->status);
    }

    public function test_yazar_can_delete_own_draft_book(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)
            ->delete(route('panel.yayinlarim.kitap.sil', $book))
            ->assertRedirect(route('panel.yayinlarim.taslaklarim'));

        // Faz G1: silme iki kademeli — önce çöp kutusu.
        $this->assertSoftDeleted($book);
    }

    public function test_yazar_cannot_delete_a_submitted_book(): void
    {
        $author = $this->yazar();
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($author)
            ->delete(route('panel.yayinlarim.kitap.sil', $book))
            ->assertForbidden();

        $this->assertModelExists($book);
    }

    public function test_yazar_cannot_delete_someone_elses_book(): void
    {
        $owner = $this->yazar();
        $intruder = $this->yazar();
        $book = Book::factory()->for($owner, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($intruder)
            ->delete(route('panel.yayinlarim.kitap.sil', $book))
            ->assertForbidden();

        $this->assertModelExists($book);
    }

    public function test_yazar_can_delete_own_draft_article(): void
    {
        $author = $this->yazar();
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)
            ->delete(route('panel.yayinlarim.makale.sil', $article))
            ->assertRedirect(route('panel.yayinlarim.taslaklarim'));

        $this->assertSoftDeleted($article);
    }

    public function test_delete_button_only_appears_for_draft_items_in_taslaklarim(): void
    {
        $author = $this->yazar();
        Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak, 'title' => 'Silinebilir Taslak']);

        $this->actingAs($author)
            ->get(route('panel.yayinlarim.taslaklarim'))
            ->assertOk()
            ->assertSee('Çöp kutusuna taşı');
    }

    public function test_delete_button_does_not_appear_for_submitted_items(): void
    {
        $author = $this->yazar();
        Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($author)
            ->get(route('panel.yayinlarim.taslaklarim'))
            ->assertOk()
            ->assertDontSee('Çöp kutusuna taşı');
    }
}
