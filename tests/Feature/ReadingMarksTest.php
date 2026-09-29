<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\NoteType;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz H3 ("Okurun Gözünden" — Okuma moduna dair): okur metni seçip Alıntıla / Not Al /
 * Fosforla diyor; işaret metindeki yeriyle kaydediliyor ve okuma sayfasında gösteriliyor.
 */
class ReadingMarksTest extends TestCase
{
    use RefreshDatabase;

    private function reader(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('okur');

        return $user;
    }

    private function freeBook(): Book
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        Chapter::factory()->for($book)->create(['order' => 1, 'title' => 'Egemenlik', 'content' => '<p>Locke’a göre egemenlik, bölünemez ve devredilemezdir.</p>']);

        return $book;
    }

    private function payload(Book $book, string $type, array $overrides = []): array
    {
        return array_replace_recursive([
            'type' => $type,
            'noteable_type' => Book::class,
            'noteable_id' => $book->id,
            'quote' => 'egemenlik, bölünemez',
            'anchor' => ['chapter' => 1, 'start' => 13, 'end' => 33, 'prefix' => 'Locke’a göre ', 'suffix' => ' ve devredilemezdir.'],
            'page' => '112',
            'location' => 'Sayfa 112 · Egemenlik',
        ], $overrides);
    }

    public function test_reader_quotes_notes_and_highlights_the_text(): void
    {
        $user = $this->reader();
        $book = $this->freeBook();

        $quote = $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'alinti'))
            ->assertCreated()
            ->assertJson(['type' => 'alinti', 'quote' => 'egemenlik, bölünemez', 'page' => '112', 'anchor' => ['chapter' => 1, 'start' => 13, 'end' => 33]])
            ->json();

        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'not', [
            'content' => 'Hobbes ile karşılaştır.',
            'anchor' => ['start' => 0, 'end' => 12], 'quote' => 'Locke’a göre',
        ]))->assertCreated()->assertJson(['type' => 'not', 'content' => 'Hobbes ile karşılaştır.', 'quote' => 'Locke’a göre']);

        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'fosfor'))->assertCreated();

        // Alıntı Alıntılarım'da (içerik = seçilen metin), not Notlarım'da (içerik = okurun notu).
        $this->assertSame('egemenlik, bölünemez', Note::find($quote['id'])->content);
        $this->assertSame(['alinti', 'not', 'fosfor'], $user->notes()->orderBy('id')->get()->map(fn (Note $note) => $note->type->value)->all());
        $this->actingAs($user)->get(route('panel.alintilarim'))->assertSee('egemenlik, bölünemez');
        $this->actingAs($user)->get(route('panel.notlarim'))->assertSee('Hobbes ile karşılaştır.');
    }

    public function test_note_needs_text_and_marks_need_a_readable_work(): void
    {
        $user = $this->reader();
        $book = $this->freeBook();

        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'not'))
            ->assertUnprocessable()->assertJsonValidationErrors(['content' => 'Notunuzu yazın.']);

        $paid = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 80]);
        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($paid, 'fosfor'))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'fosfor'))->assertUnauthorized();
        $this->assertSame(0, Note::count());
    }

    public function test_the_same_words_cannot_be_quoted_twice(): void
    {
        // Belge: "Okur aynı kelimeyi iki defa alıntılayamaz."
        $user = $this->reader();
        $book = $this->freeBook();
        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'alinti'))->assertCreated();

        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'alinti', ['anchor' => ['start' => 20, 'end' => 40], 'quote' => 'bölünemez ve devredi']))
            ->assertUnprocessable()->assertJson(['message' => 'Bu kısmı zaten alıntıladınız.']);

        // Yan yana (çakışmayan) alıntı ve aynı yere fosfor / not serbest.
        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'alinti', ['anchor' => ['start' => 33, 'end' => 51], 'quote' => ' ve devredilemezdi']))->assertCreated();
        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'fosfor'))->assertCreated();
        // Başka okurun alıntısı engel değil.
        $this->actingAs($this->reader())->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'alinti'))->assertCreated();
    }

    public function test_quota_applies_to_quotes_and_notes_but_not_to_highlights(): void
    {
        $user = $this->reader();
        $book = $this->freeBook();
        Note::factory()->count(10)->for($user)->create(['type' => NoteType::Alinti]);

        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'alinti'))
            ->assertUnprocessable()
            ->assertJson(['premium' => route('abonelik')])
            ->assertJsonPath('message', "Ücretsiz hesapta Alıntı alanında en fazla 10 kayıt tutabilirsiniz. Sınırsız kullanım için Premium'a geçebilirsiniz.");

        Note::factory()->count(12)->for($user)->create(['type' => NoteType::Fosfor]);
        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'fosfor'))->assertCreated();

        // Abonelik sayfalarında fosfor kota olarak geçmiyor.
        $this->actingAs($user)->get(route('panel.aboneligim'))->assertOk()->assertDontSee('Fosfor');
    }

    public function test_owner_edits_the_note_and_removes_marks(): void
    {
        $user = $this->reader();
        $book = $this->freeBook();
        $note = $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'not', ['content' => 'İlk düşünce']))->json();
        $highlight = $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'fosfor'))->json();

        $this->actingAs($user)->patchJson(route('panel.isaretler.guncelle', $note['id']), ['content' => 'Düzeltilmiş düşünce'])
            ->assertOk()->assertJson(['content' => 'Düzeltilmiş düşünce']);
        // Fosforun düzenlenecek metni yok.
        $this->actingAs($user)->patchJson(route('panel.isaretler.guncelle', $highlight['id']), ['content' => 'x'])->assertUnprocessable();

        $other = $this->reader();
        $this->actingAs($other)->patchJson(route('panel.isaretler.guncelle', $note['id']), ['content' => 'x'])->assertForbidden();
        $this->actingAs($other)->deleteJson(route('panel.isaretler.sil', $highlight['id']))->assertForbidden();

        $this->actingAs($user)->deleteJson(route('panel.isaretler.sil', $highlight['id']))->assertNoContent();
        $this->assertSame(['not'], $user->notes()->get()->map(fn (Note $n) => $n->type->value)->all());
    }

    public function test_reading_page_carries_only_the_readers_own_marks_with_a_place_in_the_text(): void
    {
        $user = $this->reader();
        $book = $this->freeBook();
        $this->actingAs($user)->postJson(route('panel.isaretler.ekle'), $this->payload($book, 'alinti'))->assertCreated();
        // Eski, elle eklenmiş (konumsuz) alıntı metinde görünmez; başka okurun işareti hiç gelmez.
        Note::factory()->for($user)->create(['type' => NoteType::Alinti, 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'content' => 'Konumsuz eski alıntı']);
        Note::factory()->for($this->reader())->create(['type' => NoteType::Fosfor, 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'content' => 'Başkasının fosforu', 'anchor' => ['chapter' => 1, 'start' => 0, 'end' => 5]]);

        $this->assertCount(1, $user->readingMarksFor($book));
        $response = $this->actingAs($user)->get(route('kitaplar.oku', $book))->assertOk();
        $this->assertStringContainsString('egemenlik, bölünemez', $response->getContent());
        $this->assertStringNotContainsString('Konumsuz eski alıntı', $response->getContent());
        $this->assertStringNotContainsString('Başkasının fosforu', $response->getContent());
        $response->assertSeeInOrder(['Alıntıla', 'Not Al', 'Fosforla']);

        // Ziyaretçide seçim menüsü yok.
        $this->app['auth']->forgetGuards();
        $this->get(route('kitaplar.oku', $book))->assertOk()->assertDontSee('Fosforla');
    }
}
