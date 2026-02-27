<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityHeartbeatFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_defaults_elapsed_seconds_when_value_not_provided(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/activity/heartbeat', [
            'feature' => 'social',
            'session_id' => 'social:default-elapsed-001',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.feature', 'social')
            ->assertJsonPath('data.elapsed_seconds', 30)
            ->assertJsonPath('data.ended', false);

        $this->assertDatabaseHas('user_activity_sessions', [
            'user_id' => $user->id,
            'feature' => 'social',
            'session_id' => 'social:default-elapsed-001',
            'total_seconds' => 30,
            'heartbeats_count' => 1,
            'is_active' => true,
        ]);
    }

    public function test_heartbeat_accepts_boundary_elapsed_seconds_values(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/activity/heartbeat', [
            'feature' => 'radio',
            'session_id' => 'radio:boundary-001',
            'elapsed_seconds' => 1,
        ])->assertOk();

        $this->postJson('/api/activity/heartbeat', [
            'feature' => 'radio',
            'session_id' => 'radio:boundary-001',
            'elapsed_seconds' => 300,
        ])->assertOk()->assertJsonPath('data.ended', false);

        $this->assertDatabaseHas('user_activity_sessions', [
            'user_id' => $user->id,
            'feature' => 'radio',
            'session_id' => 'radio:boundary-001',
            'total_seconds' => 301,
            'heartbeats_count' => 2,
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_can_store_activity_heartbeat_and_update_session_and_daily_stats(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'feature' => 'chats',
            'session_id' => 'chats:test-session-001',
            'elapsed_seconds' => 30,
        ];

        $response = $this->postJson('/api/activity/heartbeat', $payload);

        $response
            ->assertOk()
            ->assertJsonPath('data.feature', 'chats')
            ->assertJsonPath('data.session_id', 'chats:test-session-001')
            ->assertJsonPath('data.elapsed_seconds', 30)
            ->assertJsonPath('data.ended', false);

        $this->assertDatabaseHas('user_activity_sessions', [
            'user_id' => $user->id,
            'feature' => 'chats',
            'session_id' => 'chats:test-session-001',
            'total_seconds' => 30,
            'heartbeats_count' => 1,
            'is_active' => true,
        ]);

        $this->assertTrue(
            \App\Models\UserActivityDailyStat::query()
                ->where('user_id', $user->id)
                ->where('feature', 'chats')
                ->whereDate('activity_date', now()->toDateString())
                ->where('seconds_total', 30)
                ->where('heartbeats_count', 1)
                ->exists()
        );

        $secondResponse = $this->postJson('/api/activity/heartbeat', [
            ...$payload,
            'elapsed_seconds' => 25,
            'ended' => true,
        ]);

        $secondResponse
            ->assertOk()
            ->assertJsonPath('data.ended', true);

        $this->assertDatabaseHas('user_activity_sessions', [
            'user_id' => $user->id,
            'feature' => 'chats',
            'session_id' => 'chats:test-session-001',
            'total_seconds' => 55,
            'heartbeats_count' => 2,
            'is_active' => false,
        ]);

        $this->assertTrue(
            \App\Models\UserActivityDailyStat::query()
                ->where('user_id', $user->id)
                ->where('feature', 'chats')
                ->whereDate('activity_date', now()->toDateString())
                ->where('seconds_total', 55)
                ->where('heartbeats_count', 2)
                ->exists()
        );
    }

    public function test_heartbeat_endpoint_rejects_invalid_payload(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/activity/heartbeat', [
            'feature' => 'unknown',
            'session_id' => 'bad session id',
            'elapsed_seconds' => 999,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['feature', 'session_id', 'elapsed_seconds']);
    }

    public function test_guest_cannot_send_activity_heartbeat(): void
    {
        $response = $this->postJson('/api/activity/heartbeat', [
            'feature' => 'social',
            'session_id' => 'social:test-session-guest',
            'elapsed_seconds' => 30,
        ]);

        $response->assertStatus(401);
    }
}
