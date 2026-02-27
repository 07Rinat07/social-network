<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\LikedPost;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Database\Seeders\DemoSocialContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSocialContentSeederFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_social_content_seeder_creates_users_and_content_with_seed_assets(): void
    {
        Storage::fake('public');

        putenv('DEMO_SEED_USE_REMOTE_IMAGES=1');
        try {
            Http::fake([
                'https://loremflickr.com/*' => Http::response("\xFF\xD8\xFF\xD9", 200, [
                    'Content-Type' => 'image/jpeg',
                ]),
            ]);

            $this->seed(DemoSocialContentSeeder::class);
        } finally {
            putenv('DEMO_SEED_USE_REMOTE_IMAGES=0');
        }

        $this->assertGreaterThanOrEqual(18, User::query()->count());
        $this->assertGreaterThanOrEqual(54, Post::query()->count());
        $this->assertGreaterThan(0, Comment::query()->count());
        $this->assertGreaterThan(0, LikedPost::query()->count());
        $this->assertGreaterThan(0, PostImage::query()->count());

        $demoUser = User::query()->where('email', 'demo01@example.com')->first();
        $this->assertNotNull($demoUser);
        $this->assertIsString($demoUser?->avatar_path);
        $this->assertStringStartsWith('seed/avatars/', (string) $demoUser?->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists((string) $demoUser?->avatar_path));

        $postImage = PostImage::query()->where('path', 'like', 'seed/posts/%')->first();
        $this->assertNotNull($postImage);
        $this->assertTrue(Storage::disk('public')->exists((string) $postImage?->path));
    }

    public function test_demo_social_content_seeder_works_offline_without_remote_placeholders(): void
    {
        Storage::fake('public');
        putenv('DEMO_SEED_USE_REMOTE_IMAGES=0');

        Http::preventStrayRequests();

        $this->seed(DemoSocialContentSeeder::class);

        $postImage = PostImage::query()->where('path', 'like', 'seed/posts/%')->first();

        $this->assertNotNull($postImage);
        $this->assertTrue(Storage::disk('public')->exists((string) $postImage?->path));
    }
}
