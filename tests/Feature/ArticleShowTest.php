<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_article_is_visible_to_guests(): void
    {
        $article = Article::factory()->create(['status' => ContentStatus::Yayinda, 'title' => 'Herkese Açık Makale']);

        $this->get(route('makaleler.show', $article))
            ->assertOk()
            ->assertSee('Herkese Açık Makale');
    }

    public function test_draft_article_returns_404_for_guests(): void
    {
        $article = Article::factory()->create(['status' => ContentStatus::Taslak]);

        $this->get(route('makaleler.show', $article))->assertNotFound();
    }

    public function test_author_can_view_own_draft_article(): void
    {
        $author = User::factory()->create();
        $author->assignRole('yazar');
        $article = Article::factory()->for($author, 'author')->create(['status' => ContentStatus::Taslak, 'title' => 'Taslak Makalem']);

        $this->actingAs($author)
            ->get(route('makaleler.show', $article))
            ->assertOk()
            ->assertSee('Taslak Makalem');
    }

    public function test_signed_in_reader_can_mark_the_text_and_guest_is_asked_to_sign_in(): void
    {
        // Faz H3: not / alıntı metinde seçilerek (Alıntıla · Not Al · Fosforla).
        $user = User::factory()->create();
        $user->assignRole('okur');
        $article = Article::factory()->create(['status' => ContentStatus::Yayinda]);

        $this->actingAs($user)
            ->get(route('makaleler.show', $article))
            ->assertOk()
            ->assertSeeInOrder(['Alıntıla', 'Not Al', 'Fosforla']);

        $this->app['auth']->forgetGuards();
        $this->get(route('makaleler.show', $article))
            ->assertOk()
            ->assertDontSee('Fosforla')
            ->assertSee('alıntılamak, not almak ya da fosforlamak için');
    }
}
