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
use Tests\TestCase;

/**
 * Faz F2 (mockup 3 / 3.1): metne gömülü belgeler — kar tanesi ikonu, üstüne gelince
 * "ad (tarih / N sayfa)", tıklayınca site içinde açılır, indirilmez.
 */
class EmbeddedDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.documents_disk'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function pdf(string $name = 'yazi.pdf', int $pages = 4): UploadedFile
    {
        $kids = implode(' ', array_map(fn ($i) => ($i + 3).' 0 R', range(0, $pages - 1)));

        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [{$kids}] /Count {$pages} >> endobj\n%%EOF");
    }

    private function realUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function storedDocument(Book|Article $owner, array $attributes = []): Document
    {
        Storage::disk(config('filesystems.documents_disk'))->put('documents/belge.pdf', '%PDF-1.4 test');

        return Document::factory()->create([
            'documentable_type' => $owner::class,
            'documentable_id' => $owner->id,
            'file_path' => 'documents/belge.pdf',
            ...$attributes,
        ]);
    }

    // --- Yazar: yükleme / düzenleme / silme ------------------------------------------

    public function test_author_uploads_a_pdf_to_own_draft_book_and_page_count_is_detected(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.belgeler.store', $book), [
            'title' => "Paris Sefareti'nden Gönderilen Yazı",
            'date_label' => '3 Haziran 1873',
            'file' => $this->pdf(pages: 4),
        ])->assertSessionHasNoErrors();

        $document = $book->documents()->firstOrFail();
        $this->assertSame(4, $document->page_count);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame("Paris Sefareti'nden Gönderilen Yazı (3 Haziran 1873 / 4 sayfa)", $document->caption());
        Storage::disk(config('filesystems.documents_disk'))->assertExists($document->file_path);
    }

    public function test_only_pdf_and_images_are_accepted_by_content_not_extension(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.belgeler.store', $book), [
            'title' => 'Sahte',
            // fake() MIME'ı uzantıdan bildiriyor; gerçek dosya ile içerikten tespit sınanıyor.
            'file' => $this->realUpload('sahte.pdf', '<html><body><script>alert(1)</script></body></html>'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, $book->documents()->count());
    }

    public function test_other_authors_cannot_manage_the_books_documents(): void
    {
        $book = Book::factory()->for($this->user('yazar'), 'author')->create(['status' => ContentStatus::Taslak]);
        $document = $this->storedDocument($book);
        $stranger = $this->user('yazar');

        $this->actingAs($stranger)->get(route('panel.yayinlarim.kitap.belgeler', $book))->assertForbidden();
        $this->actingAs($stranger)->post(route('panel.yayinlarim.kitap.belgeler.store', $book), ['title' => 'x', 'file' => $this->pdf()])->assertForbidden();
        $this->actingAs($stranger)->delete(route('panel.yayinlarim.belgeler.sil', $document))->assertForbidden();
        $this->assertModelExists($document);
    }

    public function test_author_edits_metadata_and_deleting_removes_the_file(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);
        $document = $this->storedDocument($book);

        $this->actingAs($author)->put(route('panel.yayinlarim.belgeler.guncelle', $document), [
            'title' => 'Yeni Ad', 'date_label' => '1873', 'page_count' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Yeni Ad (1873 / 2 sayfa)', $document->refresh()->caption());

        $this->actingAs($author)->delete(route('panel.yayinlarim.belgeler.sil', $document));
        $this->assertModelMissing($document);
        Storage::disk(config('filesystems.documents_disk'))->assertMissing('documents/belge.pdf');
    }

    public function test_documents_page_shows_where_each_document_is_used(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);
        $used = $this->storedDocument($book, ['title' => 'Kullanılan Belge']);
        $this->storedDocument($book, ['title' => 'Kullanılmayan Belge']);
        Chapter::factory()->for($book)->create(['order' => 1, 'content' => '<p>Metin<span data-document="'.$used->id.'"></span></p>']);

        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.belgeler', $book))
            ->assertOk()
            ->assertSeeInOrder(['Kullanılan Belge', 'Metinde kullanılıyor (1 bölüm)', 'Kullanılmayan Belge', 'Henüz metne eklenmedi']);
    }

    // --- Okur: kar tanesi ve görüntüleme --------------------------------------------

    public function test_reading_page_renders_a_snowflake_with_the_caption_and_drops_foreign_documents(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        $own = $this->storedDocument($book, ['title' => 'Londra Raporu', 'date_label' => '1873', 'page_count' => 3]);
        $foreign = $this->storedDocument(Book::factory()->create(), ['title' => 'Başka Kitabın Belgesi']);
        Chapter::factory()->for($book)->create([
            'order' => 1,
            'content' => '<p>Rapor burada.<span data-document="'.$own->id.'"></span> Diğeri<span data-document="'.$foreign->id.'"></span></p>',
        ]);

        $this->get(route('kitaplar.oku', $book))
            ->assertOk()
            ->assertSee('class="document-marker"', false)
            ->assertSee('Londra Raporu (1873 / 3 sayfa)')
            ->assertSee(route('belgeler.goster', $own), false)
            ->assertDontSee('Başka Kitabın Belgesi')
            ->assertDontSee(route('belgeler.goster', $foreign), false)
            ->assertDontSee('data-document=', false);
    }

    public function test_reader_of_a_free_book_can_view_the_document_inline_without_caching(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        $document = $this->storedDocument($book);

        $response = $this->get(route('belgeler.goster', $document))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_documents_of_paid_books_require_a_purchase(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Yayinda, 'price' => 120]);
        $document = $this->storedDocument($book);
        $reader = $this->user('okur');

        $this->get(route('belgeler.goster', $document))->assertNotFound();
        $this->actingAs($reader)->get(route('belgeler.goster', $document))->assertNotFound();

        $reader->purchases()->create(['book_id' => $book->id, 'amount' => 120, 'purchased_at' => now()]);
        $this->actingAs($reader)->get(route('belgeler.goster', $document))->assertOk();
        $this->actingAs($author)->get(route('belgeler.goster', $document))->assertOk();
    }

    public function test_documents_of_draft_content_are_only_visible_to_the_author_and_reviewers(): void
    {
        $author = $this->user('yazar');
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);
        $document = $this->storedDocument($article);

        $this->get(route('belgeler.goster', $document))->assertNotFound();
        $this->actingAs($this->user('okur'))->get(route('belgeler.goster', $document))->assertNotFound();
        $this->actingAs($author)->get(route('belgeler.goster', $document))->assertOk();
        $this->actingAs($this->user('super_admin'))->get(route('belgeler.goster', $document))->assertOk();
    }

    public function test_deleting_a_book_removes_its_documents(): void
    {
        $book = Book::factory()->create();
        $document = $this->storedDocument($book);

        $book->delete();

        $this->assertModelMissing($document);
        Storage::disk(config('filesystems.documents_disk'))->assertMissing('documents/belge.pdf');
    }
}
