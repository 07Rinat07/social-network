<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaPostFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_image_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->image('cover.jpg', 1200, 900),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', PostImage::TYPE_IMAGE);

        $mediaId = (int) $response->json('data.id');
        $path = (string) $response->json('data.path');
        $url = (string) $response->json('data.url');

        $this->assertSame(
            route('media.post-images.show', ['postImage' => $mediaId]),
            $url
        );

        $this->assertDatabaseHas('post_images', [
            'id' => $mediaId,
            'user_id' => $user->id,
            'type' => PostImage::TYPE_IMAGE,
            'post_id' => null,
        ]);

        Storage::disk('public')->assertExists($path);
    }

    public function test_authenticated_user_can_upload_image_media_through_legacy_route_alias(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/post_images', [
            'file' => UploadedFile::fake()->image('legacy-cover.jpg', 800, 600),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', PostImage::TYPE_IMAGE);

        $mediaId = (int) $response->json('data.id');

        $this->assertDatabaseHas('post_images', [
            'id' => $mediaId,
            'user_id' => $user->id,
            'type' => PostImage::TYPE_IMAGE,
        ]);
    }

    public function test_user_can_create_post_with_multiple_media_and_orphan_media_is_cleared(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $firstMedia = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->image('first.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->json('data');

        $secondMedia = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->create('demo.mp4', 512, 'video/mp4'),
        ], [
            'Accept' => 'application/json',
        ])->json('data');

        $orphanMedia = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->image('orphan.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->json('data');

        $createPostResponse = $this->postJson('/api/posts', [
            'title' => 'New rich post',
            'content' => 'Post with image and video attachments.',
            'media_ids' => [(int) $firstMedia['id'], (int) $secondMedia['id']],
        ]);

        $createPostResponse
            ->assertCreated()
            ->assertJsonPath('data.media.0.id', (int) $firstMedia['id'])
            ->assertJsonPath('data.media.1.id', (int) $secondMedia['id']);

        $postId = (int) $createPostResponse->json('data.id');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('post_images', [
            'id' => (int) $firstMedia['id'],
            'post_id' => $postId,
        ]);

        $this->assertDatabaseHas('post_images', [
            'id' => (int) $secondMedia['id'],
            'post_id' => $postId,
        ]);

        $this->assertDatabaseMissing('post_images', [
            'id' => (int) $orphanMedia['id'],
        ]);

        Storage::disk('public')->assertMissing((string) $orphanMedia['path']);
    }

    public function test_user_cannot_attach_foreign_media_ids_to_post(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $author = User::factory()->create();

        Sanctum::actingAs($owner);
        $foreignMediaId = (int) $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->image('foreign.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->json('data.id');

        Sanctum::actingAs($author);

        $response = $this->postJson('/api/posts', [
            'title' => 'Security validation',
            'content' => 'Foreign media id should fail.',
            'media_ids' => [$foreignMediaId],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['media_ids.0']);
    }

    public function test_posts_index_returns_media_list_and_image_url_from_first_image(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $post = Post::query()->create([
            'title' => 'Post with explicit media',
            'content' => 'Index must return media items.',
            'user_id' => $user->id,
        ]);

        PostImage::query()->create([
            'path' => 'media/videos/clip.mp4',
            'type' => PostImage::TYPE_VIDEO,
            'mime_type' => 'video/mp4',
            'size' => 100,
            'original_name' => 'clip.mp4',
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $coverImage = PostImage::query()->create([
            'path' => 'media/images/cover.jpg',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 200,
            'original_name' => 'cover.jpg',
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $response = $this->getJson('/api/posts');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.media.0.type', PostImage::TYPE_VIDEO)
            ->assertJsonPath('data.0.media.1.type', PostImage::TYPE_IMAGE)
            ->assertJsonPath(
                'data.0.image_url',
                route('media.post-images.show', ['postImage' => $coverImage->id])
            );
    }

    public function test_carousel_returns_api_media_url_for_local_disk_file_and_file_is_accessible(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $post = Post::query()->create([
            'title' => 'Legacy local media',
            'content' => 'Local disk media should be served via API endpoint.',
            'user_id' => $user->id,
            'is_public' => true,
            'show_in_feed' => true,
            'show_in_carousel' => true,
        ]);

        Storage::disk('local')->put('media/images/local-carousel.jpg', 'legacy-local-image-content');

        $image = PostImage::query()->create([
            'path' => 'media/images/local-carousel.jpg',
            'storage_disk' => 'local',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 120,
            'original_name' => 'local-carousel.jpg',
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $response = $this->getJson('/api/posts/carousel?limit=5');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $image->id)
            ->assertJsonPath('data.0.url', route('media.post-images.show', ['postImage' => $image->id]));

        $this->get(route('media.post-images.show', ['postImage' => $image->id]))
            ->assertOk();
    }
}
