<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CommentRequest;
use App\Http\Requests\Post\RepostRequest;
use App\Http\Requests\Post\StoreRequest;
use App\Http\Resources\Comment\CommentResource;
use App\Http\Resources\Post\PostResource;
use App\Models\Post;
use App\Models\PostView;
use App\Services\PostService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PostController extends Controller
{
    public function __construct(private readonly PostService $postService)
    {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $posts = Post::query()
            ->where('user_id', $request->user()->id)
            ->with(Post::API_RELATIONS)
            ->withCount(Post::API_COUNTS)
            ->latest()
            ->paginate($perPage);

        $this->postService->markLikedPosts($posts->getCollection(), $request->user()->id);

        return PostResource::collection($posts);
    }

    public function discover(Request $request)
    {
        $perPage = max(1, min((int) $request->integer('per_page', 20), 50));
        $sort = (string) $request->string('sort', 'popular');

        if (!in_array($sort, ['popular', 'most_viewed', 'newest'], true)) {
            $sort = 'popular';
        }

        $posts = Post::query()
            ->where('is_public', true)
            ->where('show_in_feed', true)
            ->with(Post::API_RELATIONS)
            ->withCount(Post::API_COUNTS)
            ->when($sort === 'popular', function ($query) {
                $query->orderByRaw('(COALESCE(liked_users_count, 0) * 3 + COALESCE(comments_count, 0) * 2 + COALESCE(reposted_by_posts_count, 0) * 2 + COALESCE(views_count, 0)) DESC')
                    ->latest();
            })
            ->when($sort === 'most_viewed', fn ($query) => $query->orderByDesc('views_count')->latest())
            ->when($sort === 'newest', fn ($query) => $query->latest())
            ->paginate($perPage);

        $this->postService->markLikedPosts($posts->getCollection(), $request->user()->id);

        return PostResource::collection($posts);
    }

    public function store(StoreRequest $request): JsonResponse|PostResource
    {
        try {
            $post = $this->postService->create($request->validated(), $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Post creation failed. Please try again later.',
            ], 500);
        }

        return new PostResource($post);
    }

    public function repost(RepostRequest $request, Post $post): PostResource
    {
        $repost = $this->postService->repost($post, $request->validated(), $request->user());

        return new PostResource($repost);
    }


    public function toggleLike(Post $post, Request $request): array
    {
        $res = $request->user()->likedPosts()->toggle($post->id);

        return [
            'is_liked' => count($res['attached']) > 0,
            'likes_count' => $post->likedUsers()->count(),
        ];
    }

    public function comment(Post $post, CommentRequest $request): CommentResource
    {
        $comment = $this->postService->createComment($post, $request->validated(), $request->user());

        return new CommentResource($comment);
    }


    public function commentList(Post $post, Request $request)
    {
        $perPage = (int) $request->integer('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $comments = $post->comments()
            ->with(['user', 'parent.user'])
            ->latest()
            ->paginate($perPage);

        return CommentResource::collection($comments);
    }

    public function carousel(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 30), 100));

        $posts = Post::query()
            ->where('is_public', true)
            ->where('show_in_carousel', true)
            ->with(['media', 'user'])
            ->latest()
            ->limit($limit)
            ->get();

        $items = [];

        foreach ($posts as $post) {
            foreach ($post->media as $media) {
                $items[] = [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => $media->url,
                    'original_name' => $media->original_name,
                    'post' => [
                        'id' => $post->id,
                        'title' => $post->title,
                        'content' => $post->content,
                        'views_count' => (int) ($post->views_count ?? 0),
                        'user' => [
                            'id' => $post->user?->id,
                            'name' => $post->user?->name,
                            'display_name' => $post->user?->display_name,
                            'avatar_url' => $post->user?->avatar_url,
                        ],
                    ],
                ];
            }
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function markViewed(Post $post, Request $request): JsonResponse
    {
        $viewer = $request->user();

        if (!$post->is_public && $post->user_id !== $viewer->id && !$viewer->is_admin) {
            return response()->json([
                'message' => 'Post is not available for this viewer.',
            ], 403);
        }

        $attributes = [
            'post_id' => $post->id,
            'user_id' => $viewer->id,
            'viewed_on' => now()->toDateString(),
        ];

        $view = PostView::query()
            ->where($attributes)
            ->first();

        $counted = false;

        if (!$view) {
            try {
                PostView::query()->create($attributes);
                $counted = true;
            } catch (QueryException) {
                $view = PostView::query()
                    ->where($attributes)
                    ->first();
            }
        }

        if ($counted) {
            $post->increment('views_count');
        }

        return response()->json([
            'data' => [
                'post_id' => $post->id,
                'views_count' => (int) $post->fresh()->views_count,
                'counted' => $counted,
            ],
        ]);
    }

}
