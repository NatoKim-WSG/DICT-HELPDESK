<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_email_format(): void
    {
        $response = $this->from(route('login'))->post('/login', [
            'login' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
    }
}
