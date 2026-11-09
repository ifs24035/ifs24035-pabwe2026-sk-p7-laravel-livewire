<?php

namespace Tests\Feature\Auth;

use App\Features\Auth\Livewire\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_register_page(): void
    {
        $this->get('/register')
            ->assertStatus(200)
            ->assertSee('Buat Akun Member')
            ->assertSee('Username');
    }

    public function test_register_validation(): void
    {
        Livewire::test(Register::class)
            ->set('name', '')
            ->set('username', '')
            ->set('email', 'not-an-email')
            ->set('password', 'short')
            ->set('password_confirmation', 'mismatch')
            ->call('register')
            ->assertHasErrors(['name', 'username', 'email', 'password']);
    }

    public function test_register_duplicate_email_and_username(): void
    {
        User::factory()->create([
            'username' => 'existing_user',
            'email' => 'duplicate@test.com',
        ]);

        Livewire::test(Register::class)
            ->set('name', 'John Doe')
            ->set('username', 'existing_user')
            ->set('email', 'duplicate@test.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasErrors(['username', 'email']);
    }

    public function test_successful_registration(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'New Member')
            ->set('username', 'new_member')
            ->set('email', 'newmember@test.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('stocks.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'new_member',
            'email' => 'newmember@test.com',
            'role' => 'member',
        ]);
    }
}
