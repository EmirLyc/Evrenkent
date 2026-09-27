<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_only_super_admin_can_access_the_discounts_page(): void
    {
        $this->actingAs($this->superAdmin())->get(route('panel.adminpanel.indirimler.index'))->assertOk();

        $yazar = User::factory()->create();
        $yazar->assignRole('yazar');
        $this->actingAs($yazar)->get(route('panel.adminpanel.indirimler.index'))->assertForbidden();
        $this->actingAs($yazar)->post(route('panel.adminpanel.indirimler.toplu'), ['target' => 'tumu', 'percent' => 50])->assertForbidden();
    }

    public function test_bulk_discount_by_category_only_touches_published_paid_books_in_it(): void
    {
        $roman = Category::factory()->create();
        $siir = Category::factory()->create();

        $inCategory = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 200]);
        $inCategory->categories()->attach($roman);
        $otherCategory = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 200]);
        $otherCategory->categories()->attach($siir);
        $draft = Book::factory()->create(['status' => ContentStatus::Taslak, 'price' => 200]);
        $draft->categories()->attach($roman);
        $free = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 0]);
        $free->categories()->attach($roman);

        $endsAt = now()->addWeek()->startOfMinute();

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.indirimler.toplu'), [
                'target' => 'kategori',
                'category_id' => $roman->id,
                'percent' => 25,
                'ends_at' => $endsAt->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('panel.adminpanel.indirimler.index'))
            ->assertSessionHas('status', '1 kitaba %25 indirim uygulandı.');

        $inCategory->refresh();
        $this->assertSame('150.00', $inCategory->discount_price);
        $this->assertTrue($inCategory->discount_ends_at->equalTo($endsAt));
        $this->assertNull($otherCategory->refresh()->discount_price);
        $this->assertNull($draft->refresh()->discount_price);
        $this->assertNull($free->refresh()->discount_price);
    }

    public function test_bulk_discount_on_selected_books(): void
    {
        $selected = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 100]);
        $notSelected = Book::factory()->create(['status' => ContentStatus::Yayinda, 'price' => 100]);

        $this->actingAs($this->superAdmin())
            ->post(route('panel.adminpanel.indirimler.toplu'), [
                'target' => 'secili',
                'book_ids' => [$selected->id],
                'percent' => 10,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('90.00', $selected->refresh()->discount_price);
        $this->assertNull($selected->discount_ends_at);
        $this->assertNull($notSelected->refresh()->discount_price);
    }

    public function test_bulk_discount_validates_percent_and_target_details(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.indirimler.toplu'), ['target' => 'tumu', 'percent' => 95])
            ->assertSessionHasErrors('percent');

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.indirimler.toplu'), ['target' => 'kategori', 'percent' => 20])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($admin)
            ->post(route('panel.adminpanel.indirimler.toplu'), ['target' => 'tumu', 'percent' => 20, 'ends_at' => now()->subDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_index_lists_discounted_books_with_active_and_expired_status(): void
    {
        Book::factory()->create(['title' => 'Aktif Kampanya', 'price' => 100, 'discount_price' => 80]);
        Book::factory()->create(['title' => 'Biten Kampanya', 'price' => 100, 'discount_price' => 80, 'discount_ends_at' => now()->subDay()]);
        Book::factory()->create(['title' => 'İndirimsiz Kitap', 'price' => 100]);

        $this->actingAs($this->superAdmin())
            ->get(route('panel.adminpanel.indirimler.index'))
            ->assertOk()
            ->assertSee('Aktif Kampanya')
            ->assertSee('Biten Kampanya')
            ->assertSee('Süresi doldu')
            ->assertDontSee('İndirimsiz Kitap</div>', false);
    }

    public function test_removing_a_discount_clears_price_and_end_date(): void
    {
        $book = Book::factory()->create(['price' => 100, 'discount_price' => 80, 'discount_ends_at' => now()->addDay()]);

        $this->actingAs($this->superAdmin())
            ->delete(route('panel.adminpanel.indirimler.kaldir', $book))
            ->assertRedirect(route('panel.adminpanel.indirimler.index'));

        $book->refresh();
        $this->assertNull($book->discount_price);
        $this->assertNull($book->discount_ends_at);
    }
}
