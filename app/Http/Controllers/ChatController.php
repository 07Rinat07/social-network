<?php

namespace App\Http\Controllers;

use App\Events\ConversationMessageSent;
use App\Models\ChatArchive;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\ConversationMessageReaction;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Models\UserChatSetting;
use App\Models\UserBlock;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    protected const CHAT_MESSAGE_REACTION_EMOJIS = ['👍', '❤️', '🔥', '😂', '👏', '😮'];
    protected const CHAT_ARCHIVE_SCOPE_ALL = 'all';
    protected const CHAT_ARCHIVE_SCOPE_CONVERSATION = 'conversation';
    protected const CHAT_STORAGE_MAX_RETENTION_DAYS = 3650;

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
                'lastMessage.reactions',
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

    public function settings(Request $request): JsonResponse
    {
        $settings = $this->resolveUserChatSetting($request->user());

        return response()->json([
            'data' => $this->chatSettingPayload($settings),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'save_text_messages' => ['required', 'boolean'],
            'save_media_attachments' => ['required', 'boolean'],
            'save_file_attachments' => ['required', 'boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:' . self::CHAT_STORAGE_MAX_RETENTION_DAYS],
            'auto_archive_enabled' => ['required', 'boolean'],
        ]);

        $settings = $this->resolveUserChatSetting($request->user());
        $settings->fill([
            'save_text_messages' => (bool) $validated['save_text_messages'],
            'save_media_attachments' => (bool) $validated['save_media_attachments'],
            'save_file_attachments' => (bool) $validated['save_file_attachments'],
            'retention_days' => $validated['retention_days'] ?? null,
            'auto_archive_enabled' => (bool) $validated['auto_archive_enabled'],
        ]);
        $settings->save();

        return response()->json([
            'message' => 'Chat storage settings updated.',
            'data' => $this->chatSettingPayload($settings),
        ]);
    }

    public function archives(Request $request): JsonResponse
    {
        $user = $request->user();

        $archives = ChatArchive::query()
            ->where('user_id', $user->id)
            ->with([
                'conversation:id,title,type',
                'restoredConversation:id,title,type',
            ])
            ->latest('id')
            ->limit(40)
            ->get();

        return response()->json([
            'data' => $archives
                ->map(fn (ChatArchive $archive) => $this->chatArchivePayload($archive))
                ->values(),
        ]);
    }

    public function createArchive(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $this->resolveUserChatSetting($user);

        $validated = $request->validate([
            'scope' => ['required', 'string', Rule::in([self::CHAT_ARCHIVE_SCOPE_ALL, self::CHAT_ARCHIVE_SCOPE_CONVERSATION])],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $scope = (string) $validated['scope'];
        $targetConversation = null;
        if ($scope === self::CHAT_ARCHIVE_SCOPE_CONVERSATION) {
            $conversationId = (int) ($validated['conversation_id'] ?? 0);
            if ($conversationId <= 0) {
                return response()->json([
                    'message' => 'Conversation id is required for conversation archive.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $targetConversation = Conversation::query()->findOrFail($conversationId);
            $this->ensureAccess($targetConversation, $user->id);
            $targetConversation->loadMissing('participants:id,name,nickname,avatar_path,is_admin');
        }

        if ($scope === self::CHAT_ARCHIVE_SCOPE_ALL) {
            $globalConversation = Conversation::query()->firstOrCreate(
                ['type' => Conversation::TYPE_GLOBAL],
                [
                    'title' => 'Общий чат',
                    'created_by' => $user->id,
                ]
            );

            $conversations = Conversation::query()
                ->forUser($user->id)
                ->where('type', '!=', Conversation::TYPE_ARCHIVE)
                ->with('participants:id,name,nickname,avatar_path,is_admin')
                ->orderBy('updated_at', 'desc')
                ->get();

            $this->ensureParticipantRecords($conversations, $user->id, $globalConversation->id);
        } else {
            $conversations = collect([$targetConversation]);
        }

        $retentionBorder = $settings->retention_days !== null
            ? now()->subDays((int) $settings->retention_days)
            : null;

        $messagesCount = 0;
        $payloadConversations = [];

        foreach ($conversations as $conversation) {
            $messagesQuery = $conversation->messages()
                ->with([
                    'user:id,name,nickname,avatar_path,is_admin',
                    'attachments',
                    'reactions',
                ])
                ->orderBy('id');

            if ($retentionBorder !== null) {
                $messagesQuery->where('created_at', '>=', $retentionBorder);
            }

            $messages = $messagesQuery->get();
            $messagesPayload = [];

            foreach ($messages as $message) {
                $basePayload = $this->messagePayload($message, $user->id);
                $preparedPayload = $this->applyArchiveStorageRules($basePayload, $settings);
                if ($preparedPayload === null) {
                    continue;
                }

                $preparedPayload['attachments'] = collect($preparedPayload['attachments'])
                    ->map(function (array $attachment) use ($message): array {
                        $source = $message->attachments->firstWhere('id', (int) ($attachment['id'] ?? 0));

                        return [
                            ...$attachment,
                            'path' => $source?->path,
                            'storage_disk' => $source?->storage_disk,
                        ];
                    })
                    ->values()
                    ->all();

                $messagesPayload[] = $preparedPayload;
                $messagesCount++;
            }

            $payloadConversations[] = [
                'id' => (int) $conversation->id,
                'type' => (string) $conversation->type,
                'title' => (string) ($conversation->title ?: ($conversation->type === Conversation::TYPE_GLOBAL ? 'Общий чат' : 'Личный чат')),
                'participants' => $conversation->participants
                    ->map(fn (User $participant) => [
                        'id' => (int) $participant->id,
                        'display_name' => (string) $participant->display_name,
                        'nickname' => $participant->nickname,
                    ])
                    ->values()
                    ->all(),
                'messages' => $messagesPayload,
            ];
        }

        $archive = ChatArchive::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $scope === self::CHAT_ARCHIVE_SCOPE_CONVERSATION ? $targetConversation?->id : null,
            'scope' => $scope,
            'title' => $this->buildArchiveTitle($scope, $targetConversation),
            'payload' => [
                'generated_at' => now()->toIso8601String(),
                'scope' => $scope,
                'settings' => $this->chatSettingPayload($settings),
                'conversations' => $payloadConversations,
            ],
            'messages_count' => $messagesCount,
        ]);

        $archive->load(['conversation:id,title,type', 'restoredConversation:id,title,type']);

        return response()->json([
            'message' => 'Chat archive created successfully.',
            'data' => $this->chatArchivePayload($archive),
        ], Response::HTTP_CREATED);
    }

    public function downloadArchive(ChatArchive $archive, Request $request): StreamedResponse
    {
        $this->ensureArchiveAccess($archive, $request->user()->id);

        $payload = $archive->payload;
        $fileName = sprintf(
            'chat-archive-%d-%s.json',
            (int) $archive->id,
            $archive->created_at?->format('Ymd-His') ?: now()->format('Ymd-His')
        );

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            },
            $fileName,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]
        );
    }

    public function restoreArchive(ChatArchive $archive, Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureArchiveAccess($archive, $user->id);

        $payload = $archive->payload;
        $conversations = is_array($payload['conversations'] ?? null) ? $payload['conversations'] : null;
        if ($conversations === null) {
            return response()->json([
                'message' => 'Archive payload is invalid or empty.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $restoredConversation = DB::transaction(function () use ($user, $archive, $conversations): Conversation {
            $created = Conversation::query()->create([
                'type' => Conversation::TYPE_ARCHIVE,
                'title' => sprintf(
                    'Восстановленный архив #%d (%s)',
                    (int) $archive->id,
                    now()->format('d.m.Y H:i')
                ),
                'created_by' => $user->id,
            ]);

            $created->participants()->sync([$user->id]);

            foreach ($conversations as $conversationData) {
                if (!is_array($conversationData)) {
                    continue;
                }

                $sectionTitle = trim((string) ($conversationData['title'] ?? 'Чат'));
                if ($sectionTitle !== '') {
                    $created->messages()->create([
                        'user_id' => $user->id,
                        'body' => sprintf('=== Архив: %s ===', $sectionTitle),
                    ]);
                }

                $messages = is_array($conversationData['messages'] ?? null) ? $conversationData['messages'] : [];
                foreach ($messages as $messageData) {
                    if (!is_array($messageData)) {
                        continue;
                    }

                    $authorName = trim((string) ($messageData['user']['display_name'] ?? $messageData['user']['name'] ?? 'Пользователь'));
                    $createdAt = trim((string) ($messageData['created_at'] ?? ''));
                    $body = trim((string) ($messageData['body'] ?? ''));

                    $metaLine = '[Архив] ' . ($authorName !== '' ? $authorName : 'Пользователь');
                    if ($createdAt !== '') {
                        $metaLine .= ' · ' . $createdAt;
                    }

                    $restoredBody = $body !== '' ? $metaLine . "\n" . $body : $metaLine;

                    $restoredMessage = $created->messages()->create([
                        'user_id' => $user->id,
                        'body' => $restoredBody,
                    ]);

                    $attachments = is_array($messageData['attachments'] ?? null) ? $messageData['attachments'] : [];
                    foreach ($attachments as $attachmentData) {
                        if (!is_array($attachmentData)) {
                            continue;
                        }

                        $restoredAttachment = $this->restoreArchiveAttachment($attachmentData, $user);
                        if ($restoredAttachment === null) {
                            continue;
                        }

                        $restoredMessage->attachments()->create($restoredAttachment);
                    }
                }
            }

            return $created;
        });

        $archive->forceFill([
            'restored_at' => now(),
            'restored_conversation_id' => $restoredConversation->id,
        ])->save();

        $restoredConversation->load([
            'participants:id,name,nickname,avatar_path,is_admin',
            'lastMessage.user:id,name,nickname,avatar_path,is_admin',
            'lastMessage.attachments',
            'lastMessage.reactions',
        ]);

        $this->markConversationAsRead($restoredConversation, $user->id);
        $unreadCounts = $this->resolveUnreadCounts([$restoredConversation->id], $user->id);

        return response()->json([
            'message' => 'Archive restored to a new chat.',
            'data' => [
                'archive' => $this->chatArchivePayload($archive->fresh(['conversation:id,title,type', 'restoredConversation:id,title,type'])),
                'conversation' => $this->conversationPayload(
                    $restoredConversation,
                    $user->id,
                    (int) ($unreadCounts[$restoredConversation->id] ?? 0)
                ),
            ],
        ]);
    }

    protected function resolveUserChatSetting(User $user): UserChatSetting
    {
        return UserChatSetting::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'save_text_messages' => true,
                'save_media_attachments' => true,
                'save_file_attachments' => true,
                'retention_days' => null,
                'auto_archive_enabled' => true,
            ]
        );
    }

    protected function chatSettingPayload(UserChatSetting $settings): array
    {
        return [
            'save_text_messages' => (bool) $settings->save_text_messages,
            'save_media_attachments' => (bool) $settings->save_media_attachments,
            'save_file_attachments' => (bool) $settings->save_file_attachments,
            'retention_days' => $settings->retention_days !== null ? (int) $settings->retention_days : null,
            'auto_archive_enabled' => (bool) $settings->auto_archive_enabled,
            'max_retention_days' => self::CHAT_STORAGE_MAX_RETENTION_DAYS,
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }

    protected function chatArchivePayload(ChatArchive $archive): array
    {
        $payload = is_array($archive->payload) ? $archive->payload : [];
        $payloadConversations = is_array($payload['conversations'] ?? null) ? $payload['conversations'] : [];

        return [
            'id' => (int) $archive->id,
            'scope' => (string) $archive->scope,
            'title' => (string) ($archive->title ?: 'Архив чата'),
            'messages_count' => (int) $archive->messages_count,
            'conversations_count' => count($payloadConversations),
            'generated_at' => (string) ($payload['generated_at'] ?? $archive->created_at?->toIso8601String()),
            'created_at' => $archive->created_at?->toIso8601String(),
            'restored_at' => $archive->restored_at?->toIso8601String(),
            'conversation' => $archive->relationLoaded('conversation') && $archive->conversation
                ? [
                    'id' => (int) $archive->conversation->id,
                    'title' => (string) ($archive->conversation->title ?: 'Чат'),
                    'type' => (string) $archive->conversation->type,
                ]
                : null,
            'restored_conversation' => $archive->relationLoaded('restoredConversation') && $archive->restoredConversation
                ? [
                    'id' => (int) $archive->restoredConversation->id,
                    'title' => (string) ($archive->restoredConversation->title ?: 'Восстановленный архив'),
                    'type' => (string) $archive->restoredConversation->type,
                ]
                : null,
        ];
    }

    protected function applyArchiveStorageRules(array $messagePayload, UserChatSetting $settings): ?array
    {
        $prepared = $messagePayload;

        $body = trim((string) ($prepared['body'] ?? ''));
        if (!$settings->save_text_messages) {
            $body = '';
        }

        $attachments = collect($prepared['attachments'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->filter(function (array $attachment) use ($settings): bool {
                $type = (string) ($attachment['type'] ?? '');

                if ($type === ConversationMessageAttachment::TYPE_FILE) {
                    return (bool) $settings->save_file_attachments;
                }

                return (bool) $settings->save_media_attachments;
            })
            ->values()
            ->all();

        if ($body === '' && $attachments === []) {
            return null;
        }

        $prepared['body'] = $body;
        $prepared['attachments'] = $attachments;

        return $prepared;
    }

    protected function buildArchiveTitle(string $scope, ?Conversation $conversation = null): string
    {
        $timestamp = now()->format('d.m.Y H:i');

        if ($scope === self::CHAT_ARCHIVE_SCOPE_CONVERSATION && $conversation) {
            $title = trim((string) ($conversation->title ?? ''));
            if ($title === '') {
                $title = $conversation->type === Conversation::TYPE_GLOBAL ? 'Общий чат' : 'Личный чат';
            }

            return sprintf('Архив "%s" от %s', $title, $timestamp);
        }

        return sprintf('Архив всех чатов от %s', $timestamp);
    }

    protected function ensureArchiveAccess(ChatArchive $archive, int $userId): void
    {
        if ((int) $archive->user_id !== $userId) {
            abort(403, 'Access denied to this archive.');
        }
    }

    protected function restoreArchiveAttachment(array $attachmentData, User $viewer): ?array
    {
        $sourcePath = trim((string) ($attachmentData['path'] ?? ''));
        if ($sourcePath === '') {
            return null;
        }

        $sourceDisk = trim((string) ($attachmentData['storage_disk'] ?? 'public'));
        if ($sourceDisk === '') {
            $sourceDisk = 'public';
        }

        $type = trim((string) ($attachmentData['type'] ?? ConversationMessageAttachment::TYPE_FILE));
        if (!in_array($type, [
            ConversationMessageAttachment::TYPE_IMAGE,
            ConversationMessageAttachment::TYPE_VIDEO,
            ConversationMessageAttachment::TYPE_GIF,
            ConversationMessageAttachment::TYPE_AUDIO,
            ConversationMessageAttachment::TYPE_FILE,
        ], true)) {
            $type = ConversationMessageAttachment::TYPE_FILE;
        }

        try {
            if (!Storage::disk($sourceDisk)->exists($sourcePath)) {
                return null;
            }

            $content = Storage::disk($sourceDisk)->get($sourcePath);
        } catch (\Throwable) {
            return null;
        }

        $preferredDisk = $this->siteSettingService->resolveMediaDiskForUser($viewer);
        $targetDisk = $preferredDisk ?: 'public';
        $targetFolder = match ($type) {
            ConversationMessageAttachment::TYPE_IMAGE => 'chat/images',
            ConversationMessageAttachment::TYPE_VIDEO => 'chat/videos',
            ConversationMessageAttachment::TYPE_GIF => 'chat/gifs',
            ConversationMessageAttachment::TYPE_AUDIO => 'chat/audio',
            default => 'chat/files',
        };

        $originalName = trim((string) ($attachmentData['original_name'] ?? 'archive-file'));
        if ($originalName === '') {
            $originalName = 'archive-file';
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $fileName = 'archive-' . now()->format('YmdHis') . '-' . Str::random(12);
        if ($extension !== '') {
            $fileName .= '.' . $extension;
        }

        $targetPath = $targetFolder . '/' . $fileName;

        try {
            Storage::disk($targetDisk)->put($targetPath, $content);
        } catch (\Throwable) {
            $targetDisk = 'public';
            try {
                Storage::disk($targetDisk)->put($targetPath, $content);
            } catch (\Throwable) {
                return null;
            }
        }

        $size = (int) ($attachmentData['size'] ?? 0);
        if ($size <= 0) {
            $size = strlen($content);
        }

        return [
            'path' => $targetPath,
            'storage_disk' => $targetDisk,
            'type' => $type,
            'mime_type' => (string) ($attachmentData['mime_type'] ?? ''),
            'size' => $size,
            'original_name' => $originalName,
        ];
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
            'lastMessage.reactions',
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
            'lastMessage.reactions',
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
            ->with(['user:id,name,nickname,avatar_path,is_admin', 'attachments', 'reactions'])
            ->latest('id')
            ->paginate($perPage);

        $items = collect($messages->items())
            ->reverse()
            ->map(fn (ConversationMessage $message) => $this->messagePayload($message, $viewerId))
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
                        $fail("The {$attribute} must be a valid chat attachment file.");
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
        $message->load(['user:id,name,nickname,avatar_path,is_admin', 'attachments', 'reactions']);
        broadcast(new ConversationMessageSent($message))->toOthers();

        return response()->json([
            'data' => $this->messagePayload($message, $viewer->id),
        ], 201);
    }

    public function toggleMessageReaction(
        Conversation $conversation,
        ConversationMessage $message,
        Request $request
    ): JsonResponse {
        $viewer = $request->user();

        $this->ensureAccess($conversation, $viewer->id);

        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }

        $validated = $request->validate([
            'emoji' => [
                'required',
                'string',
                'max:32',
                Rule::in(self::CHAT_MESSAGE_REACTION_EMOJIS),
            ],
        ]);

        $emoji = (string) $validated['emoji'];

        $existingReaction = ConversationMessageReaction::query()
            ->where('conversation_message_id', $message->id)
            ->where('user_id', $viewer->id)
            ->where('emoji', $emoji)
            ->first();

        $reacted = false;
        if ($existingReaction) {
            $existingReaction->delete();
        } else {
            ConversationMessageReaction::query()->create([
                'conversation_message_id' => $message->id,
                'user_id' => $viewer->id,
                'emoji' => $emoji,
            ]);
            $reacted = true;
        }

        $message->load(['user:id,name,nickname,avatar_path,is_admin', 'attachments', 'reactions']);

        return response()->json([
            'data' => [
                'conversation_id' => (int) $conversation->id,
                'message_id' => (int) $message->id,
                'emoji' => $emoji,
                'reacted' => $reacted,
                'available_emojis' => self::CHAT_MESSAGE_REACTION_EMOJIS,
                'message' => $this->messagePayload($message, $viewer->id),
            ],
        ]);
    }

    public function destroyMessage(Conversation $conversation, ConversationMessage $message, Request $request): JsonResponse
    {
        $viewer = $request->user();

        $this->ensureAccess($conversation, $viewer->id);

        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }

        $canDelete = (int) $message->user_id === (int) $viewer->id;
        if (!$canDelete) {
            return response()->json([
                'message' => 'Access denied to delete this message.',
            ], 403);
        }

        $attachments = $message->attachments()->get();
        foreach ($attachments as $attachment) {
            Storage::disk($attachment->storage_disk ?: 'public')->delete($attachment->path);
        }

        $messageId = (int) $message->id;
        $conversationId = (int) $conversation->id;

        $message->delete();
        $this->syncConversationTimestampAfterDeletion($conversation);

        return response()->json([
            'data' => [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'message_deleted' => true,
            ],
        ]);
    }

    public function destroyMessageAttachment(
        Conversation $conversation,
        ConversationMessage $message,
        ConversationMessageAttachment $attachment,
        Request $request
    ): JsonResponse {
        $viewer = $request->user();

        $this->ensureAccess($conversation, $viewer->id);

        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }

        if ((int) $attachment->conversation_message_id !== (int) $message->id) {
            abort(404);
        }

        $canDelete = (int) $message->user_id === (int) $viewer->id;
        if (!$canDelete) {
            return response()->json([
                'message' => 'Access denied to delete this attachment.',
            ], 403);
        }

        Storage::disk($attachment->storage_disk ?: 'public')->delete($attachment->path);

        $attachmentId = (int) $attachment->id;
        $conversationId = (int) $conversation->id;
        $messageId = (int) $message->id;
        $attachment->delete();

        $message->refresh();
        $body = trim((string) ($message->body ?? ''));
        $remainingAttachments = $message->attachments()->count();
        $messageDeleted = false;

        if ($body === '' && $remainingAttachments === 0) {
            $message->delete();
            $this->syncConversationTimestampAfterDeletion($conversation);
            $messageDeleted = true;
        }

        return response()->json([
            'data' => [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'attachment_id' => $attachmentId,
                'message_deleted' => $messageDeleted,
                'remaining_attachments' => $messageDeleted ? 0 : (int) $remainingAttachments,
            ],
        ]);
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
        } elseif (
            str_starts_with($mimeType, 'image/')
            || str_starts_with($clientMimeType, 'image/')
            || $this->nameHasExtension($originalName, ['.jpg', '.jpeg', '.png', '.webp', '.bmp', '.svg'])
        ) {
            $type = ConversationMessageAttachment::TYPE_IMAGE;
            $folder = 'chat/images';
        } else {
            $type = ConversationMessageAttachment::TYPE_FILE;
            $folder = 'chat/files';
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
            'bmp',
            'svg',
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
            'pdf',
            'txt',
            'csv',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'rtf',
            'zip',
            'rar',
            '7z',
            'tar',
            'gz',
            'json',
            'xml',
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
            || in_array($mimeType, [
                'application/ogg',
                'application/pdf',
                'text/plain',
                'text/csv',
                'application/json',
                'application/xml',
                'text/xml',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/rtf',
                'application/zip',
                'application/x-zip-compressed',
                'application/x-rar-compressed',
                'application/vnd.rar',
                'application/x-7z-compressed',
                'application/x-tar',
                'application/gzip',
                'application/x-gzip',
            ], true);
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

    protected function syncConversationTimestampAfterDeletion(Conversation $conversation): void
    {
        $lastMessageCreatedAt = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->value('created_at');

        if ($lastMessageCreatedAt) {
            $conversation->forceFill([
                'updated_at' => $lastMessageCreatedAt,
            ])->saveQuietly();
            return;
        }

        $conversation->forceFill([
            'updated_at' => $conversation->created_at ?? now(),
        ])->saveQuietly();
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
            'last_message' => $lastMessage ? $this->messagePayload($lastMessage, $viewerId) : null,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    protected function messagePayload(ConversationMessage $message, ?int $viewerId = null): array
    {
        $reactionItems = $message->relationLoaded('reactions')
            ? $message->reactions
            : collect();

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
                    'download_url' => $attachment->download_url,
                ])->values()
                : [],
            'reactions' => $this->messageReactionsPayload($reactionItems, $viewerId),
        ];
    }

    protected function messageReactionsPayload(Collection $reactionItems, ?int $viewerId = null): array
    {
        if ($reactionItems->isEmpty()) {
            return [];
        }

        return $reactionItems
            ->groupBy(fn (ConversationMessageReaction $reaction) => (string) $reaction->emoji)
            ->map(function (Collection $items, string $emoji) use ($viewerId): array {
                $count = $items->count();
                $reactedByMe = $viewerId !== null
                    ? $items->contains(fn (ConversationMessageReaction $reaction) => (int) $reaction->user_id === $viewerId)
                    : false;

                return [
                    'emoji' => $emoji,
                    'count' => $count,
                    'reacted_by_me' => $reactedByMe,
                ];
            })
            ->sort(function (array $first, array $second): int {
                $countDiff = ((int) $second['count']) <=> ((int) $first['count']);
                if ($countDiff !== 0) {
                    return $countDiff;
                }

                return strcmp((string) $first['emoji'], (string) $second['emoji']);
            })
            ->values()
            ->all();
    }
}
