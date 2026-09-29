<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\NoteType;
use App\Models\Article;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz H4 ("Okurun Gözünden" — Alıntılarıma Dair + Notlarım): Alıntılarım kitap listesi, Benim
 * Seçkim ve üç sütunlu Notlarım.
 */
class QuotesAndNotesPagesTest extends TestCase
{
    use RefreshDatabase;

    private function reader(): User
    {
        $user = User::factory()->create();
        $user->assignRole('okur');

        return $user;
    }

    private function book(string $title, string $author = 'Platon'): Book
    {
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0, 'title' => $title]);
        $book->author->update(['name' => $author]);

        return $book;
    }

    private function mark(User $user, Book|Article $work, NoteType $type, string $quote, array $extra = []): Note
    {
        return $user->notes()->create(array_merge([
            'type' => $type,
            'noteable_type' => $work::class,
            'noteable_id' => $work->id,
            'quote' => $quote,
            'content' => $quote,
            'anchor' => ['chapter' => 1, 'start' => 0, 'end' => mb_strlen($quote), 'prefix' => '', 'suffix' => ''],
            'page' => '1',
        ], $extra));
    }

    public function test_quotes_are_listed_by_book_with_count_and_last_date(): void
    {
        $user = $this->reader();
        $devlet = $this->book('Devlet');
        $prens = $this->book('Prens', 'Niccolò Machiavelli');
        $this->mark($user, $devlet, NoteType::Alinti, 'Adalet, herkesin kendi işini yapmasıdır.')->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->mark($user, $devlet, NoteType::Alinti, 'Eğitim ruhu çevirmektir.')->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->mark($user, $prens, NoteType::Alinti, 'Sevilmekten çok korkulmak daha güvenlidir.');
        // Not ve fosfor Alıntılarım'a girmez.
        $this->mark($user, $this->book('Leviathan'), NoteType::Fosfor, 'Fosforlu satır');

        $this->actingAs($user)->get(route('panel.alintilarim'))->assertOk()
            ->assertSee('Okuduklarından seçtiklerin, senin düşünce evrenin.')
            ->assertSeeInOrder(['Prens', 'Niccolò Machiavelli', '1 alıntı', 'Devlet', 'Platon', '2 alıntı'])
            ->assertDontSee('Leviathan');

        // En çok alıntı; arama kitap adında ve alıntı metninde.
        $this->actingAs($user)->get(route('panel.alintilarim', ['sirala' => 'cok']))->assertSeeInOrder(['Devlet', 'Prens']);
        $this->actingAs($user)->get(route('panel.alintilarim', ['q' => 'EĞİTİM']))->assertSee('Devlet')->assertDontSee('Prens');
        $this->actingAs($user)->get(route('panel.alintilarim', ['q' => 'machiavelli']))->assertSee('Prens')->assertDontSee('>Devlet<', false);
    }

    public function test_my_selection_lists_quotes_in_book_order_with_page_and_a_way_back_to_the_text(): void
    {
        $user = $this->reader();
        $book = $this->book('Devlet');
        $later = $this->mark($user, $book, NoteType::Alinti, 'Sonraki bölümden.', ['anchor' => ['chapter' => 2, 'start' => 10, 'end' => 27], 'page' => '41']);
        $first = $this->mark($user, $book, NoteType::Alinti, 'İlk bölümden.', ['page' => '24']);

        $this->actingAs($user)->get(route('panel.alintilarim.seckim', ['kitap', $book->id]))->assertOk()
            ->assertSee('Alıntılarıma Dön')
            ->assertSee('BENİM SEÇKİM')
            ->assertSeeInOrder(['İlk bölümden.', 's. 24', 'Sonraki bölümden.', 's. 41'])
            ->assertSee(route('kitaplar.oku', [$book, 1]).'#isaret-'.$first->id, false)
            ->assertSee(route('kitaplar.oku', [$book, 2]).'#isaret-'.$later->id, false)
            // Salt okunur: seçim menüsü yok; arama ve Aa var.
            ->assertDontSee('Fosforla')
            ->assertSee('Seçkimde ara…')
            ->assertSee('Okuma Görünümü');

        // Alıntısı olmayan (ya da başkasının) esere seçkim yok.
        $this->actingAs($this->reader())->get(route('panel.alintilarim.seckim', ['kitap', $book->id]))->assertNotFound();
        $this->actingAs($user)->get(route('panel.alintilarim.seckim', ['kitap', 999999]))->assertNotFound();
    }

    public function test_notes_page_lists_works_and_the_selected_works_notes_in_three_views(): void
    {
        $user = $this->reader();
        $devlet = $this->book('Devlet');
        Chapter::factory()->for($devlet)->create(['order' => 1, 'title' => 'Adalet Üzerine']);
        Chapter::factory()->for($devlet)->create(['order' => 2, 'title' => 'Mağara']);
        $siyaset = $this->book('Siyaset', 'Aristoteles');
        $this->mark($user, $devlet, NoteType::Not, 'Adalet, güçlünün işine gelendir.', ['content' => 'Thrasymakhos güç ilişkisi diyor.', 'page' => '47']);
        $this->mark($user, $devlet, NoteType::Not, 'Mağaradaki gölgeler.', ['content' => 'Epistemoloji.', 'anchor' => ['chapter' => 2, 'start' => 0, 'end' => 5]]);
        $this->mark($user, $siyaset, NoteType::Not, 'İnsan siyasal bir hayvandır.', ['content' => 'Doğa ve toplum.'])->forceFill(['created_at' => now()->subYear()])->save();
        // Eski, elle eklenmiş konumsuz not.
        $user->notes()->create(['type' => NoteType::Not, 'noteable_type' => Book::class, 'noteable_id' => $devlet->id, 'content' => 'Konumsuz eski not.']);

        $page = $this->actingAs($user)->get(route('panel.notlarim'))->assertOk()
            ->assertSee('Okurken aldığın notlar ve onlara bağlı metinler.')
            ->assertSeeInOrder(['Devlet', '3 not', 'Siyaset', '1 not'])
            // İlk eser seçili: kaynak metin, sayfa, notum, sayfayı aç.
            ->assertSee('Tüm Notlar (3)')
            ->assertSee('Kaynak Metin')
            ->assertSee('“Adalet, güçlünün işine gelendir.”')
            ->assertSee('s. 47')
            ->assertSee('Thrasymakhos güç ilişkisi diyor.')
            ->assertSee('Sayfayı Aç')
            ->assertSee('Konumsuz eski not.')
            ->assertSee(route('kitaplar.oku', $devlet), false);
        $page->assertDontSee('Doğa ve toplum.');

        $this->actingAs($user)->get(route('panel.notlarim', ['eser' => 'kitap-'.$devlet->id, 'gorunum' => 'bolum']))
            ->assertSeeInOrder(['Adalet Üzerine', 'Thrasymakhos', 'Mağara', 'Epistemoloji.', 'Konumu olmayan notlar', 'Konumsuz eski not.']);

        $this->actingAs($user)->get(route('panel.notlarim', ['eser' => 'kitap-'.$siyaset->id, 'gorunum' => 'tarih']))
            ->assertSee(now()->subYear()->translatedFormat('j F Y'))
            ->assertSee('Doğa ve toplum.');

        // Arama not metninde ve kaynak metinde; tür süzgeci.
        $this->actingAs($user)->get(route('panel.notlarim', ['q' => 'siyasal']))->assertSee('Doğa ve toplum.')->assertDontSee('Thrasymakhos');
        $this->actingAs($user)->get(route('panel.notlarim', ['tur' => 'makale']))->assertSee('Aramaya uyan not yok.');
    }

    public function test_editing_a_note_returns_to_the_same_work_and_view(): void
    {
        $user = $this->reader();
        $book = $this->book('Devlet');
        $note = $this->mark($user, $book, NoteType::Not, 'Kaynak.', ['content' => 'Eski not']);
        $from = route('panel.notlarim', ['eser' => 'kitap-'.$book->id, 'gorunum' => 'tarih']);

        $this->actingAs($user)->from($from)->put(route('panel.notlar.guncelle', $note), ['content' => 'Yeni not'])->assertRedirect($from);
        $this->assertSame('Yeni not', $note->fresh()->content);

        $this->actingAs($user)->from($from)->delete(route('panel.notlar.sil', $note))->assertRedirect($from);
        $this->assertModelMissing($note);
    }
}
