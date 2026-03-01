<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Solid Social API",
 *     version="1.4.0",
 *     description="API documentation for Solid Social Network SPA, synchronized with the latest verified routes for feed, media upload, site config, chat settings/archives/mood status, radio favorites, IPTV playback/library, client analytics tracking, lifetime site error logging, and extended admin summary/analytics/export flows. Detailed analytics formulas, diagnostics notes, and source tables are documented in docs/analytics-metrics.md."
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
 *     name="Site",
 *     description="Public site content and overview data"
 * )
 *
 * @OA\Tag(
 *     name="Users",
 *     description="User discovery, profile and follow actions"
 * )
 *
 * @OA\Tag(
 *     name="Posts",
 *     description="Feed, discover and engagement endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Media",
 *     description="Media upload and download endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Activity",
 *     description="User activity heartbeat tracking"
 * )
 *
 * @OA\Tag(
 *     name="Admin Chat",
 *     description="Admin chat moderation endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Admin Analytics",
 *     description="Admin dashboard analytics and exports. Metric formulas, fallback logic, and source tables are documented in docs/analytics-metrics.md."
 * )
 *
 * @OA\Tag(
 *     name="Admin Diagnostics",
 *     description="Admin diagnostics endpoints for lifetime site error log preview, search, export, and raw log download."
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
 *
 * @OA\Schema(
 *     schema="RadioStation",
 *     type="object",
 *     required={"station_uuid","name","stream_url","is_favorite"},
 *     @OA\Property(property="station_uuid", type="string", example="station-123"),
 *     @OA\Property(property="name", type="string", example="Rock FM"),
 *     @OA\Property(property="stream_url", type="string", format="uri", example="https://stream.example.com/live"),
 *     @OA\Property(property="homepage", type="string", format="uri", nullable=true, example="https://station.example.com"),
 *     @OA\Property(property="favicon", type="string", format="uri", nullable=true, example="https://station.example.com/icon.png"),
 *     @OA\Property(property="country", type="string", nullable=true, example="Germany"),
 *     @OA\Property(property="language", type="string", nullable=true, example="German"),
 *     @OA\Property(property="tags", type="string", nullable=true, example="rock,pop"),
 *     @OA\Property(property="codec", type="string", nullable=true, example="MP3"),
 *     @OA\Property(property="bitrate", type="integer", example=128),
 *     @OA\Property(property="votes", type="integer", example=420),
 *     @OA\Property(property="is_favorite", type="boolean", example=false)
 * )
 *
 * @OA\Schema(
 *     schema="RadioFavorite",
 *     type="object",
 *     required={"id","station_uuid","name","stream_url"},
 *     @OA\Property(property="id", type="integer", example=15),
 *     @OA\Property(property="station_uuid", type="string", example="station-123"),
 *     @OA\Property(property="name", type="string", example="Rock FM"),
 *     @OA\Property(property="stream_url", type="string", format="uri", example="https://stream.example.com/live"),
 *     @OA\Property(property="homepage", type="string", format="uri", nullable=true),
 *     @OA\Property(property="favicon", type="string", format="uri", nullable=true),
 *     @OA\Property(property="country", type="string", nullable=true),
 *     @OA\Property(property="language", type="string", nullable=true),
 *     @OA\Property(property="tags", type="string", nullable=true),
 *     @OA\Property(property="codec", type="string", nullable=true),
 *     @OA\Property(property="bitrate", type="integer", example=128),
 *     @OA\Property(property="votes", type="integer", example=420)
 * )
 *
 * @OA\Schema(
 *     schema="UserSummary",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="name", type="string", example="Test User 1"),
 *     @OA\Property(property="display_name", type="string", nullable=true, example="Test User 1"),
 *     @OA\Property(property="nickname", type="string", nullable=true, example="test_user_1"),
 *     @OA\Property(property="avatar_url", type="string", format="uri", nullable=true, example="https://example.com/api/media/avatars/12"),
 *     @OA\Property(property="is_followed", type="boolean", nullable=true, example=false)
 * )
 *
 * @OA\Schema(
 *     schema="UploadedPostMedia",
 *     type="object",
 *     required={"id","type","url"},
 *     @OA\Property(property="id", type="integer", example=81),
 *     @OA\Property(property="type", type="string", enum={"image","video"}, example="video"),
 *     @OA\Property(property="url", type="string", format="uri", example="https://example.com/api/media/post-images/81"),
 *     @OA\Property(property="mime_type", type="string", example="video/mp4"),
 *     @OA\Property(property="size", type="integer", example=10485760),
 *     @OA\Property(property="original_name", type="string", example="clip.mp4")
 * )
 *
 * @OA\Schema(
 *     schema="IptvSavedPlaylist",
 *     type="object",
 *     required={"id","name","url","channels_count"},
 *     @OA\Property(property="id", type="integer", example=4),
 *     @OA\Property(property="name", type="string", example="Main playlist"),
 *     @OA\Property(property="url", type="string", format="uri", example="https://iptv.example.com/playlist.m3u8"),
 *     @OA\Property(property="channels_count", type="integer", example=240),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="IptvSavedChannel",
 *     type="object",
 *     required={"id","name","url"},
 *     @OA\Property(property="id", type="integer", example=9),
 *     @OA\Property(property="name", type="string", example="Discovery HD"),
 *     @OA\Property(property="url", type="string", format="uri", example="https://stream.example.com/discovery.m3u8"),
 *     @OA\Property(property="group", type="string", nullable=true, example="Entertainment"),
 *     @OA\Property(property="logo", type="string", format="uri", nullable=true, example="https://stream.example.com/logo.png"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PlaybackSession",
 *     type="object",
 *     required={"session_id","source_url","playlist_url"},
 *     @OA\Property(property="session_id", type="string", example="iptv_01hxy2kkt2f7b8s"),
 *     @OA\Property(property="source_url", type="string", format="uri", example="https://stream.example.com/live.m3u8"),
 *     @OA\Property(property="playlist_url", type="string", example="/api/iptv/proxy/iptv_01hxy2kkt2f7b8s/playlist.m3u8"),
 *     @OA\Property(property="profile", type="string", nullable=true, example="balanced")
 * )
 *
 * @OA\Schema(
 *     schema="AnalyticsEventRequest",
 *     type="object",
 *     description="Client analytics event payload for media, radio, and IPTV metrics. These events feed admin transport/media blocks and the XLS/JSON export.",
 *     required={"feature","event_name"},
 *     @OA\Property(property="feature", type="string", enum={"media","social","chats","radio","iptv"}, example="media"),
 *     @OA\Property(
 *         property="event_name",
 *         type="string",
 *         enum={
 *             "media_upload_failed",
 *             "video_session",
 *             "video_theater_open",
 *             "video_fullscreen_enter",
 *             "radio_play_started",
 *             "radio_play_failed",
 *             "iptv_direct_started",
 *             "iptv_direct_failed",
 *             "iptv_proxy_started",
 *             "iptv_proxy_failed",
 *             "iptv_relay_started",
 *             "iptv_relay_failed",
 *             "iptv_ffmpeg_started",
 *             "iptv_ffmpeg_failed"
 *         },
 *         example="video_session"
 *     ),
 *     @OA\Property(property="entity_type", type="string", nullable=true, example="post_media"),
 *     @OA\Property(property="entity_id", type="integer", nullable=true, minimum=1, example=81),
 *     @OA\Property(property="entity_key", type="string", nullable=true, example="station-abc"),
 *     @OA\Property(property="session_id", type="string", nullable=true, example="video:01hr8g0dn7q"),
 *     @OA\Property(property="duration_seconds", type="integer", nullable=true, minimum=0, example=95),
 *     @OA\Property(property="metric_value", type="number", format="float", nullable=true, example=82.4),
 *     @OA\Property(
 *         property="context",
 *         type="object",
 *         nullable=true,
 *         additionalProperties=true,
 *         example={"completed": true, "source": "theater", "channel_name": "News 24"}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ClientErrorRequest",
 *     type="object",
 *     description="Public client-side runtime error payload mirrored into the lifetime site error log.",
 *     required={"kind","message"},
 *     @OA\Property(property="kind", type="string", enum={"runtime","promise","vue","http"}, example="http"),
 *     @OA\Property(property="message", type="string", maxLength=4000, example="Request failed with status code 500"),
 *     @OA\Property(property="stack", type="string", nullable=true, maxLength=30000, example="AxiosError: Request failed with status code 500\n    at fetchDashboard (...snip...)"),
 *     @OA\Property(property="page_url", type="string", nullable=true, maxLength=2048, example="https://example.com/ru/admin"),
 *     @OA\Property(property="route_name", type="string", nullable=true, maxLength=120, example="admin"),
 *     @OA\Property(property="request_url", type="string", nullable=true, maxLength=2048, example="https://example.com/api/admin/dashboard"),
 *     @OA\Property(property="request_method", type="string", nullable=true, maxLength=16, example="GET"),
 *     @OA\Property(property="status_code", type="integer", nullable=true, minimum=0, maximum=999, example=500),
 *     @OA\Property(property="source_file", type="string", nullable=true, maxLength=2048, example="resources/js/views/user/Admin.vue"),
 *     @OA\Property(property="source_line", type="integer", nullable=true, minimum=0, maximum=999999, example=2683),
 *     @OA\Property(property="source_column", type="integer", nullable=true, minimum=0, maximum=999999, example=17),
 *     @OA\Property(
 *         property="context",
 *         type="object",
 *         nullable=true,
 *         additionalProperties=true,
 *         example={"component": "AdminErrorLogTab", "filter_type": "client_error"}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="SiteErrorLogPreview",
 *     type="object",
 *     required={"exists","file_name","relative_path","size_bytes","truncated","preview","archive_count","archive_size_bytes","archive_relative_path"},
 *     @OA\Property(property="exists", type="boolean", example=true),
 *     @OA\Property(property="file_name", type="string", example="site-errors.log"),
 *     @OA\Property(property="relative_path", type="string", example="storage/logs/site-errors.log"),
 *     @OA\Property(property="size_bytes", type="integer", example=18342),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2026-02-28T20:15:03+00:00"),
 *     @OA\Property(property="truncated", type="boolean", example=true),
 *     @OA\Property(property="preview", type="string", example="=== SITE ERROR ENTRY ===\nTimestamp: 2026-02-28T20:15:03+00:00\nType: server_exception\n..."),
 *     @OA\Property(property="archive_count", type="integer", example=4),
 *     @OA\Property(property="archive_size_bytes", type="integer", example=512993),
 *     @OA\Property(property="archive_relative_path", type="string", example="storage/logs/site-errors-archive")
 * )
 *
 * @OA\Schema(
 *     schema="SiteErrorLogEntry",
 *     type="object",
 *     required={"id","timestamp","type","headline","summary","raw"},
 *     @OA\Property(property="id", type="string", example="d7b120ff24399f0d1ef5de18fcb2468f95f2f3c2"),
 *     @OA\Property(property="timestamp", type="string", format="date-time", nullable=true, example="2026-02-28T20:15:03+00:00"),
 *     @OA\Property(property="type", type="string", enum={"server_exception","client_error","analytics_failure"}, example="client_error"),
 *     @OA\Property(property="headline", type="string", example="Request failed with status code 500"),
 *     @OA\Property(property="message", type="string", nullable=true, example="Request failed with status code 500"),
 *     @OA\Property(property="summary", type="string", example="HTTP 500 | https://example.com/api/admin/dashboard"),
 *     @OA\Property(property="exception", type="string", nullable=true, example="RuntimeException"),
 *     @OA\Property(property="file", type="string", nullable=true, example="app/Http/Controllers/AdminController.php:221"),
 *     @OA\Property(property="feature", type="string", nullable=true, example="iptv"),
 *     @OA\Property(property="event", type="string", nullable=true, example="iptv_proxy_failed"),
 *     @OA\Property(property="kind", type="string", nullable=true, example="http"),
 *     @OA\Property(property="status_code", type="string", nullable=true, example="500"),
 *     @OA\Property(property="page_url", type="string", nullable=true, example="https://example.com/ru/admin"),
 *     @OA\Property(property="request_url", type="string", nullable=true, example="https://example.com/api/admin/dashboard"),
 *     @OA\Property(property="request_method", type="string", nullable=true, example="GET"),
 *     @OA\Property(property="user_id", type="string", nullable=true, example="7"),
 *     @OA\Property(property="source", type="string", nullable=true, example="resources/js/views/user/Admin.vue:2683:17"),
 *     @OA\Property(property="route_name", type="string", nullable=true, example="admin"),
 *     @OA\Property(property="environment", type="string", nullable=true, example="production"),
 *     @OA\Property(property="entity_type", type="string", nullable=true, example="channel"),
 *     @OA\Property(property="entity_id", type="string", nullable=true, example="42"),
 *     @OA\Property(property="entity_key", type="string", nullable=true, example="discovery-hd"),
 *     @OA\Property(property="session_id", type="string", nullable=true, example="iptv:01hr8g0dn7q"),
 *     @OA\Property(property="metric_value", type="string", nullable=true, example="1"),
 *     @OA\Property(property="raw", type="string", example="=== SITE ERROR ENTRY ===\nTimestamp: 2026-02-28T20:15:03+00:00\nType: client_error\n...")
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
     * @OA\Get(
     *     path="/api/site/world-overview",
     *     operationId="getWorldOverview",
     *     tags={"Site"},
     *     summary="Get time/weather overview widget data",
     *     @OA\Parameter(name="locale", in="query", required=false, @OA\Schema(type="string", enum={"ru","en"})),
     *     @OA\Response(
     *         response=200,
     *         description="World overview payload"
     *     )
     * )
     */
    public function getWorldOverview(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/site/config",
     *     operationId="getPublicSiteConfig",
     *     tags={"Site"},
     *     summary="Get public site configuration for authenticated verified user",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(response=200, description="Public config payload"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getPublicSiteConfig(): void
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
     *     path="/api/users",
     *     operationId="getUsersIndex",
     *     tags={"Users"},
     *     summary="Search and paginate users except the current viewer",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Name or nickname search", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50)),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated users",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UserSummary"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getUsersIndex(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/users/{user}/posts",
     *     operationId="getUserPosts",
     *     tags={"Users"},
     *     summary="Get paginated posts for a specific user",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50)),
     *     @OA\Response(response=200, description="User posts payload"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getUserPosts(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/users/following_posts",
     *     operationId="getFollowingPosts",
     *     tags={"Users"},
     *     summary="Get feed posts from followed users",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50)),
     *     @OA\Response(response=200, description="Following feed payload"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getFollowingPosts(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/users/profile",
     *     operationId="updateUserProfile",
     *     tags={"Users"},
     *     summary="Update current user profile and avatar",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", maxLength=255, example="Test User 1"),
     *                 @OA\Property(property="nickname", type="string", nullable=true, maxLength=40, example="test_user_1"),
     *                 @OA\Property(property="avatar", type="string", format="binary"),
     *                 @OA\Property(property="remove_avatar", type="boolean", nullable=true, example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profile updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateUserProfile(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/users/{user}/toggle_following",
     *     operationId="toggleFollowing",
     *     tags={"Users"},
     *     summary="Follow or unfollow a target user",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Follow state updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Invalid target")
     * )
     */
    public function toggleFollowing(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/post_media",
     *     operationId="uploadPostMedia",
     *     tags={"Media"},
     *     summary="Upload image or video asset before post creation",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Supported formats: jpg, jpeg, png, webp, gif, mp4, webm, mov, m4v, avi, mkv. Maximum size: 200 MB."
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Uploaded media payload",
     *         @OA\JsonContent(ref="#/components/schemas/UploadedPostMedia")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function uploadPostMedia(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/media/post-images/{postImage}",
     *     operationId="getPostMediaFile",
     *     tags={"Media"},
     *     summary="Get public placeholder-aware post media file",
     *     @OA\Parameter(name="postImage", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Image or video bytes",
     *         @OA\MediaType(mediaType="image/*", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="video/*", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(response=403, description="Private media is not available"),
     *     @OA\Response(response=404, description="Media not found")
     * )
     */
    public function getPostMediaFile(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/media/chat-attachments/{attachment}",
     *     operationId="getChatAttachment",
     *     tags={"Media"},
     *     summary="Stream chat attachment for conversation participant",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="attachment", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Attachment bytes"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="No access to this attachment"),
     *     @OA\Response(response=404, description="Attachment not found")
     * )
     */
    public function getChatAttachment(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/media/chat-attachments/{attachment}/download",
     *     operationId="downloadChatAttachment",
     *     tags={"Media"},
     *     summary="Download chat attachment for conversation participant",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="attachment", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Attachment download stream"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="No access to this attachment"),
     *     @OA\Response(response=404, description="Attachment not found")
     * )
     */
    public function downloadChatAttachment(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/posts",
     *     operationId="getOwnPosts",
     *     tags={"Posts"},
     *     summary="Get current user's own posts",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50)),
     *     @OA\Response(response=200, description="Paginated own posts"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getOwnPosts(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/posts",
     *     operationId="createPost",
     *     tags={"Posts"},
     *     summary="Create a post with optional uploaded media",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","content"},
     *             @OA\Property(property="title", type="string", maxLength=255, example="Weekend notes"),
     *             @OA\Property(property="content", type="string", maxLength=5000, example="Fresh air, good road and no notifications for two hours."),
     *             @OA\Property(property="image_id", type="integer", nullable=true, example=81),
     *             @OA\Property(property="media_ids", type="array", @OA\Items(type="integer"), nullable=true, example={81,82}),
     *             @OA\Property(property="is_public", type="boolean", nullable=true, example=true),
     *             @OA\Property(property="show_in_feed", type="boolean", nullable=true, example=true),
     *             @OA\Property(property="show_in_carousel", type="boolean", nullable=true, example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Created post resource"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createPost(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/posts/discover",
     *     operationId="getDiscoverPosts",
     *     tags={"Posts"},
     *     summary="Get public discover feed",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", enum={"popular","most_viewed","newest"})),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50)),
     *     @OA\Response(response=200, description="Discover feed payload"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getDiscoverPosts(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/posts/carousel",
     *     operationId="getCarouselMedia",
     *     tags={"Posts"},
     *     summary="Get public media items marked for home carousel",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="Carousel media payload"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getCarouselMedia(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/posts/{post}/view",
     *     operationId="markPostViewed",
     *     tags={"Posts"},
     *     summary="Mark post as viewed once per viewer per day",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="View counter payload"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Post is not available for this viewer")
     * )
     */
    public function markPostViewed(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/posts/{post}/comment",
     *     operationId="createPostComment",
     *     tags={"Posts"},
     *     summary="Create comment for post with optional parent comment",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"body"},
     *             @OA\Property(property="body", type="string", maxLength=2000, example="Отличный пост."),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, example=15)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Created comment resource"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createPostComment(): void
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
     *         description="List of normalized stations",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/RadioStation")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Radio catalog provider is temporarily unavailable"
     *     )
     * )
     */
    public function getRadioStations(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/radio/stream",
     *     operationId="getRadioStreamProxy",
     *     tags={"Radio"},
     *     summary="Proxy external radio stream (useful for mixed-content/CORS constraints)",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(
     *         name="url",
     *         in="query",
     *         required=true,
     *         description="Original station stream URL",
     *         @OA\Schema(type="string", format="uri")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audio stream bytes",
     *         @OA\MediaType(
     *             mediaType="audio/mpeg",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Invalid or unsafe URL"),
     *     @OA\Response(response=503, description="Upstream stream unavailable")
     * )
     */
    public function getRadioStreamProxy(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/radio/favorites",
     *     operationId="getRadioFavorites",
     *     tags={"Radio"},
     *     summary="Get current user's favorite stations",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Favorite stations",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/RadioFavorite")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getRadioFavorites(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/radio/favorites",
     *     operationId="storeRadioFavorite",
     *     tags={"Radio"},
     *     summary="Add or update station in current user's favorites",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"station_uuid","name","stream_url"},
     *             @OA\Property(property="station_uuid", type="string", maxLength=64, example="station-123"),
     *             @OA\Property(property="name", type="string", maxLength=255, example="Rock FM"),
     *             @OA\Property(property="stream_url", type="string", format="uri", maxLength=2000, example="https://stream.example.com/live"),
     *             @OA\Property(property="homepage", type="string", format="uri", nullable=true),
     *             @OA\Property(property="favicon", type="string", format="uri", nullable=true),
     *             @OA\Property(property="country", type="string", nullable=true),
     *             @OA\Property(property="language", type="string", nullable=true),
     *             @OA\Property(property="tags", type="string", nullable=true),
     *             @OA\Property(property="codec", type="string", nullable=true),
     *             @OA\Property(property="bitrate", type="integer", nullable=true, minimum=0, maximum=9999),
     *             @OA\Property(property="votes", type="integer", nullable=true, minimum=0, maximum=99999999)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Favorite station stored",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="station_uuid", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeRadioFavorite(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/radio/favorites/{stationUuid}",
     *     operationId="deleteRadioFavorite",
     *     tags={"Radio"},
     *     summary="Remove station from current user's favorites",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(
     *         name="stationUuid",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Favorite removed"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function deleteRadioFavorite(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/iptv/transcode/capabilities",
     *     operationId="getIptvTranscodeCapabilities",
     *     tags={"IPTV"},
     *     summary="Check FFmpeg/transcode capabilities for current server",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(response=200, description="Capabilities payload"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getIptvTranscodeCapabilities(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/iptv/playlist/fetch",
     *     operationId="fetchIptvPlaylist",
     *     tags={"IPTV"},
     *     summary="Fetch remote IPTV playlist text by URL",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(property="url", type="string", format="uri", maxLength=2000, example="https://iptv.example.com/playlist.m3u8")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Playlist text payload"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Invalid or unsafe playlist URL"),
     *     @OA\Response(response=503, description="Playlist source unavailable")
     * )
     */
    public function fetchIptvPlaylist(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/iptv/saved",
     *     operationId="getSavedIptvLibrary",
     *     tags={"IPTV"},
     *     summary="Get current user's saved IPTV playlists and channels",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Saved IPTV library payload",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="playlists", type="array", @OA\Items(ref="#/components/schemas/IptvSavedPlaylist")),
     *                 @OA\Property(property="channels", type="array", @OA\Items(ref="#/components/schemas/IptvSavedChannel"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getSavedIptvLibrary(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/iptv/saved/playlists",
     *     operationId="storeSavedIptvPlaylist",
     *     tags={"IPTV"},
     *     summary="Save IPTV playlist URL into personal library",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(property="name", type="string", nullable=true, maxLength=120, example="News pack"),
     *             @OA\Property(property="url", type="string", format="uri", maxLength=2000, example="https://iptv.example.com/news.m3u8"),
     *             @OA\Property(property="channels_count", type="integer", nullable=true, example=120)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Playlist saved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/IptvSavedPlaylist"))),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeSavedIptvPlaylist(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/iptv/saved/channels",
     *     operationId="storeSavedIptvChannel",
     *     tags={"IPTV"},
     *     summary="Save IPTV channel into personal library",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","url"},
     *             @OA\Property(property="name", type="string", maxLength=120, example="Discovery HD"),
     *             @OA\Property(property="url", type="string", format="uri", maxLength=4000, example="https://stream.example.com/discovery.m3u8"),
     *             @OA\Property(property="group", type="string", nullable=true, maxLength=160, example="Entertainment"),
     *             @OA\Property(property="logo", type="string", format="uri", nullable=true, maxLength=2000, example="https://stream.example.com/logo.png")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Channel saved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/IptvSavedChannel"))),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeSavedIptvChannel(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/iptv/proxy/start",
     *     operationId="startIptvProxy",
     *     tags={"IPTV"},
     *     summary="Start proxy playback session for remote IPTV stream",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(property="url", type="string", format="uri", maxLength=4000, example="https://stream.example.com/live.m3u8")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Proxy session payload", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/PlaybackSession"))),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Invalid or unsafe source URL"),
     *     @OA\Response(response=503, description="Proxy session could not be started")
     * )
     */
    public function startIptvProxy(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/iptv/transcode/start",
     *     operationId="startIptvTranscode",
     *     tags={"IPTV"},
     *     summary="Start FFmpeg-backed compatibility session",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(property="url", type="string", format="uri", maxLength=2000, example="https://stream.example.com/live.m3u8"),
     *             @OA\Property(property="profile", type="string", nullable=true, enum={"fast","balanced","stable"}, example="balanced")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Transcode session payload", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/PlaybackSession"))),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Invalid or unsafe source URL"),
     *     @OA\Response(response=503, description="Compatibility session could not be started")
     * )
     */
    public function startIptvTranscode(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/iptv/relay/start",
     *     operationId="startIptvRelay",
     *     tags={"IPTV"},
     *     summary="Start lightweight relay playback session",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(property="url", type="string", format="uri", maxLength=2000, example="https://stream.example.com/live.m3u8")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Relay session payload", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/PlaybackSession"))),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Invalid or unsafe source URL"),
     *     @OA\Response(response=503, description="Relay session could not be started")
     * )
     */
    public function startIptvRelay(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/iptv/proxy/{session}",
     *     operationId="stopIptvProxy",
     *     tags={"IPTV"},
     *     summary="Stop active proxy playback session",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Proxy session stopped"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function stopIptvProxy(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/iptv/transcode/{session}",
     *     operationId="stopIptvTranscode",
     *     tags={"IPTV"},
     *     summary="Stop active transcode playback session",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Transcode session stopped"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function stopIptvTranscode(): void
    {
    }

    /**
     * @OA\Delete(
     *     path="/api/iptv/relay/{session}",
     *     operationId="stopIptvRelay",
     *     tags={"IPTV"},
     *     summary="Stop active relay playback session",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Relay session stopped"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function stopIptvRelay(): void
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
     * @OA\Get(
     *     path="/api/chats/users",
     *     operationId="getChatUsers",
     *     tags={"Chat"},
     *     summary="List users available for direct chats with optional search",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Chat user list"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getChatUsers(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/chats/settings",
     *     operationId="getChatSettings",
     *     tags={"Chat"},
     *     summary="Get current user's chat storage settings",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(response=200, description="Chat settings payload"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getChatSettings(): void
    {
    }

    /**
     * @OA\Patch(
     *     path="/api/chats/settings",
     *     operationId="updateChatSettings",
     *     tags={"Chat"},
     *     summary="Update current user's chat storage settings",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"save_text_messages","save_media_attachments","save_file_attachments","auto_archive_enabled"},
     *             @OA\Property(property="save_text_messages", type="boolean", example=true),
     *             @OA\Property(property="save_media_attachments", type="boolean", example=true),
     *             @OA\Property(property="save_file_attachments", type="boolean", example=true),
     *             @OA\Property(property="retention_days", type="integer", nullable=true, example=30),
     *             @OA\Property(property="auto_archive_enabled", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Chat settings updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateChatSettings(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/chats/archives",
     *     operationId="getChatArchives",
     *     tags={"Chat"},
     *     summary="List chat archives created by current user",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(response=200, description="Archive list"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getChatArchives(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/chats/archives",
     *     operationId="createChatArchive",
     *     tags={"Chat"},
     *     summary="Create downloadable archive for current user's chats",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Response(response=201, description="Archive created"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function createChatArchive(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/chats/archives/{archive}/download",
     *     operationId="downloadChatArchive",
     *     tags={"Chat"},
     *     summary="Download previously created chat archive",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="archive", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Archive download stream"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Archive not found")
     * )
     */
    public function downloadChatArchive(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/chats/archives/{archive}/restore",
     *     operationId="restoreChatArchive",
     *     tags={"Chat"},
     *     summary="Restore chat archive into current account",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(name="archive", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Archive restored"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Archive not found")
     * )
     */
    public function restoreChatArchive(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/chats/direct/{user}",
     *     operationId="createOrGetDirectChat",
     *     tags={"Chat"},
     *     summary="Create or return existing direct chat with target user",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     * @OA\Post(
     *     path="/api/chats/{conversation}/read",
     *     operationId="markChatRead",
     *     tags={"Chat"},
     *     summary="Mark conversation as read for current user",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\Parameter(name="conversation", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Conversation marked as read"),
     *     @OA\Response(response=403, description="No access to this conversation")
     * )
     */
    public function markChatRead(): void
    {
    }

    /**
     * @OA\Patch(
     *     path="/api/chats/{conversation}/mood-status",
     *     operationId="upsertMoodStatus",
     *     tags={"Chat"},
     *     summary="Create/update own mood status in conversation. Empty text removes status.",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
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

    /**
     * @OA\Post(
     *     path="/api/activity/heartbeat",
     *     operationId="storeActivityHeartbeat",
     *     tags={"Activity"},
     *     summary="Store user activity heartbeat for current feature/session",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"feature","session_id"},
     *             @OA\Property(property="feature", type="string", enum={"social","chats","radio","iptv"}, example="social"),
     *             @OA\Property(property="session_id", type="string", minLength=8, maxLength=120, example="social:lrzjg3h6:73ca9ba8a6674d1f8cc53a99"),
     *             @OA\Property(property="elapsed_seconds", type="integer", minimum=1, maximum=300, nullable=true, example=30),
     *             @OA\Property(property="ended", type="boolean", nullable=true, example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Heartbeat accepted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeActivityHeartbeat(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/analytics/events",
     *     operationId="storeAnalyticsEvent",
     *     tags={"Activity"},
     *     summary="Store a lightweight authenticated analytics event from the client",
     *     security={{"sanctumCookie":{}, "xsrfHeader":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AnalyticsEventRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Analytics event accepted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Analytics event accepted."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=501)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeAnalyticsEvent(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/client-errors",
     *     operationId="storeClientError",
     *     tags={"Activity"},
     *     summary="Store a public client runtime/Vue/promise/HTTP error in the lifetime site error log",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ClientErrorRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Client error accepted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Client error accepted.")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function storeClientError(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/summary",
     *     operationId="adminSummary",
     *     tags={"Admin Analytics"},
     *     summary="Admin summary counters",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(response=200, description="Summary payload with direct COUNT(*) counters from users, posts, comments, media, likes, feedback, chat, and active blocks tables"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function adminSummary(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/dashboard",
     *     operationId="adminDashboard",
     *     tags={"Admin Analytics"},
     *     summary="Admin analytics dashboard data for selected year and optional date range",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="year", in="query", required=false, @OA\Schema(type="integer", minimum=2000)),
     *     @OA\Parameter(name="date_from", in="query", required=false, description="YYYY-MM-DD", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, description="YYYY-MM-DD", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Dashboard analytics payload with KPI, retention, cohort, content, chats, media, radio, IPTV, and moderation/error sections. Uses heartbeat-derived time metrics when user_activity_daily_stats is populated; otherwise falls back to action-based counts."),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function adminDashboard(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/dashboard/export",
     *     operationId="adminDashboardExport",
     *     tags={"Admin Analytics"},
     *     summary="Export admin dashboard analytics (XLS or JSON) for selected range",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="year", in="query", required=false, @OA\Schema(type="integer", minimum=2000)),
     *     @OA\Parameter(name="date_from", in="query", required=false, description="YYYY-MM-DD", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, description="YYYY-MM-DD", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"xls","json"})),
     *     @OA\Parameter(name="locale", in="query", required=false, description="Locale for XLS headings. JSON payload is not localized.", @OA\Schema(type="string", enum={"ru","en"})),
     *     @OA\Response(
     *         response=200,
     *         description="Export stream containing the same analytics payload as admin dashboard: summary KPI, retention, content, chats, media, radio, IPTV and moderation/error sections",
     *         @OA\MediaType(mediaType="application/vnd.ms-excel", @OA\Schema(type="string", format="binary")),
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(type="string"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function adminDashboardExport(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/error-log",
     *     operationId="adminErrorLogPreview",
     *     tags={"Admin Diagnostics"},
     *     summary="Get lifetime site error log preview and archive statistics",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Preview payload for active site-errors.log and archive folder",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/SiteErrorLogPreview")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function adminErrorLogPreview(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/error-log/entries",
     *     operationId="adminErrorLogEntries",
     *     tags={"Admin Diagnostics"},
     *     summary="Search and filter structured error log entries across active log and archives",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Free-text search across raw log entries", @OA\Schema(type="string", maxLength=200)),
     *     @OA\Parameter(name="type", in="query", required=false, description="Entry type filter", @OA\Schema(type="string", enum={"all","server_exception","client_error","analytics_failure"})),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated structured entries for admin diagnostics UI",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/SiteErrorLogEntry")),
     *                 @OA\Property(property="meta", type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=3),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=45),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=20)
     *                 ),
     *                 @OA\Property(property="filters", type="object",
     *                     @OA\Property(property="search", type="string", example="500"),
     *                     @OA\Property(property="type", type="string", example="client_error")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function adminErrorLogEntries(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/error-log/export",
     *     operationId="adminErrorLogExport",
     *     tags={"Admin Diagnostics"},
     *     summary="Export only the filtered site error log entries as a text file",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Free-text search across raw log entries", @OA\Schema(type="string", maxLength=200)),
     *     @OA\Parameter(name="type", in="query", required=false, description="Entry type filter", @OA\Schema(type="string", enum={"all","server_exception","client_error","analytics_failure"})),
     *     @OA\Response(
     *         response=200,
     *         description="Plain-text filtered export stream",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function adminErrorLogExport(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/error-log/download",
     *     operationId="adminErrorLogDownload",
     *     tags={"Admin Diagnostics"},
     *     summary="Download the current raw lifetime site error log file",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current raw site-errors.log stream",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Log file does not exist yet",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Site error log file does not exist yet.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function adminErrorLogDownload(): void
    {
    }
}
