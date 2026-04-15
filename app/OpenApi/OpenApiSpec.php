<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.0',
    info: new OA\Info(
        version: '1.5.0',
        description: 'API documentation for Solid Social Network SPA, verified on the upgraded PHP 8.5, Laravel 13, Node 24 and Vite 8 stack. The spec is synchronized with routes for feed, media upload, site config, chat settings/archives/mood status, radio favorites, IPTV playback/library, activity heartbeat, client analytics tracking, lifetime site error logging, and extended admin summary/analytics/export flows.',
        title: 'Solid Social API',
    ),
    servers: [
        new OA\Server(
            url: '/',
            description: 'Current host',
        ),
    ],
    tags: [
        new OA\Tag(
            name: 'Public',
            description: 'Public endpoints',
        ),
        new OA\Tag(
            name: 'Radio',
            description: 'Radio features',
        ),
        new OA\Tag(
            name: 'Chat',
            description: 'Chat features',
        ),
        new OA\Tag(
            name: 'IPTV',
            description: 'IPTV features',
        ),
        new OA\Tag(
            name: 'Site',
            description: 'Public site content and overview data',
        ),
        new OA\Tag(
            name: 'Users',
            description: 'User discovery, profile and follow actions',
        ),
        new OA\Tag(
            name: 'Posts',
            description: 'Feed, discover and engagement endpoints',
        ),
        new OA\Tag(
            name: 'Media',
            description: 'Media upload and download endpoints',
        ),
        new OA\Tag(
            name: 'Activity',
            description: 'User activity heartbeat tracking',
        ),
        new OA\Tag(
            name: 'Admin Chat',
            description: 'Admin chat moderation endpoints',
        ),
        new OA\Tag(
            name: 'Admin Analytics',
            description: 'Admin dashboard analytics and exports.',
        ),
        new OA\Tag(
            name: 'Admin Diagnostics',
            description: 'Admin diagnostics endpoints for lifetime site error log preview, search, export, and raw log download.',
        ),
    ],
    components: new OA\Components(
        schemas: [
            new OA\Schema(
                schema: 'RadioStation',
                type: 'object',
                required: [
                    'station_uuid',
                    'name',
                    'stream_url',
                    'is_favorite',
                ],
                properties: [
                    new OA\Property(
                        property: 'station_uuid',
                        type: 'string',
                        example: 'station-123',
                    ),
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Rock FM',
                    ),
                    new OA\Property(
                        property: 'stream_url',
                        type: 'string',
                        format: 'uri',
                        example: 'https://stream.example.com/live',
                    ),
                    new OA\Property(
                        property: 'homepage',
                        type: 'string',
                        format: 'uri',
                        nullable: true,
                        example: 'https://station.example.com',
                    ),
                    new OA\Property(
                        property: 'favicon',
                        type: 'string',
                        format: 'uri',
                        nullable: true,
                        example: 'https://station.example.com/icon.png',
                    ),
                    new OA\Property(
                        property: 'country',
                        type: 'string',
                        nullable: true,
                        example: 'Germany',
                    ),
                    new OA\Property(
                        property: 'language',
                        type: 'string',
                        nullable: true,
                        example: 'German',
                    ),
                    new OA\Property(
                        property: 'tags',
                        type: 'string',
                        nullable: true,
                        example: 'rock,pop',
                    ),
                    new OA\Property(
                        property: 'codec',
                        type: 'string',
                        nullable: true,
                        example: 'MP3',
                    ),
                    new OA\Property(
                        property: 'bitrate',
                        type: 'integer',
                        example: 128,
                    ),
                    new OA\Property(
                        property: 'votes',
                        type: 'integer',
                        example: 420,
                    ),
                    new OA\Property(
                        property: 'is_favorite',
                        type: 'boolean',
                        example: false,
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'RadioFavorite',
                type: 'object',
                required: [
                    'id',
                    'station_uuid',
                    'name',
                    'stream_url',
                ],
                properties: [
                    new OA\Property(
                        property: 'id',
                        type: 'integer',
                        example: 15,
                    ),
                    new OA\Property(
                        property: 'station_uuid',
                        type: 'string',
                        example: 'station-123',
                    ),
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Rock FM',
                    ),
                    new OA\Property(
                        property: 'stream_url',
                        type: 'string',
                        format: 'uri',
                        example: 'https://stream.example.com/live',
                    ),
                    new OA\Property(
                        property: 'homepage',
                        type: 'string',
                        format: 'uri',
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'favicon',
                        type: 'string',
                        format: 'uri',
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'country',
                        type: 'string',
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'language',
                        type: 'string',
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'tags',
                        type: 'string',
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'codec',
                        type: 'string',
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'bitrate',
                        type: 'integer',
                        example: 128,
                    ),
                    new OA\Property(
                        property: 'votes',
                        type: 'integer',
                        example: 420,
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'UserSummary',
                type: 'object',
                required: [
                    'id',
                    'name',
                ],
                properties: [
                    new OA\Property(
                        property: 'id',
                        type: 'integer',
                        example: 12,
                    ),
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Test User 1',
                    ),
                    new OA\Property(
                        property: 'display_name',
                        type: 'string',
                        nullable: true,
                        example: 'Test User 1',
                    ),
                    new OA\Property(
                        property: 'nickname',
                        type: 'string',
                        nullable: true,
                        example: 'test_user_1',
                    ),
                    new OA\Property(
                        property: 'avatar_url',
                        type: 'string',
                        format: 'uri',
                        nullable: true,
                        example: 'https://example.com/api/media/avatars/12',
                    ),
                    new OA\Property(
                        property: 'is_followed',
                        type: 'boolean',
                        nullable: true,
                        example: false,
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'UploadedPostMedia',
                type: 'object',
                required: [
                    'id',
                    'type',
                    'url',
                ],
                properties: [
                    new OA\Property(
                        property: 'id',
                        type: 'integer',
                        example: 81,
                    ),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        example: 'video',
                        enum: [
                            'image',
                            'video',
                        ],
                    ),
                    new OA\Property(
                        property: 'url',
                        type: 'string',
                        format: 'uri',
                        example: 'https://example.com/api/media/post-images/81',
                    ),
                    new OA\Property(
                        property: 'mime_type',
                        type: 'string',
                        example: 'video/mp4',
                    ),
                    new OA\Property(
                        property: 'size',
                        type: 'integer',
                        example: 10485760,
                    ),
                    new OA\Property(
                        property: 'original_name',
                        type: 'string',
                        example: 'clip.mp4',
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'PostComment',
                type: 'object',
                required: [
                    'id',
                    'body',
                    'date',
                    'can_delete',
                    'user',
                ],
                properties: [
                    new OA\Property(
                        property: 'id',
                        type: 'integer',
                        example: 15,
                    ),
                    new OA\Property(
                        property: 'body',
                        type: 'string',
                        example: 'Отличный пост.',
                    ),
                    new OA\Property(
                        property: 'date',
                        type: 'string',
                        example: '2 minutes ago',
                    ),
                    new OA\Property(
                        property: 'answered_for_user',
                        type: 'string',
                        nullable: true,
                        example: 'Admin',
                    ),
                    new OA\Property(
                        property: 'can_delete',
                        type: 'boolean',
                        example: false,
                    ),
                    new OA\Property(
                        property: 'user',
                        ref: '#/components/schemas/UserSummary',
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'IptvSavedPlaylist',
                type: 'object',
                required: [
                    'id',
                    'name',
                    'url',
                    'channels_count',
                ],
                properties: [
                    new OA\Property(
                        property: 'id',
                        type: 'integer',
                        example: 4,
                    ),
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Main playlist',
                    ),
                    new OA\Property(
                        property: 'url',
                        type: 'string',
                        format: 'uri',
                        example: 'https://iptv.example.com/playlist.m3u8',
                    ),
                    new OA\Property(
                        property: 'channels_count',
                        type: 'integer',
                        example: 240,
                    ),
                    new OA\Property(
                        property: 'updated_at',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'IptvSavedChannel',
                type: 'object',
                required: [
                    'id',
                    'name',
                    'url',
                ],
                properties: [
                    new OA\Property(
                        property: 'id',
                        type: 'integer',
                        example: 9,
                    ),
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Discovery HD',
                    ),
                    new OA\Property(
                        property: 'url',
                        type: 'string',
                        format: 'uri',
                        example: 'https://stream.example.com/discovery.m3u8',
                    ),
                    new OA\Property(
                        property: 'group',
                        type: 'string',
                        nullable: true,
                        example: 'Entertainment',
                    ),
                    new OA\Property(
                        property: 'logo',
                        type: 'string',
                        format: 'uri',
                        nullable: true,
                        example: 'https://stream.example.com/logo.png',
                    ),
                    new OA\Property(
                        property: 'updated_at',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'PlaybackSession',
                type: 'object',
                required: [
                    'session_id',
                    'source_url',
                    'playlist_url',
                ],
                properties: [
                    new OA\Property(
                        property: 'session_id',
                        type: 'string',
                        example: 'iptv_01hxy2kkt2f7b8s',
                    ),
                    new OA\Property(
                        property: 'source_url',
                        type: 'string',
                        format: 'uri',
                        example: 'https://stream.example.com/live.m3u8',
                    ),
                    new OA\Property(
                        property: 'playlist_url',
                        type: 'string',
                        example: '/api/iptv/proxy/iptv_01hxy2kkt2f7b8s/playlist.m3u8',
                    ),
                    new OA\Property(
                        property: 'profile',
                        type: 'string',
                        nullable: true,
                        example: 'balanced',
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'AnalyticsEventRequest',
                description: 'Client analytics event payload for media, radio, and IPTV metrics. These events feed admin transport/media blocks and the XLS/JSON export.',
                type: 'object',
                required: [
                    'feature',
                    'event_name',
                ],
                properties: [
                    new OA\Property(
                        property: 'feature',
                        type: 'string',
                        example: 'media',
                        enum: [
                            'media',
                            'social',
                            'chats',
                            'radio',
                            'iptv',
                        ],
                    ),
                    new OA\Property(
                        property: 'event_name',
                        type: 'string',
                        example: 'video_session',
                        enum: [
                            'media_upload_failed',
                            'video_session',
                            'video_theater_open',
                            'video_fullscreen_enter',
                            'radio_play_started',
                            'radio_play_failed',
                            'iptv_direct_started',
                            'iptv_direct_failed',
                            'iptv_proxy_started',
                            'iptv_proxy_failed',
                            'iptv_relay_started',
                            'iptv_relay_failed',
                            'iptv_ffmpeg_started',
                            'iptv_ffmpeg_failed',
                        ],
                    ),
                    new OA\Property(
                        property: 'entity_type',
                        type: 'string',
                        nullable: true,
                        example: 'post_media',
                    ),
                    new OA\Property(
                        property: 'entity_id',
                        type: 'integer',
                        nullable: true,
                        example: 81,
                        minimum: 1,
                    ),
                    new OA\Property(
                        property: 'entity_key',
                        type: 'string',
                        nullable: true,
                        example: 'station-abc',
                    ),
                    new OA\Property(
                        property: 'session_id',
                        type: 'string',
                        nullable: true,
                        example: 'video:01hr8g0dn7q',
                    ),
                    new OA\Property(
                        property: 'duration_seconds',
                        type: 'integer',
                        nullable: true,
                        example: 95,
                        minimum: 0,
                    ),
                    new OA\Property(
                        property: 'metric_value',
                        type: 'number',
                        format: 'float',
                        nullable: true,
                        example: 82.4,
                    ),
                    new OA\Property(
                        property: 'context',
                        type: 'object',
                        nullable: true,
                        example: [
                            'completed' => true,
                            'source' => 'theater',
                            'channel_name' => 'News 24',
                        ],
                        additionalProperties: true,
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'ClientErrorRequest',
                description: 'Public client-side runtime error payload mirrored into the lifetime site error log.',
                type: 'object',
                required: [
                    'kind',
                    'message',
                ],
                properties: [
                    new OA\Property(
                        property: 'kind',
                        type: 'string',
                        example: 'http',
                        enum: [
                            'runtime',
                            'promise',
                            'vue',
                            'http',
                        ],
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Request failed with status code 500',
                        maxLength: 4000,
                    ),
                    new OA\Property(
                        property: 'stack',
                        type: 'string',
                        nullable: true,
                        example: 'AxiosError: Request failed with status code 500\\n    at fetchDashboard (...snip...)',
                        maxLength: 30000,
                    ),
                    new OA\Property(
                        property: 'page_url',
                        type: 'string',
                        nullable: true,
                        example: 'https://example.com/ru/admin',
                        maxLength: 2048,
                    ),
                    new OA\Property(
                        property: 'route_name',
                        type: 'string',
                        nullable: true,
                        example: 'admin',
                        maxLength: 120,
                    ),
                    new OA\Property(
                        property: 'request_url',
                        type: 'string',
                        nullable: true,
                        example: 'https://example.com/api/admin/dashboard',
                        maxLength: 2048,
                    ),
                    new OA\Property(
                        property: 'request_method',
                        type: 'string',
                        nullable: true,
                        example: 'GET',
                        maxLength: 16,
                    ),
                    new OA\Property(
                        property: 'status_code',
                        type: 'integer',
                        nullable: true,
                        example: 500,
                        minimum: 0,
                        maximum: 999,
                    ),
                    new OA\Property(
                        property: 'source_file',
                        type: 'string',
                        nullable: true,
                        example: 'resources/js/views/user/Admin.vue',
                        maxLength: 2048,
                    ),
                    new OA\Property(
                        property: 'source_line',
                        type: 'integer',
                        nullable: true,
                        example: 2683,
                        minimum: 0,
                        maximum: 999999,
                    ),
                    new OA\Property(
                        property: 'source_column',
                        type: 'integer',
                        nullable: true,
                        example: 17,
                        minimum: 0,
                        maximum: 999999,
                    ),
                    new OA\Property(
                        property: 'context',
                        type: 'object',
                        nullable: true,
                        example: [
                            'component' => 'AdminErrorLogTab',
                            'filter_type' => 'client_error',
                        ],
                        additionalProperties: true,
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'SiteErrorLogPreview',
                type: 'object',
                required: [
                    'exists',
                    'file_name',
                    'relative_path',
                    'size_bytes',
                    'truncated',
                    'preview',
                    'archive_count',
                    'archive_size_bytes',
                    'archive_relative_path',
                ],
                properties: [
                    new OA\Property(
                        property: 'exists',
                        type: 'boolean',
                        example: true,
                    ),
                    new OA\Property(
                        property: 'file_name',
                        type: 'string',
                        example: 'site-errors.log',
                    ),
                    new OA\Property(
                        property: 'relative_path',
                        type: 'string',
                        example: 'storage/logs/site-errors.log',
                    ),
                    new OA\Property(
                        property: 'size_bytes',
                        type: 'integer',
                        example: 18342,
                    ),
                    new OA\Property(
                        property: 'updated_at',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                        example: '2026-02-28T20:15:03+00:00',
                    ),
                    new OA\Property(
                        property: 'truncated',
                        type: 'boolean',
                        example: true,
                    ),
                    new OA\Property(
                        property: 'preview',
                        type: 'string',
                        example: '=== SITE ERROR ENTRY ===\\nTimestamp: 2026-02-28T20:15:03+00:00\\nType: server_exception\\n...',
                    ),
                    new OA\Property(
                        property: 'archive_count',
                        type: 'integer',
                        example: 4,
                    ),
                    new OA\Property(
                        property: 'archive_size_bytes',
                        type: 'integer',
                        example: 512993,
                    ),
                    new OA\Property(
                        property: 'archive_relative_path',
                        type: 'string',
                        example: 'storage/logs/site-errors-archive',
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'SiteErrorLogEntry',
                type: 'object',
                required: [
                    'id',
                    'timestamp',
                    'type',
                    'headline',
                    'summary',
                    'raw',
                ],
                properties: [
                    new OA\Property(
                        property: 'id',
                        type: 'string',
                        example: 'd7b120ff24399f0d1ef5de18fcb2468f95f2f3c2',
                    ),
                    new OA\Property(
                        property: 'timestamp',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                        example: '2026-02-28T20:15:03+00:00',
                    ),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        example: 'client_error',
                        enum: [
                            'server_exception',
                            'client_error',
                            'analytics_failure',
                        ],
                    ),
                    new OA\Property(
                        property: 'headline',
                        type: 'string',
                        example: 'Request failed with status code 500',
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        nullable: true,
                        example: 'Request failed with status code 500',
                    ),
                    new OA\Property(
                        property: 'summary',
                        type: 'string',
                        example: 'HTTP 500 | https://example.com/api/admin/dashboard',
                    ),
                    new OA\Property(
                        property: 'exception',
                        type: 'string',
                        nullable: true,
                        example: 'RuntimeException',
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        nullable: true,
                        example: 'app/Http/Controllers/AdminController.php:221',
                    ),
                    new OA\Property(
                        property: 'feature',
                        type: 'string',
                        nullable: true,
                        example: 'iptv',
                    ),
                    new OA\Property(
                        property: 'event',
                        type: 'string',
                        nullable: true,
                        example: 'iptv_proxy_failed',
                    ),
                    new OA\Property(
                        property: 'kind',
                        type: 'string',
                        nullable: true,
                        example: 'http',
                    ),
                    new OA\Property(
                        property: 'status_code',
                        type: 'string',
                        nullable: true,
                        example: '500',
                    ),
                    new OA\Property(
                        property: 'page_url',
                        type: 'string',
                        nullable: true,
                        example: 'https://example.com/ru/admin',
                    ),
                    new OA\Property(
                        property: 'request_url',
                        type: 'string',
                        nullable: true,
                        example: 'https://example.com/api/admin/dashboard',
                    ),
                    new OA\Property(
                        property: 'request_method',
                        type: 'string',
                        nullable: true,
                        example: 'GET',
                    ),
                    new OA\Property(
                        property: 'user_id',
                        type: 'string',
                        nullable: true,
                        example: '7',
                    ),
                    new OA\Property(
                        property: 'source',
                        type: 'string',
                        nullable: true,
                        example: 'resources/js/views/user/Admin.vue:2683:17',
                    ),
                    new OA\Property(
                        property: 'route_name',
                        type: 'string',
                        nullable: true,
                        example: 'admin',
                    ),
                    new OA\Property(
                        property: 'environment',
                        type: 'string',
                        nullable: true,
                        example: 'production',
                    ),
                    new OA\Property(
                        property: 'entity_type',
                        type: 'string',
                        nullable: true,
                        example: 'channel',
                    ),
                    new OA\Property(
                        property: 'entity_id',
                        type: 'string',
                        nullable: true,
                        example: '42',
                    ),
                    new OA\Property(
                        property: 'entity_key',
                        type: 'string',
                        nullable: true,
                        example: 'discovery-hd',
                    ),
                    new OA\Property(
                        property: 'session_id',
                        type: 'string',
                        nullable: true,
                        example: 'iptv:01hr8g0dn7q',
                    ),
                    new OA\Property(
                        property: 'metric_value',
                        type: 'string',
                        nullable: true,
                        example: '1',
                    ),
                    new OA\Property(
                        property: 'raw',
                        type: 'string',
                        example: '=== SITE ERROR ENTRY ===\\nTimestamp: 2026-02-28T20:15:03+00:00\\nType: client_error\\n...',
                    ),
                ],
            ),
        ],
        securitySchemes: [
            new OA\SecurityScheme(
                securityScheme: 'sanctumCookie',
                type: 'apiKey',
                description: 'Sanctum stateful session cookie',
                name: 'laravel_session',
                in: 'cookie',
            ),
            new OA\SecurityScheme(
                securityScheme: 'xsrfHeader',
                type: 'apiKey',
                description: 'CSRF header for state-changing requests',
                name: 'X-XSRF-TOKEN',
                in: 'header',
            ),
        ],
    ),
    paths: [
        new OA\PathItem(
            path: '/api/site/home-content',
            get: new OA\Get(
                operationId: 'getHomeContent',
                summary: 'Get localized content for home page',
                tags: [
                    'Public',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Home content payload',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/site/world-overview',
            get: new OA\Get(
                operationId: 'getWorldOverview',
                summary: 'Get time/weather overview widget data',
                tags: [
                    'Site',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'locale',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            enum: [
                                'ru',
                                'en',
                            ],
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'World overview payload',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/site/config',
            get: new OA\Get(
                operationId: 'getPublicSiteConfig',
                summary: 'Get public site configuration for authenticated verified user',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Site',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Public config payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/feedback',
            post: new OA\Post(
                operationId: 'storeFeedback',
                summary: 'Create feedback message',
                tags: [
                    'Public',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'name',
                                    'email',
                                    'message',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string',
                                        example: 'Alex',
                                    ),
                                    new OA\Property(
                                        property: 'email',
                                        type: 'string',
                                        format: 'email',
                                        example: 'alex@example.com',
                                    ),
                                    new OA\Property(
                                        property: 'message',
                                        type: 'string',
                                        example: 'Great project!',
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 201,
                        description: 'Feedback stored',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/users',
            get: new OA\Get(
                operationId: 'getUsersIndex',
                summary: 'Search and paginate users except the current viewer',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Users',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'search',
                        description: 'Name or nickname search',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 50,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Paginated users',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            type: 'array',
                                            items: new OA\Items(
                                                ref: '#/components/schemas/UserSummary',
                                            ),
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/users/{user}/posts',
            get: new OA\Get(
                operationId: 'getUserPosts',
                summary: 'Get paginated posts for a specific user',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Users',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'user',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 50,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'User posts payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/users/following_posts',
            get: new OA\Get(
                operationId: 'getFollowingPosts',
                summary: 'Get feed posts from followed users',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Users',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 50,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Following feed payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/users/profile',
            post: new OA\Post(
                operationId: 'updateUserProfile',
                summary: 'Update current user profile and avatar',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Users',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'multipart/form-data',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'name',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string',
                                        example: 'Test User 1',
                                        maxLength: 255,
                                    ),
                                    new OA\Property(
                                        property: 'nickname',
                                        type: 'string',
                                        nullable: true,
                                        example: 'test_user_1',
                                        maxLength: 40,
                                    ),
                                    new OA\Property(
                                        property: 'avatar',
                                        type: 'string',
                                        format: 'binary',
                                    ),
                                    new OA\Property(
                                        property: 'remove_avatar',
                                        type: 'boolean',
                                        nullable: true,
                                        example: false,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Profile updated',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/users/{user}/toggle_following',
            post: new OA\Post(
                operationId: 'toggleFollowing',
                summary: 'Follow or unfollow a target user',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Users',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'user',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Follow state updated',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Invalid target',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/post_media',
            post: new OA\Post(
                operationId: 'uploadPostMedia',
                summary: 'Upload image or video asset before post creation',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Media',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'multipart/form-data',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'file',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'file',
                                        description: 'Supported formats: jpg, jpeg, png, webp, gif, mp4, webm, mov, m4v, avi, mkv. Maximum size: 200 MB.',
                                        type: 'string',
                                        format: 'binary',
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Uploaded media payload',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    ref: '#/components/schemas/UploadedPostMedia',
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/media/post-images/{postImage}',
            get: new OA\Get(
                operationId: 'getPostMediaFile',
                summary: 'Get public placeholder-aware post media file',
                tags: [
                    'Media',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'postImage',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Image or video bytes',
                        content: [
                            new OA\MediaType(
                                mediaType: 'image/*',
                                schema: new OA\Schema(
                                    type: 'string',
                                    format: 'binary',
                                ),
                            ),
                            new OA\MediaType(
                                mediaType: 'video/*',
                                schema: new OA\Schema(
                                    type: 'string',
                                    format: 'binary',
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Private media is not available',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Media not found',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/media/chat-attachments/{attachment}',
            get: new OA\Get(
                operationId: 'getChatAttachment',
                summary: 'Stream chat attachment for conversation participant',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Media',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'attachment',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Attachment bytes',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'No access to this attachment',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Attachment not found',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/media/chat-attachments/{attachment}/download',
            get: new OA\Get(
                operationId: 'downloadChatAttachment',
                summary: 'Download chat attachment for conversation participant',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Media',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'attachment',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Attachment download stream',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'No access to this attachment',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Attachment not found',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/posts',
            get: new OA\Get(
                operationId: 'getOwnPosts',
                summary: 'Get current user\'s own posts',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Posts',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 50,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Paginated own posts',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
            post: new OA\Post(
                operationId: 'createPost',
                summary: 'Create a post with optional uploaded media',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Posts',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'title',
                                    'content',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'title',
                                        type: 'string',
                                        example: 'Weekend notes',
                                        maxLength: 255,
                                    ),
                                    new OA\Property(
                                        property: 'content',
                                        type: 'string',
                                        example: 'Fresh air, good road and no notifications for two hours.',
                                        maxLength: 5000,
                                    ),
                                    new OA\Property(
                                        property: 'image_id',
                                        type: 'integer',
                                        nullable: true,
                                        example: 81,
                                    ),
                                    new OA\Property(
                                        property: 'media_ids',
                                        type: 'array',
                                        nullable: true,
                                        example: [
                                            81,
                                            82,
                                        ],
                                        items: new OA\Items(
                                            type: 'integer',
                                        ),
                                    ),
                                    new OA\Property(
                                        property: 'is_public',
                                        type: 'boolean',
                                        nullable: true,
                                        example: true,
                                    ),
                                    new OA\Property(
                                        property: 'show_in_feed',
                                        type: 'boolean',
                                        nullable: true,
                                        example: true,
                                    ),
                                    new OA\Property(
                                        property: 'show_in_carousel',
                                        type: 'boolean',
                                        nullable: true,
                                        example: false,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Created post resource',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/posts/discover',
            get: new OA\Get(
                operationId: 'getDiscoverPosts',
                summary: 'Get public discover feed',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Posts',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'sort',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            enum: [
                                'popular',
                                'most_viewed',
                                'newest',
                            ],
                        ),
                    ),
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 50,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Discover feed payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/posts/carousel',
            get: new OA\Get(
                operationId: 'getCarouselMedia',
                summary: 'Get public media items marked for home carousel',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Posts',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'limit',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 100,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Carousel media payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/posts/{post}/view',
            post: new OA\Post(
                operationId: 'markPostViewed',
                summary: 'Mark post as viewed once per viewer per day',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Posts',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'post',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'View counter payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Post is not available for this viewer',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/posts/{post}/comment',
            get: new OA\Get(
                operationId: 'getPostComments',
                summary: 'Get paginated comments for a post',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Posts',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'post',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 100,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Paginated post comments',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            type: 'array',
                                            items: new OA\Items(
                                                ref: '#/components/schemas/PostComment',
                                            ),
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
            post: new OA\Post(
                operationId: 'createPostComment',
                summary: 'Create comment for post with optional parent comment',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Posts',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'body',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'body',
                                        type: 'string',
                                        example: 'Отличный пост.',
                                        maxLength: 2000,
                                    ),
                                    new OA\Property(
                                        property: 'parent_id',
                                        type: 'integer',
                                        nullable: true,
                                        example: 15,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                parameters: [
                    new OA\Parameter(
                        name: 'post',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Created comment resource',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/radio/stations',
            get: new OA\Get(
                operationId: 'getRadioStations',
                summary: 'Search radio stations',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Radio',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'q',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                    new OA\Parameter(
                        name: 'country',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                    new OA\Parameter(
                        name: 'language',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                    new OA\Parameter(
                        name: 'tag',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                    new OA\Parameter(
                        name: 'limit',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 100,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'offset',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 0,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'List of normalized stations',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            type: 'array',
                                            items: new OA\Items(
                                                ref: '#/components/schemas/RadioStation',
                                            ),
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 503,
                        description: 'Radio catalog provider is temporarily unavailable',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/radio/stream',
            get: new OA\Get(
                operationId: 'getRadioStreamProxy',
                summary: 'Proxy external radio stream (useful for mixed-content/CORS constraints)',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Radio',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'url',
                        description: 'Original station stream URL',
                        in: 'query',
                        required: true,
                        schema: new OA\Schema(
                            type: 'string',
                            format: 'uri',
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Audio stream bytes',
                        content: [
                            new OA\MediaType(
                                mediaType: 'audio/mpeg',
                                schema: new OA\Schema(
                                    type: 'string',
                                    format: 'binary',
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Invalid or unsafe URL',
                    ),
                    new OA\Response(
                        response: 503,
                        description: 'Upstream stream unavailable',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/radio/favorites',
            get: new OA\Get(
                operationId: 'getRadioFavorites',
                summary: 'Get current user\'s favorite stations',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Radio',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Favorite stations',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            type: 'array',
                                            items: new OA\Items(
                                                ref: '#/components/schemas/RadioFavorite',
                                            ),
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
            post: new OA\Post(
                operationId: 'storeRadioFavorite',
                summary: 'Add or update station in current user\'s favorites',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Radio',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'station_uuid',
                                    'name',
                                    'stream_url',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'station_uuid',
                                        type: 'string',
                                        example: 'station-123',
                                        maxLength: 64,
                                    ),
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string',
                                        example: 'Rock FM',
                                        maxLength: 255,
                                    ),
                                    new OA\Property(
                                        property: 'stream_url',
                                        type: 'string',
                                        format: 'uri',
                                        example: 'https://stream.example.com/live',
                                        maxLength: 2000,
                                    ),
                                    new OA\Property(
                                        property: 'homepage',
                                        type: 'string',
                                        format: 'uri',
                                        nullable: true,
                                    ),
                                    new OA\Property(
                                        property: 'favicon',
                                        type: 'string',
                                        format: 'uri',
                                        nullable: true,
                                    ),
                                    new OA\Property(
                                        property: 'country',
                                        type: 'string',
                                        nullable: true,
                                    ),
                                    new OA\Property(
                                        property: 'language',
                                        type: 'string',
                                        nullable: true,
                                    ),
                                    new OA\Property(
                                        property: 'tags',
                                        type: 'string',
                                        nullable: true,
                                    ),
                                    new OA\Property(
                                        property: 'codec',
                                        type: 'string',
                                        nullable: true,
                                    ),
                                    new OA\Property(
                                        property: 'bitrate',
                                        type: 'integer',
                                        nullable: true,
                                        minimum: 0,
                                        maximum: 9999,
                                    ),
                                    new OA\Property(
                                        property: 'votes',
                                        type: 'integer',
                                        nullable: true,
                                        minimum: 0,
                                        maximum: 99999999,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 201,
                        description: 'Favorite station stored',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'message',
                                            type: 'string',
                                        ),
                                        new OA\Property(
                                            property: 'data',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(
                                                    property: 'id',
                                                    type: 'integer',
                                                ),
                                                new OA\Property(
                                                    property: 'station_uuid',
                                                    type: 'string',
                                                ),
                                            ],
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/radio/favorites/{stationUuid}',
            delete: new OA\Delete(
                operationId: 'deleteRadioFavorite',
                summary: 'Remove station from current user\'s favorites',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Radio',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'stationUuid',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Favorite removed',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/transcode/capabilities',
            get: new OA\Get(
                operationId: 'getIptvTranscodeCapabilities',
                summary: 'Check FFmpeg/transcode capabilities for current server',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Capabilities payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/playlist/fetch',
            post: new OA\Post(
                operationId: 'fetchIptvPlaylist',
                summary: 'Fetch remote IPTV playlist text by URL',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'url',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'url',
                                        type: 'string',
                                        format: 'uri',
                                        example: 'https://iptv.example.com/playlist.m3u8',
                                        maxLength: 2000,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Playlist text payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Invalid or unsafe playlist URL',
                    ),
                    new OA\Response(
                        response: 503,
                        description: 'Playlist source unavailable',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/saved',
            get: new OA\Get(
                operationId: 'getSavedIptvLibrary',
                summary: 'Get current user\'s saved IPTV playlists and channels',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Saved IPTV library payload',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(
                                                    property: 'playlists',
                                                    type: 'array',
                                                    items: new OA\Items(
                                                        ref: '#/components/schemas/IptvSavedPlaylist',
                                                    ),
                                                ),
                                                new OA\Property(
                                                    property: 'channels',
                                                    type: 'array',
                                                    items: new OA\Items(
                                                        ref: '#/components/schemas/IptvSavedChannel',
                                                    ),
                                                ),
                                            ],
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/saved/playlists',
            post: new OA\Post(
                operationId: 'storeSavedIptvPlaylist',
                summary: 'Save IPTV playlist URL into personal library',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'url',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string',
                                        nullable: true,
                                        example: 'News pack',
                                        maxLength: 120,
                                    ),
                                    new OA\Property(
                                        property: 'url',
                                        type: 'string',
                                        format: 'uri',
                                        example: 'https://iptv.example.com/news.m3u8',
                                        maxLength: 2000,
                                    ),
                                    new OA\Property(
                                        property: 'channels_count',
                                        type: 'integer',
                                        nullable: true,
                                        example: 120,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 201,
                        description: 'Playlist saved',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            ref: '#/components/schemas/IptvSavedPlaylist',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/saved/channels',
            post: new OA\Post(
                operationId: 'storeSavedIptvChannel',
                summary: 'Save IPTV channel into personal library',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'name',
                                    'url',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string',
                                        example: 'Discovery HD',
                                        maxLength: 120,
                                    ),
                                    new OA\Property(
                                        property: 'url',
                                        type: 'string',
                                        format: 'uri',
                                        example: 'https://stream.example.com/discovery.m3u8',
                                        maxLength: 4000,
                                    ),
                                    new OA\Property(
                                        property: 'group',
                                        type: 'string',
                                        nullable: true,
                                        example: 'Entertainment',
                                        maxLength: 160,
                                    ),
                                    new OA\Property(
                                        property: 'logo',
                                        type: 'string',
                                        format: 'uri',
                                        nullable: true,
                                        example: 'https://stream.example.com/logo.png',
                                        maxLength: 2000,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 201,
                        description: 'Channel saved',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            ref: '#/components/schemas/IptvSavedChannel',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/proxy/start',
            post: new OA\Post(
                operationId: 'startIptvProxy',
                summary: 'Start proxy playback session for remote IPTV stream',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'url',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'url',
                                        type: 'string',
                                        format: 'uri',
                                        example: 'https://stream.example.com/live.m3u8',
                                        maxLength: 4000,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Proxy session payload',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            ref: '#/components/schemas/PlaybackSession',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Invalid or unsafe source URL',
                    ),
                    new OA\Response(
                        response: 503,
                        description: 'Proxy session could not be started',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/transcode/start',
            post: new OA\Post(
                operationId: 'startIptvTranscode',
                summary: 'Start FFmpeg-backed compatibility session',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'url',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'url',
                                        type: 'string',
                                        format: 'uri',
                                        example: 'https://stream.example.com/live.m3u8',
                                        maxLength: 2000,
                                    ),
                                    new OA\Property(
                                        property: 'profile',
                                        type: 'string',
                                        nullable: true,
                                        example: 'balanced',
                                        enum: [
                                            'fast',
                                            'balanced',
                                            'stable',
                                        ],
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Transcode session payload',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            ref: '#/components/schemas/PlaybackSession',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Invalid or unsafe source URL',
                    ),
                    new OA\Response(
                        response: 503,
                        description: 'Compatibility session could not be started',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/relay/start',
            post: new OA\Post(
                operationId: 'startIptvRelay',
                summary: 'Start lightweight relay playback session',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'url',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'url',
                                        type: 'string',
                                        format: 'uri',
                                        example: 'https://stream.example.com/live.m3u8',
                                        maxLength: 2000,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Relay session payload',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            ref: '#/components/schemas/PlaybackSession',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Invalid or unsafe source URL',
                    ),
                    new OA\Response(
                        response: 503,
                        description: 'Relay session could not be started',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/proxy/{session}',
            delete: new OA\Delete(
                operationId: 'stopIptvProxy',
                summary: 'Stop active proxy playback session',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'session',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Proxy session stopped',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/transcode/{session}',
            delete: new OA\Delete(
                operationId: 'stopIptvTranscode',
                summary: 'Stop active transcode playback session',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'session',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Transcode session stopped',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/iptv/relay/{session}',
            delete: new OA\Delete(
                operationId: 'stopIptvRelay',
                summary: 'Stop active relay playback session',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'IPTV',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'session',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Relay session stopped',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/unread-summary',
            get: new OA\Get(
                operationId: 'getChatUnreadSummary',
                summary: 'Get unread chat counters',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Unread counters',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats',
            get: new OA\Get(
                operationId: 'getChatsIndex',
                summary: 'Get available conversations for current user',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Conversation list',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/users',
            get: new OA\Get(
                operationId: 'getChatUsers',
                summary: 'List users available for direct chats with optional search',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'search',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Chat user list',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/settings',
            get: new OA\Get(
                operationId: 'getChatSettings',
                summary: 'Get current user\'s chat storage settings',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Chat settings payload',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
            patch: new OA\Patch(
                operationId: 'updateChatSettings',
                summary: 'Update current user\'s chat storage settings',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'save_text_messages',
                                    'save_media_attachments',
                                    'save_file_attachments',
                                    'auto_archive_enabled',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'save_text_messages',
                                        type: 'boolean',
                                        example: true,
                                    ),
                                    new OA\Property(
                                        property: 'save_media_attachments',
                                        type: 'boolean',
                                        example: true,
                                    ),
                                    new OA\Property(
                                        property: 'save_file_attachments',
                                        type: 'boolean',
                                        example: true,
                                    ),
                                    new OA\Property(
                                        property: 'retention_days',
                                        type: 'integer',
                                        nullable: true,
                                        example: 30,
                                    ),
                                    new OA\Property(
                                        property: 'auto_archive_enabled',
                                        type: 'boolean',
                                        example: false,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Chat settings updated',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/archives',
            get: new OA\Get(
                operationId: 'getChatArchives',
                summary: 'List chat archives created by current user',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Archive list',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
            post: new OA\Post(
                operationId: 'createChatArchive',
                summary: 'Create downloadable archive for current user\'s chats',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                responses: [
                    new OA\Response(
                        response: 201,
                        description: 'Archive created',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/archives/{archive}/download',
            get: new OA\Get(
                operationId: 'downloadChatArchive',
                summary: 'Download previously created chat archive',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'archive',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Archive download stream',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Archive not found',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/archives/{archive}/restore',
            post: new OA\Post(
                operationId: 'restoreChatArchive',
                summary: 'Restore chat archive into current account',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'archive',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Archive restored',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Archive not found',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/direct/{user}',
            post: new OA\Post(
                operationId: 'createOrGetDirectChat',
                summary: 'Create or return existing direct chat with target user',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'user',
                        description: 'Target user id',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Conversation payload',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Invalid target (for example, current user)',
                    ),
                    new OA\Response(
                        response: 423,
                        description: 'Direct chat is blocked between users',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/{conversation}',
            get: new OA\Get(
                operationId: 'getChatConversation',
                summary: 'Get conversation details including participants and mood statuses',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Conversation details',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'No access to this conversation',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/{conversation}/read',
            post: new OA\Post(
                operationId: 'markChatRead',
                summary: 'Mark conversation as read for current user',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Conversation marked as read',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'No access to this conversation',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/{conversation}/mood-status',
            patch: new OA\Patch(
                operationId: 'upsertMoodStatus',
                summary: 'Create/update own mood status in conversation. Empty text removes status.',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'is_visible_to_all',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'text',
                                        type: 'string',
                                        nullable: true,
                                        example: 'Сегодня в хорошем настроении.',
                                        maxLength: 500,
                                    ),
                                    new OA\Property(
                                        property: 'is_visible_to_all',
                                        type: 'boolean',
                                        example: true,
                                    ),
                                    new OA\Property(
                                        property: 'hidden_user_ids',
                                        type: 'array',
                                        example: [
                                            12,
                                            15,
                                        ],
                                        items: new OA\Items(
                                            type: 'integer',
                                            minimum: 1,
                                        ),
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Mood status saved',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'No access to this conversation',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/{conversation}/messages',
            get: new OA\Get(
                operationId: 'getChatMessages',
                summary: 'Get paginated messages for conversation and mark conversation as read',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 100,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Message list',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'No access to this conversation',
                    ),
                ],
            ),
            post: new OA\Post(
                operationId: 'storeChatMessage',
                summary: 'Send message with optional text/files to conversation',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                requestBody: new OA\RequestBody(
                    required: false,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                properties: [
                                    new OA\Property(
                                        property: 'body',
                                        type: 'string',
                                        example: 'Привет!',
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 201,
                        description: 'Message created',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'No access to this conversation',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                    new OA\Response(
                        response: 423,
                        description: 'Conversation is blocked',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/{conversation}/messages/{message}/reactions',
            post: new OA\Post(
                operationId: 'toggleChatMessageReaction',
                summary: 'Toggle emoji reaction on message',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'emoji',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'emoji',
                                        type: 'string',
                                        example: '👍',
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'message',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Reaction toggled',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'No access to this conversation',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/{conversation}/messages/{message}',
            delete: new OA\Delete(
                operationId: 'deleteChatMessage',
                summary: 'Delete message (author or admin)',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'message',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Message deleted',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Access denied',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Message not found in conversation',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/chats/{conversation}/messages/{message}/attachments/{attachment}',
            delete: new OA\Delete(
                operationId: 'deleteChatMessageAttachment',
                summary: 'Delete message attachment (author or admin)',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'message',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'attachment',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Attachment deleted',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Access denied',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Attachment not found in message',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/conversations',
            get: new OA\Get(
                operationId: 'adminGetConversations',
                summary: 'Admin: list conversations with participants and messages count',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 100,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Conversation list',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                ],
            ),
            delete: new OA\Delete(
                operationId: 'adminDeleteAllConversations',
                summary: 'Admin: delete all conversations',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Admin Chat',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'All conversations deleted',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/conversations/{conversation}/messages',
            delete: new OA\Delete(
                operationId: 'adminClearConversationMessages',
                summary: 'Admin: clear all messages in a specific conversation',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Admin Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Conversation messages cleared',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Conversation not found',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/conversations/{conversation}',
            delete: new OA\Delete(
                operationId: 'adminDeleteConversation',
                summary: 'Admin: delete a conversation with all related messages/attachments',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Admin Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'conversation',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Conversation deleted',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Conversation not found',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/conversations/messages',
            delete: new OA\Delete(
                operationId: 'adminClearAllConversationMessages',
                summary: 'Admin: clear messages in all conversations',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Admin Chat',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'All conversation messages cleared',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/messages',
            get: new OA\Get(
                operationId: 'adminGetMessages',
                summary: 'Admin: list chat messages with optional conversation filter',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'conversation_id',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 200,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Message list',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/messages/{message}',
            delete: new OA\Delete(
                operationId: 'adminDeleteMessage',
                summary: 'Admin: delete specific chat message and its attachments',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Admin Chat',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'message',
                        in: 'path',
                        required: true,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Message deleted',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Message not found',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/activity/heartbeat',
            post: new OA\Post(
                operationId: 'storeActivityHeartbeat',
                summary: 'Store user activity heartbeat for current feature/session',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Activity',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                type: 'object',
                                required: [
                                    'feature',
                                    'session_id',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'feature',
                                        type: 'string',
                                        example: 'social',
                                        enum: [
                                            'social',
                                            'chats',
                                            'radio',
                                            'iptv',
                                        ],
                                    ),
                                    new OA\Property(
                                        property: 'session_id',
                                        type: 'string',
                                        example: 'social:lrzjg3h6:73ca9ba8a6674d1f8cc53a99',
                                        minLength: 8,
                                        maxLength: 120,
                                    ),
                                    new OA\Property(
                                        property: 'elapsed_seconds',
                                        type: 'integer',
                                        nullable: true,
                                        example: 30,
                                        minimum: 1,
                                        maximum: 300,
                                    ),
                                    new OA\Property(
                                        property: 'ended',
                                        type: 'boolean',
                                        nullable: true,
                                        example: false,
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Heartbeat accepted',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/analytics/events',
            post: new OA\Post(
                operationId: 'storeAnalyticsEvent',
                summary: 'Store a lightweight authenticated analytics event from the client',
                security: [
                    [
                        'sanctumCookie' => [],
                        'xsrfHeader' => [],
                    ],
                ],
                tags: [
                    'Activity',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                ref: '#/components/schemas/AnalyticsEventRequest',
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 201,
                        description: 'Analytics event accepted',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'message',
                                            type: 'string',
                                            example: 'Analytics event accepted.',
                                        ),
                                        new OA\Property(
                                            property: 'data',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(
                                                    property: 'id',
                                                    type: 'integer',
                                                    example: 501,
                                                ),
                                            ],
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/client-errors',
            post: new OA\Post(
                operationId: 'storeClientError',
                summary: 'Store a public client runtime/Vue/promise/HTTP error in the lifetime site error log',
                tags: [
                    'Activity',
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: [
                        new OA\MediaType(
                            mediaType: 'application/json',
                            schema: new OA\Schema(
                                ref: '#/components/schemas/ClientErrorRequest',
                            ),
                        ),
                    ],
                ),
                responses: [
                    new OA\Response(
                        response: 201,
                        description: 'Client error accepted',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'message',
                                            type: 'string',
                                            example: 'Client error accepted.',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                    new OA\Response(
                        response: 429,
                        description: 'Too many requests',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/summary',
            get: new OA\Get(
                operationId: 'adminSummary',
                summary: 'Admin summary counters',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Analytics',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Summary payload with direct COUNT(*) counters from users, posts, comments, media, likes, feedback, chat, and active blocks tables',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/dashboard',
            get: new OA\Get(
                operationId: 'adminDashboard',
                summary: 'Admin analytics dashboard data for selected year and optional date range',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Analytics',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'year',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 2000,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'date_from',
                        description: 'YYYY-MM-DD',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            format: 'date',
                        ),
                    ),
                    new OA\Parameter(
                        name: 'date_to',
                        description: 'YYYY-MM-DD',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            format: 'date',
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Dashboard analytics payload with KPI, retention, cohort, content, chats, media, radio, IPTV, and moderation/error sections. Uses heartbeat-derived time metrics when user_activity_daily_stats is populated; otherwise falls back to action-based counts.',
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/dashboard/export',
            get: new OA\Get(
                operationId: 'adminDashboardExport',
                summary: 'Export admin dashboard analytics (XLS or JSON) for selected range',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Analytics',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'year',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 2000,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'date_from',
                        description: 'YYYY-MM-DD',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            format: 'date',
                        ),
                    ),
                    new OA\Parameter(
                        name: 'date_to',
                        description: 'YYYY-MM-DD',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            format: 'date',
                        ),
                    ),
                    new OA\Parameter(
                        name: 'format',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            enum: [
                                'xls',
                                'json',
                            ],
                        ),
                    ),
                    new OA\Parameter(
                        name: 'locale',
                        description: 'Locale for XLS headings. JSON payload is not localized.',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            enum: [
                                'ru',
                                'en',
                            ],
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Export stream containing the same analytics payload as admin dashboard: summary KPI, retention, content, chats, media, radio, IPTV and moderation/error sections',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/vnd.ms-excel',
                                schema: new OA\Schema(
                                    type: 'string',
                                    format: 'binary',
                                ),
                            ),
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'string',
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/error-log',
            get: new OA\Get(
                operationId: 'adminErrorLogPreview',
                summary: 'Get lifetime site error log preview and archive statistics',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Diagnostics',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Preview payload for active site-errors.log and archive folder',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            ref: '#/components/schemas/SiteErrorLogPreview',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/error-log/entries',
            get: new OA\Get(
                operationId: 'adminErrorLogEntries',
                summary: 'Search and filter structured error log entries across active log and archives',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Diagnostics',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'search',
                        description: 'Free-text search across raw log entries',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            maxLength: 200,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'type',
                        description: 'Entry type filter',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            enum: [
                                'all',
                                'server_exception',
                                'client_error',
                                'analytics_failure',
                            ],
                        ),
                    ),
                    new OA\Parameter(
                        name: 'page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'per_page',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'integer',
                            minimum: 1,
                            maximum: 100,
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Paginated structured entries for admin diagnostics UI',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'data',
                                            type: 'object',
                                            properties: [
                                                new OA\Property(
                                                    property: 'items',
                                                    type: 'array',
                                                    items: new OA\Items(
                                                        ref: '#/components/schemas/SiteErrorLogEntry',
                                                    ),
                                                ),
                                                new OA\Property(
                                                    property: 'meta',
                                                    type: 'object',
                                                    properties: [
                                                        new OA\Property(
                                                            property: 'current_page',
                                                            type: 'integer',
                                                            example: 1,
                                                        ),
                                                        new OA\Property(
                                                            property: 'last_page',
                                                            type: 'integer',
                                                            example: 3,
                                                        ),
                                                        new OA\Property(
                                                            property: 'per_page',
                                                            type: 'integer',
                                                            example: 20,
                                                        ),
                                                        new OA\Property(
                                                            property: 'total',
                                                            type: 'integer',
                                                            example: 45,
                                                        ),
                                                        new OA\Property(
                                                            property: 'from',
                                                            type: 'integer',
                                                            example: 1,
                                                        ),
                                                        new OA\Property(
                                                            property: 'to',
                                                            type: 'integer',
                                                            example: 20,
                                                        ),
                                                    ],
                                                ),
                                                new OA\Property(
                                                    property: 'filters',
                                                    type: 'object',
                                                    properties: [
                                                        new OA\Property(
                                                            property: 'search',
                                                            type: 'string',
                                                            example: '500',
                                                        ),
                                                        new OA\Property(
                                                            property: 'type',
                                                            type: 'string',
                                                            example: 'client_error',
                                                        ),
                                                    ],
                                                ),
                                            ],
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/error-log/export',
            get: new OA\Get(
                operationId: 'adminErrorLogExport',
                summary: 'Export only the filtered site error log entries as a text file',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Diagnostics',
                ],
                parameters: [
                    new OA\Parameter(
                        name: 'search',
                        description: 'Free-text search across raw log entries',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            maxLength: 200,
                        ),
                    ),
                    new OA\Parameter(
                        name: 'type',
                        description: 'Entry type filter',
                        in: 'query',
                        required: false,
                        schema: new OA\Schema(
                            type: 'string',
                            enum: [
                                'all',
                                'server_exception',
                                'client_error',
                                'analytics_failure',
                            ],
                        ),
                    ),
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Plain-text filtered export stream',
                        content: [
                            new OA\MediaType(
                                mediaType: 'text/plain',
                                schema: new OA\Schema(
                                    type: 'string',
                                    format: 'binary',
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                    new OA\Response(
                        response: 422,
                        description: 'Validation error',
                    ),
                ],
            ),
        ),
        new OA\PathItem(
            path: '/api/admin/error-log/download',
            get: new OA\Get(
                operationId: 'adminErrorLogDownload',
                summary: 'Download the current raw lifetime site error log file',
                security: [
                    [
                        'sanctumCookie' => [],
                    ],
                ],
                tags: [
                    'Admin Diagnostics',
                ],
                responses: [
                    new OA\Response(
                        response: 200,
                        description: 'Current raw site-errors.log stream',
                        content: [
                            new OA\MediaType(
                                mediaType: 'text/plain',
                                schema: new OA\Schema(
                                    type: 'string',
                                    format: 'binary',
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 404,
                        description: 'Log file does not exist yet',
                        content: [
                            new OA\MediaType(
                                mediaType: 'application/json',
                                schema: new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(
                                            property: 'message',
                                            type: 'string',
                                            example: 'Site error log file does not exist yet.',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                    new OA\Response(
                        response: 401,
                        description: 'Unauthenticated',
                    ),
                    new OA\Response(
                        response: 403,
                        description: 'Admin access required',
                    ),
                ],
            ),
        ),
    ],
)]
class OpenApiSpec {}
