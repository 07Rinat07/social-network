<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_supports_search_by_name_surname_and_nickname(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer User']);
        $ivanPetrov = User::factory()->create(['name' => 'Иван Петров', 'nickname' => null]);
        $anna = User::factory()->create(['name' => 'Анна Ильина', 'nickname' => 'ann_star']);
        $maria = User::factory()->create(['name' => 'Мария Сидорова', 'nickname' => 'maria88']);

        Sanctum::actingAs($viewer);

        $byName = $this->getJson('/api/users?search=Иван&per_page=50');
        $byName
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ivanPetrov->id);

        $bySurname = $this->getJson('/api/users?search=Сидорова&per_page=50');
        $bySurname
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $maria->id);

        $byNickname = $this->getJson('/api/users?search=ann_star&per_page=50');
        $byNickname
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $anna->id);

        $byCombinedTokens = $this->getJson('/api/users?search=Петров Иван&per_page=50');
        $byCombinedTokens
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ivanPetrov->id);

        $this->assertFalse(
            collect($byCombinedTokens->json('data'))
                ->contains(fn (array $item): bool => (int) ($item['id'] ?? 0) === (int) $viewer->id)
        );
    }

    public function test_guest_cannot_update_profile(): void
    {
        $response = $this->post('/api/users/profile', [
            'name' => 'Guest',
            'nickname' => 'guest_nick',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_can_update_nickname_and_avatar_for_posts_and_chats(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Real Name']);
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $profileResponse = $this->post('/api/users/profile', [
            'name' => 'Real Name',
            'nickname' => 'chat_master',
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
        ], [
            'Accept' => 'application/json',
        ]);

        $profileResponse
            ->assertOk()
            ->assertJsonPath('data.nickname', 'chat_master')
            ->assertJsonPath('data.display_name', 'chat_master');

        $avatarUrl = (string) $profileResponse->json('data.avatar_url');
        $this->assertStringStartsWith('/api/media/avatars/', $avatarUrl);

        $avatarResponse = $this->get($avatarUrl);
        $avatarResponse->assertOk();
        $this->assertStringStartsWith('image/', (string) $avatarResponse->headers->get('Content-Type'));

        $user->refresh();

        $this->assertSame('chat_master', $user->nickname);
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        Post::query()->create([
            'title' => 'Post from nickname user',
            'content' => 'Nickname must be visible in post author card.',
            'user_id' => $user->id,
            'is_public' => true,
            'show_in_feed' => true,
        ]);

        $postsResponse = $this->getJson('/api/posts');

        $postsResponse
            ->assertOk()
            ->assertJsonPath('data.0.user.display_name', 'chat_master');

        Sanctum::actingAs($otherUser);

        $searchResponse = $this->getJson('/api/chats/users?search=chat_master&per_page=50');

        $searchResponse
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nickname', 'chat_master');

        Sanctum::actingAs($user);

        $conversationResponse = $this->postJson("/api/chats/direct/{$otherUser->id}");
        $conversationId = (int) $conversationResponse->json('data.id');

        $messageResponse = $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Hello from nickname user',
        ]);

        $messageResponse
            ->assertStatus(201)
            ->assertJsonPath('data.user.display_name', 'chat_master');
    }

    public function test_profile_update_rejects_duplicate_nickname(): void
    {
        $firstUser = User::factory()->create(['nickname' => 'taken_nick']);
        $secondUser = User::factory()->create();
        Sanctum::actingAs($secondUser);

        $response = $this->postJson('/api/users/profile', [
            'name' => $secondUser->name,
            'nickname' => $firstUser->nickname,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nickname']);
    }

    public function test_user_can_remove_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $uploadResponse = $this->post('/api/users/profile', [
            'name' => $user->name,
            'nickname' => 'avatar_owner',
            'avatar' => UploadedFile::fake()->image('avatar-remove.jpg', 300, 300),
        ], [
            'Accept' => 'application/json',
        ]);

        $uploadResponse->assertOk();

        $user->refresh();
        $oldPath = $user->avatar_path;

        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);

        $removeResponse = $this->postJson('/api/users/profile', [
            'name' => $user->name,
            'nickname' => 'avatar_owner',
            'remove_avatar' => true,
        ]);

        $removeResponse
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
    }
}
