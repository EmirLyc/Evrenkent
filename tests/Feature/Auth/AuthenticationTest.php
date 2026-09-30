<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        // Giriş sonrası herkes anasayfaya gelir (bkz. User::redirectPath()).
        $response->assertRedirect('/');
    }

    public function test_users_with_panel_roles_land_on_homepage_after_login(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        foreach (['super_admin', 'dergi_editoru', 'yazar'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect('/');

            $this->post('/logout');
        }
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
