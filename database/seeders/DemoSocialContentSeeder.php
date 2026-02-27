<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\LikedPost;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DemoSocialContentSeeder extends Seeder
{
    private const EXTRA_USERS_COUNT = 18;
    private const POSTS_PER_USER = 3;
    private const COMMENTS_PER_POST = 3;
    private const LIKES_PER_POST = 6;

    private const TOPICS = [
        [
            'title' => 'City Lights Mood',
            'category' => 'city',
            'tag' => 'citylife',
            'content' => 'Night city vibes, neon lights, and a long walk after work.',
        ],
        [
            'title' => 'Morning Coffee Spot',
            'category' => 'coffee',
            'tag' => 'coffee',
            'content' => 'Found a quiet place with great coffee and a calm playlist.',
        ],
        [
            'title' => 'Weekend Travel Notes',
            'category' => 'travel',
            'tag' => 'travel',
            'content' => 'Short trip, fresh air, and too many photos for one day.',
        ],
        [
            'title' => 'Street Food Discovery',
            'category' => 'food',
            'tag' => 'food',
            'content' => 'Tried a new street food place and it was surprisingly good.',
        ],
        [
            'title' => 'Park Run Session',
            'category' => 'fitness',
            'tag' => 'fitness',
            'content' => 'Quick run in the park before sunset, feeling productive.',
        ],
        [
            'title' => 'Coding Setup Refresh',
            'category' => 'technology',
            'tag' => 'dev',
            'content' => 'Cleaned up my workspace and tuned terminal themes.',
        ],
        [
            'title' => 'Rainy Day Playlist',
            'category' => 'music',
            'tag' => 'music',
            'content' => 'Slow tracks for a rainy day and deep focus.',
        ],
        [
            'title' => 'Nature Walk Journal',
            'category' => 'nature',
            'tag' => 'nature',
            'content' => 'Small trail, fresh wind, and no notifications for 2 hours.',
        ],
    ];

    private const COMMENT_TEMPLATES = [
        'Nice shot, this looks awesome.',
        'Great post, thanks for sharing.',
        'I would definitely try this too.',
        'This mood is exactly what I needed today.',
        'Looks very clean and inspiring.',
        'Solid content, keep posting.',
    ];

    /** @var array<string, array{path: string, mime: string, size: int}> */
    protected array $assetCache = [];
    protected ?bool $remotePlaceholdersEnabled = null;

    public function run(): void
    {
        $users = $this->ensureDemoUsers()->values();
        if ($users->count() < 2) {
            return;
        }

        foreach ($users as $userIndex => $user) {
            $this->ensureUserAvatar($user, $userIndex + 1);
            $this->seedPostsForUser($user, $users, $userIndex);
        }

        $this->seedFollowings($users);
    }

    protected function ensureDemoUsers(): Collection
    {
        $now = now();

        foreach (range(1, self::EXTRA_USERS_COUNT) as $index) {
            $email = sprintf('demo%02d@example.com', $index);
            $name = sprintf('Demo User %02d', $index);
            $nickname = sprintf('demo_user_%02d', $index);

            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'nickname' => $nickname,
                    'email_verified_at' => $now,
                    'password' => Hash::make('password'),
                    'is_admin' => false,
                ]
            );
        }

        return User::query()->orderBy('id')->get();
    }

    protected function ensureUserAvatar(User $user, int $index): void
    {
        $avatarPath = trim((string) ($user->avatar_path ?? ''));
        if ($avatarPath !== '' && !str_starts_with($avatarPath, 'seed/avatars/')) {
            return;
        }

        $asset = $this->resolvePlaceholderAsset('portrait', 6000 + (($index - 1) % 12) + 1, 320, 320, 'avatars');

        if ($avatarPath === $asset['path']) {
            return;
        }

        $user->forceFill([
            'avatar_path' => $asset['path'],
        ])->save();
    }

    protected function seedPostsForUser(User $user, Collection $users, int $userIndex): void
    {
        $topicsCount = count(self::TOPICS);

        foreach (range(1, self::POSTS_PER_USER) as $slot) {
            $topic = self::TOPICS[(($userIndex * self::POSTS_PER_USER) + ($slot - 1)) % $topicsCount];
            $createdAt = now()->subDays(($userIndex * self::POSTS_PER_USER) + ($slot - 1));

            $title = sprintf('[Seed] %s #%02d-%d', (string) $topic['title'], $userIndex + 1, $slot);
            $content = sprintf(
                "[seed-post] %s\n\n#%s #social",
                (string) $topic['content'],
                (string) $topic['tag']
            );

            $post = Post::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'title' => $title,
                ],
                [
                    'content' => $content,
                    'is_public' => true,
                    'show_in_feed' => true,
                    'show_in_carousel' => $slot === 1,
                    'views_count' => (int) (25 + (($userIndex + 1) * 7) + ($slot * 11)),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            $this->ensurePostImage($post, $user, $topic, $userIndex, $slot);
            $this->seedCommentsForPost($post, $users, $userIndex, $slot);
            $this->seedLikesForPost($post, $users, $userIndex, $slot);
        }
    }

    /**
     * @param array{title: string, category: string, tag: string, content: string} $topic
     */
    protected function ensurePostImage(Post $post, User $user, array $topic, int $userIndex, int $slot): void
    {
        $lock = ((($userIndex % 10) + 1) * 10) + $slot;
        $asset = $this->resolvePlaceholderAsset((string) $topic['category'], $lock, 1280, 720, 'posts');
        $fileNamePrefix = sprintf('seed-post-%02d-%d', $userIndex + 1, $slot);
        $originalName = sprintf('%s.%s', $fileNamePrefix, $this->extensionFromMime($asset['mime']));

        $existingImage = PostImage::query()
            ->where('post_id', $post->id)
            ->where('type', PostImage::TYPE_IMAGE)
            ->orderBy('id')
            ->first();

        if ($existingImage) {
            $existingImage->fill([
                'path' => $asset['path'],
                'storage_disk' => 'public',
                'mime_type' => $asset['mime'],
                'size' => $asset['size'],
                'status' => true,
                'user_id' => $user->id,
            ])->save();

            return;
        }

        PostImage::query()->create([
            'path' => $asset['path'],
            'storage_disk' => 'public',
            'type' => PostImage::TYPE_IMAGE,
            'mime_type' => $asset['mime'],
            'size' => $asset['size'],
            'original_name' => $originalName,
            'post_id' => $post->id,
            'user_id' => $user->id,
            'status' => true,
        ]);
    }

    protected function seedCommentsForPost(Post $post, Collection $users, int $userIndex, int $slot): void
    {
        $usersCount = $users->count();
        if ($usersCount < 2) {
            return;
        }

        $commentsToCreate = min(self::COMMENTS_PER_POST, $usersCount - 1);

        foreach (range(1, $commentsToCreate) as $step) {
            /** @var User $commentAuthor */
            $commentAuthor = $users[(($userIndex + $slot + $step) % $usersCount)];

            if ((int) $commentAuthor->id === (int) $post->user_id) {
                continue;
            }

            $template = self::COMMENT_TEMPLATES[(($userIndex * 3) + $slot + $step) % count(self::COMMENT_TEMPLATES)];
            $body = sprintf('[seed-comment] %s', $template);

            $existing = Comment::query()
                ->where('post_id', $post->id)
                ->where('user_id', $commentAuthor->id)
                ->where('body', $body)
                ->exists();

            if ($existing) {
                continue;
            }

            $createdAt = now()->subDays($slot)->subMinutes(($userIndex * 5) + ($step * 7));

            Comment::query()->create([
                'body' => $body,
                'user_id' => $commentAuthor->id,
                'post_id' => $post->id,
                'parent_id' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    protected function seedLikesForPost(Post $post, Collection $users, int $userIndex, int $slot): void
    {
        $usersCount = $users->count();
        if ($usersCount < 2) {
            return;
        }

        $likesToCreate = min(self::LIKES_PER_POST, $usersCount - 1);
        $selectedUsers = [];

        foreach (range(1, $usersCount - 1) as $step) {
            /** @var User $candidate */
            $candidate = $users[(($userIndex + ($slot * 2) + $step) % $usersCount)];

            if ((int) $candidate->id === (int) $post->user_id) {
                continue;
            }

            $selectedUsers[$candidate->id] = $candidate;

            if (count($selectedUsers) >= $likesToCreate) {
                break;
            }
        }

        $position = 0;
        foreach ($selectedUsers as $liker) {
            $createdAt = now()->subDays($slot)->subMinutes(($userIndex * 3) + ($position * 4));

            LikedPost::query()->firstOrCreate(
                [
                    'user_id' => $liker->id,
                    'post_id' => $post->id,
                ],
                [
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            $position++;
        }
    }

    protected function seedFollowings(Collection $users): void
    {
        $usersCount = $users->count();
        if ($usersCount < 2) {
            return;
        }

        $followingsPerUser = min(4, $usersCount - 1);

        foreach ($users as $userIndex => $subscriber) {
            foreach (range(1, $followingsPerUser) as $offset) {
                /** @var User $following */
                $following = $users[(($userIndex + ($offset * 2)) % $usersCount)];

                if ((int) $following->id === (int) $subscriber->id) {
                    continue;
                }

                $createdAt = now()->subDays(($userIndex + 1) + $offset);

                DB::table('subscriber_followings')->updateOrInsert(
                    [
                        'subscriber_id' => $subscriber->id,
                        'following_id' => $following->id,
                    ],
                    [
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]
                );
            }
        }
    }

    /**
     * @return array{path: string, mime: string, size: int}
     */
    protected function resolvePlaceholderAsset(string $category, int $lock, int $width, int $height, string $group): array
    {
        $normalizedCategory = preg_replace('/[^a-z0-9_-]/i', '', strtolower(trim($category))) ?: 'photo';
        $cacheKey = implode(':', [$group, $normalizedCategory, $lock, $width, $height]);

        if (isset($this->assetCache[$cacheKey])) {
            return $this->assetCache[$cacheKey];
        }

        $disk = Storage::disk('public');
        $basePath = sprintf('seed/%s/%s-%dx%d-%d', $group, $normalizedCategory, $width, $height, $lock);

        foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $ext) {
            $path = $basePath . '.' . $ext;
            if ($disk->exists($path)) {
                $asset = [
                    'path' => $path,
                    'mime' => $this->mimeFromExtension($ext),
                    'size' => (int) ($disk->size($path) ?: 0),
                ];

                $this->assetCache[$cacheKey] = $asset;

                return $asset;
            }
        }

        if ($this->shouldFetchRemotePlaceholders()) {
            $url = sprintf('https://loremflickr.com/%d/%d/%s?lock=%d', $width, $height, $normalizedCategory, $lock);

            try {
                $response = Http::timeout(15)
                    ->retry(1, 200)
                    ->withHeaders(['User-Agent' => 'social-network-seeder'])
                    ->get($url);

                $mimeType = strtolower(trim((string) explode(';', (string) $response->header('Content-Type', ''), 2)[0]));
                $body = $response->body();

                if (
                    $response->successful()
                    && $body !== ''
                    && str_starts_with($mimeType, 'image/')
                ) {
                    $extension = $this->extensionFromMime($mimeType);
                    $path = $basePath . '.' . $extension;
                    $disk->put($path, $body);

                    $asset = [
                        'path' => $path,
                        'mime' => $mimeType,
                        'size' => strlen($body),
                    ];

                    $this->assetCache[$cacheKey] = $asset;

                    return $asset;
                }
            } catch (Throwable) {
                // Fallback to local SVG placeholder when external image is unavailable.
            }
        }

        $path = $basePath . '.svg';
        $svg = $this->buildFallbackSvg($width, $height, $normalizedCategory);
        $disk->put($path, $svg);

        $asset = [
            'path' => $path,
            'mime' => 'image/svg+xml',
            'size' => strlen($svg),
        ];

        $this->assetCache[$cacheKey] = $asset;

        return $asset;
    }

    protected function extensionFromMime(string $mimeType): string
    {
        return match (strtolower(trim($mimeType))) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }

    protected function mimeFromExtension(string $extension): string
    {
        return match (strtolower(trim($extension))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    protected function buildFallbackSvg(int $width, int $height, string $label): string
    {
        $safeLabel = htmlspecialchars(strtoupper($label), ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#9ec5fe"/>
      <stop offset="100%" stop-color="#c8f7dc"/>
    </linearGradient>
  </defs>
  <rect width="{$width}" height="{$height}" fill="url(#g)"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#1f365c" font-family="Arial, sans-serif" font-size="36" font-weight="700">
    {$safeLabel}
  </text>
</svg>
SVG;
    }

    protected function shouldFetchRemotePlaceholders(): bool
    {
        if ($this->remotePlaceholdersEnabled !== null) {
            return $this->remotePlaceholdersEnabled;
        }

        $value = env('DEMO_SEED_USE_REMOTE_IMAGES', false);
        if (is_bool($value)) {
            $this->remotePlaceholdersEnabled = $value;

            return $this->remotePlaceholdersEnabled;
        }

        $normalized = strtolower(trim((string) $value));
        $this->remotePlaceholdersEnabled = in_array($normalized, ['1', 'true', 'yes', 'on'], true);

        return $this->remotePlaceholdersEnabled;
    }
}
