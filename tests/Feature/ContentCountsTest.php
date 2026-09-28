<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kitap tanıtım sayfasındaki belge ve video sayıları bölüm metninden hesaplanıyor
 * (önceden yazar elle giriyordu).
 */
class ContentCountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.documents_disk'));
    }

    private function document(Book $book): Document
    {
        return Document::factory()->create(['documentable_type' => Book::class, 'documentable_id' => $book->id]);
    }

    public function test_counts_follow_the_chapter_content(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        $used = $this->document($book);
        $this->document($book); // yüklenmiş ama metinde kullanılmıyor — sayılmaz
        $foreign = $this->document(Book::factory()->create()); // başka kitabın — sayılmaz

        Chapter::factory()->for($book)->create(['order' => 1, 'content' => '<p>A<span data-document="'.$used->id.'"></span><span data-document="'.$used->id.'"></span><span data-document="'.$foreign->id.'"></span></p><figure data-video="https://youtu.be/dQw4w9WgXcQ" data-title="Bir"></figure>']);
        Chapter::factory()->for($book)->create(['order' => 2, 'content' => '<p>B</p><figure data-video="https://vimeo.com/123456789" data-title="İki"></figure><figure data-video="https://kotu.example/x" data-title="Geçersiz"></figure>']);

        $book->refresh();
        $this->assertSame(1, $book->document_count);
        $this->assertSame(2, $book->video_count);

        $this->get(route('kitaplar.show', $book))->assertSeeInOrder(['1', 'belge'])->assertSeeInOrder(['2', 'video']);
    }

    public function test_deleting_a_used_document_or_chapter_updates_the_counts(): void
    {
        $book = Book::factory()->create();
        $document = $this->document($book);
        $chapter = Chapter::factory()->for($book)->create(['order' => 1, 'content' => '<p>A<span data-document="'.$document->id.'"></span></p><figure data-video="https://youtu.be/dQw4w9WgXcQ"></figure>']);
        $this->assertSame(1, $book->refresh()->document_count);

        $document->delete();
        $this->assertNull($book->refresh()->document_count);

        $chapter->delete();
        $this->assertNull($book->refresh()->video_count);
    }

    public function test_authors_can_no_longer_type_the_counts(): void
    {
        $author = User::factory()->create();
        $author->assignRole('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        // Faz G2: sayfa ve kaynak sayısı da otomatik (editördeki sayfa hesabı, tekil kaynaklar);
        // "Kapak ve Tanıtım" adımında sadece harita ve yazar notu elle giriliyor.
        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.kapak', $book), [
            'description' => 'Açıklama',
            'map_count' => 4,
            'page_count' => 120,
            'source_count' => 50,
            'document_count' => 99,
            'video_count' => 99,
        ])->assertSessionHasNoErrors();

        $book->refresh();
        $this->assertSame(4, $book->map_count);
        $this->assertNull($book->page_count);
        $this->assertNull($book->source_count);
        $this->assertNull($book->document_count);
        $this->assertNull($book->video_count);
    }
}
