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

        $asset = $this->resolveClothedAvatarAsset($index);

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
    protected function resolveClothedAvatarAsset(int $index): array
    {
        $normalizedIndex = max(1, $index);
        $cacheKey = 'avatar-svg:' . $normalizedIndex;

        if (isset($this->assetCache[$cacheKey])) {
            return $this->assetCache[$cacheKey];
        }

        $path = sprintf('seed/avatars/clothed-avatar-%02d.svg', (($normalizedIndex - 1) % 24) + 1);
        $svg = $this->buildClothedAvatarSvg($normalizedIndex);

        Storage::disk('public')->put($path, $svg);

        $asset = [
            'path' => $path,
            'mime' => 'image/svg+xml',
            'size' => strlen($svg),
        ];

        $this->assetCache[$cacheKey] = $asset;

        return $asset;
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

    protected function buildClothedAvatarSvg(int $index): string
    {
        $backgrounds = [
            ['#d9edff', '#fce7f3'],
            ['#dff7ec', '#dbeafe'],
            ['#fef3c7', '#fde2e4'],
            ['#e0f2fe', '#ede9fe'],
            ['#dcfce7', '#fef9c3'],
            ['#ffe4e6', '#dbeafe'],
        ];
        $hairColors = ['#1f2937', '#4b5563', '#7c2d12', '#854d0e', '#0f172a', '#5b3a29'];
        $skinColors = ['#f7d7c4', '#edc4a3', '#d9a77c', '#f2cbb2', '#c98f62', '#8f5b3a'];
        $shirtColors = ['#2563eb', '#0f766e', '#9333ea', '#dc2626', '#ea580c', '#1d4ed8'];
        $jacketColors = ['#ffffff', '#e2e8f0', '#dbeafe', '#ecfeff', '#f8fafc', '#ede9fe'];

        $background = $backgrounds[($index - 1) % count($backgrounds)];
        $hairColor = $hairColors[($index - 1) % count($hairColors)];
        $skinColor = $skinColors[($index - 1) % count($skinColors)];
        $shirtColor = $shirtColors[($index - 1) % count($shirtColors)];
        $jacketColor = $jacketColors[($index - 1) % count($jacketColors)];
        $accentColor = $shirtColors[$index % count($shirtColors)];
        $accessoryColor = $hairColors[($index + 2) % count($hairColors)];

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320" role="img" aria-label="Seed avatar {$index}">
  <defs>
    <linearGradient id="bg{$index}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$background[0]}"/>
      <stop offset="100%" stop-color="{$background[1]}"/>
    </linearGradient>
    <linearGradient id="shirt{$index}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$shirtColor}"/>
      <stop offset="100%" stop-color="{$accentColor}"/>
    </linearGradient>
  </defs>
  <rect width="320" height="320" rx="48" fill="url(#bg{$index})"/>
  <circle cx="160" cy="118" r="56" fill="{$skinColor}"/>
  <path d="M92 104c8-43 45-70 86-70 41 0 75 23 88 68-11-10-24-19-43-22-12 25-37 40-66 40-24 0-46-8-64-16-1 8-1 17-1 25 0 16 5 29 14 43-19-10-26-36-14-68Z" fill="{$hairColor}"/>
  <path d="M124 110c0-8 6-14 14-14h44c8 0 14 6 14 14v5c0 8-6 14-14 14h-44c-8 0-14-6-14-14Z" fill="rgba(255,255,255,0.14)"/>
  <circle cx="142" cy="118" r="5" fill="#1f2937"/>
  <circle cx="178" cy="118" r="5" fill="#1f2937"/>
  <path d="M145 143c8 7 22 7 30 0" fill="none" stroke="#8a4b35" stroke-width="5" stroke-linecap="round"/>
  <path d="M127 167c7 11 17 19 33 19 16 0 26-8 33-19v29h-66Z" fill="{$skinColor}"/>
  <path d="M76 278c9-58 46-94 84-94 38 0 75 36 84 94" fill="url(#shirt{$index})"/>
  <path d="M108 215c12 24 31 37 52 37s40-13 52-37l18 63H90Z" fill="{$jacketColor}" fill-opacity="0.92"/>
  <path d="M139 190h42l10 21-31 28-31-28Z" fill="#ffffff" fill-opacity="0.9"/>
  <path d="M152 211h16l8 67h-32Z" fill="{$accessoryColor}" fill-opacity="0.95"/>
  <circle cx="101" cy="212" r="12" fill="{$skinColor}"/>
  <circle cx="219" cy="212" r="12" fill="{$skinColor}"/>
  <path d="M87 223c6-17 17-31 34-41l15 18c-18 7-31 19-42 35Z" fill="{$jacketColor}" fill-opacity="0.96"/>
  <path d="M233 223c-6-17-17-31-34-41l-15 18c18 7 31 19 42 35Z" fill="{$jacketColor}" fill-opacity="0.96"/>
  <path d="M120 69c14-16 31-24 49-24 20 0 38 10 53 27-12-3-23-4-33-4-24 0-47 8-69 24 0-8 0-15 0-23Z" fill="{$hairColor}" fill-opacity="0.9"/>
  <circle cx="252" cy="70" r="12" fill="#ffffff" fill-opacity="0.42"/>
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
