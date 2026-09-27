<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026-09-27 revizesi: ziyaretçi de giriş yapmış okurla aynı sidebar'ı görür, kişisel
 * bir menüye tıklayınca giriş ekranına düşer ve giriş sonrası o sayfaya döner.
 */
class GuestSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_same_reader_sidebar_with_login_actions(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kişisel Kütüphanem')
            ->assertSee('Çalışma Alanım')
            ->assertSee(route('panel.index'), false)
            ->assertSee(route('panel.favorilerim'), false)
            ->assertSee(route('panel.notlarim'), false)
            ->assertDontSee('Çıkış Yap')
            // Rol grupları ziyaretçide görünmez.
            ->assertDontSee('Yayın Yönetimi')
            ->assertDontSee('Dergi Yönetimi');
    }

    public function test_guest_clicking_a_personal_link_lands_on_login_and_returns_there_after_login(): void
    {
        $this->get(route('panel.favorilerim'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $user->assignRole('okur');

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('panel.favorilerim'));
    }

    public function test_logged_in_reader_still_sees_logout_instead_of_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('okur');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Çıkış Yap');
    }
}
