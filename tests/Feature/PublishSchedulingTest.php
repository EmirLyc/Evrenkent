<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\MagazineIssueResource\Pages\ListMagazineIssues;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Notifications\ContentPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Faz D (2026-09-27 revizesi): "Şimdi Yayınla / İleri Tarihte Yayınla", dergi sayısı ve
 * makale zamanlama, sayı/makale kuralları, Yakında Çıkacaklar'da geri sayım.
 */
class PublishSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    // --- Kitap: şimdi / ileri tarih ----------------------------------------------

    public function test_approving_a_book_with_publish_now_publishes_it_immediately(): void
    {
        Notification::fake();
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi, 'price' => 50]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), ['price' => 50, 'publish_mode' => 'simdi'])
            ->assertSessionHasNoErrors();

        $book->refresh();
        $this->assertSame(ContentStatus::Yayinda, $book->status);
        $this->assertNotNull($book->published_at);
        $this->assertSame(['onaylandi', 'yayinda'], $book->reviews()->orderBy('id')->pluck('action')->all());
        Notification::assertSentTo($book->author, ContentPublished::class);
    }

    public function test_scheduling_requires_a_future_date(): void
    {
        $admin = $this->superAdmin();
        $book = Book::factory()->create(['status' => ContentStatus::Gonderildi, 'price' => 50]);

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), ['price' => 50, 'publish_mode' => 'ileri'])
            ->assertSessionHasErrors('scheduled_publish_at');

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.kitap.onayla', $book), [
                'price' => 50,
                'publish_mode' => 'ileri',
                'scheduled_publish_at' => now()->subHour()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('scheduled_publish_at');

        $this->assertSame(ContentStatus::Gonderildi, $book->refresh()->status);
    }

    public function test_approval_form_preselects_the_authors_suggested_date(): void
    {
        $book = Book::factory()->create([
            'status' => ContentStatus::Gonderildi,
            'scheduled_publish_at' => now()->addDays(20)->setTime(10, 0),
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('panel.adminpanel.onaylar.kitap.onayla-form', $book))
            ->assertOk()
            ->assertSee('İleri tarihte yayınla')
            ->assertSee($book->scheduled_publish_at->format('Y-m-d\TH:i'));
    }

    // --- Dergi sayısı + makaleler ---------------------------------------------------

    public function test_publishing_an_issue_now_also_publishes_its_approved_articles_only(): void
    {
        Notification::fake();
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi]);
        $approved = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi]);
        $pending = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Incelemede]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.onaylar.dergi.onayla', $issue), ['publish_mode' => 'simdi'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ContentStatus::Yayinda, $issue->refresh()->status);
        $this->assertSame(ContentStatus::Yayinda, $approved->refresh()->status);
        $this->assertSame(ContentStatus::Incelemede, $pending->refresh()->status);
        Notification::assertSentTo($issue->editor, ContentPublished::class);
        Notification::assertSentTo($approved->author, ContentPublished::class);
    }

    public function test_issue_without_approved_articles_cannot_be_approved(): void
    {
        $admin = $this->superAdmin();
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi]);
        Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Incelemede]);

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.onaylar.dergi.onayla', $issue), ['publish_mode' => 'simdi'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler']))
            ->assertOk()
            ->assertSee('Onaylı makale yok');
    }

    public function test_filament_issue_publish_also_cascades_to_articles(): void
    {
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Onaylandi]);
        $article = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListMagazineIssues::class)
            ->callTableAction('publish', $issue)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ContentStatus::Yayinda, $article->refresh()->status);
    }

    public function test_article_in_an_unpublished_issue_is_only_approved_and_waits_for_the_issue(): void
    {
        $admin = $this->superAdmin();
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Gonderildi, 'title' => 'Bekleyen Sayı']);
        $article = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Incelemede]);

        $this->actingAs($admin)
            ->get(route('panel.adminpanel.onaylar.makale.onayla-form', $article))
            ->assertOk()
            ->assertSee('henüz yayında değil')
            ->assertDontSee('İleri tarihte yayınla');

        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.makale.onayla', $article))->assertSessionHasNoErrors();
        $this->assertSame(ContentStatus::Onaylandi, $article->refresh()->status);

        $this->actingAs($admin)
            ->get(route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler']))
            ->assertSee('Sayıyla yayınlanacak');
    }

    public function test_article_approved_after_its_issue_is_live_can_be_scheduled(): void
    {
        $admin = $this->superAdmin();
        $issue = MagazineIssue::factory()->create(['status' => ContentStatus::Yayinda]);
        $article = Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Incelemede]);

        $this->actingAs($admin)
            ->get(route('panel.adminpanel.onaylar.makale.onayla-form', $article))
            ->assertSee('İleri tarihte yayınla');

        $this->actingAs($admin)->post(route('panel.adminpanel.onaylar.makale.onayla', $article), [
            'publish_mode' => 'ileri',
            'scheduled_publish_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame(ContentStatus::Onaylandi, $article->status);
        $this->assertNotNull($article->scheduled_publish_at);
    }

    // --- Zamanlayıcı ---------------------------------------------------------------

    public function test_scheduler_publishes_due_books_issues_with_articles_and_articles_in_live_issues(): void
    {
        Notification::fake();

        $dueBook = Book::factory()->create(['status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => now()->subMinute()]);
        $futureBook = Book::factory()->create(['status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => now()->addDay()]);

        $dueIssue = MagazineIssue::factory()->create(['status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => now()->subMinute()]);
        $cascaded = Article::factory()->for($dueIssue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi]);

        // Zamanlandıktan sonra tek makalesi revizyona düşmüş sayı: boş yayına çıkmamalı.
        $emptiedIssue = MagazineIssue::factory()->create(['status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => now()->subMinute()]);
        Article::factory()->for($emptiedIssue, 'magazineIssue')->create(['status' => ContentStatus::RevizyonIstendi]);

        $liveIssue = MagazineIssue::factory()->create(['status' => ContentStatus::Yayinda]);
        $dueArticle = Article::factory()->for($liveIssue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => now()->subMinute()]);

        Artisan::call('content:publish-scheduled');

        $this->assertSame(ContentStatus::Yayinda, $dueBook->refresh()->status);
        $this->assertSame(ContentStatus::Onaylandi, $futureBook->refresh()->status);
        $this->assertSame(ContentStatus::Yayinda, $dueIssue->refresh()->status);
        $this->assertSame(ContentStatus::Yayinda, $cascaded->refresh()->status);
        $this->assertSame(ContentStatus::Onaylandi, $emptiedIssue->refresh()->status);
        $this->assertSame(ContentStatus::Yayinda, $dueArticle->refresh()->status);

        // Zamanlayıcının yaptığı yayın geçmişe "Sistem" (reviewer yok) olarak düşer.
        $this->assertNull($dueBook->reviews()->latest('id')->first()->reviewer_id);
    }

    public function test_old_command_name_still_works(): void
    {
        $book = Book::factory()->create(['status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => now()->subMinute()]);

        Artisan::call('books:publish-scheduled');

        $this->assertSame(ContentStatus::Yayinda, $book->refresh()->status);
    }

    // --- Yakında Çıkacaklar + geri sayım --------------------------------------------

    public function test_scheduled_issue_appears_in_upcoming_with_countdown_and_has_a_public_teaser(): void
    {
        // Geri sayım metni saniyeye bağlı — zaman saniye başında donduruluyor: DB tarihi saniye
        // hassasiyetinde saklıyor, milisaniyeli bir an "2 saat"i "1 saat 59 dk"ya çeviriyordu.
        $this->freezeSecond();

        $issue = MagazineIssue::factory()->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => now()->addDays(3)->addHours(2),
            'title' => 'Zamanlanmış Sayı',
        ]);
        Article::factory()->for($issue, 'magazineIssue')->create(['status' => ContentStatus::Onaylandi, 'title' => 'Gizli Makale']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Yakında Çıkacaklar')
            ->assertSee('Zamanlanmış Sayı')
            ->assertSee('3 gün 2 saat kaldı');

        $this->get(route('dergiler.index'))->assertOk()->assertSee('Yakında Çıkacak Sayılar')->assertSee('Zamanlanmış Sayı');

        $this->get(route('dergiler.show', $issue))
            ->assertOk()
            ->assertSee('Yakında Çıkacak')
            ->assertSee('yayın tarihinde açılacak')
            ->assertDontSee('Gizli Makale');
    }

    public function test_upcoming_book_card_and_teaser_show_a_countdown(): void
    {
        $this->freezeSecond();

        $book = Book::factory()->create([
            'status' => ContentStatus::Onaylandi,
            'scheduled_publish_at' => now()->addHours(5)->addMinutes(30),
        ]);

        $this->get(route('kitaplar.index', ['raf' => 'yakinda']))->assertOk()->assertSee('5 saat 30 dk kaldı');
        $this->get(route('kitaplar.show', $book))->assertOk()->assertSee('5 saat 30 dk kaldı');
    }
}
