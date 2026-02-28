<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use App\Services\UploadedVideoTranscodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaPostFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_remove_like_from_post_via_delete_endpoint(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Like removal target',
            'content' => 'Detach like for current viewer.',
            'user_id' => $author->id,
        ]);

        $viewer->likedPosts()->attach($post->id);
        Sanctum::actingAs($viewer);

        $response = $this->deleteJson("/api/posts/{$post->id}/like");

        $response
            ->assertOk()
            ->assertJsonPath('is_liked', false)
            ->assertJsonPath('likes_count', 0);

        $this->assertDatabaseMissing('liked_posts', [
            'post_id' => $post->id,
            'user_id' => $viewer->id,
        ]);
    }

    public function test_user_can_delete_own_comment_and_children_are_detached_from_parent(): void
    {
        $author = User::factory()->create();
        $responder = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Comment delete post',
            'content' => 'Parent comment should be deleted safely.',
            'user_id' => $author->id,
        ]);

        $parent = Comment::query()->create([
            'body' => 'Parent comment',
            'user_id' => $author->id,
            'post_id' => $post->id,
            'parent_id' => null,
        ]);

        $child = Comment::query()->create([
            'body' => 'Child comment',
            'user_id' => $responder->id,
            'post_id' => $post->id,
            'parent_id' => $parent->id,
        ]);

        Sanctum::actingAs($author);

        $response = $this->deleteJson("/api/posts/{$post->id}/comments/{$parent->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.comment_id', $parent->id)
            ->assertJsonPath('data.post_id', $post->id);

        $this->assertDatabaseMissing('comments', [
            'id' => $parent->id,
        ]);

        $this->assertDatabaseHas('comments', [
            'id' => $child->id,
            'parent_id' => null,
        ]);
    }

    public function test_user_cannot_delete_foreign_comment(): void
    {
        $author = User::factory()->create();
        $intruder = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Unauthorized comment delete',
            'content' => 'Only owner/admin can delete.',
            'user_id' => $author->id,
        ]);

        $comment = Comment::query()->create([
            'body' => 'Author comment',
            'user_id' => $author->id,
            'post_id' => $post->id,
        ]);

        Sanctum::actingAs($intruder);

        $response = $this->deleteJson("/api/posts/{$post->id}/comments/{$comment->id}");

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Access denied to delete this comment.');

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'user_id' => $author->id,
        ]);
    }

    public function test_admin_cannot_delete_foreign_comment_via_user_comment_delete_endpoint(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $post = Post::query()->create([
            'title' => 'Foreign comment for admin user endpoint',
            'content' => 'Admin should use dedicated admin endpoints.',
            'user_id' => $author->id,
        ]);

        $comment = Comment::query()->create([
            'body' => 'Comment owned by author',
            'user_id' => $author->id,
            'post_id' => $post->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/posts/{$post->id}/comments/{$comment->id}");

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Access denied to delete this comment.');

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'user_id' => $author->id,
        ]);
    }

    public function test_user_can_delete_own_post_media_and_file_is_removed_from_storage(): void
    {
        Storage::fake('public');

        $author = User::factory()->create();
        Sanctum::actingAs($author);

        $post = Post::query()->create([
            'title' => 'Media delete post',
            'content' => 'Delete media endpoint coverage.',
            'user_id' => $author->id,
        ]);

        $path = 'media/images/delete-me.jpg';
        Storage::disk('public')->put($path, 'binary-content');

        $media = PostImage::query()->create([
            'path' => $path,
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 128,
            'original_name' => 'delete-me.jpg',
            'post_id' => $post->id,
            'user_id' => $author->id,
        ]);

        $response = $this->deleteJson("/api/posts/{$post->id}/media/{$media->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.media_id', $media->id)
            ->assertJsonPath('data.post_id', $post->id)
            ->assertJsonPath('data.remaining_media', 0);

        $this->assertDatabaseMissing('post_images', [
            'id' => $media->id,
        ]);

        Storage::disk('public')->assertMissing($path);
    }

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
            route('media.post-images.show', ['postImage' => $mediaId], false),
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

    public function test_authenticated_user_can_upload_mkv_video_media_with_octet_stream_fallback(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->create('clip.mkv', 1024, 'application/octet-stream'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', PostImage::TYPE_VIDEO)
            ->assertJsonPath('data.mime_type', 'video/x-matroska')
            ->assertJsonPath('data.original_name', 'clip.mkv');

        $mediaId = (int) $response->json('data.id');
        $path = (string) $response->json('data.path');

        $this->assertDatabaseHas('post_images', [
            'id' => $mediaId,
            'user_id' => $user->id,
            'type' => PostImage::TYPE_VIDEO,
            'mime_type' => 'video/x-matroska',
            'original_name' => 'clip.mkv',
            'post_id' => null,
        ]);

        $this->assertStringStartsWith('media/videos/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_authenticated_user_can_store_transcoded_mp4_output_for_post_video_upload(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $convertedPath = tempnam(sys_get_temp_dir(), 'post-transcoded-');
        $this->assertIsString($convertedPath);
        @unlink($convertedPath);
        $convertedPath .= '.mp4';
        file_put_contents($convertedPath, str_repeat('optimized-video-frame', 32));
        $convertedSize = (int) (filesize($convertedPath) ?: 0);

        $this->partialMock(UploadedVideoTranscodeService::class, function ($mock) use ($convertedPath, $convertedSize): void {
            $mock->shouldReceive('maybeConvertToBrowserFriendlyMp4')
                ->once()
                ->andReturn([
                    'path' => $convertedPath,
                    'original_name' => 'heavy-source.mp4',
                    'size' => $convertedSize,
                    'mime_type' => 'video/mp4',
                ]);
        });

        $response = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->create('heavy-source.mkv', 4096, 'video/x-matroska'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', PostImage::TYPE_VIDEO)
            ->assertJsonPath('data.mime_type', 'video/mp4')
            ->assertJsonPath('data.original_name', 'heavy-source.mp4');

        $mediaId = (int) $response->json('data.id');
        $path = (string) $response->json('data.path');

        $this->assertDatabaseHas('post_images', [
            'id' => $mediaId,
            'user_id' => $user->id,
            'type' => PostImage::TYPE_VIDEO,
            'mime_type' => 'video/mp4',
            'original_name' => 'heavy-source.mp4',
            'size' => $convertedSize,
        ]);

        $this->assertStringStartsWith('media/videos/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_authenticated_user_cannot_upload_mkv_with_non_media_mime_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/post_media', [
            'file' => UploadedFile::fake()->create('fake-video.mkv', 256, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseMissing('post_images', [
            'user_id' => $user->id,
            'original_name' => 'fake-video.mkv',
        ]);
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
                route('media.post-images.show', ['postImage' => $coverImage->id], false)
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
            ->assertJsonPath('data.0.url', route('media.post-images.show', ['postImage' => $image->id], false));

        $this->get(route('media.post-images.show', ['postImage' => $image->id]))
            ->assertOk();
    }

    public function test_guest_can_view_public_post_image(): void
    {
        Storage::fake('public');

        $author = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Public media',
            'content' => 'Public post image should be available for guests.',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
        ]);

        $path = 'media/images/public-image.jpg';
        Storage::disk('public')->put($path, 'public-image-content');

        $image = PostImage::query()->create([
            'path' => $path,
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 180,
            'original_name' => 'public-image.jpg',
            'user_id' => $author->id,
            'post_id' => $post->id,
        ]);

        $this->get(route('media.post-images.show', ['postImage' => $image->id]))
            ->assertOk();
    }

    public function test_guest_can_view_public_post_image_with_retry_query_parameter(): void
    {
        Storage::fake('public');

        $author = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Public media retry query',
            'content' => 'Public post image should stay accessible with cache-busting query.',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
        ]);

        $path = 'media/images/public-image-retry.jpg';
        Storage::disk('public')->put($path, 'public-image-retry-content');

        $image = PostImage::query()->create([
            'path' => $path,
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 200,
            'original_name' => 'public-image-retry.jpg',
            'user_id' => $author->id,
            'post_id' => $post->id,
        ]);

        $url = route('media.post-images.show', ['postImage' => $image->id]) . '?_img_retry=1';

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_guest_cannot_view_private_post_image(): void
    {
        Storage::fake('public');

        $author = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Private media',
            'content' => 'Private post image must be restricted for guests.',
            'user_id' => $author->id,
            'is_public' => false,
            'show_in_feed' => false,
        ]);

        $path = 'media/images/private-image.jpg';
        Storage::disk('public')->put($path, 'private-image-content');

        $image = PostImage::query()->create([
            'path' => $path,
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 180,
            'original_name' => 'private-image.jpg',
            'user_id' => $author->id,
            'post_id' => $post->id,
        ]);

        $this->get(route('media.post-images.show', ['postImage' => $image->id]))
            ->assertStatus(403);
    }

    public function test_public_post_image_with_external_or_missing_path_falls_back_to_local_placeholder(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://example.invalid/*' => Http::response('', 500),
        ]);

        $author = User::factory()->create();

        $post = Post::query()->create([
            'title' => 'Broken external image',
            'content' => 'External media should be rehydrated to local fallback.',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
        ]);

        $image = PostImage::query()->create([
            'path' => 'https://example.invalid/broken.jpg',
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 0,
            'original_name' => 'broken.jpg',
            'user_id' => $author->id,
            'post_id' => $post->id,
        ]);

        $this->get(route('media.post-images.show', ['postImage' => $image->id]))
            ->assertOk();

        $image->refresh();
        $this->assertSame('public', $image->storage_disk);
        $this->assertSame('image/svg+xml', $image->mime_type);
        $this->assertStringStartsWith('seed/posts/missing-post-image-', (string) $image->path);
        Storage::disk('public')->assertExists((string) $image->path);
    }

    public function test_public_post_image_endpoint_is_not_throttled_by_default_api_limiter(): void
    {
        Storage::fake('public');

        $author = User::factory()->create();
        $post = Post::query()->create([
            'title' => 'Throttle-safe media',
            'content' => 'Image endpoint must stay available under burst traffic.',
            'user_id' => $author->id,
            'is_public' => true,
            'show_in_feed' => true,
        ]);

        $path = 'media/images/throttle-safe.jpg';
        Storage::disk('public')->put($path, 'throttle-safe-content');

        $image = PostImage::query()->create([
            'path' => $path,
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => 160,
            'original_name' => 'throttle-safe.jpg',
            'user_id' => $author->id,
            'post_id' => $post->id,
        ]);

        $url = route('media.post-images.show', ['postImage' => $image->id]);

        foreach (range(1, 80) as $index) {
            $this->get($url)->assertOk();
        }
    }
}
