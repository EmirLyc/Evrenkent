<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\FakeDocx;
use Tests\TestCase;

/**
 * Faz F1 (2026-09-27 revizesi): bölüm ve makale içeriği zengin metin — başlık, italik,
 * dipnot — ve Word'den (.docx) içe aktarma.
 */
class RichContentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function draftBook(User $author): Book
    {
        return Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);
    }

    public function test_chapter_content_is_sanitized_on_save(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.bolumler.store', $book), [
            'title' => 'Giriş',
            'order' => 1,
            'content' => '<h2>Başlık</h2><p onclick="kotu()"><em>Eğik</em> metin<span data-footnote="Kaynak"></span></p><script>alert(1)</script>',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            '<h2>Başlık</h2><p><em>Eğik</em> metin<span data-footnote="Kaynak"></span></p>',
            $book->chapters()->first()->content
        );
    }

    public function test_empty_editor_output_is_rejected(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.bolumler.store', $book), [
            'title' => 'Boş',
            'order' => 1,
            'content' => '<p></p><p><br></p>',
        ])->assertSessionHasErrors('content');

        $this->assertSame(0, $book->chapters()->count());
    }

    public function test_plain_text_is_still_accepted_and_stored_as_paragraphs(): void
    {
        $chapter = Chapter::factory()->create(['content' => "Birinci paragraf.\n\nİkinci paragraf."]);

        $this->assertSame('<p>Birinci paragraf.</p><p>İkinci paragraf.</p>', $chapter->content);
    }

    public function test_reading_page_renders_formatting_and_numbered_footnotes(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create([
            'order' => 1,
            'content' => '<h2>Ara Başlık</h2><p><strong>Kalın</strong> cümle<span data-footnote="Birinci kaynak"></span> ve devamı<span data-footnote="İkinci kaynak"></span>.</p>',
        ]);

        $this->get(route('kitaplar.oku', $book))
            ->assertOk()
            ->assertSee('<h2>Ara Başlık</h2>', false)
            ->assertSee('<strong>Kalın</strong>', false)
            ->assertSee('href="#dn-bolum-1-2"', false)
            ->assertSeeInOrder(['Birinci kaynak', 'İkinci kaynak'])
            ->assertDontSee('data-footnote', false);
    }

    public function test_article_page_renders_html_instead_of_escaped_tags(): void
    {
        $article = Article::factory()->create([
            'status' => ContentStatus::Yayinda,
            'content' => '<p>Bir <em>makale</em> paragrafı.</p>',
        ]);

        $this->get(route('makaleler.show', $article))
            ->assertOk()
            ->assertSee('<em>makale</em>', false)
            ->assertDontSee('&lt;em&gt;', false);
    }

    public function test_new_article_draft_keeps_rich_content_but_book_description_stays_plain(): void
    {
        $author = $this->user('yazar');
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Taslak]);
        $issue->magazine->authors()->attach($author->id);

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'makale',
            'title' => 'Zengin Makale',
            'magazine_issue_id' => $issue->id,
            'body' => '<h2>Giriş</h2><p>Metin<span data-footnote="Not"></span></p>',
        ])->assertSessionHasNoErrors();

        $this->assertSame('<h2>Giriş</h2><p>Metin<span data-footnote="Not"></span></p>', Article::where('title', 'Zengin Makale')->value('content'));

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'kitap',
            'title' => 'Düz Kitap',
            'body' => 'Kitap açıklaması.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Kitap açıklaması.', Book::where('title', 'Düz Kitap')->value('description'));
    }

    // --- Word'den aktarma ---------------------------------------------------------

    public function test_word_preview_returns_html_and_suggested_title_without_saving(): void
    {
        $author = $this->user('yazar');
        $file = FakeDocx::upload(
            FakeDocx::p('Makalenin Adı', 'Balk1').'<w:p><w:r><w:t>Metin</w:t></w:r><w:r><w:footnoteReference w:id="1"/></w:r></w:p>',
            ['1' => 'Dipnot metni']
        );

        $this->actingAs($author)
            ->postJson(route('panel.yayinlarim.word-aktar'), ['file' => $file])
            ->assertOk()
            ->assertExactJson([
                'title' => 'Makalenin Adı',
                'html' => '<p>Metin<span data-footnote="Dipnot metni"></span></p>',
            ]);

        $this->assertSame(0, Chapter::count() + Article::count());
    }

    public function test_word_preview_rejects_non_docx_files(): void
    {
        $author = $this->user('yazar');

        $this->actingAs($author)
            ->postJson(route('panel.yayinlarim.word-aktar'), ['file' => UploadedFile::fake()->createWithContent('metin.pdf', '%PDF-1.4')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        // Uzantısı .docx ama içi Word değil.
        $this->actingAs($author)
            ->postJson(route('panel.yayinlarim.word-aktar'), ['file' => UploadedFile::fake()->createWithContent('sahte.docx', 'düz metin')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_only_authors_can_use_word_import(): void
    {
        $this->actingAs($this->user('okur'))
            ->postJson(route('panel.yayinlarim.word-aktar'), ['file' => FakeDocx::upload(FakeDocx::p('Metin'))])
            ->assertForbidden();
    }

    public function test_book_file_is_split_into_chapters_appended_after_existing_ones(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);
        Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'Mevcut Bölüm']);

        $file = FakeDocx::upload(
            FakeDocx::p('Önsöz')
            .FakeDocx::p('Uyanış', 'Balk1').FakeDocx::p('Uyanış metni')
            .FakeDocx::p('Sisin İçinde', 'Balk1').FakeDocx::p('Sis metni', null, '<w:i/>'),
            name: 'roman.docx'
        );

        $this->actingAs($author)
            ->post(route('panel.yayinlarim.kitap.bolumler.word-aktar', $book), ['file' => $file])
            ->assertRedirect(route('panel.yayinlarim.kitap.bolumler', $book))
            ->assertSessionHas('status');

        $chapters = $book->chapters()->orderBy('order')->get();
        $this->assertSame(['Mevcut Bölüm', 'Giriş', 'Uyanış', 'Sisin İçinde'], $chapters->pluck('title')->all());
        $this->assertSame([1, 2, 3, 4], $chapters->pluck('order')->all());
        $this->assertSame('<p><em>Sis metni</em></p>', $chapters->last()->content);
    }

    public function test_file_without_headings_becomes_one_chapter_named_after_the_file(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.bolumler.word-aktar', $book), [
            'file' => FakeDocx::upload(FakeDocx::p('Tek parça metin'), name: 'Kısa Öykü.docx'),
        ]);

        $this->assertSame(['Kısa Öykü'], $book->chapters()->pluck('title')->all());
    }

    public function test_author_cannot_import_chapters_into_someone_elses_book(): void
    {
        $book = $this->draftBook($this->user('yazar'));

        $this->actingAs($this->user('yazar'))
            ->post(route('panel.yayinlarim.kitap.bolumler.word-aktar', $book), ['file' => FakeDocx::upload(FakeDocx::p('Metin'))])
            ->assertForbidden();

        $this->assertSame(0, $book->chapters()->count());
    }
}
