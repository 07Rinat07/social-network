<?php

namespace App\Http\Controllers;

use App\Events\FeedbackStatusUpdated;
use App\Http\Requests\Admin\UpdateFeedbackRequest;
use App\Http\Resources\Post\PostResource;
use App\Rules\NoUnsafeMarkup;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\FeedbackMessage;
use App\Models\LikedPost;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\SubscriberFollowing;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users' => User::query()->count(),
                'admins' => User::query()->where('is_admin', true)->count(),
                'posts' => Post::query()->count(),
                'public_posts' => Post::query()->where('is_public', true)->count(),
                'carousel_posts' => Post::query()->where('is_public', true)->where('show_in_carousel', true)->count(),
                'comments' => Comment::query()->count(),
                'media' => PostImage::query()->count(),
                'feedback_new' => FeedbackMessage::query()->where('status', FeedbackMessage::STATUS_NEW)->count(),
                'feedback_in_progress' => FeedbackMessage::query()->where('status', FeedbackMessage::STATUS_IN_PROGRESS)->count(),
                'feedback_resolved' => FeedbackMessage::query()->where('status', FeedbackMessage::STATUS_RESOLVED)->count(),
                'conversations' => Conversation::query()->count(),
                'messages' => ConversationMessage::query()->count(),
                'chat_attachments' => ConversationMessageAttachment::query()->count(),
                'active_blocks' => UserBlock::query()->active()->count(),
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->withCount(['posts', 'conversationMessages'])
            ->latest()
            ->paginate($this->resolvePerPage($request, 30, 100));

        return response()->json($users);
    }

    public function updateUser(User $user, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_admin' => ['required', 'boolean'],
        ]);

        if ($request->user()->id === $user->id && !$validated['is_admin']) {
            return response()->json([
                'message' => 'You cannot remove admin rights from your own account.',
            ], 422);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroyUser(User $user, Request $request): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account from admin panel.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $ownedPostIds = Post::query()
                ->where('user_id', $user->id)
                ->pluck('id');

            $ownedMedia = PostImage::query()
                ->where('user_id', $user->id)
                ->get();

            foreach ($ownedMedia as $media) {
                Storage::disk($media->storage_disk ?: 'public')->delete($media->path);
            }

            if ($ownedPostIds->isNotEmpty()) {
                Post::query()
                    ->whereIn('reposted_id', $ownedPostIds)
                    ->update(['reposted_id' => null]);

                LikedPost::query()->whereIn('post_id', $ownedPostIds)->delete();

                $ownedCommentIds = Comment::query()
                    ->whereIn('post_id', $ownedPostIds)
                    ->pluck('id');

                if ($ownedCommentIds->isNotEmpty()) {
                    Comment::query()
                        ->whereIn('parent_id', $ownedCommentIds)
                        ->update(['parent_id' => null]);

                    Comment::query()
                        ->whereIn('id', $ownedCommentIds)
                        ->delete();
                }

                PostImage::query()->whereIn('post_id', $ownedPostIds)->delete();
                Post::query()->whereIn('id', $ownedPostIds)->delete();
            }

            $userCommentIds = Comment::query()
                ->where('user_id', $user->id)
                ->pluck('id');

            if ($userCommentIds->isNotEmpty()) {
                Comment::query()
                    ->whereIn('parent_id', $userCommentIds)
                    ->update(['parent_id' => null]);

                Comment::query()
                    ->whereIn('id', $userCommentIds)
                    ->delete();
            }
            LikedPost::query()->where('user_id', $user->id)->delete();
            SubscriberFollowing::query()
                ->where('subscriber_id', $user->id)
                ->orWhere('following_id', $user->id)
                ->delete();

            UserBlock::query()
                ->where('blocker_id', $user->id)
                ->orWhere('blocked_user_id', $user->id)
                ->delete();

            $userMessageIds = ConversationMessage::query()
                ->where('user_id', $user->id)
                ->pluck('id');

            if ($userMessageIds->isNotEmpty()) {
                $attachments = ConversationMessageAttachment::query()
                    ->whereIn('conversation_message_id', $userMessageIds)
                    ->get();

                foreach ($attachments as $attachment) {
                    Storage::disk($attachment->storage_disk ?: 'public')->delete($attachment->path);
                }
            }

            $user->conversations()->detach();
            ConversationMessage::query()->where('user_id', $user->id)->delete();
            PostImage::query()->where('user_id', $user->id)->delete();

            $user->delete();

            $obsoleteDirectConversations = Conversation::query()
                ->where('type', Conversation::TYPE_DIRECT)
                ->withCount('participants')
                ->get()
                ->filter(fn (Conversation $conversation) => (int) $conversation->participants_count < 2);

            foreach ($obsoleteDirectConversations as $conversation) {
                $conversationAttachments = ConversationMessageAttachment::query()
                    ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id))
                    ->get();

                foreach ($conversationAttachments as $attachment) {
                    Storage::disk($attachment->storage_disk ?: 'public')->delete($attachment->path);
                }

                $conversation->delete();
            }
        });

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    public function posts(Request $request)
    {
        $query = Post::query()
            ->with(Post::API_RELATIONS)
            ->withCount(Post::API_COUNTS)
            ->latest();

        if ($request->boolean('all')) {
            return PostResource::collection($query->get());
        }

        $posts = $query->paginate($this->resolvePerPage($request, 20, 100));

        return PostResource::collection($posts);
    }

    public function storePost(Request $request): JsonResponse
    {
        $validated = $this->validateAdminPostPayload($request, null);
        $attributes = $this->buildAdminPostAttributes($validated);

        $post = Post::query()->create($attributes);
        $post->load(Post::API_RELATIONS)->loadCount(Post::API_COUNTS);

        return response()->json([
            'message' => 'Post created successfully.',
            'data' => (new PostResource($post))->resolve($request),
        ], 201);
    }

    public function updatePost(Post $post, Request $request): JsonResponse
    {
        $validated = $this->validateAdminPostPayload($request, $post);
        $attributes = $this->buildAdminPostAttributes($validated);

        $post->update($attributes);
        $post->load(Post::API_RELATIONS)->loadCount(Post::API_COUNTS);

        return response()->json([
            'message' => 'Post updated successfully.',
            'data' => (new PostResource($post))->resolve($request),
        ]);
    }

    public function destroyPost(Post $post): JsonResponse
    {
        DB::transaction(function () use ($post) {
            Post::query()
                ->where('reposted_id', $post->id)
                ->update(['reposted_id' => null]);

            LikedPost::query()
                ->where('post_id', $post->id)
                ->delete();

            $commentIds = Comment::query()
                ->where('post_id', $post->id)
                ->pluck('id');

            if ($commentIds->isNotEmpty()) {
                Comment::query()
                    ->whereIn('parent_id', $commentIds)
                    ->update(['parent_id' => null]);

                Comment::query()
                    ->whereIn('id', $commentIds)
                    ->delete();
            }

            $media = PostImage::query()
                ->where('post_id', $post->id)
                ->get();

            foreach ($media as $item) {
                Storage::disk($item->storage_disk ?: 'public')->delete($item->path);
            }

            PostImage::query()->where('post_id', $post->id)->delete();
            $post->delete();
        });

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }

    public function comments(Request $request): JsonResponse
    {
        $comments = Comment::query()
            ->with([
                'user:id,name,is_admin',
                'post:id,title,user_id',
                'parent.user:id,name,is_admin',
            ])
            ->latest()
            ->paginate($this->resolvePerPage($request, 30, 100));

        return response()->json($comments);
    }

    public function destroyComment(Comment $comment): JsonResponse
    {
        Comment::query()
            ->where('parent_id', $comment->id)
            ->update(['parent_id' => null]);

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $feedback = FeedbackMessage::query()
            ->with('user:id,name,email,is_admin')
            ->latest()
            ->paginate($this->resolvePerPage($request, 30, 100));

        return response()->json($feedback);
    }

    public function updateFeedback(FeedbackMessage $feedback, UpdateFeedbackRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $feedback->update($validated);
        $updatedFeedback = $feedback->fresh('user:id,name,email,is_admin');

        if ($updatedFeedback && $updatedFeedback->user_id) {
            broadcast(new FeedbackStatusUpdated($updatedFeedback))->toOthers();
        }

        return response()->json([
            'message' => 'Feedback status updated successfully.',
            'data' => $updatedFeedback,
        ]);
    }

    public function destroyFeedback(FeedbackMessage $feedback): JsonResponse
    {
        $feedback->delete();

        return response()->json([
            'message' => 'Feedback deleted successfully.',
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->with([
                'participants:id,name,is_admin',
                'lastMessage.user:id,name,is_admin',
            ])
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate($this->resolvePerPage($request, 30, 100));

        $conversations->getCollection()->transform(function (Conversation $conversation) {
            $conversation->display_title = $conversation->title
                ?: ($conversation->type === Conversation::TYPE_GLOBAL ? 'Общий чат' : 'Личный чат');

            return $conversation;
        });

        return response()->json($conversations);
    }

    public function messages(Request $request): JsonResponse
    {
        $conversationId = $request->integer('conversation_id');

        $messages = ConversationMessage::query()
            ->when($conversationId, fn ($query) => $query->where('conversation_id', $conversationId))
            ->with([
                'user:id,name,is_admin',
                'conversation:id,type,title',
                'attachments',
            ])
            ->latest()
            ->paginate($this->resolvePerPage($request, 50, 200));

        return response()->json($messages);
    }

    public function destroyMessage(ConversationMessage $message): JsonResponse
    {
        $attachments = $message->attachments()->get();
        foreach ($attachments as $attachment) {
            Storage::disk($attachment->storage_disk ?: 'public')->delete($attachment->path);
        }

        $message->delete();

        return response()->json([
            'message' => 'Message deleted successfully.',
        ]);
    }

    public function blocks(Request $request): JsonResponse
    {
        $blocks = UserBlock::query()
            ->with([
                'blocker:id,name,email,is_admin',
                'blockedUser:id,name,email,is_admin',
            ])
            ->latest()
            ->paginate($this->resolvePerPage($request, 50, 200));

        return response()->json($blocks);
    }

    public function updateBlock(UserBlock $userBlock, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $attributes = [];

        if (array_key_exists('expires_at', $validated)) {
            $attributes['expires_at'] = $validated['expires_at'];
        }

        if (array_key_exists('reason', $validated)) {
            $attributes['reason'] = $validated['reason'];
        }

        if (!empty($attributes)) {
            $userBlock->update($attributes);
        }

        return response()->json([
            'message' => 'Block updated successfully.',
            'data' => $userBlock->fresh(['blocker:id,name,email,is_admin', 'blockedUser:id,name,email,is_admin']),
        ]);
    }

    public function destroyBlock(UserBlock $userBlock): JsonResponse
    {
        $userBlock->delete();

        return response()->json([
            'message' => 'Block deleted successfully.',
        ]);
    }

    protected function resolvePerPage(Request $request, int $default = 20, int $max = 100): int
    {
        return max(1, min((int) $request->integer('per_page', $default), $max));
    }

    protected function validateAdminPostPayload(Request $request, ?Post $post): array
    {
        $request->merge([
            'title' => $this->normalizeSingleLineText($request->input('title')),
            'content' => $this->normalizeMultilineText($request->input('content')),
        ]);

        $rules = [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'min:1', 'max:255', new NoUnsafeMarkup(false)],
            'content' => ['required', 'string', 'min:1', 'max:5000', new NoUnsafeMarkup()],
            'reposted_id' => ['nullable', 'integer', Rule::exists('posts', 'id')],
            'is_public' => ['nullable', 'boolean'],
            'show_in_feed' => ['nullable', 'boolean'],
            'show_in_carousel' => ['nullable', 'boolean'],
        ];

        if ($post) {
            $rules['reposted_id'][] = Rule::notIn([$post->id]);
        }

        return $request->validate($rules);
    }

    protected function buildAdminPostAttributes(array $validated): array
    {
        $isPublic = (bool) ($validated['is_public'] ?? true);
        $showInFeed = $isPublic && (bool) ($validated['show_in_feed'] ?? true);
        $showInCarousel = $isPublic && (bool) ($validated['show_in_carousel'] ?? false);

        return [
            'user_id' => (int) $validated['user_id'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'reposted_id' => $validated['reposted_id'] ?? null,
            'is_public' => $isPublic,
            'show_in_feed' => $showInFeed,
            'show_in_carousel' => $showInCarousel,
        ];
    }

    protected function normalizeSingleLineText(mixed $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $normalized === null ? '' : $normalized;
    }

    protected function normalizeMultilineText(mixed $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);

        return trim($text);
    }
}
