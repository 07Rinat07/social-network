<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\FeedbackMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
