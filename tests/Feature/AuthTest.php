<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_receive_a_token(): void
    {
        $user = User::factory()->manager()->create(['email' => 'morgan@example.com']);

        $response = $this->postJson('/api/auth/login', ['email' => 'morgan@example.com', 'password' => 'password']);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonMissingPath('user.password');

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$response->json('token')])
            ->assertOk()
            ->assertJsonPath('data.email', 'morgan@example.com');
    }

    public function test_wrong_password_is_rejected_without_revealing_whether_the_email_exists(): void
    {
        User::factory()->create(['email' => 'eli@example.com']);

        $wrongPassword = $this->postJson('/api/auth/login', ['email' => 'eli@example.com', 'password' => 'nope']);
        $unknownEmail = $this->postJson('/api/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope']);

        $wrongPassword->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($wrongPassword->json('errors'), $unknownEmail->json('errors'));
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'eli@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', ['email' => 'eli@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'eli@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/requests')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }
}
