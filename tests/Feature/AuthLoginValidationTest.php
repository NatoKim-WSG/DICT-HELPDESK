<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_shows_error_for_invalid_credentials(): void
    {
        $response = $this->from(route('login'))->post('/login', [
            'login' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
    }

    public function test_user_can_login_with_full_name(): void
    {
        $user = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan-login@example.com',
            'phone' => '09120001111',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'login' => 'Juan Dela Cruz',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/client/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_full_name_login_is_case_sensitive(): void
    {
        User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan-case-sensitive@example.com',
            'phone' => '09123334444',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'login' => 'juan dela cruz',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}
