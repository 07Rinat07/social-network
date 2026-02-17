<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Solid Social API",
 *     version="1.0.0",
 *     description="API documentation for Solid Social Network SPA"
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="Current host"
 * )
 *
 * @OA\Tag(
 *     name="Public",
 *     description="Public endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Radio",
 *     description="Radio features"
 * )
 *
 * @OA\Tag(
 *     name="Chat",
 *     description="Chat features"
 * )
 *
 * @OA\Tag(
 *     name="IPTV",
 *     description="IPTV features"
 * )
 *
 * @OA\Tag(
 *     name="Admin Chat",
 *     description="Admin chat moderation endpoints"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctumCookie",
 *     type="apiKey",
 *     in="cookie",
 *     name="laravel_session",
 *     description="Sanctum stateful session cookie"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="xsrfHeader",
 *     type="apiKey",
 *     in="header",
 *     name="X-XSRF-TOKEN",
 *     description="CSRF header for state-changing requests"
 * )
 */
class OpenApiSpec
{
    /**
     * @OA\Get(
     *     path="/api/site/home-content",
     *     operationId="getHomeContent",
     *     tags={"Public"},
     *     summary="Get localized content for home page",
     *     @OA\Response(
     *         response=200,
     *         description="Home content payload"
     *     )
     * )
     */
    public function getHomeContent(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/feedback",
     *     operationId="storeFeedback",
     *     tags={"Public"},
     *     summary="Create feedback message",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","message"},
     *             @OA\Property(property="name", type="string", example="Alex"),
     *             @OA\Property(property="email", type="string", format="email", example="alex@example.com"),
     *             @OA\Property(property="message", type="string", example="Great project!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Feedback stored"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function storeFeedback(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/radio/stations",
     *     operationId="getRadioStations",
     *     tags={"Radio"},
     *     summary="Search radio stations",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="country", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="language", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="tag", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="offset", in="query", required=false, @OA\Schema(type="integer", minimum=0)),
     *     @OA\Response(
     *         response=200,
     *         description="List of stations"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getRadioStations(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/chats/unread-summary",
     *     operationId="getChatUnreadSummary",
     *     tags={"Chat"},
     *     summary="Get unread chat counters",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread counters"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getChatUnreadSummary(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/chats",
     *     operationId="getChatsIndex",
     *     tags={"Chat"},
     *     summary="Get available conversations for current user",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Conversation list"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getChatsIndex(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/chats/direct/{user}",
     *     operationId="createOrGetDirectChat",
     *     tags={"Chat"},
     *     summary="Create or return existing direct chat with target user",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="Target user id",
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversation payload"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid target (for example, current user)"
     *     ),
     *     @OA\Response(
     *         response=423,
     *         description="Direct chat is blocked between users"
     *     )
     * )
     */
    public function createOrGetDirectChat(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/chats/{conversation}",
     *     operationId="getChatConversation",
     *     tags={"Chat"},
     *     summary="Get conversation details including participants and mood statuses",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(
     *         name="conversation",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversation details"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No access to this conversation"
     *     )
     * )
     */
    public function getChatConversation(): void
    {
    }

    /**
     * @OA\Patch(
     *     path="/api/chats/{conversation}/mood-status",
     *     operationId="upsertMoodStatus",
     *     tags={"Chat"},
     *     summary="Create/update own mood status in conversation. Empty text removes status.",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(
     *         name="conversation",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_visible_to_all"},
     *             @OA\Property(property="text", type="string", nullable=true, maxLength=500, example="Сегодня в хорошем настроении."),
     *             @OA\Property(property="is_visible_to_all", type="boolean", example=true),
     *             @OA\Property(
     *                 property="hidden_user_ids",
     *                 type="array",
     *                 @OA\Items(type="integer", minimum=1),
     *                 example={12, 15}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Mood status saved"),
     *     @OA\Response(response=403, description="No access to this conversation"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function upsertMoodStatus(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/chats/{conversation}/messages",
     *     operationId="getChatMessages",
     *     tags={"Chat"},
     *     summary="Get paginated messages for conversation and mark conversation as read",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="Message list"),
     *     @OA\Response(response=403, description="No access to this conversation")
     * )
     */
    public function getChatMessages(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/chats/{conversation}/messages",
     *     operationId="storeChatMessage",
     *     tags={"Chat"},
     *     summary="Send message with optional text/files to conversation",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="body", type="string", example="Привет!")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Message created"),
     *     @OA\Response(response=403, description="No access to this conversation"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=423, description="Conversation is blocked")
     * )
     */
    public function storeChatMessage(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/chats/{conversation}/messages/{message}/reactions",
     *     operationId="toggleChatMessageReaction",
     *     tags={"Chat"},
     *     summary="Toggle emoji reaction on message",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="message", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"emoji"},
     *             @OA\Property(property="emoji", type="string", example="👍")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reaction toggled"),
     *     @OA\Response(response=403, description="No access to this conversation"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function toggleChatMessageReaction(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/chats/{conversation}/messages/{message}",
     *     operationId="deleteChatMessage",
     *     tags={"Chat"},
     *     summary="Delete message (author or admin)",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="message", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Message deleted"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Message not found in conversation")
     * )
     */
    public function deleteChatMessage(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/chats/{conversation}/messages/{message}/attachments/{attachment}",
     *     operationId="deleteChatMessageAttachment",
     *     tags={"Chat"},
     *     summary="Delete message attachment (author or admin)",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="message", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="attachment", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Attachment deleted"),
     *     @OA\Response(response=403, description="Access denied"),
     *     @OA\Response(response=404, description="Attachment not found in message")
     * )
     */
    public function deleteChatMessageAttachment(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/conversations",
     *     operationId="adminGetConversations",
     *     tags={"Admin Chat"},
     *     summary="Admin: list conversations with participants and messages count",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="Conversation list"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function adminGetConversations(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/conversations/{conversation}/messages",
     *     operationId="adminClearConversationMessages",
     *     tags={"Admin Chat"},
     *     summary="Admin: clear all messages in a specific conversation",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Conversation messages cleared"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required"),
     *     @OA\Response(response=404, description="Conversation not found")
     * )
     */
    public function adminClearConversationMessages(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/conversations/{conversation}",
     *     operationId="adminDeleteConversation",
     *     tags={"Admin Chat"},
     *     summary="Admin: delete a conversation with all related messages/attachments",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Conversation deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required"),
     *     @OA\Response(response=404, description="Conversation not found")
     * )
     */
    public function adminDeleteConversation(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/conversations/messages",
     *     operationId="adminClearAllConversationMessages",
     *     tags={"Admin Chat"},
     *     summary="Admin: clear messages in all conversations",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(response=200, description="All conversation messages cleared"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function adminClearAllConversationMessages(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/conversations",
     *     operationId="adminDeleteAllConversations",
     *     tags={"Admin Chat"},
     *     summary="Admin: delete all conversations",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(response=200, description="All conversations deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function adminDeleteAllConversations(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/messages",
     *     operationId="adminGetMessages",
     *     tags={"Admin Chat"},
     *     summary="Admin: list chat messages with optional conversation filter",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="conversation_id", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=200)),
     *     @OA\Response(response=200, description="Message list"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function adminGetMessages(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/messages/{message}",
     *     operationId="adminDeleteMessage",
     *     tags={"Admin Chat"},
     *     summary="Admin: delete specific chat message and its attachments",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="message", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Message deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required"),
     *     @OA\Response(response=404, description="Message not found")
     * )
     */
    public function adminDeleteMessage(): void
    {
    }
}
