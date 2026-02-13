<?php

namespace Tests\Feature;

use App\Models\FeedbackMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedbackFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_send_feedback_message(): void
    {
        $response = $this->postJson('/api/feedback', [
            'name' => 'Guest Reporter',
            'email' => 'guest@example.com',
            'message' => 'I would like to report an issue in the feed.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.status', FeedbackMessage::STATUS_NEW);

        $this->assertDatabaseHas('feedback_messages', [
            'name' => 'Guest Reporter',
            'email' => 'guest@example.com',
            'status' => FeedbackMessage::STATUS_NEW,
            'user_id' => null,
        ]);
    }

    public function test_authenticated_user_feedback_is_bound_to_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'name' => 'Authenticated User',
            'email' => 'member@example.com',
            'message' => 'Need additional moderation dashboard filters.',
        ]);

        $feedbackId = (int) $response->json('data.id');

        $response->assertStatus(201);

        $this->assertDatabaseHas('feedback_messages', [
            'id' => $feedbackId,
            'user_id' => $user->id,
            'status' => FeedbackMessage::STATUS_NEW,
        ]);
    }

    public function test_feedback_validation_errors_are_returned(): void
    {
        $response = $this->postJson('/api/feedback', [
            'name' => '',
            'email' => 'invalid-email',
            'message' => 'bad',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'message']);
    }

    public function test_feedback_rejects_malicious_markup_payload(): void
    {
        $response = $this->postJson('/api/feedback', [
            'name' => 'Guest <script>alert(1)</script>',
            'email' => 'guest@example.com',
            'message' => 'Hello <img src=x onerror=alert(1)> support team',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'message']);

        $this->assertDatabaseCount('feedback_messages', 0);
    }

    public function test_feedback_returns_service_unavailable_when_storage_fails(): void
    {
        $table = 'feedback_messages';
        $backupTable = 'feedback_messages_test_backup';

        if (Schema::hasTable($backupTable)) {
            Schema::drop($backupTable);
        }

        Schema::rename($table, $backupTable);

        try {
            $response = $this->postJson('/api/feedback', [
                'name' => 'Guest Reporter',
                'email' => 'guest@example.com',
                'message' => 'I would like to report an issue in the feed.',
            ]);

            $response
                ->assertStatus(503)
                ->assertJson([
                    'message' => 'Сервис обратной связи временно недоступен. Попробуйте позже.',
                ]);
        } finally {
            if (Schema::hasTable($backupTable) && ! Schema::hasTable($table)) {
                Schema::rename($backupTable, $table);
            }
        }
    }

    public function test_guest_cannot_access_my_feedback_endpoint(): void
    {
        $response = $this->getJson('/api/feedback/my');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_only_own_feedback_messages(): void
    {
        $viewer = User::factory()->create();
        $anotherUser = User::factory()->create();
        Sanctum::actingAs($viewer);

        $firstFeedback = FeedbackMessage::query()->create([
            'user_id' => $viewer->id,
            'name' => 'Viewer 1',
            'email' => 'viewer1@example.com',
            'message' => 'First viewer message.',
            'status' => FeedbackMessage::STATUS_NEW,
            'admin_note' => null,
        ]);

        FeedbackMessage::query()->create([
            'user_id' => $anotherUser->id,
            'name' => 'Another User',
            'email' => 'another@example.com',
            'message' => 'Another user message.',
            'status' => FeedbackMessage::STATUS_RESOLVED,
            'admin_note' => 'Hidden from viewer.',
        ]);

        $secondFeedback = FeedbackMessage::query()->create([
            'user_id' => $viewer->id,
            'name' => 'Viewer 2',
            'email' => 'viewer2@example.com',
            'message' => 'Second viewer message.',
            'status' => FeedbackMessage::STATUS_IN_PROGRESS,
            'admin_note' => 'Processing now.',
        ]);

        $response = $this->getJson('/api/feedback/my?per_page=50');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 2);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $statuses = collect($response->json('data'))->pluck('status', 'id');

        $this->assertEqualsCanonicalizing([$firstFeedback->id, $secondFeedback->id], $ids);
        $this->assertSame(FeedbackMessage::STATUS_NEW, $statuses->get($firstFeedback->id));
        $this->assertSame(FeedbackMessage::STATUS_IN_PROGRESS, $statuses->get($secondFeedback->id));
    }
}
