<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\NoteType;
use App\Models\Book;
use App\Models\Note;
use App\Models\User;
use App\Support\NotebookHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Faz H5 ("Okurun Gözünden" — Defterim 1 + Bazı Prensipler): defterler, zengin metin, otomatik
 * kayıt, etiket / bilgi, Alıntı ekle / Not ekle, görsel, ücretsiz hesapta 1 defter ve ~1.000 kelime.
 */
class NotebookTest extends TestCase
{
    use RefreshDatabase;

    private function reader(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('okur');

        return $user;
    }

    private function notebook(User $user, array $attributes = []): Note
    {
        return $user->notes()->create(array_merge(['type' => NoteType::Defter, 'title' => 'Platon ve demokrasi', 'content' => '<p>İlk düşünce.</p>'], $attributes));
    }

    public function test_first_visit_invites_a_new_notebook_and_later_visits_open_the_latest(): void
    {
        $user = $this->reader();

        $this->actingAs($user)->get(route('panel.defterim'))->assertOk()
            ->assertSee('DEFTERLERİM')
            ->assertSee('Yeni Defter')
            ->assertSee('Başlamaya hazır mısın?')
            ->assertDontSee('Kitap, yazar veya konu ara'); // sitenin başlığı yok, odak düzeni

        $response = $this->actingAs($user)->post(route('panel.defterim.yeni'));
        $note = $user->notes()->sole();
        $response->assertRedirect(route('panel.defterim.goster', $note));
        $this->assertSame(['defter', 'Yeni Defter'], [$note->type->value, $note->title]);

        $this->actingAs($user)->get(route('panel.defterim'))->assertRedirect(route('panel.defterim.goster', $note));
        $this->actingAs($user)->get(route('panel.defterim.goster', $note))->assertOk()
            ->assertSee('Alıntı ekle')
            ->assertSee('Not ekle')
            ->assertSee('Bağlantı ekle')
            ->assertSee('etiket ekle')
            ->assertSee('Deftere bilgi ekle')
            ->assertSee('Tam ekran');
    }

    public function test_autosave_keeps_title_subtitle_tags_info_and_clean_rich_text(): void
    {
        Storage::fake('public');
        config(['filesystems.covers_disk' => 'public']);
        $user = $this->reader();
        $note = $this->notebook($user);
        $ownImage = Storage::disk('public')->url('defterler/'.$user->id.'/resim.png');

        $this->actingAs($user)->putJson(route('panel.defterim.kaydet', $note), [
            'title' => '  Egemenlik üzerine ',
            'subtitle' => 'Hobbes ve Locke',
            'content' => '<h1>Başlık</h1><p><strong>Kalın</strong> <u>altı</u> <s>çizik</s> <a href="https://ornek.org">bağ</a></p>'
                .'<ul><li><p>madde</p></li></ul><blockquote><p>alıntı</p></blockquote>'
                .'<script>alert(1)</script><img src="https://baska.site/iz.png"><img src="'.$ownImage.'" alt="harita"><p onclick="x()">son</p>',
            'tags' => ['#siyaset', 'felsefe', 'felsefe'],
            'info' => 'Doktora okumaları',
        ])->assertOk()->assertJsonStructure(['saved_at', 'words']);

        $note->refresh();
        $this->assertSame(['Egemenlik üzerine', 'Hobbes ve Locke', ['siyaset', 'felsefe'], 'Doktora okumaları'], [$note->title, $note->subtitle, $note->tags, $note->info]);
        $this->assertStringContainsString('<h1>Başlık</h1>', $note->content);
        $this->assertStringContainsString('<u>altı</u>', $note->content);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $note->content);
        $this->assertStringContainsString('<img src="'.$ownImage.'" alt="harita"', $note->content);
        $this->assertStringNotContainsString('script', $note->content);
        $this->assertStringNotContainsString('baska.site', $note->content);
        $this->assertStringNotContainsString('onclick', $note->content);

        $this->actingAs($user)->putJson(route('panel.defterim.kaydet', $note), ['title' => ''])->assertJsonValidationErrors(['title' => 'Deftere bir ad verin.']);
    }

    public function test_free_notebook_is_limited_to_about_a_thousand_words(): void
    {
        $user = $this->reader();
        $note = $this->notebook($user);
        $words = fn (int $count) => '<p>'.trim(str_repeat('kelime ', $count)).'</p>';

        $this->actingAs($user)->putJson(route('panel.defterim.kaydet', $note), ['title' => 'T', 'content' => $words(1000)])->assertOk()->assertJson(['words' => 1000]);
        $this->actingAs($user)->putJson(route('panel.defterim.kaydet', $note), ['title' => 'T', 'content' => $words(1001)])
            ->assertUnprocessable()
            ->assertJson(['premium' => route('abonelik'), 'words' => 1001]);
        $this->assertSame(1000, NotebookHtml::wordCount($note->fresh()->content));

        // Sınırın üstünde kalmış eski defter (ör. premium bitti) kısaltılarak kaydedilebilir.
        $old = $this->notebook($user, ['content' => $words(1500)]);
        $this->actingAs($user)->putJson(route('panel.defterim.kaydet', $old), ['title' => 'T', 'content' => $words(1400)])->assertOk();

        $premium = $this->reader(['is_premium' => true, 'premium_until' => now()->addMonth()]);
        $this->actingAs($premium)->putJson(route('panel.defterim.kaydet', $this->notebook($premium)), ['title' => 'T', 'content' => $words(3000)])->assertOk();
    }

    public function test_notebooks_are_private_and_only_notebooks_open_here(): void
    {
        $owner = $this->reader();
        $note = $this->notebook($owner);
        $intruder = $this->reader();

        $this->actingAs($intruder)->get(route('panel.defterim.goster', $note))->assertForbidden();
        $this->actingAs($intruder)->putJson(route('panel.defterim.kaydet', $note), ['title' => 'X'])->assertForbidden();
        $this->actingAs($intruder)->delete(route('panel.defterim.sil', $note))->assertForbidden();

        $book = Book::factory()->create(['status' => ContentStatus::Yayinda]);
        $quote = $owner->notes()->create(['type' => NoteType::Alinti, 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'content' => 'Alıntı']);
        $this->actingAs($owner)->get(route('panel.defterim.goster', $quote))->assertNotFound();
    }

    public function test_list_groups_notebooks_by_date_and_searches_their_text(): void
    {
        $user = $this->reader();
        $this->notebook($user, ['title' => 'Bugünkü defter']);
        $this->notebook($user, ['title' => 'Geçen haftanın defteri'])->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        $this->notebook($user, ['title' => 'Eski defter'])->forceFill(['updated_at' => now()->subMonths(2)])->saveQuietly();

        $this->actingAs($user)->followingRedirects()->get(route('panel.defterim'))->assertOk()
            ->assertSeeInOrder(['Bugün', 'Bugünkü defter', 'Son 7 Gün', 'Geçen haftanın defteri', now()->subMonths(2)->translatedFormat(now()->subMonths(2)->year === now()->year ? 'F' : 'F Y'), 'Eski defter']);

        $this->actingAs($user)->followingRedirects()->get(route('panel.defterim', ['sirala' => 'ad']))
            ->assertSeeInOrder(['Tümü', 'Bugünkü defter', 'Eski defter', 'Geçen haftanın defteri']);
    }

    public function test_quote_and_note_pickers_offer_the_readers_own_marks(): void
    {
        $user = $this->reader();
        $book = Book::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Devlet']);
        $user->notes()->create(['type' => NoteType::Alinti, 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'content' => 'Adalet erdemdir.', 'quote' => 'Adalet erdemdir.', 'page' => '41', 'anchor' => ['chapter' => 1, 'start' => 0, 'end' => 16]]);
        $user->notes()->create(['type' => NoteType::Not, 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'quote' => 'Mağara', 'content' => 'Epistemoloji']);
        $this->reader()->notes()->create(['type' => NoteType::Alinti, 'noteable_type' => Book::class, 'noteable_id' => $book->id, 'content' => 'Başkasının alıntısı']);

        $this->actingAs($user)->getJson(route('panel.defterim.kaynaklar', ['tur' => 'alinti']))->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.quote', 'Adalet erdemdir.')
            ->assertJsonPath('0.work', 'Devlet')
            ->assertJsonPath('0.page', '41');
        $this->actingAs($user)->getJson(route('panel.defterim.kaynaklar', ['tur' => 'not']))->assertJsonPath('0.note', 'Epistemoloji')->assertJsonPath('0.quote', 'Mağara');
        $this->actingAs($user)->getJson(route('panel.defterim.kaynaklar', ['tur' => 'alinti', 'q' => 'yok böyle']))->assertJsonCount(0);
    }

    public function test_image_upload_goes_to_the_notebook_images(): void
    {
        Storage::fake('public');
        config(['filesystems.covers_disk' => 'public']);
        $user = $this->reader();
        $note = $this->notebook($user);

        $url = $this->actingAs($user)->postJson(route('panel.defterim.gorsel', $note), ['image' => UploadedFile::fake()->image('harita.png')])
            ->assertOk()->json('url');
        $this->assertStringContainsString('/defterler/'.$user->id.'/', $url);
        $this->assertCount(1, Storage::disk('public')->files('defterler/'.$user->id));

        $this->actingAs($user)->postJson(route('panel.defterim.gorsel', $note), ['image' => UploadedFile::fake()->create('belge.pdf', 10, 'application/pdf')])
            ->assertJsonValidationErrors('image');
    }

    /** Diske bir defter görseli koyar ve metinde kullanılacak <img> etiketini döner. */
    private function storedImage(User $user, string $name, int $ageInDays = 0): string
    {
        $path = 'defterler/'.$user->id.'/'.$name;
        Storage::disk('public')->put($path, 'resim');
        touch(Storage::disk('public')->path($path), now()->subDays($ageInDays)->getTimestamp());

        return '<img src="'.Storage::disk('public')->url($path).'" alt="">';
    }

    public function test_deleting_a_notebook_deletes_its_images_but_keeps_ones_used_elsewhere(): void
    {
        Storage::fake('public');
        config(['filesystems.covers_disk' => 'public']);
        $user = $this->reader(['is_premium' => true]);
        $other = $this->reader();
        $own = $this->storedImage($user, 'sadece-bunda.png');
        $shared = $this->storedImage($user, 'ikisinde.png');
        $foreign = $this->storedImage($other, 'baskasinin.png');

        $note = $this->notebook($user, ['content' => '<p>A</p>'.$own.$shared]);
        $this->notebook($user, ['title' => 'Kopya', 'content' => '<p>B</p>'.$shared]);
        // Başka okurun görselinin adresi metne elle konsa bile silinmez.
        $note->forceFill(['content' => $note->content.$foreign])->saveQuietly();

        $this->actingAs($user)->delete(route('panel.defterim.sil', $note))->assertRedirect(route('panel.defterim'));

        Storage::disk('public')->assertMissing('defterler/'.$user->id.'/sadece-bunda.png');
        Storage::disk('public')->assertExists('defterler/'.$user->id.'/ikisinde.png');
        Storage::disk('public')->assertExists('defterler/'.$other->id.'/baskasinin.png');
    }

    public function test_scheduled_cleanup_removes_images_no_notebook_uses_any_more(): void
    {
        Storage::fake('public');
        config(['filesystems.covers_disk' => 'public']);
        $user = $this->reader();
        $used = $this->storedImage($user, 'kullaniliyor.png', 3);
        $this->storedImage($user, 'metinden-cikarildi.png', 3);
        $this->storedImage($user, 'yeni-yuklendi.png'); // otomatik kaydı henüz gelmemiş olabilir
        $this->notebook($user, ['content' => '<p>A</p>'.$used]);

        $this->artisan('notebooks:prune-images')->assertSuccessful();

        Storage::disk('public')->assertExists('defterler/'.$user->id.'/kullaniliyor.png');
        Storage::disk('public')->assertMissing('defterler/'.$user->id.'/metinden-cikarildi.png');
        Storage::disk('public')->assertExists('defterler/'.$user->id.'/yeni-yuklendi.png');
    }
}
