<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Support\BookDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Faz G2 — Yeni Yayın sayfası ("Yazarın Gözünden" 1.1.1–1.1.6): 4 adım, tek belge editörü
 * (kitapta her Başlık 1 bir bölüm), otomatik kayıt, kapak ve tanıtım, önizleme ve gönder.
 */
class WorkEditorTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function draftBook(User $author, array $attributes = []): Book
    {
        return Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak, ...$attributes]);
    }

    public function test_new_work_is_created_from_the_basic_info_step(): void
    {
        $author = $this->user('yazar');
        $category = Category::factory()->create();

        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim.yeni'))
            ->assertOk()
            ->assertSee('Temel Bilgiler')
            ->assertSee('Sayfa oranı')
            ->assertSee('13 × 20 cm')
            ->assertSee('Hazır bir dosyanız var mı?');

        $response = $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'kitap',
            'title' => 'Modern Devletin Dönüşümü',
            'subtitle' => 'Egemenlik, Sınırlar ve Yeni İmkanlar',
            'page_ratio' => '16x24',
            'heading_numbering' => '0',
            'categories' => [$category->id],
        ])->assertSessionHasNoErrors();

        $book = Book::where('title', 'Modern Devletin Dönüşümü')->sole();
        $response->assertRedirect(route('panel.yayinlarim.kitap.duzenle', [$book, 'icerik']));
        $this->assertSame(['Egemenlik, Sınırlar ve Yeni İmkanlar', '16x24', false, ContentStatus::Taslak, $author->id], [$book->subtitle, $book->page_ratio, $book->heading_numbering, $book->status, $book->author_id]);
        $this->assertSame([$category->id], $book->categories->pluck('id')->all());
    }

    public function test_article_requires_an_assigned_magazine_issue(): void
    {
        $author = $this->user('yazar');
        $issue = MagazineIssue::factory()->create();

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'makale', 'title' => 'Yazı', 'page_ratio' => '21x27.5', 'magazine_issue_id' => $issue->id,
        ])->assertSessionHasErrors('magazine_issue_id');

        $issue->magazine->authors()->attach($author->id);
        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'makale', 'title' => 'Yazı', 'page_ratio' => '21x27.5', 'magazine_issue_id' => $issue->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($issue->id, Article::where('title', 'Yazı')->value('magazine_issue_id'));
    }

    public function test_editor_page_shows_the_book_as_one_document_with_the_mockup_toolbar(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author, ['title' => 'Kitabım', 'subtitle' => 'Alt başlık']);
        Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'Önsöz', 'is_preface' => true, 'content' => '<p>Giriş metni</p>']);
        Chapter::factory()->for($book)->create(['order' => 2, 'title' => 'Birinci', 'content' => '<p>Bir</p>']);

        $response = $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', $book))->assertOk();

        $this->assertSame('<p>Giriş metni</p><h1>Birinci</h1><p>Bir</p>', $response->viewData('editorHtml'));
        $response->assertSee('Yeni Yayın')
            ->assertSee('Kitabım')
            ->assertSee('Alt başlık')
            ->assertSee('Yayınlarıma Dön')
            ->assertSeeInOrder(['Temel Bilgiler', 'İçerik', 'Kapak ve Tanıtım', 'Önizleme ve Gönder'])
            ->assertSeeInOrder(['Ekle', 'İçindekiler', 'Görsel', 'Tablo', 'Video', 'Belge', 'Bağlantı', 'Kaynakça'])
            ->assertSee('Normal Metin')
            ->assertSee('Georgia')
            ->assertSee('Kaynak No.')
            ->assertSee('Dipnot')
            ->assertSee('Sayfa Sonu')
            ->assertSee('Taslak kaydedildi');

        // Eski "Bölümler" sayfası editöre yönleniyor.
        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.bolumler', $book))->assertRedirect(route('panel.yayinlarim.kitap.duzenle', [$book, 'icerik']));
    }

    public function test_saving_keeps_chapter_rows_and_follows_the_document(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);
        $first = Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'Bir']);
        $second = Chapter::factory()->for($book)->create(['order' => 2, 'title' => 'İki']);
        $third = Chapter::factory()->for($book)->create(['order' => 3, 'title' => 'Üç']);

        // İkinci bölüm silindi, sona yenisi eklendi, ilk bölümün metni değişti.
        BookDocument::sync($book, '<h1>Bir (yeni ad)</h1><p>Yeni metin</p><h1>Üç</h1><p>Üç metni</p><h1>Dört</h1><p>Dört metni</p>');

        $chapters = $book->chapters()->get();
        $this->assertSame(['Bir (yeni ad)', 'Üç', 'Dört'], $chapters->pluck('title')->all());
        $this->assertSame([1, 2, 3], $chapters->pluck('order')->all());
        // Var olan satırlar sırayla yeniden kullanılıyor (kimlikler korunuyor).
        $this->assertSame([$first->id, $second->id, $third->id], $chapters->pluck('id')->all());
        $this->assertSame('<p>Yeni metin</p>', $chapters->first()->content);

        // Belge kısalınca fazla bölüm satırları silinir, yenisi eklenince oluşur.
        BookDocument::sync($book, '<h1>Tek</h1><p>Metin</p>');
        $this->assertSame([$first->id], $book->chapters()->pluck('id')->all());
        $this->assertModelMissing($second);
        $this->assertModelMissing($third);

        BookDocument::sync($book, '<h1>Tek</h1><p>Metin</p><h1>Yeni</h1><p>Ek</p>');
        $this->assertSame(['Tek', 'Yeni'], $book->chapters()->pluck('title')->all());
    }

    public function test_page_count_and_source_count_are_automatic(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->putJson(route('panel.yayinlarim.kitap.icerik', $book), [
            'html' => '<h1>Bir</h1><p>A<span data-cite="Arendt"></span><span data-cite="Weber"></span></p><h1>İki</h1><p>B<span data-cite="Arendt"></span></p><section data-bibliography="true"></section>',
            'page_count' => 12,
            'page_ratio' => '13x21',
        ])->assertOk();

        $book->refresh();
        $this->assertSame([12, 2, '13x21'], [$book->page_count, $book->source_count, $book->page_ratio]);
    }

    public function test_citations_are_numbered_across_chapters_and_link_to_the_bibliography(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);
        BookDocument::sync($book, '<h1>Bir</h1><p>A<span data-cite="Arendt"></span></p><h1>İki</h1><p>B<span data-cite="Weber"></span><span data-cite="Arendt"></span></p><nav data-toc="true"></nav><h2>Alt</h2><section data-bibliography="true"></section>');

        $first = $this->actingAs($author)->get(route('kitaplar.oku', [$book, 1]))->assertOk();
        $bibliographyPage = route('kitaplar.oku', [$book, 2]);
        $first->assertSee('<a href="'.$bibliographyPage.'#kaynak-1" title="Arendt">[1]</a>', false);

        $this->actingAs($author)->get($bibliographyPage)->assertOk()
            ->assertSee('<a href="#kaynak-2" title="Weber">[2]</a>', false)
            ->assertSee('<a href="#kaynak-1" title="Arendt">[1]</a>', false)
            ->assertSeeInOrder(['Kaynakça', 'Arendt', 'Weber'])
            // İçindekiler: bölümler (II.) ve alt başlık (2.1.) bağlantılarıyla.
            ->assertSeeInOrder(['İçindekiler', 'I.', 'Bir', 'II.', 'İki', '2.1.', 'Alt'])
            ->assertSee($bibliographyPage.'#b2-1', false);
    }

    public function test_preface_is_not_numbered(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);
        BookDocument::sync($book, '<p>Önsöz</p><h1>Asıl Bölüm</h1><h2>Alt</h2><p>x</p>');

        $this->actingAs($author)->get(route('kitaplar.oku', [$book, 2]))->assertOk()
            ->assertSee('<span class="rt-num">1.1.</span>Alt', false);
    }

    public function test_cover_and_description_step(): void
    {
        Storage::fake(config('filesystems.covers_disk'));
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', [$book, 'kapak']))->assertOk()->assertSee('Tanıtım metni');

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.kapak', $book), [
            'cover_image' => UploadedFile::fake()->image('kapak.jpg', 600, 800),
            'description' => 'Tanıtım.',
            'map_count' => 3,
            'devam' => 1,
        ])->assertRedirect(route('panel.yayinlarim.kitap.duzenle', [$book, 'gonder']));

        $book->refresh();
        $this->assertSame(['Tanıtım.', 3], [$book->description, $book->map_count]);
        Storage::disk(config('filesystems.covers_disk'))->assertExists($book->cover_image);

        // Dergi yazısının da kapağı ve tanıtımı var.
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);
        $this->actingAs($author)->post(route('panel.yayinlarim.makale.kapak', $article), [
            'cover_image' => UploadedFile::fake()->image('kapak.png', 600, 800),
            'description' => 'Yazının tanıtımı.',
        ])->assertRedirect();
        $this->assertNotNull($article->refresh()->cover_image);
    }

    public function test_submit_step_lists_what_is_missing_and_submits(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', [$book, 'gonder']))->assertOk()
            ->assertSee('Göndermeden önce')
            ->assertSee('Göndermek için: İçerik.');

        BookDocument::sync($book, '<h1>Bir</h1><p>Metin</p>');
        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', [$book, 'gonder']))->assertOk()
            ->assertDontSee('Göndermek için')
            ->assertSee('1 bölüm');

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.gonder', $book))->assertRedirect();
        $this->assertSame(ContentStatus::Gonderildi, $book->refresh()->status);
    }

    public function test_only_the_author_can_edit_and_only_while_editable(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($this->user('yazar'))->get(route('panel.yayinlarim.kitap.duzenle', $book))->assertForbidden();
        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', [$book, 'olmayan-adim']))->assertNotFound();

        $book->update(['status' => ContentStatus::Gonderildi]);
        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', $book))->assertForbidden();
    }
}
