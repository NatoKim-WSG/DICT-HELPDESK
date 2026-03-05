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

    public function test_account_lockout_blocks_login_after_repeated_failed_attempts(): void
    {
        User::create([
            'name' => 'Lockout User',
            'email' => 'lockout-user@example.com',
            'phone' => '09125556666',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('StrongPass123!'),
            'is_active' => true,
        ]);

        foreach (range(1, 8) as $attempt) {
            $response = $this->from(route('login'))->post(route('login'), [
                'login' => 'lockout-user@example.com',
                'password' => 'invalid-password',
            ], [
                'REMOTE_ADDR' => '198.51.100.'.$attempt,
            ]);

            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors('login');
        }

        $lockedResponse = $this->from(route('login'))->post(route('login'), [
            'login' => 'lockout-user@example.com',
            'password' => 'StrongPass123!',
        ], [
            'REMOTE_ADDR' => '203.0.113.250',
        ]);

        $lockedResponse->assertRedirect(route('login'));
        $lockedResponse->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_user_can_login_with_generated_username(): void
    {
        $user = User::create([
            'name' => 'Unique Username User',
            'email' => 'unique-username@example.com',
            'phone' => '09127778888',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'login' => (string) $user->username,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/client/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_duplicate_full_name_login_requires_email_or_username(): void
    {
        User::create([
            'name' => 'Same Login Name',
            'email' => 'same-login-1@example.com',
            'phone' => '09128889990',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Same Login Name',
            'email' => 'same-login-2@example.com',
            'phone' => '09128889991',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'login' => 'Same Login Name',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'login' => 'Multiple accounts share this name. Please sign in with email or your username.',
        ]);
        $this->assertGuest();
    }
}
