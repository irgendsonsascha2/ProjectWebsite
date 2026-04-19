<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_with_invite_code(): void
    {
        RegistrationCode::create([
            'code' => 'ABCDEF01',
            'role' => 'community_member',
            'is_used' => false,
            'created_at' => now(),
        ]);

        $response = $this->post('/register', [
            'registration_code' => 'abcdef01',
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertTrue(
            \App\Models\User::query()->where('email', 'newuser@example.com')->where('username', 'newuser')->exists()
        );
    }
}
