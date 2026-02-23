<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthRoutesTest extends TestCase
{
    public function test_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
