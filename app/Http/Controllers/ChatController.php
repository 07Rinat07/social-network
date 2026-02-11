<?php

namespace App\Http\Controllers;

use App\Events\ConversationMessageSent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function __construct(private readonly SiteSettingService $siteSettingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $globalConversation = Conversation::query()->firstOrCreate(
            ['type' => Conversation::TYPE_GLOBAL],
            [
                'title' => 'Общий чат',
                'created_by' => $user->id,
            ]
        );

        $conversations = Conversation::query()
            ->where(function ($query) use ($user) {
                $query->where('type', Conversation::TYPE_GLOBAL)
                    ->orWhereHas('participants', fn ($builder) => $builder->where('users.id', $user->id));
            })
            ->with([
                'participants:id,name,nickname,avatar_path,is_admin',
                'lastMessage.user:id,name,nickname,avatar_path,is_admin',
                'lastMessage.attachments',
            ])
            ->latest('updated_at')
            ->get();

        $this->ensureParticipantRecords($conversations, $user->id, $globalConversation->id);
        $unreadCounts = $this->resolveUnreadCounts($conversations->pluck('id'), $user->id);
        $totalUnread = array_sum($unreadCounts);

        return response()->json([
            'data' => $conversations
                ->map(fn (Conversation $conversation) => $this->conversationPayload(
                    $conversation,
                    $user->id,
                    (int) ($unreadCounts[$conversation->id] ?? 0)
                ))
                ->values(),
            'meta' => [
                'total_unread' => (int) $totalUnread,
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $perPage = max(1, min((int) $request->integer('per_page', 30), 100));
        $search = trim((string) $request->string('search', ''));

        $users = User::query()
            ->where('id', '!=', $viewer->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('nickname', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->latest()
            ->paginate($perPage);

        $userIds = collect($users->items())->pluck('id');

        $blockedByMe = UserBlock::query()
            ->active()
            ->where('blocker_id', $viewer->id)
            ->whereIn('blocked_user_id', $userIds)
            ->pluck('blocked_user_id')
            ->flip();

        $blockedMe = UserBlock::query()
            ->active()
            ->where('blocked_user_id', $viewer->id)
            ->whereIn('blocker_id', $userIds)
            ->pluck('blocker_id')
            ->flip();

        foreach ($users as $user) {
            $user->is_blocked_by_me = $blockedByMe->has($user->id);
            $user->has_blocked_me = $blockedMe->has($user->id);
        }

        return response()->json($users);
    }

    public function createOrGetDirect(User $user, Request $request): JsonResponse
    {
        $viewer = $request->user();

        if ($viewer->id === $user->id) {
            return response()->json([
                'message' => 'You cannot create a personal chat with yourself.',
            ], 422);
        }

        $block = $this->findActiveBlockBetween($viewer->id, $user->id);
        if ($block) {
            return response()->json([
                'message' => 'Direct chat is blocked between these users.',
                'data' => [
                    'blocked_by_user_id' => $block->blocker_id,
                    'blocked_user_id' => $block->blocked_user_id,
                    'expires_at' => $block->expires_at?->toIso8601String(),
                ],
            ], 423);
        }

        $conversation = Conversation::query()
            ->where('type', Conversation::TYPE_DIRECT)
            ->withCount('participants')
            ->whereHas('participants', fn ($query) => $query->where('users.id', $viewer->id))
            ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
            ->get()
            ->first(fn (Conversation $item) => (int) $item->participants_count === 2);

        if (!$conversation) {
            $conversation = DB::transaction(function () use ($viewer, $user) {
                $created = Conversation::query()->create([
                    'type' => Conversation::TYPE_DIRECT,
                    'created_by' => $viewer->id,
                ]);

                $created->participants()->sync([$viewer->id, $user->id]);

                return $created;
            });
        }

        $conversation->load([
            'participants:id,name,nickname,avatar_path,is_admin',
            'lastMessage.user:id,name,nickname,avatar_path,is_admin',
            'lastMessage.attachments',
        ]);

        $unreadCounts = $this->resolveUnreadCounts([$conversation->id], $viewer->id);

        return response()->json([
            'data' => $this->conversationPayload($conversation, $viewer->id, (int) ($unreadCounts[$conversation->id] ?? 0)),
        ]);
    }

    public function show(Conversation $conversation, Request $request): JsonResponse
    {
        $this->ensureAccess($conversation, $request->user()->id);

        $conversation->load([
            'participants:id,name,nickname,avatar_path,is_admin',
            'lastMessage.user:id,name,nickname,avatar_path,is_admin',
            'lastMessage.attachments',
        ]);

        $viewerId = $request->user()->id;
        $this->ensureParticipantRecords(
            collect([$conversation]),
            $viewerId,
            $conversation->type === Conversation::TYPE_GLOBAL ? $conversation->id : null
        );
        $unreadCounts = $this->resolveUnreadCounts([$conversation->id], $viewerId);

        return response()->json([
            'data' => $this->conversationPayload($conversation, $viewerId, (int) ($unreadCounts[$conversation->id] ?? 0)),
        ]);
    }

    public function messages(Conversation $conversation, Request $request): JsonResponse
    {
        $viewerId = $request->user()->id;
        $this->ensureAccess($conversation, $viewerId);
        $this->markConversationAsRead($conversation, $viewerId);

        $perPage = max(1, min((int) $request->integer('per_page', 40), 100));

        $messages = $conversation->messages()
            ->with(['user:id,name,nickname,avatar_path,is_admin', 'attachments'])
            ->latest('id')
            ->paginate($perPage);

        $items = collect($messages->items())
            ->reverse()
            ->map(fn (ConversationMessage $message) => $this->messagePayload($message))
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        $viewer = $request->user();

        $globalConversation = Conversation::query()->firstOrCreate(
            ['type' => Conversation::TYPE_GLOBAL],
            [
                'title' => 'Общий чат',
                'created_by' => $viewer->id,
            ]
        );

        $conversations = Conversation::query()
            ->forUser($viewer->id)
            ->get(['id', 'type']);

        $this->ensureParticipantRecords($conversations, $viewer->id, $globalConversation->id);
        $unreadCounts = $this->resolveUnreadCounts($conversations->pluck('id'), $viewer->id);

        return response()->json([
            'data' => [
                'total_unread' => (int) array_sum($unreadCounts),
                'conversation_unread' => collect($unreadCounts)
                    ->map(fn (int $count, int|string $conversationId) => [
                        'conversation_id' => (int) $conversationId,
                        'unread_count' => $count,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function markRead(Conversation $conversation, Request $request): JsonResponse
    {
        $viewerId = $request->user()->id;
        $this->ensureAccess($conversation, $viewerId);
        $this->markConversationAsRead($conversation, $viewerId);

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'unread_count' => 0,
            ],
        ]);
    }

    public function storeMessage(Conversation $conversation, Request $request): JsonResponse
    {
        $viewer = $request->user();

        $this->ensureAccess($conversation, $viewer->id);

        if ($conversation->type === Conversation::TYPE_DIRECT) {
            $participantIds = $this->getDirectParticipantIds($conversation);
            if (count($participantIds) === 2) {
                $block = $this->findActiveBlockBetween($participantIds[0], $participantIds[1]);
                if ($block) {
                    return response()->json([
                        'message' => 'This direct chat is blocked. Unblock user to continue messaging.',
                        'data' => [
                            'blocked_by_user_id' => $block->blocker_id,
                            'blocked_user_id' => $block->blocked_user_id,
                            'expires_at' => $block->expires_at?->toIso8601String(),
                        ],
                    ], 423);
                }
            }
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:6'],
            'files.*' => [
                'file',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$value instanceof UploadedFile || !$this->isValidChatAttachmentFile($value)) {
                        $fail("The {$attribute} must be a valid image, video, or audio file.");
                        return;
                    }

                    if ((int) ($value->getSize() ?? 0) <= 0) {
                        $fail("The {$attribute} must not be empty.");
                    }
                },
                'max:204800',
            ],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $uploadedFiles = $request->file('files', []);

        if ($uploadedFiles instanceof UploadedFile) {
            $uploadedFiles = [$uploadedFiles];
        }

        if ($body === '' && empty($uploadedFiles)) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    'body' => ['Body is required when no attachments are provided.'],
                ],
            ], 422);
        }

        $message = DB::transaction(function () use ($conversation, $viewer, $body, $uploadedFiles) {
            $createdMessage = $conversation->messages()->create([
                'user_id' => $viewer->id,
                'body' => $body !== '' ? $body : null,
            ]);

            foreach ($uploadedFiles as $file) {
                $this->storeAttachment($createdMessage, $file, $viewer);
            }

            return $createdMessage;
        });

        $this->markConversationAsRead($conversation, $viewer->id);

        $conversation->touch();
        $message->load(['user:id,name,nickname,avatar_path,is_admin', 'attachments']);
        broadcast(new ConversationMessageSent($message))->toOthers();

        return response()->json([
            'data' => $this->messagePayload($message),
        ], 201);
    }

    protected function storeAttachment(ConversationMessage $message, UploadedFile $file, User $viewer): void
    {
        $mimeType = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $clientMimeType = strtolower((string) ($file->getClientMimeType() ?: ''));
        $originalName = strtolower($file->getClientOriginalName());

        if ($mimeType === 'image/gif' || $clientMimeType === 'image/gif' || str_ends_with($originalName, '.gif')) {
            $type = ConversationMessageAttachment::TYPE_GIF;
            $folder = 'chat/gifs';
        } elseif (
            str_starts_with($mimeType, 'audio/')
            || str_starts_with($clientMimeType, 'audio/')
            || $this->nameHasExtension($originalName, ['.mp3', '.wav', '.ogg', '.m4a', '.aac', '.opus', '.weba'])
            || (str_starts_with($originalName, 'voice-') && $this->nameHasExtension($originalName, ['.webm']))
        ) {
            $type = ConversationMessageAttachment::TYPE_AUDIO;
            $folder = 'chat/audio';
        } elseif (
            str_starts_with($mimeType, 'video/')
            || str_starts_with($clientMimeType, 'video/')
            || $this->nameHasExtension($originalName, ['.mp4', '.webm', '.mov', '.m4v', '.avi'])
        ) {
            $type = ConversationMessageAttachment::TYPE_VIDEO;
            $folder = 'chat/videos';
        } else {
            $type = ConversationMessageAttachment::TYPE_IMAGE;
            $folder = 'chat/images';
        }

        $disk = $this->siteSettingService->resolveMediaDiskForUser($viewer);

        try {
            $path = $file->store($folder, $disk);
        } catch (\Throwable) {
            $disk = 'public';
            $path = $file->store($folder, $disk);
        }

        $message->attachments()->create([
            'path' => $path,
            'storage_disk' => $disk,
            'type' => $type,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    protected function nameHasExtension(string $name, array $extensions): bool
    {
        foreach ($extensions as $extension) {
            if (str_ends_with($name, $extension)) {
                return true;
            }
        }

        return false;
    }

    protected function isValidChatAttachmentFile(UploadedFile $file): bool
    {
        $allowedExtensions = [
            'jpg',
            'jpeg',
            'png',
            'webp',
            'gif',
            'mp4',
            'webm',
            'mov',
            'm4v',
            'avi',
            'mp3',
            'wav',
            'ogg',
            'm4a',
            'aac',
            'weba',
            'opus',
        ];

        $clientExtension = strtolower((string) $file->getClientOriginalExtension());
        $guessedExtension = strtolower((string) ($file->guessExtension() ?? ''));

        $hasAllowedExtension = in_array($clientExtension, $allowedExtensions, true)
            || in_array($guessedExtension, $allowedExtensions, true);

        $serverMimeType = strtolower((string) ($file->getMimeType() ?? ''));
        $clientMimeType = strtolower((string) ($file->getClientMimeType() ?? ''));

        $hasAllowedMime = $this->isAllowedChatAttachmentMime($serverMimeType)
            || $this->isAllowedChatAttachmentMime($clientMimeType);

        if ($hasAllowedMime) {
            return true;
        }

        if ($hasAllowedExtension && ($serverMimeType === '' || $clientMimeType === '')) {
            return true;
        }

        $hasBinaryMime = $this->isBinaryMimeType($serverMimeType)
            || $this->isBinaryMimeType($clientMimeType);

        return $hasBinaryMime && $hasAllowedExtension;
    }

    protected function isAllowedChatAttachmentMime(string $mimeType): bool
    {
        if ($mimeType === '') {
            return false;
        }

        return str_starts_with($mimeType, 'image/')
            || str_starts_with($mimeType, 'video/')
            || str_starts_with($mimeType, 'audio/')
            || $mimeType === 'application/ogg';
    }

    protected function isBinaryMimeType(string $mimeType): bool
    {
        return in_array($mimeType, ['application/octet-stream', 'binary/octet-stream'], true);
    }

    protected function ensureParticipantRecords(Collection $conversations, int $userId, ?int $globalConversationId = null): void
    {
        if ($conversations->isEmpty()) {
            return;
        }

        $conversationIds = $conversations->pluck('id')->map(fn ($id) => (int) $id)->values();
        $existingConversationIds = ConversationParticipant::query()
            ->where('user_id', $userId)
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('conversation_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $now = now();
        $rows = [];

        foreach ($conversations as $conversation) {
            if ($existingConversationIds->has((int) $conversation->id)) {
                continue;
            }

            $rows[] = [
                'conversation_id' => (int) $conversation->id,
                'user_id' => $userId,
                'last_read_at' => ($globalConversationId !== null && (int) $conversation->id === $globalConversationId) ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            ConversationParticipant::query()->insert($rows);
        }
    }

    protected function resolveUnreadCounts(iterable $conversationIds, int $userId): array
    {
        $ids = collect($conversationIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $rows = DB::table('conversation_messages as messages')
            ->leftJoin('conversation_participants as participant', function ($join) use ($userId) {
                $join->on('participant.conversation_id', '=', 'messages.conversation_id')
                    ->where('participant.user_id', '=', $userId);
            })
            ->selectRaw('messages.conversation_id as conversation_id, COUNT(*) as unread_count')
            ->whereIn('messages.conversation_id', $ids)
            ->where('messages.user_id', '!=', $userId)
            ->where(function ($query) {
                $query->whereNull('participant.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'participant.last_read_at');
            })
            ->groupBy('messages.conversation_id')
            ->get();

        $counts = $ids->mapWithKeys(fn (int $id) => [$id => 0])->all();

        foreach ($rows as $row) {
            $counts[(int) $row->conversation_id] = (int) $row->unread_count;
        }

        return $counts;
    }

    protected function markConversationAsRead(Conversation $conversation, int $userId): void
    {
        ConversationParticipant::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
            ],
            [
                'last_read_at' => now(),
            ]
        );
    }

    protected function ensureAccess(Conversation $conversation, int $userId): void
    {
        if (!$conversation->isAccessibleBy($userId)) {
            abort(403, 'Access denied to this conversation.');
        }
    }

    protected function getDirectParticipantIds(Conversation $conversation): array
    {
        if ($conversation->relationLoaded('participants')) {
            return $conversation->participants->pluck('id')->values()->all();
        }

        return $conversation->participants()->pluck('users.id')->values()->all();
    }

    protected function findActiveBlockBetween(int $firstUserId, int $secondUserId): ?UserBlock
    {
        return UserBlock::query()
            ->where(function ($builder) use ($firstUserId, $secondUserId) {
                $builder->where(function ($pairQuery) use ($firstUserId, $secondUserId) {
                    $pairQuery->where('blocker_id', $firstUserId)
                        ->where('blocked_user_id', $secondUserId);
                })->orWhere(function ($pairQuery) use ($firstUserId, $secondUserId) {
                    $pairQuery->where('blocker_id', $secondUserId)
                        ->where('blocked_user_id', $firstUserId);
                });
            })
            ->active()
            ->latest('id')
            ->first();
    }

    protected function conversationPayload(Conversation $conversation, int $viewerId, int $unreadCount = 0): array
    {
        $participants = $conversation->relationLoaded('participants')
            ? $conversation->participants
            : collect();

        $lastMessage = $conversation->relationLoaded('lastMessage')
            ? $conversation->lastMessage
            : null;

        $otherParticipant = $participants->first(fn (User $user) => $user->id !== $viewerId);

        $title = $conversation->title;
        if (!$title) {
            $title = $conversation->type === Conversation::TYPE_GLOBAL
                ? 'Общий чат'
                : (($otherParticipant?->display_name ?? $otherParticipant?->name) ?? 'Личный чат');
        }

        $isBlocked = false;
        $blockedByUserId = null;

        if ($conversation->type === Conversation::TYPE_DIRECT) {
            $participantIds = $participants->pluck('id')->values()->all();
            if (count($participantIds) === 2) {
                $block = $this->findActiveBlockBetween($participantIds[0], $participantIds[1]);
                if ($block) {
                    $isBlocked = true;
                    $blockedByUserId = $block->blocker_id;
                }
            }
        }

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $title,
            'is_blocked' => $isBlocked,
            'blocked_by_user_id' => $blockedByUserId,
            'unread_count' => max(0, $unreadCount),
            'has_unread' => $unreadCount > 0,
            'participants' => $participants->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name,
                'nickname' => $user->nickname,
                'avatar_url' => $user->avatar_url,
                'is_admin' => (bool) $user->is_admin,
            ])->values(),
            'last_message' => $lastMessage ? $this->messagePayload($lastMessage) : null,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    protected function messagePayload(ConversationMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => (string) ($message->body ?? ''),
            'date' => $message->date,
            'created_at' => $message->created_at?->toIso8601String(),
            'user' => $message->relationLoaded('user') ? [
                'id' => $message->user->id,
                'name' => $message->user->name,
                'display_name' => $message->user->display_name,
                'nickname' => $message->user->nickname,
                'avatar_url' => $message->user->avatar_url,
                'is_admin' => (bool) $message->user->is_admin,
            ] : null,
            'attachments' => $message->relationLoaded('attachments')
                ? $message->attachments->map(fn (ConversationMessageAttachment $attachment) => [
                    'id' => $attachment->id,
                    'type' => $attachment->type,
                    'mime_type' => $attachment->mime_type,
                    'size' => (int) ($attachment->size ?? 0),
                    'original_name' => $attachment->original_name,
                    'url' => $attachment->url,
                ])->values()
                : [],
        ];
    }
}
