<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSecurityRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_follow_themself(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/users/{$user->id}/toggle_following");

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot follow yourself.',
            ]);
    }

    public function test_user_cannot_attach_foreign_image_to_post(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();

        $image = PostImage::query()->create([
            'path' => 'images/foreign-image.jpg',
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($author);

        $response = $this->postJson('/api/posts', [
            'title' => 'Security test',
            'content' => 'Image ownership must be enforced.',
            'image_id' => $image->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image_id']);
    }

    public function test_comment_parent_must_belong_to_same_post(): void
    {
        $author = User::factory()->create();
        Sanctum::actingAs($author);

        $firstPost = Post::query()->create([
            'title' => 'First post',
            'content' => 'First content',
            'user_id' => $author->id,
        ]);

        $secondPost = Post::query()->create([
            'title' => 'Second post',
            'content' => 'Second content',
            'user_id' => $author->id,
        ]);

        $foreignParentComment = Comment::query()->create([
            'body' => 'Parent from another post',
            'user_id' => $author->id,
            'post_id' => $secondPost->id,
        ]);

        $response = $this->postJson("/api/posts/{$firstPost->id}/comment", [
            'body' => 'Should fail',
            'parent_id' => $foreignParentComment->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_post_creation_rejects_malicious_markup_in_title_and_content(): void
    {
        $author = User::factory()->create();
        Sanctum::actingAs($author);

        $response = $this->postJson('/api/posts', [
            'title' => 'Safe? <script>alert(1)</script>',
            'content' => 'Text with <img src=x onerror=alert(1)> payload',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_repost_rejects_malicious_markup_in_title_and_content(): void
    {
        $owner = User::factory()->create();
        $reposter = User::factory()->create();

        $source = Post::query()->create([
            'title' => 'Original title',
            'content' => 'Original content',
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($reposter);

        $response = $this->postJson("/api/posts/{$source->id}/repost", [
            'title' => '&lt;script&gt;alert(1)&lt;/script&gt;',
            'content' => 'javascript:alert(1)',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);

        $this->assertDatabaseMissing('posts', [
            'user_id' => $reposter->id,
            'reposted_id' => $source->id,
        ]);
    }

    public function test_comment_rejects_malicious_markup_in_body(): void
    {
        $author = User::factory()->create();
        Sanctum::actingAs($author);

        $post = Post::query()->create([
            'title' => 'Post title',
            'content' => 'Post content',
            'user_id' => $author->id,
        ]);

        $response = $this->postJson("/api/posts/{$post->id}/comment", [
            'body' => '<script>alert("xss")</script>',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->assertDatabaseCount('comments', 0);
    }
}
