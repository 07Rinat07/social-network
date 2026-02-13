<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StatRequest;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\User\UserResource;
use App\Models\LikedPost;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private readonly PostService $postService)
    {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));
        $search = trim((string) $request->string('search', ''));
        $searchTokens = collect(preg_split('/[\s,.;:|]+/u', $search) ?: [])
            ->map(static fn (string $token): string => trim($token, "@ \t\n\r\0\x0B"))
            ->filter(static fn (string $token): bool => $token !== '')
            ->unique()
            ->take(8)
            ->values();

        $usersQuery = User::query()
            ->where('id', '!=', $request->user()->id)
            ->latest();

        if ($searchTokens->isNotEmpty()) {
            $usersQuery->where(function ($query) use ($searchTokens) {
                foreach ($searchTokens as $token) {
                    $query->where(function ($tokenQuery) use ($token) {
                        $tokenQuery
                            ->where('name', 'like', '%' . $token . '%')
                            ->orWhere('nickname', 'like', '%' . $token . '%');
                    });
                }
            });
        }

        $users = $usersQuery->paginate($perPage)->withQueryString();

        $followingIds = $request->user()
            ->followings()
            ->pluck('users.id')
            ->flip();

        foreach ($users as $user) {
            $user->is_followed = $followingIds->has($user->id);
        }

        return UserResource::collection($users);
    }

    public function post(User $user, Request $request)
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $posts = $user->posts()
            ->when(
                $request->user()->id !== $user->id && !$request->user()->is_admin,
                fn ($query) => $query->where('is_public', true)
            )
            ->with(Post::API_RELATIONS)
            ->withCount(Post::API_COUNTS)
            ->latest()
            ->paginate($perPage);

        $this->postService->markLikedPosts($posts->getCollection(), $request->user()->id);

        return PostResource::collection($posts);
    }

    public function toggleFollowing(User $user, Request $request)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot follow yourself.',
            ], 422);
        }

        $res = $request->user()->followings()->toggle($user->id);

        return [
            'is_followed' => count($res['attached']) > 0,
        ];
    }

    public function followingPost(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $followedIds = $request->user()
            ->followings()
            ->pluck('users.id');

        $posts = Post::query()
            ->whereIn('user_id', $followedIds)
            ->where('is_public', true)
            ->where('show_in_feed', true)
            ->with(Post::API_RELATIONS)
            ->withCount(Post::API_COUNTS)
            ->latest()
            ->paginate($perPage);

        $this->postService->markLikedPosts($posts->getCollection(), $request->user()->id);

        return PostResource::collection($posts);
    }

    public function stat(StatRequest $request)
    {
        $data = $request->validated();
        $userId = $data['user_id'] ?? $request->user()->id;
        $user = User::query()->findOrFail($userId);

        $postIds = Post::query()
            ->where('user_id', $userId)
            ->pluck('id');

        $result = [];
        $result['subscribers_count'] = $user->followers()->count();
        $result['followings_count'] = $user->followings()->count();
        $result['posts_count'] = $postIds->count();
        $result['likes_count'] = $postIds->isEmpty()
            ? 0
            : LikedPost::query()->whereIn('post_id', $postIds)->count();
        $result['given_likes_count'] = LikedPost::query()
            ->where('user_id', $userId)
            ->count();

        return response()->json(['data' => $result]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $nickname = trim((string) $request->input('nickname', ''));
        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'nickname' => $nickname !== '' ? $nickname : null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => [
                'nullable',
                'string',
                'min:3',
                'max:40',
                'regex:/^[\pL\pN._-]+$/u',
                Rule::unique('users', 'nickname')->ignore($user->id),
            ],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $user->name = $validated['name'];
        $user->nickname = $validated['nickname'] ?? null;

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return response()->json([
            'message' => 'Профиль обновлён.',
            'data' => UserResource::make($user->fresh())->resolve($request),
        ]);
    }
}
