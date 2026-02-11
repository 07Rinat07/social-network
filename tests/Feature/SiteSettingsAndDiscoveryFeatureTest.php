<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SiteSettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteSettingsAndDiscoveryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_settings_and_storage_policy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/admin/settings', [
            'key' => 'homepage_hero_title',
            'type' => SiteSetting::TYPE_STRING,
            'value' => 'Welcome to Solid Social',
            'description' => 'Home hero heading',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.key', 'homepage_hero_title')
            ->assertJsonPath('data.value', 'Welcome to Solid Social');

        $settingId = (int) $createResponse->json('data.id');

        $updateResponse = $this->patchJson("/api/admin/settings/{$settingId}", [
            'type' => SiteSetting::TYPE_INTEGER,
            'value' => 12,
            'description' => 'Max hero blocks',
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.type', SiteSetting::TYPE_INTEGER)
            ->assertJsonPath('data.value', 12);

        $storageResponse = $this->patchJson('/api/admin/settings/storage', [
            'media_storage_mode' => 'user_choice',
            'server_media_disk' => 'public',
            'cloud_media_disk' => 's3',
        ]);

        $storageResponse
            ->assertOk()
            ->assertJsonPath('data.media_storage_mode', 'user_choice')
            ->assertJsonPath('data.allow_user_storage_choice', true)
            ->assertJsonPath('data.server_media_disk', 'public')
            ->assertJsonPath('data.cloud_media_disk', 's3');

        $this->assertDatabaseHas('site_settings', [
            'key' => 'media_storage_mode',
            'value' => 'user_choice',
        ]);

        $deleteResponse = $this->deleteJson("/api/admin/settings/{$settingId}");

        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('site_settings', [
            'id' => $settingId,
        ]);
    }

    public function test_guest_can_fetch_home_page_content_defaults(): void
    {
        $response = $this->getJson('/api/site/home-content');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'badge',
                    'hero_title',
                    'hero_note',
                    'feature_items',
                    'feedback_title',
                    'feedback_subtitle',
                ],
            ])
            ->assertJsonCount(3, 'data.feature_items')
            ->assertJsonPath('data.badge', 'Социальная сеть SPA');
    }

    public function test_admin_can_update_and_reset_home_page_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $payload = [
            'badge' => 'Solid Network',
            'hero_title' => 'Новый управляемый заголовок для главной',
            'hero_note' => 'Админ обновил описание главной страницы.',
            'feature_items' => [
                'Пункт 1',
                'Пункт 2',
                'Пункт 3',
                'Пункт 4',
            ],
            'feedback_title' => 'Связаться с администрацией',
            'feedback_subtitle' => 'Опишите вопрос и получите ответ в личном кабинете.',
        ];

        $updateResponse = $this->patchJson('/api/admin/settings/home-content', $payload);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.badge', 'Solid Network')
            ->assertJsonPath('data.hero_title', 'Новый управляемый заголовок для главной')
            ->assertJsonPath('data.feature_items.3', 'Пункт 4');

        $this->assertDatabaseHas('site_settings', [
            'key' => SiteSettingService::KEY_HOME_PAGE_CONTENT,
            'type' => SiteSetting::TYPE_JSON,
        ]);

        $publicResponse = $this->getJson('/api/site/home-content');
        $publicResponse
            ->assertOk()
            ->assertJsonPath('data.badge', 'Solid Network')
            ->assertJsonPath('data.feedback_title', 'Связаться с администрацией');

        $resetResponse = $this->deleteJson('/api/admin/settings/home-content');

        $resetResponse
            ->assertOk()
            ->assertJsonPath('data.badge', 'Социальная сеть SPA')
            ->assertJsonCount(3, 'data.feature_items');

        $this->assertDatabaseMissing('site_settings', [
            'key' => SiteSettingService::KEY_HOME_PAGE_CONTENT,
        ]);
    }

    public function test_non_admin_cannot_update_home_page_content(): void
    {
        $member = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($member);

        $response = $this->patchJson('/api/admin/settings/home-content', [
            'badge' => 'Denied',
            'hero_title' => 'Denied',
            'hero_note' => 'Denied',
            'feature_items' => ['Denied'],
            'feedback_title' => 'Denied',
            'feedback_subtitle' => 'Denied',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_admin_home_page_content_validation_rejects_malicious_markup(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/admin/settings/home-content', [
            'badge' => '<script>alert(1)</script>',
            'hero_title' => 'Новый заголовок',
            'hero_note' => 'Описание',
            'feature_items' => ['<img src=x onerror=alert(1)>'],
            'feedback_title' => 'Связь',
            'feedback_subtitle' => 'Описание обратной связи',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['badge', 'feature_items.0']);
    }

    public function test_user_storage_preference_requires_user_choice_mode(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/storage', [
            'media_storage_mode' => 'user_choice',
            'server_media_disk' => 'public',
            'cloud_media_disk' => 's3',
        ])->assertOk();

        Sanctum::actingAs($member);

        $this->getJson('/api/site/config')
            ->assertOk()
            ->assertJsonPath('data.allow_user_storage_choice', true);

        $this->patchJson('/api/site/storage-preference', [
            'media_storage_preference' => 'cloud',
        ])
            ->assertOk()
            ->assertJsonPath('data.user_media_storage_preference', 'cloud');

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'media_storage_preference' => 'cloud',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/storage', [
            'media_storage_mode' => 'server_local',
            'server_media_disk' => 'public',
            'cloud_media_disk' => 's3',
        ])->assertOk();

        Sanctum::actingAs($member);

        $this->patchJson('/api/site/storage-preference', [
            'media_storage_preference' => 'server_local',
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'User storage preference is disabled by admin settings.',
            ]);
    }

    public function test_post_media_upload_uses_storage_disk_from_site_settings(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/storage', [
            'media_storage_mode' => 'cloud',
            'server_media_disk' => 'public',
            'cloud_media_disk' => 's3',
        ])->assertOk();

        Sanctum::actingAs($member);

        $response = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->image('cloud-image.jpg', 1200, 900),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.storage_disk', 's3');

        $imageId = (int) $response->json('data.id');
        $path = (string) $response->json('data.path');

        $this->assertDatabaseHas('post_images', [
            'id' => $imageId,
            'user_id' => $member->id,
            'storage_disk' => 's3',
        ]);

        Storage::disk('s3')->assertExists($path);
    }

    public function test_post_media_upload_falls_back_to_public_disk_for_non_public_disk_setting(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings/storage', [
            'media_storage_mode' => 'server_local',
            'server_media_disk' => 'local',
            'cloud_media_disk' => 's3',
        ])->assertOk();

        Sanctum::actingAs($member);

        $response = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->image('public-fallback.jpg', 1200, 900),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.storage_disk', 'public');

        $imageId = (int) $response->json('data.id');
        $path = (string) $response->json('data.path');

        $this->assertDatabaseHas('post_images', [
            'id' => $imageId,
            'user_id' => $member->id,
            'storage_disk' => 'public',
        ]);

        Storage::disk('public')->assertExists($path);
    }

    public function test_discover_endpoint_filters_posts_and_supports_sorting_modes(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $likerOne = User::factory()->create();
        $likerTwo = User::factory()->create();
        $commenterOne = User::factory()->create();
        $commenterTwo = User::factory()->create();

        $excludedPrivate = Post::query()->create([
            'title' => 'Private hidden',
            'content' => 'Must not be shown',
            'user_id' => $author->id,
            'is_public' => false,
            'show_in_feed' => false,
            'show_in_carousel' => false,
            'views_count' => 300,
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        $excludedFeedOff = Post::query()->create([
            'title' => 'Public but not in feed',
            'content' => 'Must not be shown',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => false,
            'show_in_carousel' => false,
            'views_count' => 400,
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        $popularPost = Post::query()->create([
            'title' => 'Popular champion',
            'content' => 'Should rank first for popular score',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => true,
            'views_count' => 2,
            'created_at' => now()->subHours(6),
            'updated_at' => now()->subHours(6),
        ]);

        $mostViewedPost = Post::query()->create([
            'title' => 'Most viewed',
            'content' => 'Should rank first for most viewed',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => false,
            'views_count' => 7,
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ]);

        $newestPost = Post::query()->create([
            'title' => 'Newest post',
            'content' => 'Should rank first for newest',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => false,
            'views_count' => 1,
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        $popularPost->likedUsers()->attach([$likerOne->id, $likerTwo->id]);

        Comment::query()->create([
            'body' => 'First comment for score',
            'user_id' => $commenterOne->id,
            'post_id' => $popularPost->id,
        ]);

        Comment::query()->create([
            'body' => 'Second comment for score',
            'user_id' => $commenterTwo->id,
            'post_id' => $popularPost->id,
        ]);

        Sanctum::actingAs($viewer);

        $popularResponse = $this->getJson('/api/posts/discover?sort=popular&per_page=20');
        $mostViewedResponse = $this->getJson('/api/posts/discover?sort=most_viewed&per_page=20');
        $newestResponse = $this->getJson('/api/posts/discover?sort=newest&per_page=20');

        $popularResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $popularPost->id);

        $popularIds = collect($popularResponse->json('data'))->pluck('id')->all();

        $this->assertContains($mostViewedPost->id, $popularIds);
        $this->assertContains($newestPost->id, $popularIds);
        $this->assertNotContains($excludedPrivate->id, $popularIds);
        $this->assertNotContains($excludedFeedOff->id, $popularIds);

        $mostViewedResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $mostViewedPost->id);

        $newestResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $newestPost->id);
    }

    public function test_carousel_endpoint_returns_only_public_media_marked_for_carousel(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        $carouselPost = Post::query()->create([
            'title' => 'Carousel post',
            'content' => 'Visible in carousel',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => true,
            'views_count' => 0,
        ]);

        $nonCarouselPost = Post::query()->create([
            'title' => 'Public but not carousel',
            'content' => 'Should be excluded',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => false,
            'views_count' => 0,
        ]);

        $privateCarouselFlagPost = Post::query()->create([
            'title' => 'Private carousel candidate',
            'content' => 'Private should still be excluded',
            'user_id' => $author->id,
            'is_public' => false,
            'show_in_feed' => false,
            'show_in_carousel' => true,
            'views_count' => 0,
        ]);

        $visibleImage = PostImage::query()->create([
            'path' => 'media/images/carousel-visible.jpg',
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 120,
            'original_name' => 'carousel-visible.jpg',
            'user_id' => $author->id,
            'post_id' => $carouselPost->id,
        ]);

        PostImage::query()->create([
            'path' => 'media/images/carousel-hidden.jpg',
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 120,
            'original_name' => 'carousel-hidden.jpg',
            'user_id' => $author->id,
            'post_id' => $nonCarouselPost->id,
        ]);

        PostImage::query()->create([
            'path' => 'media/images/carousel-private.jpg',
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 120,
            'original_name' => 'carousel-private.jpg',
            'user_id' => $author->id,
            'post_id' => $privateCarouselFlagPost->id,
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/posts/carousel?limit=20');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($visibleImage->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_mark_viewed_counts_once_per_user_per_day_and_protects_private_posts(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $otherViewer = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Public post',
            'content' => 'Track views',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => false,
            'views_count' => 0,
        ]);

        $privatePost = Post::query()->create([
            'title' => 'Private post',
            'content' => 'Forbidden for other users',
            'user_id' => $author->id,
            'is_public' => false,
            'show_in_feed' => false,
            'show_in_carousel' => false,
            'views_count' => 0,
        ]);

        Carbon::setTestNow('2026-02-11 09:00:00');

        Sanctum::actingAs($viewer);

        $this->postJson("/api/posts/{$post->id}/view")
            ->assertOk()
            ->assertJsonPath('data.counted', true)
            ->assertJsonPath('data.views_count', 1);

        $this->postJson("/api/posts/{$post->id}/view")
            ->assertOk()
            ->assertJsonPath('data.counted', false)
            ->assertJsonPath('data.views_count', 1);

        Carbon::setTestNow('2026-02-12 09:00:00');

        $this->postJson("/api/posts/{$post->id}/view")
            ->assertOk()
            ->assertJsonPath('data.counted', true)
            ->assertJsonPath('data.views_count', 2);

        Sanctum::actingAs($otherViewer);

        $this->postJson("/api/posts/{$post->id}/view")
            ->assertOk()
            ->assertJsonPath('data.counted', true)
            ->assertJsonPath('data.views_count', 3);

        $this->postJson("/api/posts/{$privatePost->id}/view")
            ->assertStatus(403);

        Carbon::setTestNow();
    }
}
