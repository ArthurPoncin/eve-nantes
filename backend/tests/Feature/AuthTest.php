<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'arthur',
            'email' => 'arthur@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'username', 'email']])
            ->assertJsonPath('user.email', 'arthur@example.com');

        $this->assertDatabaseHas('users', ['email' => 'arthur@example.com']);
    }

    public function test_login_returns_token_with_valid_credentials(): void
    {
        User::create([
            'username' => 'arthur',
            'email' => 'arthur@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'arthur@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'username', 'email']])
            ->assertJsonPath('user.email', 'arthur@example.com');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::create([
            'username' => 'arthur',
            'email' => 'arthur@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'arthur@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::create([
            'username' => 'arthur',
            'email' => 'arthur@example.com',
            'password' => 'password123',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', 'arthur@example.com');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::create([
            'username' => 'arthur',
            'email' => 'arthur@example.com',
            'password' => 'password123',
        ]);
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
