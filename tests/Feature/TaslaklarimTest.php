<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Notifications\ContentMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Faz G1 — "Yazarın Gözünden" revizesi (dosyalar/1-)Yazarın Gözünden): Yayın Yönetimi 3 başlık,
 * Taslaklarım'da durum sekmeleri + kartlar, iki kademeli silme (çöp kutusu), detay / yayın
 * süreci ve esere bağlı yazışma (Sohbet / Mesajlar).
 */
class TaslaklarimTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function book(User $author, ContentStatus $status, array $attributes = []): Book
    {
        return Book::factory()->for($author, 'author')->create(['status' => $status, ...$attributes]);
    }

    public function test_sidebar_has_the_three_new_headings_and_old_pages_redirect(): void
    {
        $author = $this->user('yazar');

        $response = $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim'))->assertOk();
        $response->assertSee(route('panel.yayinlarim.yayinlananlar'), false)
            ->assertSee(route('panel.yayinlarim.istatistiklerim'), false)
            ->assertDontSee('Gönderilenler')
            ->assertDontSee('Geri Dönenler')
            ->assertSee('Yeni Yayın Oluştur')
            ->assertSee('Ne yayınlamak istersiniz?')
            ->assertSee('Kitap / Dergi')
            ->assertSee('Sözlük');

        $this->actingAs($author)->get(route('panel.yayinlarim.index'))->assertRedirect(route('panel.yayinlarim.taslaklarim'));
        $this->actingAs($author)->get(route('panel.yayinlarim.gonderilenler'))->assertRedirect(route('panel.yayinlarim.taslaklarim', ['durum' => 'incelemede']));
        $this->actingAs($author)->get(route('panel.yayinlarim.geri-donenler'))->assertRedirect(route('panel.yayinlarim.taslaklarim', ['durum' => 'duzeltme']));
        $this->assertSame('/panel/yayinlarim/taslaklarim', $author->redirectPath());
    }

    public function test_status_tabs_group_statuses_the_way_the_author_sees_them(): void
    {
        $author = $this->user('yazar');
        $this->book($author, ContentStatus::Taslak, ['title' => 'Taslak Kitap']);
        $this->book($author, ContentStatus::Gonderildi, ['title' => 'Gönderilen Kitap']);
        Article::factory()->for($author, 'author')->for(MagazineIssue::factory(), 'magazineIssue')->create(['status' => ContentStatus::Incelemede, 'title' => 'İncelenen Yazı']);
        $this->book($author, ContentStatus::RevizyonIstendi, ['title' => 'Düzeltilecek Kitap']);
        $this->book($author, ContentStatus::Onaylandi, ['title' => 'Kabul Edilen Kitap']);
        $this->book($author, ContentStatus::Reddedildi, ['title' => 'Reddedilen Kitap']);
        $this->book($author, ContentStatus::Yayinda, ['title' => 'Yayındaki Kitap']);
        $this->book($this->user('yazar'), ContentStatus::Taslak, ['title' => 'Başkasının Taslağı']);

        $all = $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim'))->assertOk();
        $all->assertSee('Reddedilen Kitap')->assertDontSee('Yayındaki Kitap')->assertDontSee('Başkasının Taslağı');
        // Sekme sayaçları: Tümü 6, Taslak 1, İncelemede 2, Düzeltme 1, Kabul 1.
        $this->assertSame([6, 1, 2, 1, 1], array_values($all->viewData('counts')->all()));

        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['durum' => 'incelemede']))
            ->assertSee('Gönderilen Kitap')->assertSee('İncelenen Yazı')->assertDontSee('Taslak Kitap');
        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['durum' => 'duzeltme']))
            ->assertSee('Düzeltilecek Kitap')->assertSee('Düzeltme İstendi')->assertDontSee('Kabul Edilen Kitap');
        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['durum' => 'kabul']))
            ->assertSee('Kabul Edilen Kitap')->assertSee('Kabul Edildi')->assertDontSee('Reddedilen Kitap');

        $this->actingAs($author)->get(route('panel.yayinlarim.yayinlananlar'))
            ->assertSee('Yayındaki Kitap')->assertDontSee('Taslak Kitap');
    }

    public function test_cards_show_the_mockup_buttons_for_each_status(): void
    {
        $author = $this->user('yazar');
        $draft = $this->book($author, ContentStatus::Taslak);
        Chapter::factory()->for($draft)->create(['order' => 7, 'title' => 'Sessizliğin Dili']);
        $this->book($author, ContentStatus::Gonderildi);

        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['durum' => 'taslak']))
            ->assertSee('Bölüm 7 – Sessizliğin Dili')
            ->assertSee('Son düzenleme: Bugün')
            ->assertSee('Önizle')
            ->assertSee(route('panel.yayinlarim.kitap.duzenle', $draft), false);

        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['durum' => 'incelemede']))
            ->assertSee('Gönderimi Gör')->assertSee('Sohbet');

        $this->book($author, ContentStatus::RevizyonIstendi);
        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['durum' => 'duzeltme']))
            ->assertSee('Düzenlemeye Devam Et')->assertSee('Mesajlar');

        $this->book($author, ContentStatus::Onaylandi);
        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['durum' => 'kabul']))
            ->assertSee('Detayları Gör')->assertSee('Yayın Sürecini Takip Et');
    }

    public function test_search_sort_list_view_and_pagination(): void
    {
        $author = $this->user('yazar');
        $felsefe = Category::factory()->create(['name' => 'Felsefe']);
        $this->book($author, ContentStatus::Taslak, ['title' => 'Zaman Üzerine'])->categories()->attach($felsefe);
        Article::factory()->for($author, 'author')->for(MagazineIssue::factory(), 'magazineIssue')->create(['title' => 'Adalet Notları']);
        foreach (range(1, 5) as $i) {
            $this->book($author, ContentStatus::Taslak, ['title' => "Deneme $i"]);
        }

        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['q' => 'felsefe']))
            ->assertSee('Zaman Üzerine')->assertDontSee('Deneme 1');
        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['q' => 'dergi yazısı']))
            ->assertSee('Adalet Notları')->assertDontSee('Zaman Üzerine');

        // 7 eser, sayfada 6: başlığa göre sıralı ikinci sayfada sadece "Zaman Üzerine".
        $page2 = $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['sirala' => 'baslik', 'page' => 2]))->assertOk();
        $this->assertSame(['Zaman Üzerine'], $page2->viewData('items')->pluck('title')->all());
        $page2->assertSee('Toplam 7 yayın');

        $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim', ['gorunum' => 'liste']))
            ->assertOk()->assertSee('flex-row items-stretch', false);
    }

    public function test_deleting_moves_to_the_trash_and_it_can_be_restored_or_deleted_for_good(): void
    {
        $author = $this->user('yazar');
        $book = $this->book($author, ContentStatus::Taslak, ['title' => 'Yanlışlıkla Silinen']);

        $this->actingAs($author)->delete(route('panel.yayinlarim.kitap.sil', $book))->assertRedirect();
        $this->assertSoftDeleted($book);

        $list = $this->actingAs($author)->get(route('panel.yayinlarim.taslaklarim'))
            ->assertSee('"Yanlışlıkla Silinen" çöp kutusuna taşındı.')
            ->assertSee('Çöp Kutusu (1)');
        $this->assertTrue($list->viewData('items')->isEmpty());
        $this->actingAs($author)->get(route('panel.yayinlarim.cop-kutusu'))->assertOk()->assertSee('Yanlışlıkla Silinen');

        // Başka bir yazar geri alamaz / silemez.
        $intruder = $this->user('yazar');
        $this->actingAs($intruder)->post(route('panel.yayinlarim.kitap.geri-al', $book))->assertForbidden();
        $this->actingAs($intruder)->delete(route('panel.yayinlarim.kitap.kalici-sil', $book))->assertForbidden();

        $this->actingAs($author)->post(route('panel.yayinlarim.kitap.geri-al', $book))->assertRedirect(route('panel.yayinlarim.cop-kutusu'));
        $this->assertNotSoftDeleted($book);

        $book->delete();
        $this->actingAs($author)->delete(route('panel.yayinlarim.kitap.kalici-sil', $book))->assertRedirect();
        $this->assertModelMissing($book);
    }

    public function test_articles_also_go_through_the_trash(): void
    {
        $author = $this->user('yazar');
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak]);

        $this->actingAs($author)->delete(route('panel.yayinlarim.makale.sil', $article));
        $this->assertSoftDeleted($article);

        $this->actingAs($author)->post(route('panel.yayinlarim.makale.geri-al', $article));
        $this->assertNotSoftDeleted($article);
    }

    public function test_detail_page_tracks_the_publishing_process(): void
    {
        $author = $this->user('yazar');
        $book = $this->book($author, ContentStatus::Onaylandi, ['scheduled_publish_at' => now()->addDays(3)]);
        $book->reviews()->create(['reviewer_id' => $author->id, 'action' => 'gonderildi']);
        $book->reviews()->create(['reviewer_id' => $this->user('super_admin')->id, 'action' => 'onaylandi']);

        $this->actingAs($author)->get(route('panel.yayinlarim.kitap.detay', $book))
            ->assertOk()
            ->assertSee('Yayın Süreci')
            ->assertSee('Onaya gönderildi')
            ->assertSee('Kabul edildi')
            ->assertSee('gün')
            ->assertSee('kaldı');

        $this->actingAs($this->user('yazar'))->get(route('panel.yayinlarim.kitap.detay', $book))->assertForbidden();
    }

    public function test_author_and_super_admin_can_message_each_other_about_a_book(): void
    {
        Notification::fake();
        $author = $this->user('yazar');
        $admin = $this->user('super_admin');
        $book = $this->book($author, ContentStatus::RevizyonIstendi);
        $book->reviews()->create(['reviewer_id' => $admin->id, 'action' => 'revizyon_istendi', 'note' => 'Kaynakçayı tamamlayın.']);

        $this->actingAs($author)->get(route('panel.mesajlar.kitap', $book))
            ->assertOk()
            ->assertSee('Düzeltme istendi')
            ->assertSee('Kaynakçayı tamamlayın.');

        $this->actingAs($author)->post(route('panel.mesajlar.kitap.gonder', $book), ['body' => 'Hangi bölümler için?'])
            ->assertRedirect(route('panel.mesajlar.kitap', $book).'#son');
        Notification::assertSentTo($admin, ContentMessageReceived::class);
        Notification::assertNotSentTo($author, ContentMessageReceived::class);

        $this->actingAs($admin)->get(route('panel.mesajlar.kitap', $book))->assertOk()->assertSee('Hangi bölümler için?')->assertSee('Süper Admin Paneli');
        $this->actingAs($admin)->post(route('panel.mesajlar.kitap.gonder', $book), ['body' => '3. ve 5. bölümler.']);
        Notification::assertSentTo($author, ContentMessageReceived::class);

        $this->actingAs($author)->post(route('panel.mesajlar.kitap.gonder', $book), ['body' => ''])->assertSessionHasErrors('body');
        $this->assertSame(2, $book->messages()->count());
    }

    public function test_the_issue_editor_joins_article_conversations_but_outsiders_cannot(): void
    {
        Notification::fake();
        $author = $this->user('yazar');
        $editor = $this->user('dergi_editoru');
        $issue = MagazineIssue::factory()->for($editor, 'editor')->create();
        $article = Article::factory()->for($author, 'author')->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Gonderildi]);

        $this->actingAs($editor)->get(route('panel.dergi.makale-havuzu.goster', $article))->assertSee('Yazarla Mesajlaş');
        $this->actingAs($editor)->post(route('panel.mesajlar.makale.gonder', $article), ['body' => 'Özeti kısaltır mısınız?'])->assertRedirect();
        Notification::assertSentTo($author, ContentMessageReceived::class);

        $this->actingAs($this->user('dergi_editoru'))->get(route('panel.mesajlar.makale', $article))->assertForbidden();
        $this->actingAs($this->user('yazar'))->get(route('panel.mesajlar.makale', $article))->assertForbidden();
        $this->actingAs($this->user('okur'))->post(route('panel.mesajlar.makale.gonder', $article), ['body' => 'x'])->assertForbidden();

        // Dergi editörü kitap yazışmalarına katılamaz.
        $this->actingAs($editor)->get(route('panel.mesajlar.kitap', $this->book($author, ContentStatus::Gonderildi)))->assertForbidden();
    }
}
