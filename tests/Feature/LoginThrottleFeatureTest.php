<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginThrottleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_lockout_returns_retry_after_for_spa_clients(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        foreach (range(1, 10) as $_attempt) {
            $this->postJson('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $lockoutResponse = $this->postJson('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $lockoutResponse
            ->assertStatus(429)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email',
                ],
                'retry_after',
            ])
            ->assertHeader('Retry-After');

        $retryAfter = (int) $lockoutResponse->json('retry_after');
        $this->assertGreaterThan(0, $retryAfter);
    }

    public function test_login_is_available_again_after_lockout_window_expires(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        foreach (range(1, 10) as $_attempt) {
            $this->postJson('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->travel(20)->seconds();

        $this->postJson('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertNoContent();
    }
}
