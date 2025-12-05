<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_token(): void
    {
        $payload = [
            'name' => 'Ash Ketchum',
            'email' => 'ash@example.com',
            'password' => 'pikachu123',
            'password_confirmation' => 'pikachu123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
                'token',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', ['email' => $payload['email']]);
    }

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
                'token',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/logout');

        $response->assertOk();
        $response->assertJson(['message' => 'Logged out successfully.']);

        $user->refresh();
        $this->assertCount(0, $user->tokens);
    }

    public function test_protected_route_requires_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $authorizedResponse = $this->withToken($token)->getJson('/api/user');
        $authorizedResponse->assertOk();
        $authorizedResponse->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'created_at', 'updated_at'],
        ]);

        $missingTokenResponse = $this->getJson('/api/user');
        $missingTokenResponse->assertUnauthorized();

        $this->withToken($token)->postJson('/api/logout');

        $unauthorizedResponse = $this->withToken($token)->getJson('/api/user');
        $unauthorizedResponse->assertUnauthorized();
    }
}
