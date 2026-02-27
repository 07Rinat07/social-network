<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\FeedbackMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAndChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_summary(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/summary');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/dashboard');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_non_admin_cannot_access_admin_dashboard_export(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->get('/api/admin/dashboard/export?format=xls');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_admin_can_access_admin_summary(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/summary');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'users',
                    'admins',
                    'posts',
                    'comments',
                    'media',
                    'feedback_new',
                    'feedback_in_progress',
                    'feedback_resolved',
                    'conversations',
                    'messages',
                    'chat_attachments',
                    'active_blocks',
                ],
            ]);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard?year=' . now()->year);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'selected_year',
                    'available_years',
                    'period' => [
                        'mode',
                        'from',
                        'to',
                    ],
                    'kpis' => [
                        'users_total',
                        'users_new_year',
                        'users_new_period',
                        'subscriptions_total',
                        'subscriptions_year',
                        'subscriptions_period',
                        'subscriptions_previous_year',
                        'subscriptions_change_percent',
                        'subscriptions_avg_month',
                        'period_months',
                        'subscriptions_peak_month' => [
                            'month',
                            'value',
                        ],
                    ],
                    'subscriptions_by_month',
                    'registrations_by_month',
                    'activity_by_month',
                    'preference' => [
                        'method',
                        'total_actions',
                        'leader_key',
                        'items',
                    ],
                    'engagement' => [
                        'active_users_30d',
                        'creators_30d',
                        'chatters_30d',
                        'new_users_30d',
                        'social_active_users_30d',
                        'chat_active_users_30d',
                        'radio_active_users_30d',
                        'iptv_active_users_30d',
                    ],
                    'highlights' => [
                        'subscriptions_peak_month',
                        'activity_peak_month',
                        'activity_peak_value',
                    ],
                ],
            ])
            ->assertJsonCount(12, 'data.subscriptions_by_month')
            ->assertJsonCount(12, 'data.registrations_by_month')
            ->assertJsonCount(12, 'data.activity_by_month');
    }

    public function test_admin_dashboard_supports_custom_date_range_within_selected_year(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $from = now()->startOfYear()->toDateString();
        $to = now()->startOfYear()->addDays(14)->toDateString();

        $response = $this->getJson("/api/admin/dashboard?year=" . now()->year . "&date_from={$from}&date_to={$to}");

        $response
            ->assertOk()
            ->assertJsonPath('data.period.mode', 'custom_range')
            ->assertJsonPath('data.period.from', $from)
            ->assertJsonPath('data.period.to', $to)
            ->assertJsonPath('data.selected_year', now()->year);
    }

    public function test_admin_dashboard_clamps_custom_range_to_selected_year_bounds(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $year = (int) now()->year;
        $from = ($year - 1) . '-12-20';
        $to = ($year + 1) . '-01-15';

        $response = $this->getJson("/api/admin/dashboard?year={$year}&date_from={$from}&date_to={$to}");

        $response
            ->assertOk()
            ->assertJsonPath('data.period.mode', 'custom_range')
            ->assertJsonPath('data.period.from', "{$year}-01-01")
            ->assertJsonPath('data.period.to', "{$year}-12-31")
            ->assertJsonPath('data.period.is_clamped', true);
    }

    public function test_admin_dashboard_rejects_invalid_date_range_payload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $missingTo = $this->getJson('/api/admin/dashboard?date_from=' . now()->toDateString());
        $missingTo
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);

        $invalidOrder = $this->getJson('/api/admin/dashboard?date_from=2026-02-20&date_to=2026-02-10');
        $invalidOrder
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_admin_dashboard_export_rejects_invalid_params(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $invalidFormat = $this->getJson('/api/admin/dashboard/export?format=csv');
        $invalidFormat
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format']);

        $missingTo = $this->getJson('/api/admin/dashboard/export?date_from=2026-01-01&format=json');
        $missingTo
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_admin_dashboard_uses_time_based_method_when_heartbeat_stats_exist(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        Sanctum::actingAs($admin);

        DB::table('user_activity_daily_stats')->insert([
            'user_id' => $member->id,
            'feature' => 'radio',
            'activity_date' => now()->toDateString(),
            'seconds_total' => 3600,
            'heartbeats_count' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/dashboard?year=' . now()->year);

        $response
            ->assertOk()
            ->assertJsonPath('data.preference.method', 'time_minutes')
            ->assertJsonPath('data.preference.leader_key', 'radio')
            ->assertJsonPath('data.engagement.radio_active_users_30d', 1);
    }

    public function test_admin_can_export_dashboard_in_xls_and_json_with_custom_period(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $xlsResponse = $this->get("/api/admin/dashboard/export?format=xls&date_from={$from}&date_to={$to}");

        $xlsResponse->assertOk();
        $this->assertStringContainsString('.xls', (string) $xlsResponse->headers->get('content-disposition'));
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $xlsResponse->headers->get('content-type'));
        $this->assertStringContainsString('Users Activity And Statistics (Selected Period)', $xlsResponse->streamedContent());

        $jsonResponse = $this->get("/api/admin/dashboard/export?format=json&date_from={$from}&date_to={$to}");

        $jsonResponse->assertOk();
        $this->assertStringContainsString('.json', (string) $jsonResponse->headers->get('content-disposition'));
        $this->assertStringContainsString('application/json', (string) $jsonResponse->headers->get('content-type'));

        $decoded = json_decode($jsonResponse->streamedContent(), true);
        $this->assertIsArray($decoded);
        $this->assertSame($from, $decoded['period']['from'] ?? null);
        $this->assertSame($to, $decoded['period']['to'] ?? null);
        $this->assertArrayHasKey('dashboard', $decoded);
        $this->assertArrayHasKey('users', $decoded);
        $this->assertSame('custom_range', $decoded['dashboard']['period']['mode'] ?? null);
        $this->assertSame($from, $decoded['dashboard']['period']['from'] ?? null);
        $this->assertSame($to, $decoded['dashboard']['period']['to'] ?? null);
    }

    public function test_feedback_is_available_for_guests(): void
    {
        $response = $this->postJson('/api/feedback', [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'message' => 'Please improve search and moderation tools.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonFragment([
                'message' => 'Спасибо! Ваше сообщение отправлено администрации.',
            ]);

        $this->assertDatabaseHas('feedback_messages', [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'status' => FeedbackMessage::STATUS_NEW,
        ]);
    }

    public function test_user_can_create_direct_chat_and_send_message(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        Sanctum::actingAs($firstUser);

        $conversationResponse = $this->postJson("/api/chats/direct/{$secondUser->id}");

        $conversationResponse
            ->assertOk()
            ->assertJsonPath('data.type', Conversation::TYPE_DIRECT);

        $conversationId = $conversationResponse->json('data.id');

        $sendResponse = $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Привет! Проверка личного чата.',
        ]);

        $sendResponse
            ->assertStatus(201)
            ->assertJsonPath('data.body', 'Привет! Проверка личного чата.');

        $messagesResponse = $this->getJson("/api/chats/{$conversationId}/messages");

        $messagesResponse
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
