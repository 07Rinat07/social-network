<?php

namespace Tests\Feature;

use App\Events\ConversationMessageSent;
use App\Events\ConversationMoodStatusUpdated;
use App\Models\ChatArchive;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\ConversationMessageReaction;
use App\Models\User;
use App\Models\UserChatSetting;
use App\Models\UserBlock;
use App\Services\UploadedVideoTranscodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
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

    public function test_chat_index_reuses_legacy_global_chat_without_canonical_key(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conversationId = DB::table('conversations')->insertGetId([
            'type' => Conversation::TYPE_GLOBAL,
            'canonical_key' => null,
            'title' => 'Общий чат',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/chats');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversationId);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'canonical_key' => Conversation::canonicalKeyForGlobal(),
        ]);
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

    public function test_direct_chat_creation_reuses_legacy_direct_chat_without_canonical_key(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($viewer);

        $conversationId = DB::table('conversations')->insertGetId([
            'type' => Conversation::TYPE_DIRECT,
            'canonical_key' => null,
            'title' => null,
            'created_by' => $viewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversation_participants')->insert([
            [
                'conversation_id' => $conversationId,
                'user_id' => $viewer->id,
                'last_read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'conversation_id' => $conversationId,
                'user_id' => $target->id,
                'last_read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->postJson("/api/chats/direct/{$target->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $conversationId);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'canonical_key' => Conversation::canonicalKeyForDirectUsers($viewer->id, $target->id),
        ]);
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

    public function test_user_can_toggle_reaction_for_message_in_chat(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $secondUser->id,
            'body' => 'Reactable message',
        ]);

        $firstToggle = $this->postJson("/api/chats/{$conversationId}/messages/{$message->id}/reactions", [
            'emoji' => '👍',
        ]);

        $firstToggle
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversationId)
            ->assertJsonPath('data.message_id', $message->id)
            ->assertJsonPath('data.emoji', '👍')
            ->assertJsonPath('data.reacted', true)
            ->assertJsonPath('data.message.reactions.0.emoji', '👍')
            ->assertJsonPath('data.message.reactions.0.count', 1)
            ->assertJsonPath('data.message.reactions.0.reacted_by_me', true);

        $this->assertDatabaseHas('conversation_message_reactions', [
            'conversation_message_id' => $message->id,
            'user_id' => $firstUser->id,
            'emoji' => '👍',
        ]);

        $secondToggle = $this->postJson("/api/chats/{$conversationId}/messages/{$message->id}/reactions", [
            'emoji' => '👍',
        ]);

        $secondToggle
            ->assertOk()
            ->assertJsonPath('data.reacted', false);

        $this->assertDatabaseMissing('conversation_message_reactions', [
            'conversation_message_id' => $message->id,
            'user_id' => $firstUser->id,
            'emoji' => '👍',
        ]);
    }

    public function test_non_participant_cannot_toggle_reaction_for_direct_chat_message(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $intruder = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $firstUser->id,
            'body' => 'Protected reaction message',
        ]);

        Sanctum::actingAs($intruder);

        $response = $this->postJson("/api/chats/{$conversationId}/messages/{$message->id}/reactions", [
            'emoji' => '🔥',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('conversation_message_reactions', [
            'conversation_message_id' => $message->id,
            'user_id' => $intruder->id,
            'emoji' => '🔥',
        ]);
    }

    public function test_messages_endpoint_returns_chat_reaction_summary_with_viewer_state(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $secondUser->id,
            'body' => 'Reaction summary',
        ]);

        ConversationMessageReaction::query()->create([
            'conversation_message_id' => $message->id,
            'user_id' => $firstUser->id,
            'emoji' => '❤️',
        ]);

        ConversationMessageReaction::query()->create([
            'conversation_message_id' => $message->id,
            'user_id' => $secondUser->id,
            'emoji' => '❤️',
        ]);

        ConversationMessageReaction::query()->create([
            'conversation_message_id' => $message->id,
            'user_id' => $secondUser->id,
            'emoji' => '😂',
        ]);

        $response = $this->getJson("/api/chats/{$conversationId}/messages?per_page=20");
        $response->assertOk();

        $messageData = collect($response->json('data'))->firstWhere('id', $message->id);
        $this->assertNotNull($messageData);

        $reactions = collect($messageData['reactions'] ?? []);

        $heart = $reactions->firstWhere('emoji', '❤️');
        $laugh = $reactions->firstWhere('emoji', '😂');

        $this->assertNotNull($heart);
        $this->assertNotNull($laugh);
        $this->assertSame(2, (int) ($heart['count'] ?? 0));
        $this->assertTrue((bool) ($heart['reacted_by_me'] ?? false));
        $this->assertSame(1, (int) ($laugh['count'] ?? 0));
        $this->assertFalse((bool) ($laugh['reacted_by_me'] ?? true));
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

    public function test_user_can_send_chat_video_attachment_using_transcoded_mp4_output(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $convertedPath = tempnam(sys_get_temp_dir(), 'chat-transcoded-');
        $this->assertIsString($convertedPath);
        @unlink($convertedPath);
        $convertedPath .= '.mp4';
        file_put_contents($convertedPath, str_repeat('optimized-chat-video-frame', 32));
        $convertedSize = (int) (filesize($convertedPath) ?: 0);

        $this->partialMock(UploadedVideoTranscodeService::class, function ($mock) use ($convertedPath, $convertedSize): void {
            $mock->shouldReceive('maybeConvertToBrowserFriendlyMp4')
                ->once()
                ->andReturn([
                    'path' => $convertedPath,
                    'original_name' => 'camera.mp4',
                    'size' => $convertedSize,
                    'mime_type' => 'video/mp4',
                ]);
        });

        $response = $this->post("/api/chats/{$conversationId}/messages", [
            'files' => [
                UploadedFile::fake()->create('camera.webm', 4096, 'video/webm'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.attachments.0.type', ConversationMessageAttachment::TYPE_VIDEO)
            ->assertJsonPath('data.attachments.0.mime_type', 'video/mp4')
            ->assertJsonPath('data.attachments.0.original_name', 'camera.mp4');

        $attachmentId = (int) $response->json('data.attachments.0.id');

        $this->assertDatabaseHas('conversation_message_attachments', [
            'id' => $attachmentId,
            'type' => ConversationMessageAttachment::TYPE_VIDEO,
            'mime_type' => 'video/mp4',
            'original_name' => 'camera.mp4',
            'size' => $convertedSize,
        ]);

        $attachment = ConversationMessageAttachment::query()->findOrFail($attachmentId);
        Storage::disk($attachment->storage_disk ?: 'public')->assertExists($attachment->path);
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

    public function test_user_can_send_document_attachment_and_get_download_url(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->post("/api/chats/{$conversationId}/messages", [
            'files' => [
                UploadedFile::fake()->create('contract.pdf', 512, 'application/pdf'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.body', '')
            ->assertJsonPath('data.attachments.0.type', ConversationMessageAttachment::TYPE_FILE);

        $attachmentId = (int) $response->json('data.attachments.0.id');
        $downloadUrl = $response->json('data.attachments.0.download_url');

        $this->assertSame(
            route('media.chat-attachments.download', ['attachment' => $attachmentId]),
            (string) $downloadUrl
        );

        $this->assertDatabaseHas('conversation_message_attachments', [
            'id' => $attachmentId,
            'type' => ConversationMessageAttachment::TYPE_FILE,
        ]);
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

    public function test_chat_attachment_download_endpoint_is_available_for_participant(): void
    {
        Storage::fake('public');

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Sanctum::actingAs($sender);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$recipient->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $sender->id,
            'body' => null,
        ]);

        $path = 'chat/files/export.zip';
        Storage::disk('public')->put($path, 'zip-content');

        $attachment = ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $message->id,
            'path' => $path,
            'storage_disk' => 'public',
            'type' => ConversationMessageAttachment::TYPE_FILE,
            'mime_type' => 'application/zip',
            'size' => 200,
            'original_name' => 'export.zip',
        ]);

        Sanctum::actingAs($recipient);

        $downloadResponse = $this->get(route('media.chat-attachments.download', ['attachment' => $attachment->id]));
        $downloadResponse->assertOk();
        $this->assertStringContainsString(
            'attachment;',
            strtolower((string) $downloadResponse->headers->get('content-disposition'))
        );
    }

    public function test_non_participant_cannot_download_chat_attachment(): void
    {
        Storage::fake('public');

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $intruder = User::factory()->create();

        Sanctum::actingAs($sender);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$recipient->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $sender->id,
            'body' => null,
        ]);

        $attachment = ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $message->id,
            'path' => 'chat/files/private.pdf',
            'storage_disk' => 'public',
            'type' => ConversationMessageAttachment::TYPE_FILE,
            'mime_type' => 'application/pdf',
            'size' => 120,
            'original_name' => 'private.pdf',
        ]);

        Storage::disk('public')->put('chat/files/private.pdf', 'secret-content');

        Sanctum::actingAs($intruder);

        $this->get(route('media.chat-attachments.download', ['attachment' => $attachment->id]))
            ->assertStatus(403);
    }

    public function test_user_can_delete_own_message_with_attachments_and_storage_is_cleaned(): void
    {
        Storage::fake('public');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $firstUser->id,
            'body' => 'Message to remove',
        ]);

        $path = 'chat/images/delete-message.jpg';
        Storage::disk('public')->put($path, 'attachment-content');

        $attachment = ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $message->id,
            'path' => $path,
            'storage_disk' => 'public',
            'type' => ConversationMessageAttachment::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 120,
            'original_name' => 'delete-message.jpg',
        ]);

        $response = $this->deleteJson("/api/chats/{$conversationId}/messages/{$message->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversationId)
            ->assertJsonPath('data.message_id', $message->id)
            ->assertJsonPath('data.message_deleted', true);

        $this->assertDatabaseMissing('conversation_messages', [
            'id' => $message->id,
        ]);

        $this->assertDatabaseMissing('conversation_message_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_cannot_delete_message_created_by_another_participant(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $secondUser->id,
            'body' => 'Protected message',
        ]);

        $response = $this->deleteJson("/api/chats/{$conversationId}/messages/{$message->id}");

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Access denied to delete this message.');

        $this->assertDatabaseHas('conversation_messages', [
            'id' => $message->id,
        ]);
    }

    public function test_admin_can_delete_foreign_message_via_user_message_delete_endpoint(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        Sanctum::actingAs($author);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$admin->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $author->id,
            'body' => 'Owned by author',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/chats/{$conversationId}/messages/{$message->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversationId)
            ->assertJsonPath('data.message_id', $message->id)
            ->assertJsonPath('data.message_deleted', true);

        $this->assertDatabaseMissing('conversation_messages', [
            'id' => $message->id,
        ]);
    }

    public function test_admin_can_delete_foreign_attachment_via_user_attachment_delete_endpoint(): void
    {
        Storage::fake('public');

        $author = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        Sanctum::actingAs($author);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$admin->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $author->id,
            'body' => 'Message with removable audio',
        ]);

        $path = 'chat/audio/admin-delete-attachment.ogg';
        Storage::disk('public')->put($path, 'audio-content');

        $attachment = ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $message->id,
            'path' => $path,
            'storage_disk' => 'public',
            'type' => ConversationMessageAttachment::TYPE_AUDIO,
            'mime_type' => 'audio/ogg',
            'size' => 512,
            'original_name' => 'admin-delete-attachment.ogg',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(
            "/api/chats/{$conversationId}/messages/{$message->id}/attachments/{$attachment->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversationId)
            ->assertJsonPath('data.message_id', $message->id)
            ->assertJsonPath('data.attachment_id', $attachment->id)
            ->assertJsonPath('data.message_deleted', false)
            ->assertJsonPath('data.remaining_attachments', 0);

        $this->assertDatabaseHas('conversation_messages', [
            'id' => $message->id,
            'body' => 'Message with removable audio',
        ]);

        $this->assertDatabaseMissing('conversation_message_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_can_delete_attachment_without_removing_message_when_text_exists(): void
    {
        Storage::fake('public');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $firstUser->id,
            'body' => 'Text should keep message alive',
        ]);

        $path = 'chat/audio/delete-attachment.ogg';
        Storage::disk('public')->put($path, 'audio-content');

        $attachment = ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $message->id,
            'path' => $path,
            'storage_disk' => 'public',
            'type' => ConversationMessageAttachment::TYPE_AUDIO,
            'mime_type' => 'audio/ogg',
            'size' => 220,
            'original_name' => 'delete-attachment.ogg',
        ]);

        $response = $this->deleteJson(
            "/api/chats/{$conversationId}/messages/{$message->id}/attachments/{$attachment->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.message_id', $message->id)
            ->assertJsonPath('data.attachment_id', $attachment->id)
            ->assertJsonPath('data.message_deleted', false)
            ->assertJsonPath('data.remaining_attachments', 0);

        $this->assertDatabaseHas('conversation_messages', [
            'id' => $message->id,
            'body' => 'Text should keep message alive',
        ]);

        $this->assertDatabaseMissing('conversation_message_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_can_delete_last_attachment_and_empty_message_is_removed(): void
    {
        Storage::fake('public');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversationId,
            'user_id' => $firstUser->id,
            'body' => null,
        ]);

        $path = 'chat/videos/empty-message.mp4';
        Storage::disk('public')->put($path, 'video-content');

        $attachment = ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $message->id,
            'path' => $path,
            'storage_disk' => 'public',
            'type' => ConversationMessageAttachment::TYPE_VIDEO,
            'mime_type' => 'video/mp4',
            'size' => 480,
            'original_name' => 'empty-message.mp4',
        ]);

        $response = $this->deleteJson(
            "/api/chats/{$conversationId}/messages/{$message->id}/attachments/{$attachment->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.message_id', $message->id)
            ->assertJsonPath('data.attachment_id', $attachment->id)
            ->assertJsonPath('data.message_deleted', true)
            ->assertJsonPath('data.remaining_attachments', 0);

        $this->assertDatabaseMissing('conversation_messages', [
            'id' => $message->id,
        ]);

        $this->assertDatabaseMissing('conversation_message_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('public')->assertMissing($path);
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

    public function test_user_can_read_and_update_chat_storage_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $getResponse = $this->getJson('/api/chats/settings');
        $getResponse
            ->assertOk()
            ->assertJsonPath('data.save_text_messages', true)
            ->assertJsonPath('data.save_media_attachments', true)
            ->assertJsonPath('data.save_file_attachments', true)
            ->assertJsonPath('data.retention_days', null)
            ->assertJsonPath('data.auto_archive_enabled', true);

        $updateResponse = $this->patchJson('/api/chats/settings', [
            'save_text_messages' => false,
            'save_media_attachments' => true,
            'save_file_attachments' => false,
            'retention_days' => 90,
            'auto_archive_enabled' => false,
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.save_text_messages', false)
            ->assertJsonPath('data.save_media_attachments', true)
            ->assertJsonPath('data.save_file_attachments', false)
            ->assertJsonPath('data.retention_days', 90)
            ->assertJsonPath('data.auto_archive_enabled', false);

        $this->assertDatabaseHas('user_chat_settings', [
            'user_id' => $user->id,
            'save_text_messages' => false,
            'save_media_attachments' => true,
            'save_file_attachments' => false,
            'retention_days' => 90,
            'auto_archive_enabled' => false,
        ]);
    }

    public function test_user_can_create_download_and_restore_chat_archive(): void
    {
        Storage::fake('public');

        $author = User::factory()->create();
        $peer = User::factory()->create();

        Sanctum::actingAs($author);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$peer->id}")->json('data.id');

        $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Archive me',
        ])->assertStatus(201);

        $createArchiveResponse = $this->postJson('/api/chats/archives', [
            'scope' => 'all',
        ]);

        $createArchiveResponse
            ->assertStatus(201)
            ->assertJsonPath('data.scope', 'all');

        $archiveId = (int) $createArchiveResponse->json('data.id');
        $this->assertDatabaseHas('chat_archives', [
            'id' => $archiveId,
            'user_id' => $author->id,
            'scope' => 'all',
        ]);

        $downloadResponse = $this->get("/api/chats/archives/{$archiveId}/download");
        $downloadResponse->assertOk();
        $this->assertStringContainsString(
            'attachment;',
            strtolower((string) $downloadResponse->headers->get('content-disposition'))
        );

        $restoreResponse = $this->postJson("/api/chats/archives/{$archiveId}/restore");
        $restoreResponse
            ->assertOk()
            ->assertJsonPath('data.archive.id', $archiveId)
            ->assertJsonPath('data.conversation.type', Conversation::TYPE_ARCHIVE);

        $restoredConversationId = (int) $restoreResponse->json('data.conversation.id');
        $this->assertDatabaseHas('conversations', [
            'id' => $restoredConversationId,
            'type' => Conversation::TYPE_ARCHIVE,
        ]);

        $this->assertDatabaseHas('chat_archives', [
            'id' => $archiveId,
            'restored_conversation_id' => $restoredConversationId,
        ]);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $restoredConversationId,
        ]);
    }

    public function test_user_cannot_access_archive_of_another_user(): void
    {
        $author = User::factory()->create();
        $intruder = User::factory()->create();

        UserChatSetting::query()->create([
            'user_id' => $author->id,
            'save_text_messages' => true,
            'save_media_attachments' => true,
            'save_file_attachments' => true,
            'retention_days' => null,
            'auto_archive_enabled' => true,
        ]);

        $archive = ChatArchive::query()->create([
            'user_id' => $author->id,
            'scope' => 'all',
            'title' => 'Private archive',
            'payload' => [
                'generated_at' => now()->toIso8601String(),
                'scope' => 'all',
                'settings' => [],
                'conversations' => [],
            ],
            'messages_count' => 0,
        ]);

        Sanctum::actingAs($intruder);

        $this->get("/api/chats/archives/{$archive->id}/download")
            ->assertStatus(403);

        $this->postJson("/api/chats/archives/{$archive->id}/restore")
            ->assertStatus(403);
    }

    public function test_user_can_save_mood_status_and_owner_receives_visibility_payload(): void
    {
        $author = User::factory()->create();
        $peer = User::factory()->create();

        Sanctum::actingAs($author);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$peer->id}")->json('data.id');

        $response = $this->patchJson("/api/chats/{$conversationId}/mood-status", [
            'text' => 'Сегодня в хорошем настроении.',
            'is_visible_to_all' => true,
            'hidden_user_ids' => [$peer->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.conversation.id', $conversationId);

        $ownerStatus = collect($response->json('data.conversation.mood_statuses'))
            ->firstWhere('user_id', $author->id);

        $this->assertNotNull($ownerStatus);
        $this->assertSame('Сегодня в хорошем настроении.', $ownerStatus['text']);
        $this->assertTrue((bool) ($ownerStatus['visibility']['is_visible_to_all'] ?? false));
        $this->assertSame([], $ownerStatus['visibility']['hidden_user_ids'] ?? null);

        $this->assertDatabaseHas('conversation_mood_statuses', [
            'conversation_id' => $conversationId,
            'user_id' => $author->id,
            'text' => 'Сегодня в хорошем настроении.',
            'is_visible_to_all' => true,
        ]);
    }

    public function test_mood_status_can_be_hidden_from_selected_participant(): void
    {
        $author = User::factory()->create();
        $hiddenPeer = User::factory()->create();

        Sanctum::actingAs($author);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$hiddenPeer->id}")->json('data.id');

        $this->patchJson("/api/chats/{$conversationId}/mood-status", [
            'text' => 'Скрытый для собеседника статус.',
            'is_visible_to_all' => false,
            'hidden_user_ids' => [$hiddenPeer->id],
        ])->assertOk();

        Sanctum::actingAs($hiddenPeer);
        $hiddenViewResponse = $this->getJson("/api/chats/{$conversationId}");
        $hiddenViewResponse->assertOk();

        $hiddenViewStatus = collect($hiddenViewResponse->json('data.mood_statuses'))
            ->firstWhere('user_id', $author->id);
        $this->assertNull($hiddenViewStatus);

        Sanctum::actingAs($author);
        $ownerViewResponse = $this->getJson("/api/chats/{$conversationId}");
        $ownerViewResponse->assertOk();

        $ownerViewStatus = collect($ownerViewResponse->json('data.mood_statuses'))
            ->firstWhere('user_id', $author->id);

        $this->assertNotNull($ownerViewStatus);
        $this->assertFalse((bool) ($ownerViewStatus['visibility']['is_visible_to_all'] ?? true));
        $this->assertContains($hiddenPeer->id, $ownerViewStatus['visibility']['hidden_user_ids'] ?? []);
    }

    public function test_user_can_clear_own_mood_status_with_empty_text(): void
    {
        $author = User::factory()->create();
        $peer = User::factory()->create();

        Sanctum::actingAs($author);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$peer->id}")->json('data.id');

        $this->patchJson("/api/chats/{$conversationId}/mood-status", [
            'text' => 'Временный статус.',
            'is_visible_to_all' => true,
            'hidden_user_ids' => [],
        ])->assertOk();

        $clearResponse = $this->patchJson("/api/chats/{$conversationId}/mood-status", [
            'text' => '   ',
            'is_visible_to_all' => true,
            'hidden_user_ids' => [],
        ]);

        $clearResponse->assertOk();

        $this->assertDatabaseMissing('conversation_mood_statuses', [
            'conversation_id' => $conversationId,
            'user_id' => $author->id,
        ]);

        $statusesAfterClear = collect($clearResponse->json('data.conversation.mood_statuses'));
        $this->assertNull($statusesAfterClear->firstWhere('user_id', $author->id));
    }

    public function test_non_participant_cannot_update_mood_status_for_direct_chat(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $intruder = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        Sanctum::actingAs($intruder);

        $response = $this->patchJson("/api/chats/{$conversationId}/mood-status", [
            'text' => 'Чужой статус.',
            'is_visible_to_all' => true,
            'hidden_user_ids' => [],
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('conversation_mood_statuses', [
            'conversation_id' => $conversationId,
            'user_id' => $intruder->id,
        ]);
    }

    public function test_updating_mood_status_broadcasts_realtime_event(): void
    {
        Event::fake([
            ConversationMoodStatusUpdated::class,
        ]);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Sanctum::actingAs($firstUser);
        $conversationId = (int) $this->postJson("/api/chats/direct/{$secondUser->id}")->json('data.id');

        $response = $this->patchJson("/api/chats/{$conversationId}/mood-status", [
            'text' => 'Онлайн и на связи',
            'is_visible_to_all' => true,
            'hidden_user_ids' => [],
        ]);

        $response->assertOk();

        Event::assertDispatched(ConversationMoodStatusUpdated::class, function (ConversationMoodStatusUpdated $event) use ($conversationId, $firstUser) {
            return (int) $event->conversationId === $conversationId
                && (int) $event->actorUserId === $firstUser->id;
        });
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
