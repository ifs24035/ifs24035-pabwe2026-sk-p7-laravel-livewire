<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Livewire\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_login_page(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('Masuk ke Akun Anda')
            ->assertSee('Username atau Email');
    }

    public function test_login_validation(): void
    {
        Livewire::test(Login::class)
            ->set('login', '')
            ->set('password', '')
            ->call('authenticate')
            ->assertHasErrors(['login', 'password']);
    }

    public function test_login_invalid_credentials(): void
    {
        User::factory()->create([
            'username' => 'existing_user',
            'email' => 'user@test.com',
            'password' => Hash::make('correct-password'),
        ]);

        // Wrong password with email
        Livewire::test(Login::class)
            ->set('login', 'user@test.com')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['login']);

        // Wrong password with username
        Livewire::test(Login::class)
            ->set('login', 'existing_user')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['login']);

        $this->assertGuest();
    }

    public function test_member_login_with_email_redirects_to_stocks(): void
    {
        User::factory()->member()->create([
            'username' => 'member1',
            'email' => 'member@test.com',
            'password' => Hash::make('password123'),
        ]);

        Livewire::test(Login::class)
            ->set('login', 'member@test.com')
            ->set('password', 'password123')
            ->set('remember', true)
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('stocks.index'));

        $this->assertAuthenticated();
    }

    public function test_member_login_with_username_redirects_to_stocks(): void
    {
        User::factory()->member()->create([
            'username' => 'member_cool',
            'email' => 'cool@test.com',
            'password' => Hash::make('password123'),
        ]);

        Livewire::test(Login::class)
            ->set('login', 'member_cool')
            ->set('password', 'password123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('stocks.index'));

        $this->assertAuthenticated();
    }

    public function test_admin_login_with_username_redirects_to_admin_users(): void
    {
        User::factory()->admin()->create([
            'username' => 'superadmin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
        ]);

        Livewire::test(Login::class)
            ->set('login', 'superadmin')
            ->set('password', 'password123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users'));

        $this->assertAuthenticated();
    }

    public function test_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_form_has_required_element_ids(): void
    {
        Livewire::test(Login::class)
            ->assertSeeHtml('id="login-username"')
            ->assertSeeHtml('id="login-password"')
            ->assertSeeHtml('id="login-submit"');
    }
}
