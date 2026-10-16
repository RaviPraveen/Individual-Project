<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_page_is_not_available(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_public_registration_cannot_create_a_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
