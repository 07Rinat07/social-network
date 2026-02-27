<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRateLimitFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_route_exposes_expected_rate_limit_headers(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '600');
    }

    public function test_authenticated_user_does_not_hit_default_api_rate_limit_on_burst_user_requests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (range(1, 120) as $index) {
            $this->getJson('/api/user')
                ->assertOk()
                ->assertJsonPath('id', $user->id);
        }
    }

    public function test_guest_can_fetch_home_content_in_burst_without_429(): void
    {
        foreach (range(1, 120) as $index) {
            $this->getJson('/api/site/home-content?locale=ru')
                ->assertOk();
        }
    }

    public function test_feedback_endpoint_throttles_guest_spam_requests(): void
    {
        $payload = [
            'name' => 'Rate Limit Probe',
            'email' => 'probe@example.com',
            'message' => 'Please accept this feedback message.',
        ];

        foreach (range(1, 20) as $index) {
            $this->postJson('/api/feedback', $payload)
                ->assertCreated();
        }

        $this->postJson('/api/feedback', $payload)
            ->assertStatus(429);
    }
}
