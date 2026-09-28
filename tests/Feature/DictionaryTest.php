<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\DictionaryEntry;
use App\Models\User;
use App\Support\DictionaryDocument;
use App\Support\RichText;
use App\Support\WorkOutline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz G3 — "Sözlüğe Dair": sözlük ayrı yayın türü; "Kavram" satırı madde başlatır (İçindekiler'e
 * girer, sonraki metin bir sonraki kavrama kadar o maddeye ait); diğer yazarlar kelimeyi
 * "Sözlüğe Bağla" ile maddeye bağlar, okur tıklayınca madde sayfasına gider.
 */
class DictionaryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function dictionary(User $author, array $attributes = []): Book
    {
        return Book::factory()->for($author, 'author')->create(['kind' => Book::KIND_SOZLUK, 'status' => ContentStatus::Taslak, ...$attributes]);
    }

    private function save(User $author, Book $book, string $html): void
    {
        $this->actingAs($author)
            ->putJson(route('panel.yayinlarim.kitap.icerik', $book), ['html' => $html])
            ->assertOk();
    }

    /** Yayındaki bir sözlük ve maddesi. */
    private function publishedEntry(array $bookAttributes = [], string $term = 'Egemenlik', string $body = '<p>Devletin iç ve dış bağımsızlığı.</p>'): DictionaryEntry
    {
        $author = $this->user('yazar');
        $book = $this->dictionary($author, $bookAttributes);
        $this->save($author, $book, '<p data-concept="egemenlik1">'.$term.'</p>'.$body);
        $book->update(['status' => ContentStatus::Yayinda, 'published_at' => now()]);
        $this->asGuest();

        return $book->entries()->sole();
    }

    /** actingAs testin sonuna kadar sürüyor; misafir olarak devam et. */
    private function asGuest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_new_publication_popup_leads_to_a_dictionary(): void
    {
        $author = $this->user('yazar');

        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim.yeni', ['tur' => 'sozluk']))
            ->assertOk()
            ->assertSee('Ekle → Kavram', false)
            ->assertSee('name="type" value="sozluk"', false);

        $this->actingAs($author)->post(route('panel.yayinlarim.taslaklarim.store'), [
            'type' => 'sozluk', 'title' => 'Siyaset Bilimi Sözlüğü', 'page_ratio' => '16x24',
        ])->assertSessionHasNoErrors();

        $book = Book::where('title', 'Siyaset Bilimi Sözlüğü')->sole();
        $this->assertTrue($book->isDictionary());
        $this->assertSame('Sözlük', $book->kindLabel());

        // Editörde Kavram (Ekle menüsü ve yazı stili) ve durum çubuğunda madde sayısı.
        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.duzenle', $book))
            ->assertOk()
            ->assertSee("insert('concept')", false)
            ->assertSee("setStyle('concept')", false)
            ->assertSee('Madde:')
            ->assertSee('Sözlüğe Bağla');

        // Taslaklarım kartında tür etiketi.
        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim'))->assertSee('Sözlük');
    }

    public function test_concept_lines_become_entries_until_the_next_concept_or_heading(): void
    {
        $author = $this->user('yazar');
        $book = $this->dictionary($author);

        $this->save($author, $book,
            '<h1>A</h1>'
            .'<p data-concept="k-anarsi">Anarşi</p><p>Devletsizlik hali.</p><p>İkinci paragraf.</p>'
            .'<p data-concept="k-anayasa">Anayasa</p><p>Temel hukuk metni.</p>'
            .'<h2>Notlar</h2><p>Maddeye ait değil.</p>'
            .'<h1>E</h1>'
            .'<p data-concept="k-egemenlik">Egemenlik</p><p>Üstün otorite.</p>'
        );

        $entries = $book->entries()->get();
        $this->assertSame(['Anarşi', 'Anayasa', 'Egemenlik'], $entries->pluck('term')->all());
        $this->assertSame(['anarsi', 'anayasa', 'egemenlik'], $entries->pluck('slug')->all());
        $this->assertSame([1, 1, 2], $entries->pluck('chapter_order')->all());
        $this->assertSame('<p>Devletsizlik hali.</p><p>İkinci paragraf.</p>', $entries[0]->content);
        $this->assertSame('<p>Temel hukuk metni.</p>', $entries[1]->content);
        $this->assertSame('Üstün otorite.', $entries[2]->excerpt);
        $this->assertSame('anarşi', $entries[0]->term_search);
    }

    public function test_entry_identity_survives_renaming_and_removed_concepts_are_deleted(): void
    {
        $author = $this->user('yazar');
        $book = $this->dictionary($author);

        $this->save($author, $book, '<p data-concept="k1">Egemenlik</p><p>Tanım.</p><p data-concept="k2">Devlet</p><p>Tanım.</p>');
        $id = $book->entries()->where('key', 'k1')->value('id');

        $this->save($author, $book, '<p data-concept="k1">Egemenlik Hakkı</p><p>Yeni tanım.</p>');

        $entry = $book->entries()->sole();
        $this->assertSame([$id, 'Egemenlik Hakkı', 'egemenlik-hakki'], [$entry->id, $entry->term, $entry->slug]);
    }

    public function test_missing_and_duplicated_keys_are_completed_deterministically(): void
    {
        $html = DictionaryDocument::assignKeys('<p data-concept="">Egemenlik</p><p data-concept="x1">A</p><p data-concept="x1">B</p><p data-concept="BAD KEY!">İnsan Hakları</p>');

        $this->assertStringContainsString('data-concept="egemenlik"', $html);
        $this->assertStringContainsString('data-concept="x1">A', $html);
        $this->assertStringContainsString('data-concept="x1-2">B', $html);
        $this->assertStringContainsString('data-concept="insan-haklari"', $html);
        $this->assertSame($html, DictionaryDocument::assignKeys($html));

        // Aynı adlı iki kavram: ayrı maddeler, adresler -2 ile.
        $author = $this->user('yazar');
        $book = $this->dictionary($author);
        $this->save($author, $book, '<p data-concept="a">Devlet</p><p>1</p><p data-concept="a">Devlet</p><p>2</p>');
        $this->assertSame(['devlet', 'devlet-2'], $book->entries()->pluck('slug')->all());
        $this->assertStringContainsString('data-concept="a-2"', $book->chapters()->first()->content);
    }

    public function test_regular_books_do_not_produce_entries(): void
    {
        $author = $this->user('yazar');
        $book = Book::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->save($author, $book, '<p data-concept="k1">Egemenlik</p><p>Tanım.</p>');

        $this->assertSame(0, DictionaryEntry::count());
    }

    public function test_concepts_join_the_table_of_contents_and_get_anchors(): void
    {
        $author = $this->user('yazar');
        $book = $this->dictionary($author);
        $this->save($author, $book, '<h1>A</h1><nav data-toc="true"></nav><p data-concept="k-anarsi">Anarşi</p><p>Tanım.</p><h2>Alt</h2><p data-concept="k-ant">Antlaşma</p><p>Tanım.</p>');
        $book->refresh();

        $toc = WorkOutline::for($book)->toc;
        $this->assertSame(['A', 'Anarşi', 'Alt', 'Antlaşma'], array_column($toc, 'text'));
        $this->assertSame([1, 2, 2, 3], array_column($toc, 'level'));
        $this->assertStringEndsWith('#madde-k-anarsi', $toc[1]['url']);

        $html = $book->chapters->first()->renderedContent();
        $this->assertStringContainsString('<p class="rt-concept" id="madde-k-anarsi">Anarşi</p>', $html);
        $this->assertStringContainsString('rt-toc-concept', $html);
    }

    public function test_linked_words_become_clickable_only_while_the_dictionary_is_available(): void
    {
        $entry = $this->publishedEntry();
        $html = '<p>Modern devletin temeli <span data-concept-link="'.$entry->id.'" data-concept-term="Egemenlik">egemenlik</span> kavramıdır.</p>';

        $rendered = RichText::render($html);
        $this->assertStringContainsString('<a href="'.$entry->url().'" class="rt-concept-link"', $rendered);
        $this->assertStringContainsString('>egemenlik</a>', $rendered);

        // Sözlük yayından kalkınca kelime düz metin (yazar kendi önizlemesinde görmeye devam eder).
        $entry->book->update(['status' => ContentStatus::Taslak]);
        $this->assertStringContainsString('temeli egemenlik kavramıdır', RichText::render($html));
        $this->actingAs($entry->book->author);
        $this->assertStringContainsString('rt-concept-link', RichText::render($html));

        // Silinmiş madde: kelime kalır.
        $this->assertStringContainsString('<p>bir kelime</p>', RichText::render('<p><span data-concept-link="999999">bir kelime</span></p>'));
    }

    public function test_article_content_keeps_the_dictionary_link(): void
    {
        $entry = $this->publishedEntry();
        $article = Article::factory()->create(['status' => ContentStatus::Yayinda, 'published_at' => now(), 'content' => '<p><span data-concept-link="'.$entry->id.'">egemenlik</span></p>']);

        $this->assertStringContainsString('data-concept-link="'.$entry->id.'"', $article->content);
        $this->assertStringContainsString($entry->url(), $article->renderedContent());
    }

    public function test_link_search_finds_published_and_own_entries(): void
    {
        $entry = $this->publishedEntry([], 'İnsan Hakları');
        $writer = $this->user('yazar');
        $ownDraft = $this->dictionary($writer, ['title' => 'Benim Taslağım']);
        $this->save($writer, $ownDraft, '<p data-concept="k1">İnsan Onuru</p><p>Tanım.</p>');
        $foreignDraft = $this->dictionary($this->user('yazar'));
        $this->save($foreignDraft->author, $foreignDraft, '<p data-concept="k1">İnsanlık</p><p>Tanım.</p>');

        $terms = $this->actingAs($writer)->getJson(route('panel.sozluk-maddeleri', ['q' => 'insan']))
            ->assertOk()
            ->json('entries.*.term');
        sort($terms);
        $this->assertSame(['İnsan Hakları', 'İnsan Onuru'], $terms);

        // Türkçe büyük harf (İ / I) ve başkasının taslak sözlüğü aranmaz.
        $this->actingAs($writer)->getJson(route('panel.sozluk-maddeleri', ['q' => 'İNSAN HAKLARI']))->assertJsonPath('entries.0.id', $entry->id);
        $this->actingAs($writer)->getJson(route('panel.sozluk-maddeleri', ['q' => 'nlık']))->assertJsonCount(0, 'entries');

        $this->asGuest();
        $this->get(route('panel.sozluk-maddeleri', ['q' => 'insan']))->assertRedirect(route('login'));
    }

    public function test_link_search_falls_back_to_the_word_stem(): void
    {
        $entry = $this->publishedEntry();
        $writer = $this->user('yazar');

        $this->actingAs($writer)->getJson(route('panel.sozluk-maddeleri', ['q' => 'egemenliğin']))
            ->assertJsonPath('entries.0.id', $entry->id)
            ->assertJsonPath('entries.0.dictionary', $entry->book->title);
    }

    public function test_entry_page_shows_the_full_entry_to_readers_of_the_dictionary(): void
    {
        $entry = $this->publishedEntry(['price' => 0], 'Egemenlik', '<p>Devletin iç ve dış bağımsızlığı.</p><p>İkinci paragraf.</p>');

        $this->get($entry->url())
            ->assertOk()
            ->assertSee('Egemenlik')
            ->assertSee('İkinci paragraf.')
            ->assertSee($entry->readUrl(), false);
    }

    public function test_paid_dictionary_entry_shows_a_preview_and_the_product_page(): void
    {
        $entry = $this->publishedEntry(['price' => 150], 'Egemenlik', '<p>Devletin iç ve dış bağımsızlığı.</p><p>İkinci paragraf gizli.</p>');

        $this->get($entry->url())
            ->assertOk()
            ->assertSee('Devletin iç ve dış bağımsızlığı.')
            ->assertSee('Sözlüğü İncele')
            ->assertDontSee('<p>İkinci paragraf gizli.</p>', false);
    }

    public function test_draft_dictionary_entries_are_hidden_from_others(): void
    {
        $author = $this->user('yazar');
        $book = $this->dictionary($author);
        $this->save($author, $book, '<p data-concept="k1">Egemenlik</p><p>Tanım.</p>');
        $entry = $book->entries()->sole();
        $this->asGuest();

        $this->get($entry->url())->assertNotFound();
        $this->actingAs($author)->get($entry->url())->assertOk();
    }

    public function test_dictionaries_have_their_own_catalog_and_stay_out_of_book_shelves(): void
    {
        $entry = $this->publishedEntry(['title' => 'Siyaset Sözlüğü']);
        $book = Book::factory()->create(['title' => 'Bir Roman', 'status' => ContentStatus::Yayinda, 'published_at' => now()]);

        $this->get(route('kitaplar.index'))->assertSee('Bir Roman')->assertDontSee('Siyaset Sözlüğü');
        $this->get(route('sozlukler.index'))
            ->assertOk()
            ->assertSee('Siyaset Sözlüğü')
            ->assertSee('1 sözlük')
            ->assertDontSee('Bir Roman');
        $this->get(route('sozlukler.index', ['q' => 'egemen']))->assertSee('Egemenlik')->assertSee($entry->url(), false);
        $this->get(route('sozlukler.index', ['sozluk' => $entry->book->slug]))->assertSee('Egemenlik');

        // Tanıtım sayfası: maddeler; arama: sözlük ve madde.
        $this->get(route('kitaplar.show', $entry->book))->assertSee('Sözlük Hakkında')->assertSee('Maddeler');
        $this->get(route('arama', ['q' => 'egemenlik']))->assertSee('Sözlük Maddeleri');
        $this->get(route('arama', ['q' => 'Siyaset']))->assertSee('Sözlükler');
    }

    public function test_super_admin_lists_dictionaries_separately(): void
    {
        $admin = $this->user('super_admin');
        $this->dictionary($this->user('yazar'), ['title' => 'Hukuk Sözlüğü']);
        Book::factory()->create(['title' => 'Bir Roman']);

        $this->actingAs($admin)->get(route('panel.adminpanel.kitaplar.index', ['tur' => 'sozluk']))
            ->assertOk()->assertSee('Hukuk Sözlüğü')->assertDontSee('Bir Roman')->assertSee('Yeni Sözlük');
        $this->actingAs($admin)->get(route('panel.adminpanel.kitaplar.index'))
            ->assertOk()->assertSee('Bir Roman')->assertDontSee('Hukuk Sözlüğü');
    }
}
