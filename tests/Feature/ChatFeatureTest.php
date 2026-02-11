<?php

namespace Tests\Feature;

use App\Events\ConversationMessageSent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_index_creates_single_global_chat_and_returns_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $firstResponse = $this->getJson('/api/chats');
        $secondResponse = $this->getJson('/api/chats');

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversations', [
            'type' => Conversation::TYPE_GLOBAL,
            'title' => 'Общий чат',
        ]);

        $this->assertSame(Conversation::TYPE_GLOBAL, $firstResponse->json('data.0.type'));
    }

    public function test_chats_users_endpoint_excludes_current_user_and_supports_search(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        User::factory()->create(['name' => 'Alice Johnson', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Bob Stone', 'email' => 'bob@example.com']);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/chats/users?search=Alice&per_page=50');

        $response->assertOk();

        $users = collect($response->json('data'));

        $this->assertTrue($users->every(fn (array $item) => (int) $item['id'] !== $viewer->id));
        $this->assertCount(1, $users);
        $this->assertSame('Alice Johnson', $users->first()['name']);
    }

    public function test_user_cannot_create_direct_chat_with_self(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/chats/direct/{$user->id}");

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot create a personal chat with yourself.',
            ]);
    }

    public function test_direct_chat_creation_is_idempotent_for_same_pair(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($viewer);

        $firstResponse = $this->postJson("/api/chats/direct/{$target->id}");
        $secondResponse = $this->postJson("/api/chats/direct/{$target->id}");

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $firstId = (int) $firstResponse->json('data.id');
        $secondId = (int) $secondResponse->json('data.id');

        $this->assertSame($firstId, $secondId);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversations', [
            'id' => $firstId,
            'type' => Conversation::TYPE_DIRECT,
        ]);

        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_non_participant_cannot_read_or_send_messages_in_direct_chat(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $intruder = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        Sanctum::actingAs($intruder);

        $showResponse = $this->getJson("/api/chats/{$conversationId}");
        $messagesResponse = $this->getJson("/api/chats/{$conversationId}/messages");
        $storeResponse = $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Unauthorized message',
        ]);

        $showResponse->assertStatus(403);
        $messagesResponse->assertStatus(403);
        $storeResponse->assertStatus(403);
    }

    public function test_any_authenticated_user_can_send_message_to_global_chat(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $this->getJson('/api/chats');

        $globalConversation = Conversation::query()->where('type', Conversation::TYPE_GLOBAL)->firstOrFail();

        Sanctum::actingAs($secondUser);

        $sendResponse = $this->postJson("/api/chats/{$globalConversation->id}/messages", [
            'body' => 'Hello from the second user',
        ]);

        $sendResponse
            ->assertStatus(201)
            ->assertJsonPath('data.user.id', $secondUser->id);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $globalConversation->id,
            'user_id' => $secondUser->id,
            'body' => 'Hello from the second user',
        ]);
    }

    public function test_chat_messages_are_returned_in_chronological_order_after_pagination_transform(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $firstUser->id,
            'body' => 'First',
        ]);

        ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $secondUser->id,
            'body' => 'Second',
        ]);

        $response = $this->getJson("/api/chats/{$conversationId}/messages?per_page=2");

        $response
            ->assertOk()
            ->assertJsonPath('data.0.body', 'First')
            ->assertJsonPath('data.1.body', 'Second');
    }

    public function test_offline_recipient_gets_unread_badges_and_messages_persisted_until_login(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Sanctum::actingAs($sender);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$recipient->id}")->json('data.id');

        $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Первое оффлайн сообщение',
        ])->assertStatus(201);

        $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Второе оффлайн сообщение',
        ])->assertStatus(201);

        Sanctum::actingAs($recipient);

        $indexResponse = $this->getJson('/api/chats');
        $indexResponse
            ->assertOk()
            ->assertJsonPath('meta.total_unread', 2);

        $conversationRow = collect($indexResponse->json('data'))
            ->firstWhere('id', $conversationId);

        $this->assertNotNull($conversationRow);
        $this->assertSame(2, (int) ($conversationRow['unread_count'] ?? 0));

        $summaryBeforeRead = $this->getJson('/api/chats/unread-summary');
        $summaryBeforeRead
            ->assertOk()
            ->assertJsonPath('data.total_unread', 2);

        $messagesResponse = $this->getJson("/api/chats/{$conversationId}/messages");
        $messagesResponse
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $summaryAfterRead = $this->getJson('/api/chats/unread-summary');
        $summaryAfterRead
            ->assertOk()
            ->assertJsonPath('data.total_unread', 0);
    }

    public function test_chat_message_requires_non_empty_body(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => '',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_user_can_mark_conversation_as_read_via_endpoint(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Unread before mark read',
        ])->assertStatus(201);

        Sanctum::actingAs($secondUser);

        $this->getJson('/api/chats/unread-summary')
            ->assertOk()
            ->assertJsonPath('data.total_unread', 1);

        $markReadResponse = $this->postJson("/api/chats/{$conversationId}/read");
        $markReadResponse
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversationId)
            ->assertJsonPath('data.unread_count', 0);

        $this->getJson('/api/chats/unread-summary')
            ->assertOk()
            ->assertJsonPath('data.total_unread', 0);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $secondUser->id,
        ]);
    }

    public function test_user_can_block_another_user_temporarily_and_unblock(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($viewer);

        $blockResponse = $this->postJson("/api/users/{$target->id}/block", [
            'mode' => 'temporary',
            'duration_minutes' => 90,
            'reason' => 'Cooldown period.',
        ]);

        $blockResponse
            ->assertOk()
            ->assertJsonPath('data.blocked_user_id', $target->id)
            ->assertJsonPath('data.is_permanent', false);

        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $viewer->id,
            'blocked_user_id' => $target->id,
            'reason' => 'Cooldown period.',
        ]);

        $listResponse = $this->getJson('/api/users/blocks');

        $listResponse
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.blocked_user_id', $target->id);

        $unblockResponse = $this->deleteJson("/api/users/{$target->id}/block");

        $unblockResponse->assertOk();

        $this->assertDatabaseMissing('user_blocks', [
            'blocker_id' => $viewer->id,
            'blocked_user_id' => $target->id,
        ]);
    }

    public function test_active_block_prevents_direct_chat_creation(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        UserBlock::query()->create([
            'blocker_id' => $firstUser->id,
            'blocked_user_id' => $secondUser->id,
            'expires_at' => null,
            'reason' => 'Permanent block.',
        ]);

        Sanctum::actingAs($firstUser);

        $response = $this->postJson("/api/chats/direct/{$secondUser->id}");

        $response
            ->assertStatus(423)
            ->assertJsonPath('data.blocked_by_user_id', $firstUser->id)
            ->assertJsonPath('data.blocked_user_id', $secondUser->id);
    }

    public function test_active_block_prevents_sending_message_in_existing_direct_chat(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        UserBlock::query()->create([
            'blocker_id' => $secondUser->id,
            'blocked_user_id' => $firstUser->id,
            'expires_at' => now()->addDay(),
            'reason' => 'Temporary block.',
        ]);

        $response = $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Message should not be delivered.',
        ]);

        $response
            ->assertStatus(423)
            ->assertJsonPath('data.blocked_by_user_id', $secondUser->id)
            ->assertJsonPath('data.blocked_user_id', $firstUser->id);
    }

    public function test_user_can_send_message_with_gif_photo_and_video_attachments_without_text(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->post("/api/chats/{$conversationId}/messages", [
            'files' => [
                UploadedFile::fake()->image('photo.jpg', 900, 700),
                UploadedFile::fake()->create('funny.gif', 200, 'image/gif'),
                UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.body', '')
            ->assertJsonCount(3, 'data.attachments');

        $messageId = (int) $response->json('data.id');

        $this->assertDatabaseHas('conversation_messages', [
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'body' => null,
        ]);

        $attachments = ConversationMessageAttachment::query()
            ->where('conversation_message_id', $messageId)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $attachments);
        $this->assertSame([
            ConversationMessageAttachment::TYPE_IMAGE,
            ConversationMessageAttachment::TYPE_GIF,
            ConversationMessageAttachment::TYPE_VIDEO,
        ], $attachments->pluck('type')->all());
        $this->assertSame(
            route('media.chat-attachments.show', ['attachment' => $attachments->first()->id]),
            (string) $response->json('data.attachments.0.url')
        );

        foreach ($attachments as $attachment) {
            Storage::disk($attachment->storage_disk ?: 'public')->assertExists($attachment->path);
        }
    }

    public function test_user_can_send_voice_message_attachment_without_text(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->post("/api/chats/{$conversationId}/messages", [
            'files' => [
                UploadedFile::fake()->create('voice.webm', 256, 'audio/webm'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.body', '')
            ->assertJsonPath('data.attachments.0.type', ConversationMessageAttachment::TYPE_AUDIO);

        $messageId = (int) $response->json('data.id');

        $attachment = ConversationMessageAttachment::query()
            ->where('conversation_message_id', $messageId)
            ->firstOrFail();

        $this->assertSame(ConversationMessageAttachment::TYPE_AUDIO, $attachment->type);
        Storage::disk($attachment->storage_disk ?: 'public')->assertExists($attachment->path);
    }

    public function test_user_can_send_voice_message_with_octet_stream_recording_payload(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->post("/api/chats/{$conversationId}/messages", [
            'files' => [
                UploadedFile::fake()->create('voice.weba', 256, 'application/octet-stream'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.body', '')
            ->assertJsonPath('data.attachments.0.type', ConversationMessageAttachment::TYPE_AUDIO);

        $messageId = (int) $response->json('data.id');

        $attachment = ConversationMessageAttachment::query()
            ->where('conversation_message_id', $messageId)
            ->firstOrFail();

        $this->assertSame(ConversationMessageAttachment::TYPE_AUDIO, $attachment->type);
        Storage::disk($attachment->storage_disk ?: 'public')->assertExists($attachment->path);
    }

    public function test_user_can_send_voice_webm_with_octet_stream_and_keep_audio_type(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->post("/api/chats/{$conversationId}/messages", [
            'files' => [
                UploadedFile::fake()->create('voice-123.webm', 256, 'application/octet-stream'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.body', '')
            ->assertJsonPath('data.attachments.0.type', ConversationMessageAttachment::TYPE_AUDIO);

        $messageId = (int) $response->json('data.id');

        $attachment = ConversationMessageAttachment::query()
            ->where('conversation_message_id', $messageId)
            ->firstOrFail();

        $this->assertSame(ConversationMessageAttachment::TYPE_AUDIO, $attachment->type);
        Storage::disk($attachment->storage_disk ?: 'public')->assertExists($attachment->path);
    }

    public function test_chat_attachment_from_local_disk_is_exposed_via_api_media_endpoint_for_participant(): void
    {
        Storage::fake('local');

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Sanctum::actingAs($sender);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$recipient->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $sender->id,
            'body' => null,
        ]);

        Storage::disk('local')->put('chat/audio/local-legacy.webm', 'local-legacy-audio-content');

        $attachment = ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $message->id,
            'path' => 'chat/audio/local-legacy.webm',
            'storage_disk' => 'local',
            'type' => ConversationMessageAttachment::TYPE_AUDIO,
            'mime_type' => 'audio/webm',
            'size' => 128,
            'original_name' => 'local-legacy.webm',
        ]);

        Sanctum::actingAs($recipient);

        $messagesResponse = $this->getJson("/api/chats/{$conversationId}/messages?per_page=20");

        $messagesResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $message->id)
            ->assertJsonPath(
                'data.0.attachments.0.url',
                route('media.chat-attachments.show', ['attachment' => $attachment->id])
            );

        $this->get(route('media.chat-attachments.show', ['attachment' => $attachment->id]))
            ->assertOk();
    }

    public function test_user_cannot_send_empty_attachment_file(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->post("/api/chats/{$conversationId}/messages", [
            'files' => [
                UploadedFile::fake()->create('empty.weba', 0, 'application/octet-stream'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_chat_users_endpoint_marks_block_flags_for_viewer(): void
    {
        $viewer = User::factory()->create();
        $blockedByViewer = User::factory()->create();
        $blockedViewer = User::factory()->create();

        UserBlock::query()->create([
            'blocker_id' => $viewer->id,
            'blocked_user_id' => $blockedByViewer->id,
            'expires_at' => null,
        ]);

        UserBlock::query()->create([
            'blocker_id' => $blockedViewer->id,
            'blocked_user_id' => $viewer->id,
            'expires_at' => null,
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/chats/users?per_page=50');

        $response->assertOk();

        $items = collect($response->json('data'));
        $first = $items->firstWhere('id', $blockedByViewer->id);
        $second = $items->firstWhere('id', $blockedViewer->id);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertTrue((bool) $first['is_blocked_by_me']);
        $this->assertFalse((bool) $first['has_blocked_me']);
        $this->assertFalse((bool) $second['is_blocked_by_me']);
        $this->assertTrue((bool) $second['has_blocked_me']);
    }

    public function test_sending_message_broadcasts_realtime_event(): void
    {
        Event::fake([
            ConversationMessageSent::class,
        ]);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Realtime delivery test message',
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(ConversationMessageSent::class, function (ConversationMessageSent $event) use ($conversationId, $firstUser) {
            return (int) $event->message->conversation_id === $conversationId
                && (int) $event->message->user_id === $firstUser->id;
        });
    }

}
