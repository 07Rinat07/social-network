<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BroadcastChannelsFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
        ]);

        $broadcastManager = $this->app->make(BroadcastManager::class);
        $broadcastManager->setDefaultDriver('pusher');
        $broadcastManager->forgetDrivers();

        require base_path('routes/channels.php');
    }

    public function test_authenticated_user_can_authorize_site_online_presence_channel(): void
    {
        $user = User::factory()->create([
            'name' => 'Presence User',
            'nickname' => 'presence_nick',
            'is_admin' => true,
        ]);

        $response = $this->authorizeBroadcastChannel($user, 'presence-site.online');

        $response->assertOk();
        $this->assertNotEmpty($response->json('auth'));

        $channelData = json_decode((string) $response->json('channel_data'), true);

        $this->assertIsArray($channelData);
        $this->assertSame((string) $user->id, (string) ($channelData['user_id'] ?? ''));

        $userInfo = $channelData['user_info'] ?? null;
        $this->assertIsArray($userInfo);
        $this->assertSame($user->id, (int) ($userInfo['id'] ?? 0));
        $this->assertSame('Presence User', (string) ($userInfo['name'] ?? ''));
        $this->assertSame($user->display_name, (string) ($userInfo['display_name'] ?? ''));
        $this->assertSame('presence_nick', (string) ($userInfo['nickname'] ?? ''));
        $this->assertSame($user->avatar_url, $userInfo['avatar_url'] ?? null);
        $this->assertTrue((bool) ($userInfo['is_admin'] ?? false));
    }

    public function test_conversation_participant_can_authorize_presence_channel(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $conversation = $this->createDirectConversation($firstUser, $secondUser);

        $response = $this->authorizeBroadcastChannel(
            $firstUser,
            "presence-chat.presence.{$conversation->id}"
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json('auth'));
        $this->assertNotNull($response->json('channel_data'));
    }

    public function test_non_participant_cannot_authorize_chat_presence_channel(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = $this->createDirectConversation($firstUser, $secondUser);

        $response = $this->authorizeBroadcastChannel(
            $intruder,
            "presence-chat.presence.{$conversation->id}"
        );

        $response->assertForbidden();
    }

    public function test_blocked_direct_chat_denies_presence_channel_authorization(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $conversation = $this->createDirectConversation($firstUser, $secondUser);

        UserBlock::query()->create([
            'blocker_id' => $firstUser->id,
            'blocked_user_id' => $secondUser->id,
            'reason' => 'Presence denied by active block',
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->authorizeBroadcastChannel(
            $secondUser,
            "presence-chat.presence.{$conversation->id}"
        );

        $response->assertForbidden();
    }

    public function test_conversation_participant_can_authorize_private_chat_channel(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $conversation = $this->createDirectConversation($firstUser, $secondUser);

        $response = $this->authorizeBroadcastChannel(
            $secondUser,
            "private-chat.conversation.{$conversation->id}"
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json('auth'));
    }

    public function test_blocked_direct_chat_denies_private_chat_channel_authorization(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $conversation = $this->createDirectConversation($firstUser, $secondUser);

        UserBlock::query()->create([
            'blocker_id' => $secondUser->id,
            'blocked_user_id' => $firstUser->id,
            'reason' => 'Private channel denied by active block',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->authorizeBroadcastChannel(
            $firstUser,
            "private-chat.conversation.{$conversation->id}"
        );

        $response->assertForbidden();
    }

    private function createDirectConversation(User $firstUser, User $secondUser): Conversation
    {
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DIRECT,
            'title' => null,
            'created_by' => $firstUser->id,
        ]);

        $conversation->participants()->sync([$firstUser->id, $secondUser->id]);

        return $conversation;
    }

    private function authorizeBroadcastChannel(User $user, string $channelName): TestResponse
    {
        $this->actingAs($user);

        return $this->post('/api/broadcasting/auth', [
            'socket_id' => '9999.1111',
            'channel_name' => $channelName,
        ], [
            'Accept' => 'application/json',
        ]);
    }
}
