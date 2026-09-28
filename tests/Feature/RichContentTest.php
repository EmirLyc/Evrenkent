<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDocx;
use Tests\Support\FakeEpub;
use Tests\TestCase;

/**
 * Zengin metin (Faz F1) ve Yeni Yayın editörüne kayıt / Word-EPUB aktarma (Faz G2: kitap editörde
 * tek belge, her "Başlık 1" bir bölüm; Word'deki başlık katmanları aynen; görseller metin içi).
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

    public function test_book_content_is_sanitized_and_split_into_chapters_on_save(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->putJson(route('panel.yayinlarim.kitap.icerik', $book), [
            'html' => '<h1>Giriş</h1><h2>Başlık</h2><p onclick="kotu()"><em>Eğik</em> metin<span data-footnote="Kaynak"></span></p><script>alert(1)</script>',
        ])->assertOk()->assertJsonStructure(['saved_label', 'chapters']);

        $chapter = $book->chapters()->sole();
        $this->assertSame('Giriş', $chapter->title);
        $this->assertSame('<h2>Başlık</h2><p><em>Eğik</em> metin<span data-footnote="Kaynak"></span></p>', $chapter->content);
    }

    public function test_emptying_the_editor_removes_the_chapters(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);
        Chapter::factory()->for($book)->create(['order' => 1]);

        $this->actingAs($author)->putJson(route('panel.yayinlarim.kitap.icerik', $book), ['html' => '<p></p><p><br></p>'])->assertOk();

        $this->assertSame(0, $book->chapters()->count());
    }

    public function test_plain_text_is_still_accepted_and_stored_as_paragraphs(): void
    {
        $chapter = Chapter::factory()->create(['content' => "Birinci paragraf.\n\nİkinci paragraf."]);

        $this->assertSame('<p>Birinci paragraf.</p><p>İkinci paragraf.</p>', $chapter->content);
    }

    public function test_reading_page_renders_formatting_numbered_headings_and_footnotes(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create([
            'order' => 1,
            'content' => '<h2>Ara Başlık</h2><p><strong>Kalın</strong> cümle<span data-footnote="Birinci kaynak"></span> ve devamı<span data-footnote="İkinci kaynak"></span>.</p>',
        ]);

        $this->get(route('kitaplar.oku', $book))
            ->assertOk()
            // Faz G2: Başlık 2, bölüm 1'in altında "1.1." (başlık numaralandırma varsayılan açık).
            ->assertSee('<h2 id="b1-1"><span class="rt-num">1.1.</span>Ara Başlık</h2>', false)
            ->assertSee('<strong>Kalın</strong>', false)
            ->assertSee('href="#dn-bolum-1-2"', false)
            ->assertSeeInOrder(['Birinci kaynak', 'İkinci kaynak'])
            ->assertDontSee('data-footnote', false);

        $book->update(['heading_numbering' => false]);
        $this->get(route('kitaplar.oku', $book))->assertDontSee('rt-num', false);
    }

    /** Faz F3: video satırı kayıtta korunur, okuma sayfasında oynatıcı bağlantısı olur. */
    public function test_video_line_is_saved_and_rendered_on_the_reading_page(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->putJson(route('panel.yayinlarim.kitap.icerik', $book), [
            'html' => '<h1>Yazışmalar</h1><p>Metin</p><figure data-video="https://www.youtube.com/watch?v=dQw4w9WgXcQ" data-title="Yazışma Usulü" data-duration="12:45"></figure>',
        ])->assertOk();

        // Yazar taslak kitabını okuma sayfasında önizleyebiliyor.
        $this->actingAs($author)->get(route('kitaplar.oku', $book))
            ->assertOk()
            ->assertSee('data-document-viewer="video"', false)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSeeInOrder(['Video: Yazışma Usulü', '(12:45 dk.)']);
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

    public function test_article_content_keeps_rich_formatting_including_heading_1(): void
    {
        $author = $this->user('yazar');
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)->putJson(route('panel.yayinlarim.makale.icerik', $article), [
            'html' => '<h1>Giriş</h1><p data-align="justify">Metin<span data-footnote="Not"></span></p>',
            'page_ratio' => '16x24',
        ])->assertOk();

        $article->refresh();
        $this->assertSame('<h1>Giriş</h1><p data-align="justify">Metin<span data-footnote="Not"></span></p>', $article->content);
        $this->assertSame('16x24', $article->page_ratio);
    }

    public function test_content_can_only_be_saved_while_editable_and_by_the_author(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($this->user('yazar'))->putJson(route('panel.yayinlarim.kitap.icerik', $book), ['html' => '<p>x</p>'])->assertForbidden();

        $book->update(['status' => ContentStatus::Gonderildi]);
        $this->actingAs($author)->putJson(route('panel.yayinlarim.kitap.icerik', $book), ['html' => '<p>x</p>'])->assertForbidden();
    }

    // --- Word / EPUB'dan aktarma ------------------------------------------------------

    public function test_word_import_returns_html_with_heading_layers_and_the_title_without_saving(): void
    {
        $author = $this->user('yazar');
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak, 'content' => '<p>Eski</p>']);
        $file = FakeDocx::upload(
            FakeDocx::p('Makalenin Adı', 'KonuBal')
            .FakeDocx::p('Giriş', 'Balk1')
            .'<w:p><w:pPr><w:jc w:val="both"/></w:pPr><w:r><w:t>Metin</w:t></w:r><w:r><w:footnoteReference w:id="1"/></w:r></w:p>'
            .FakeDocx::p('Alt', 'Balk3'),
            ['1' => 'Dipnot metni']
        );

        $this->actingAs($author)
            ->postJson(route('panel.yayinlarim.makale.aktar', $article), ['file' => $file])
            ->assertOk()
            ->assertJson([
                'title' => 'Makalenin Adı',
                // Word'deki Başlık 1 / Başlık 3 editörde de Başlık 1 / Başlık 3 (Faz G2).
                'html' => '<h1>Giriş</h1><p data-align="justify">Metin<span data-footnote="Dipnot metni"></span></p><h3>Alt</h3>',
                'images' => ['imported' => 0, 'skipped' => 0],
            ]);

        $this->assertSame('<p>Eski</p>', $article->refresh()->content);
    }

    public function test_import_rejects_other_files(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)
            ->postJson(route('panel.yayinlarim.kitap.aktar', $book), ['file' => UploadedFile::fake()->createWithContent('metin.pdf', '%PDF-1.4')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        // Uzantısı .docx ama içi Word değil.
        $this->actingAs($author)
            ->postJson(route('panel.yayinlarim.kitap.aktar', $book), ['file' => UploadedFile::fake()->createWithContent('sahte.docx', 'düz metin')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_only_the_author_can_import_into_a_work(): void
    {
        $book = $this->draftBook($this->user('yazar'));

        $this->actingAs($this->user('okur'))
            ->postJson(route('panel.yayinlarim.kitap.aktar', $book), ['file' => FakeDocx::upload(FakeDocx::p('Metin'))])
            ->assertForbidden();
        $this->actingAs($this->user('yazar'))
            ->postJson(route('panel.yayinlarim.kitap.aktar', $book), ['file' => FakeDocx::upload(FakeDocx::p('Metin'))])
            ->assertForbidden();
    }

    public function test_imported_book_becomes_chapters_at_heading_1_when_saved(): void
    {
        $author = $this->user('yazar');
        $book = $this->draftBook($author);
        $file = FakeDocx::upload(
            FakeDocx::p('Önsöz')
            .FakeDocx::p('Uyanış', 'Balk1').FakeDocx::p('Uyanış metni')
            .FakeDocx::p('Sisin İçinde', 'Balk1').FakeDocx::p('Sis metni', null, '<w:i/>'),
            name: 'roman.docx'
        );

        $html = $this->actingAs($author)->postJson(route('panel.yayinlarim.kitap.aktar', $book), ['file' => $file])->assertOk()->json('html');
        $this->assertSame('<p>Önsöz</p><h1>Uyanış</h1><p>Uyanış metni</p><h1>Sisin İçinde</h1><p><em>Sis metni</em></p>', $html);

        $this->actingAs($author)->putJson(route('panel.yayinlarim.kitap.icerik', $book), ['html' => $html])->assertOk()->assertJsonPath('chapters', 3);

        $chapters = $book->chapters()->get();
        $this->assertSame(['Giriş', 'Uyanış', 'Sisin İçinde'], $chapters->pluck('title')->all());
        $this->assertSame([true, false, false], $chapters->pluck('is_preface')->all());
        $this->assertSame('<p><em>Sis metni</em></p>', $chapters->last()->content);
    }

    /** EPUB (DOCX'in ikinci seçeneği): her okuma dosyası bir Başlık 1, görseller metin içi görsel. */
    public function test_epub_import_gives_heading_1_per_file_and_inline_images(): void
    {
        Storage::fake(config('filesystems.documents_disk'));
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $response = $this->actingAs($author)->postJson(route('panel.yayinlarim.kitap.aktar', $book), [
            'file' => FakeEpub::upload(
                [
                    'bolum1.xhtml' => '<h1>Liman</h1><p>Kadırga yanaştı.</p><p><img src="../Images/harita.png" alt="Liman haritası"/></p>',
                    'bolum2.xhtml' => '<h1>Fener</h1><p>Fenerci merdivenleri çıktı.</p>',
                ],
                ['Images/harita.png' => FakeDocx::png(), 'Images/kapak.png' => FakeDocx::png()]
            ),
        ])->assertOk()->assertJsonPath('images.imported', 1);

        $document = $book->documents()->sole();
        $this->assertSame('Liman haritası', $document->title);
        $this->assertSame(Document::KIND_GORSEL, $document->kind);
        $this->assertSame(
            '<h1>Liman</h1><p>Kadırga yanaştı.</p><figure data-image="'.$document->id.'" data-caption="Liman haritası"></figure><h1>Fener</h1><p>Fenerci merdivenleri çıktı.</p>',
            $response->json('html')
        );
    }

    public function test_word_images_become_inline_images_and_unsupported_ones_are_skipped(): void
    {
        Storage::fake(config('filesystems.documents_disk'));
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $file = FakeDocx::upload(
            FakeDocx::p('Rapor aşağıda.').FakeDocx::imageParagraph('rIdImg1', 'Londra Raporu').FakeDocx::imageParagraph('rIdImg2'),
            images: ['rIdImg1' => ['media/image1.png', FakeDocx::png()], 'rIdImg2' => ['media/image2.gif', 'GIF89a sahte']]
        );

        $response = $this->actingAs($author)
            ->postJson(route('panel.yayinlarim.kitap.aktar', $book), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('images.imported', 1)
            ->assertJsonPath('images.skipped', 1);

        $document = $book->documents()->sole();
        $this->assertSame(['Londra Raporu', 'image/png', Document::KIND_GORSEL], [$document->title, $document->mime_type, $document->kind]);
        Storage::disk(config('filesystems.documents_disk'))->assertExists($document->file_path);
        $this->assertSame([['id' => $document->id, 'url' => $document->viewUrl(), 'title' => 'Londra Raporu']], $response->json('images_list'));
        $this->assertStringContainsString('<figure data-image="'.$document->id.'" data-caption="Londra Raporu"></figure>', $response->json('html'));
    }

    public function test_inline_image_renders_on_the_reading_page_only_for_its_own_book(): void
    {
        Storage::fake(config('filesystems.documents_disk'));
        $author = $this->user('yazar');
        $book = $this->draftBook($author);
        $image = Document::storeContents($book, FakeDocx::png(), 'image/png', 'harita.png', 'Harita', $author, Document::KIND_GORSEL);
        $foreign = Document::storeContents($this->draftBook($author), FakeDocx::png(), 'image/png', 'x.png', 'Yabancı', $author, Document::KIND_GORSEL);

        $this->actingAs($author)->putJson(route('panel.yayinlarim.kitap.icerik', $book), [
            'html' => '<h1>Bölüm</h1><figure data-image="'.$image->id.'" data-caption="Eski harita"></figure><figure data-image="'.$foreign->id.'"></figure>',
        ])->assertOk();

        $this->actingAs($author)->get(route('kitaplar.oku', $book))
            ->assertOk()
            ->assertSee('<img src="'.$image->viewUrl().'" alt="Eski harita" loading="lazy">', false)
            ->assertSee('<figcaption>Eski harita</figcaption>', false)
            ->assertDontSee($foreign->viewUrl(), false);
    }

    public function test_editor_image_upload_accepts_only_images(): void
    {
        Storage::fake(config('filesystems.documents_disk'));
        $author = $this->user('yazar');
        $book = $this->draftBook($author);

        $this->actingAs($author)->postJson(route('panel.yayinlarim.kitap.gorsel', $book), [
            'file' => UploadedFile::fake()->createWithContent('harita.png', FakeDocx::png()),
            'caption' => 'Harita',
        ])->assertCreated()->assertJsonPath('title', 'Harita');

        $this->actingAs($author)->postJson(route('panel.yayinlarim.kitap.gorsel', $book), [
            'file' => UploadedFile::fake()->createWithContent('belge.pdf', '%PDF-1.4 sahte'),
        ])->assertUnprocessable();

        $this->assertSame([Document::KIND_GORSEL], $book->documents()->pluck('kind')->all());
    }

    public function test_a_new_work_can_start_from_a_word_file(): void
    {
        $author = $this->user('yazar');

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'kitap',
            'title' => 'Dosyadan Kitap',
            'page_ratio' => '13x21',
            'file' => FakeDocx::upload(FakeDocx::p('Birinci', 'Balk1').FakeDocx::p('Metin')),
        ])->assertSessionHasNoErrors();

        $book = Book::where('title', 'Dosyadan Kitap')->sole();
        $this->assertSame(['Birinci'], $book->chapters()->pluck('title')->all());
        $this->assertSame('13x21', $book->page_ratio);
    }
}
