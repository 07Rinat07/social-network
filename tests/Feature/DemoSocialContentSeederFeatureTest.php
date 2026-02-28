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
        $this->assertStringStartsWith('seed/avatars/clothed-avatar-', (string) $demoUser?->avatar_path);
        $this->assertStringEndsWith('.svg', (string) $demoUser?->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists((string) $demoUser?->avatar_path));
        $this->assertStringContainsString('<path d="M76 278c9-58 46-94 84-94 38 0 75 36 84 94"', Storage::disk('public')->get((string) $demoUser?->avatar_path));

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

    public function test_demo_social_content_seeder_replaces_legacy_seed_avatar_with_clothed_svg_avatar(): void
    {
        Storage::fake('public');

        $legacyUser = User::query()->create([
            'name' => 'Demo User 01',
            'nickname' => 'demo_user_01',
            'email' => 'demo01@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'is_admin' => false,
            'avatar_path' => 'seed/avatars/portrait-320x320-6001.jpg',
        ]);

        Storage::disk('public')->put('seed/avatars/portrait-320x320-6001.jpg', 'legacy');
        putenv('DEMO_SEED_USE_REMOTE_IMAGES=0');

        Http::preventStrayRequests();

        $this->seed(DemoSocialContentSeeder::class);

        $legacyUser->refresh();

        $this->assertNotSame('seed/avatars/portrait-320x320-6001.jpg', $legacyUser->avatar_path);
        $this->assertStringStartsWith('seed/avatars/clothed-avatar-', (string) $legacyUser->avatar_path);
        $this->assertStringEndsWith('.svg', (string) $legacyUser->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists((string) $legacyUser->avatar_path));
    }
}
