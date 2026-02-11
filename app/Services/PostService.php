<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\LikedPost;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function create(array $payload, User $author): Post
    {
        return DB::transaction(function () use ($payload, $author) {
            $legacyImageId = $payload['image_id'] ?? null;
            $mediaIds = $payload['media_ids'] ?? [];

            unset($payload['image_id'], $payload['media_ids']);

            $isPublic = (bool) ($payload['is_public'] ?? true);
            $showInFeed = $isPublic && (bool) ($payload['show_in_feed'] ?? true);
            $showInCarousel = $isPublic && (bool) ($payload['show_in_carousel'] ?? false);

            $payload['is_public'] = $isPublic;
            $payload['show_in_feed'] = $showInFeed;
            $payload['show_in_carousel'] = $showInCarousel;

            if ($legacyImageId !== null) {
                $mediaIds[] = (int) $legacyImageId;
            }

            $payload['user_id'] = $author->id;
            $post = Post::query()->create($payload);

            $this->attachMedia($post, array_values(array_unique($mediaIds)), $author->id);
            PostImage::clearStorageForUser($author->id);

            return $post->load(Post::API_RELATIONS)->loadCount(Post::API_COUNTS);
        });
    }

    public function repost(Post $original, array $payload, User $author): Post
    {
        $isPublic = (bool) ($payload['is_public'] ?? true);
        $payload['show_in_feed'] = $isPublic && (bool) ($payload['show_in_feed'] ?? true);
        $payload['show_in_carousel'] = $isPublic && (bool) ($payload['show_in_carousel'] ?? false);
        $payload['is_public'] = $isPublic;
        $payload['user_id'] = $author->id;
        $payload['reposted_id'] = $original->id;

        $repost = Post::query()->create($payload);

        return $repost->load(Post::API_RELATIONS)->loadCount(Post::API_COUNTS);
    }

    public function createComment(Post $post, array $payload, User $author): Comment
    {
        $payload['post_id'] = $post->id;
        $payload['user_id'] = $author->id;

        $comment = Comment::query()->create($payload);

        return $comment->load(['user', 'parent.user']);
    }

    public function attachMedia(Post $post, array $mediaIds, int $ownerId): void
    {
        if ($mediaIds === []) {
            return;
        }

        $media = PostImage::query()
            ->whereIn('id', $mediaIds)
            ->where('user_id', $ownerId)
            ->whereNull('post_id')
            ->get();

        if ($media->count() !== count($mediaIds)) {
            throw ValidationException::withMessages([
                'media_ids' => ['Some selected files are invalid or already attached to another post.'],
            ]);
        }

        PostImage::query()
            ->whereIn('id', $media->pluck('id'))
            ->update(['post_id' => $post->id]);
    }

    public function markLikedPosts(Collection $posts, int $viewerId): void
    {
        if ($posts->isEmpty()) {
            return;
        }

        $likedIds = LikedPost::query()
            ->where('user_id', $viewerId)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('post_id')
            ->flip();

        foreach ($posts as $post) {
            $post->is_liked = $likedIds->has($post->id);
        }
    }
}
