<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\FeedbackMessage;
use App\Models\IptvSavedChannel;
use App\Models\IptvSavedPlaylist;
use App\Models\LikedPost;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\RadioFavorite;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAndChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_summary(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/summary');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/dashboard');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_non_admin_cannot_access_admin_dashboard_export(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->get('/api/admin/dashboard/export?format=xls');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_admin_can_access_admin_summary(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/summary');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'users',
                    'admins',
                    'posts',
                    'comments',
                    'media',
                    'feedback_new',
                    'feedback_in_progress',
                    'feedback_resolved',
                    'conversations',
                    'messages',
                    'chat_attachments',
                    'active_blocks',
                ],
            ]);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard?year=' . now()->year);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'selected_year',
                    'available_years',
                    'period' => [
                        'mode',
                        'from',
                        'to',
                    ],
                    'kpis' => [
                        'users_total',
                        'users_new_year',
                        'users_new_period',
                        'subscriptions_total',
                        'subscriptions_year',
                        'subscriptions_period',
                        'subscriptions_previous_year',
                        'subscriptions_change_percent',
                        'subscriptions_avg_month',
                        'period_months',
                        'subscriptions_peak_month' => [
                            'month',
                            'value',
                        ],
                    ],
                    'subscriptions_by_month',
                    'registrations_by_month',
                    'activity_by_month',
                    'preference' => [
                        'method',
                        'total_actions',
                        'leader_key',
                        'items',
                    ],
                    'engagement' => [
                        'active_users_30d',
                        'creators_30d',
                        'chatters_30d',
                        'new_users_30d',
                        'social_active_users_30d',
                        'chat_active_users_30d',
                        'radio_active_users_30d',
                        'iptv_active_users_30d',
                    ],
                    'highlights' => [
                        'subscriptions_peak_month',
                        'activity_peak_month',
                        'activity_peak_value',
                    ],
                    'retention' => [
                        'dau',
                        'wau',
                        'mau',
                        'stickiness_percent',
                        'new_active_users_30d',
                        'returning_users_30d',
                        'cohorts',
                    ],
                    'content' => [
                        'posts_total',
                        'public_posts',
                        'private_posts',
                        'carousel_posts',
                        'engagement_total',
                        'views_total',
                        'likes_total',
                        'comments_total',
                        'reposts_total',
                        'engagement_per_post',
                        'avg_views_per_post',
                        'view_to_engagement_rate_percent',
                        'top_posts',
                        'top_authors',
                    ],
                    'chats' => [
                        'messages_total',
                        'active_chatters',
                        'attachments_total',
                        'attachment_breakdown',
                        'reply_samples',
                        'avg_reply_minutes',
                        'median_reply_minutes',
                    ],
                    'media' => [
                        'uploads_total',
                        'post_media_uploads',
                        'chat_attachments_uploads',
                        'images_uploaded',
                        'videos_uploaded',
                        'avg_upload_size_kb',
                        'failed_uploads',
                        'upload_failure_rate_percent',
                        'video_sessions',
                        'video_completed_sessions',
                        'video_completion_rate_percent',
                        'video_watch_seconds',
                        'avg_video_completion_percent',
                        'theater_opens',
                        'fullscreen_entries',
                    ],
                    'radio' => [
                        'active_users_period',
                        'favorite_additions_period',
                        'sessions_started',
                        'failures_total',
                        'failure_rate_percent',
                        'top_stations',
                    ],
                    'iptv' => [
                        'active_users_period',
                        'saved_channels_period',
                        'saved_playlists_period',
                        'sessions_started',
                        'failures_total',
                        'failure_rate_percent',
                        'mode_split',
                        'top_channels',
                    ],
                    'errors_and_moderation' => [
                        'media_upload_failures',
                        'radio_failures',
                        'iptv_failures',
                        'total_tracked_failures',
                        'active_blocks_total',
                        'feedback_new_total',
                        'feedback_in_progress_total',
                        'feedback_resolved_total',
                        'feedback_created_period',
                    ],
                ],
            ])
            ->assertJsonCount(12, 'data.subscriptions_by_month')
            ->assertJsonCount(12, 'data.registrations_by_month')
            ->assertJsonCount(12, 'data.activity_by_month');
    }

    public function test_admin_dashboard_supports_custom_date_range_within_selected_year(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $from = now()->startOfYear()->toDateString();
        $to = now()->startOfYear()->addDays(14)->toDateString();

        $response = $this->getJson("/api/admin/dashboard?year=" . now()->year . "&date_from={$from}&date_to={$to}");

        $response
            ->assertOk()
            ->assertJsonPath('data.period.mode', 'custom_range')
            ->assertJsonPath('data.period.from', $from)
            ->assertJsonPath('data.period.to', $to)
            ->assertJsonPath('data.selected_year', now()->year);
    }

    public function test_admin_dashboard_clamps_custom_range_to_selected_year_bounds(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $year = (int) now()->year;
        $from = ($year - 1) . '-12-20';
        $to = ($year + 1) . '-01-15';

        $response = $this->getJson("/api/admin/dashboard?year={$year}&date_from={$from}&date_to={$to}");

        $response
            ->assertOk()
            ->assertJsonPath('data.period.mode', 'custom_range')
            ->assertJsonPath('data.period.from', "{$year}-01-01")
            ->assertJsonPath('data.period.to', "{$year}-12-31")
            ->assertJsonPath('data.period.is_clamped', true);
    }

    public function test_admin_dashboard_rejects_invalid_date_range_payload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $missingTo = $this->getJson('/api/admin/dashboard?date_from=' . now()->toDateString());
        $missingTo
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);

        $invalidOrder = $this->getJson('/api/admin/dashboard?date_from=2026-02-20&date_to=2026-02-10');
        $invalidOrder
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_admin_dashboard_export_rejects_invalid_params(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $invalidFormat = $this->getJson('/api/admin/dashboard/export?format=csv');
        $invalidFormat
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format']);

        $missingTo = $this->getJson('/api/admin/dashboard/export?date_from=2026-01-01&format=json');
        $missingTo
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_admin_dashboard_uses_time_based_method_when_heartbeat_stats_exist(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        Sanctum::actingAs($admin);

        DB::table('user_activity_daily_stats')->insert([
            'user_id' => $member->id,
            'feature' => 'radio',
            'activity_date' => now()->toDateString(),
            'seconds_total' => 3600,
            'heartbeats_count' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/dashboard?year=' . now()->year);

        $response
            ->assertOk()
            ->assertJsonPath('data.preference.method', 'time_minutes')
            ->assertJsonPath('data.preference.leader_key', 'radio')
            ->assertJsonPath('data.engagement.radio_active_users_30d', 1);
    }

    public function test_admin_can_export_dashboard_in_xls_and_json_with_custom_period(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $xlsResponse = $this->get("/api/admin/dashboard/export?format=xls&date_from={$from}&date_to={$to}");

        $xlsResponse->assertOk();
        $this->assertStringContainsString('.xls', (string) $xlsResponse->headers->get('content-disposition'));
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $xlsResponse->headers->get('content-type'));
        $xlsContent = $xlsResponse->streamedContent();
        $this->assertStringContainsString('Users Activity And Statistics (Selected Period)', $xlsContent);
        $this->assertStringContainsString('Retention', $xlsContent);
        $this->assertStringContainsString('Content', $xlsContent);
        $this->assertStringContainsString('Chats', $xlsContent);
        $this->assertStringContainsString('Media', $xlsContent);
        $this->assertStringContainsString('Radio', $xlsContent);
        $this->assertStringContainsString('IPTV', $xlsContent);
        $this->assertStringContainsString('Errors / Moderation', $xlsContent);

        $jsonResponse = $this->get("/api/admin/dashboard/export?format=json&date_from={$from}&date_to={$to}");

        $jsonResponse->assertOk();
        $this->assertStringContainsString('.json', (string) $jsonResponse->headers->get('content-disposition'));
        $this->assertStringContainsString('application/json', (string) $jsonResponse->headers->get('content-type'));

        $decoded = json_decode($jsonResponse->streamedContent(), true);
        $this->assertIsArray($decoded);
        $this->assertSame($from, $decoded['period']['from'] ?? null);
        $this->assertSame($to, $decoded['period']['to'] ?? null);
        $this->assertArrayHasKey('dashboard', $decoded);
        $this->assertArrayHasKey('users', $decoded);
        $this->assertSame('custom_range', $decoded['dashboard']['period']['mode'] ?? null);
        $this->assertSame($from, $decoded['dashboard']['period']['from'] ?? null);
        $this->assertSame($to, $decoded['dashboard']['period']['to'] ?? null);
        $this->assertArrayHasKey('retention', $decoded['dashboard'] ?? []);
        $this->assertArrayHasKey('content', $decoded['dashboard'] ?? []);
        $this->assertArrayHasKey('chats', $decoded['dashboard'] ?? []);
        $this->assertArrayHasKey('media', $decoded['dashboard'] ?? []);
        $this->assertArrayHasKey('radio', $decoded['dashboard'] ?? []);
        $this->assertArrayHasKey('iptv', $decoded['dashboard'] ?? []);
        $this->assertArrayHasKey('errors_and_moderation', $decoded['dashboard'] ?? []);
    }

    public function test_admin_can_export_dashboard_in_russian_xls_locale(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $response = $this->get("/api/admin/dashboard/export?format=xls&locale=ru&date_from={$from}&date_to={$to}");

        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('Экспорт аналитики платформы', $content);
        $this->assertStringContainsString('Метрика', $content);
        $this->assertStringContainsString('Удержание', $content);
        $this->assertStringContainsString('Ошибки / модерация', $content);
        $this->assertStringContainsString('Пользователи и активность за период', $content);
    }

    public function test_authenticated_user_can_store_client_analytics_event(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/analytics/events', [
            'feature' => AnalyticsEvent::FEATURE_MEDIA,
            'event_name' => AnalyticsEvent::EVENT_VIDEO_SESSION,
            'entity_type' => 'post_media',
            'entity_id' => 81,
            'entity_key' => 'clip-81',
            'session_id' => 'video:analytics:session-81',
            'duration_seconds' => 95,
            'metric_value' => 82.4,
            'context' => [
                'completed' => true,
                'source' => 'theater',
            ],
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('message', 'Analytics event accepted.')
            ->assertJsonPath('data.id', 1);

        $this->assertDatabaseHas('analytics_events', [
            'id' => 1,
            'user_id' => $user->id,
            'feature' => AnalyticsEvent::FEATURE_MEDIA,
            'event_name' => AnalyticsEvent::EVENT_VIDEO_SESSION,
            'entity_type' => 'post_media',
            'entity_id' => 81,
            'entity_key' => 'clip-81',
            'session_id' => 'video:analytics:session-81',
            'duration_seconds' => 95,
            'metric_value' => 82.4,
        ]);
    }

    public function test_analytics_event_endpoint_rejects_invalid_event_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/analytics/events', [
            'feature' => AnalyticsEvent::FEATURE_MEDIA,
            'event_name' => 'bad_event_name',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_name']);
    }

    public function test_admin_dashboard_aggregates_retention_content_media_transport_and_quality_analytics(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 28, 12, 0, 0));

        try {
            $admin = User::factory()->create([
                'is_admin' => true,
                'created_at' => now()->subYear(),
                'updated_at' => now()->subYear(),
            ]);
            $newActiveUser = User::factory()->create([
                'name' => 'New Active',
                'nickname' => 'new_active',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]);
            $returningUser = User::factory()->create([
                'name' => 'Returning User',
                'nickname' => 'returning_user',
                'created_at' => now()->subDays(80),
                'updated_at' => now()->subDays(80),
            ]);
            $viewer = User::factory()->create([
                'name' => 'Viewer User',
                'nickname' => 'viewer_user',
                'created_at' => now()->subDays(60),
                'updated_at' => now()->subDays(60),
            ]);

            Sanctum::actingAs($admin);

            $primaryPost = Post::query()->create([
                'title' => 'Primary analytics post',
                'content' => 'Main content for analytics aggregation.',
                'user_id' => $newActiveUser->id,
                'is_public' => true,
                'show_in_feed' => true,
                'show_in_carousel' => true,
            ]);
            DB::table('posts')->where('id', $primaryPost->id)->update([
                'created_at' => now()->subDay()->setTime(9, 0),
                'updated_at' => now()->subDay()->setTime(9, 0),
            ]);

            $secondaryPost = Post::query()->create([
                'title' => 'Private draft',
                'content' => 'Private content for analytics aggregation.',
                'user_id' => $returningUser->id,
                'is_public' => false,
                'show_in_feed' => false,
                'show_in_carousel' => false,
            ]);
            DB::table('posts')->where('id', $secondaryPost->id)->update([
                'created_at' => now()->subDays(2)->setTime(11, 0),
                'updated_at' => now()->subDays(2)->setTime(11, 0),
            ]);

            $repost = Post::query()->create([
                'title' => 'Repost',
                'content' => 'Boosting the main post.',
                'user_id' => $returningUser->id,
                'reposted_id' => $primaryPost->id,
                'is_public' => true,
                'show_in_feed' => true,
                'show_in_carousel' => false,
            ]);
            DB::table('posts')->where('id', $repost->id)->update([
                'created_at' => now()->setTime(9, 30),
                'updated_at' => now()->setTime(9, 30),
            ]);

            $comment = Comment::query()->create([
                'body' => 'Nice analytics-friendly post.',
                'user_id' => $viewer->id,
                'post_id' => $primaryPost->id,
            ]);
            DB::table('comments')->where('id', $comment->id)->update([
                'created_at' => now()->setTime(10, 20),
                'updated_at' => now()->setTime(10, 20),
            ]);

            $like = LikedPost::query()->create([
                'user_id' => $viewer->id,
                'post_id' => $primaryPost->id,
            ]);
            DB::table('liked_posts')->where('id', $like->id)->update([
                'created_at' => now()->setTime(10, 25),
                'updated_at' => now()->setTime(10, 25),
            ]);

            DB::table('post_views')->insert([
                [
                    'post_id' => $primaryPost->id,
                    'user_id' => $viewer->id,
                    'viewed_on' => now()->toDateString(),
                    'created_at' => now()->setTime(10, 30),
                    'updated_at' => now()->setTime(10, 30),
                ],
                [
                    'post_id' => $secondaryPost->id,
                    'user_id' => $returningUser->id,
                    'viewed_on' => now()->subDay()->toDateString(),
                    'created_at' => now()->subDay()->setTime(18, 0),
                    'updated_at' => now()->subDay()->setTime(18, 0),
                ],
            ]);

            $conversation = Conversation::query()->create([
                'type' => Conversation::TYPE_DIRECT,
                'created_by' => $newActiveUser->id,
            ]);
            DB::table('conversations')->where('id', $conversation->id)->update([
                'created_at' => now()->subDay()->setTime(9, 45),
                'updated_at' => now()->subDay()->setTime(9, 45),
            ]);

            $firstMessage = ConversationMessage::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $newActiveUser->id,
                'body' => 'First direct message.',
            ]);
            DB::table('conversation_messages')->where('id', $firstMessage->id)->update([
                'created_at' => now()->setTime(10, 0),
                'updated_at' => now()->setTime(10, 0),
            ]);

            $secondMessage = ConversationMessage::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $returningUser->id,
                'body' => 'Reply in fifteen minutes.',
            ]);
            DB::table('conversation_messages')->where('id', $secondMessage->id)->update([
                'created_at' => now()->setTime(10, 15),
                'updated_at' => now()->setTime(10, 15),
            ]);

            $attachment = ConversationMessageAttachment::query()->create([
                'conversation_message_id' => $secondMessage->id,
                'path' => 'chat/test-image.jpg',
                'storage_disk' => 'public',
                'type' => ConversationMessageAttachment::TYPE_IMAGE,
                'mime_type' => 'image/jpeg',
                'size' => 1024,
                'original_name' => 'test-image.jpg',
            ]);
            DB::table('conversation_message_attachments')->where('id', $attachment->id)->update([
                'created_at' => now()->setTime(10, 15),
                'updated_at' => now()->setTime(10, 15),
            ]);

            $postMedia = PostImage::query()->create([
                'path' => 'posts/test-video.mp4',
                'post_id' => $primaryPost->id,
                'user_id' => $newActiveUser->id,
                'storage_disk' => 'public',
                'type' => PostImage::TYPE_VIDEO,
                'mime_type' => 'video/mp4',
                'size' => 2048,
                'original_name' => 'test-video.mp4',
            ]);
            DB::table('post_images')->where('id', $postMedia->id)->update([
                'created_at' => now()->subDay()->setTime(9, 5),
                'updated_at' => now()->subDay()->setTime(9, 5),
            ]);

            $radioFavorite = RadioFavorite::query()->create([
                'user_id' => $viewer->id,
                'station_uuid' => 'station-1',
                'name' => 'Synth FM',
                'stream_url' => 'https://radio.example.test/live',
            ]);
            DB::table('radio_favorites')->where('id', $radioFavorite->id)->update([
                'created_at' => now()->setTime(8, 0),
                'updated_at' => now()->setTime(8, 0),
            ]);

            $savedPlaylist = IptvSavedPlaylist::query()->create([
                'user_id' => $viewer->id,
                'name' => 'Main playlist',
                'source_url' => 'https://iptv.example.test/main.m3u8',
                'source_url_hash' => hash('sha256', 'https://iptv.example.test/main.m3u8'),
                'channels_count' => 120,
            ]);
            DB::table('iptv_saved_playlists')->where('id', $savedPlaylist->id)->update([
                'created_at' => now()->setTime(8, 5),
                'updated_at' => now()->setTime(8, 5),
            ]);

            $savedChannel = IptvSavedChannel::query()->create([
                'user_id' => $viewer->id,
                'name' => 'News 24',
                'stream_url' => 'https://iptv.example.test/news-24.m3u8',
                'stream_url_hash' => hash('sha256', 'https://iptv.example.test/news-24.m3u8'),
                'group_title' => 'News',
            ]);
            DB::table('iptv_saved_channels')->where('id', $savedChannel->id)->update([
                'created_at' => now()->setTime(8, 10),
                'updated_at' => now()->setTime(8, 10),
            ]);

            FeedbackMessage::query()->create([
                'user_id' => $newActiveUser->id,
                'name' => 'Feedback New',
                'email' => 'new@example.test',
                'message' => 'New feedback',
                'status' => FeedbackMessage::STATUS_NEW,
            ]);
            FeedbackMessage::query()->create([
                'user_id' => $returningUser->id,
                'name' => 'Feedback Progress',
                'email' => 'progress@example.test',
                'message' => 'Progress feedback',
                'status' => FeedbackMessage::STATUS_IN_PROGRESS,
            ]);
            FeedbackMessage::query()->create([
                'user_id' => $viewer->id,
                'name' => 'Feedback Resolved',
                'email' => 'resolved@example.test',
                'message' => 'Resolved feedback',
                'status' => FeedbackMessage::STATUS_RESOLVED,
            ]);

            UserBlock::query()->create([
                'blocker_id' => $admin->id,
                'blocked_user_id' => $viewer->id,
                'expires_at' => now()->addDay(),
                'reason' => 'Temporary moderation check',
            ]);

            $this->storeAnalyticsEventRecord($newActiveUser, AnalyticsEvent::FEATURE_MEDIA, AnalyticsEvent::EVENT_MEDIA_UPLOAD_FAILED, [
                'created_at' => now()->setTime(7, 0),
                'entity_type' => 'post_media',
                'entity_id' => $postMedia->id,
                'context' => ['reason' => 'validation'],
            ]);
            $this->storeAnalyticsEventRecord($viewer, AnalyticsEvent::FEATURE_MEDIA, AnalyticsEvent::EVENT_VIDEO_SESSION, [
                'created_at' => now()->setTime(11, 0),
                'entity_type' => 'post_media',
                'entity_id' => $postMedia->id,
                'session_id' => 'video:test:session',
                'duration_seconds' => 95,
                'metric_value' => 82.4,
                'context' => ['completed' => true, 'source' => 'theater'],
            ]);
            $this->storeAnalyticsEventRecord($viewer, AnalyticsEvent::FEATURE_MEDIA, AnalyticsEvent::EVENT_VIDEO_THEATER_OPEN, [
                'created_at' => now()->setTime(11, 1),
                'entity_type' => 'post_media',
                'entity_id' => $postMedia->id,
            ]);
            $this->storeAnalyticsEventRecord($viewer, AnalyticsEvent::FEATURE_MEDIA, AnalyticsEvent::EVENT_VIDEO_FULLSCREEN_ENTER, [
                'created_at' => now()->setTime(11, 2),
                'entity_type' => 'post_media',
                'entity_id' => $postMedia->id,
            ]);
            $this->storeAnalyticsEventRecord($viewer, AnalyticsEvent::FEATURE_RADIO, AnalyticsEvent::EVENT_RADIO_PLAY_STARTED, [
                'created_at' => now()->setTime(8, 15),
                'entity_type' => 'radio_station',
                'entity_key' => 'station-1',
                'context' => ['station_name' => 'Synth FM'],
            ]);
            $this->storeAnalyticsEventRecord($viewer, AnalyticsEvent::FEATURE_RADIO, AnalyticsEvent::EVENT_RADIO_PLAY_FAILED, [
                'created_at' => now()->setTime(8, 18),
                'entity_type' => 'radio_station',
                'entity_key' => 'station-1',
                'context' => ['station_name' => 'Synth FM', 'reason' => 'timeout'],
            ]);
            $this->storeAnalyticsEventRecord($viewer, AnalyticsEvent::FEATURE_IPTV, AnalyticsEvent::EVENT_IPTV_DIRECT_STARTED, [
                'created_at' => now()->setTime(8, 20),
                'entity_type' => 'iptv_channel',
                'entity_key' => 'channel-news-24',
                'context' => ['channel_name' => 'News 24'],
            ]);
            $this->storeAnalyticsEventRecord($viewer, AnalyticsEvent::FEATURE_IPTV, AnalyticsEvent::EVENT_IPTV_PROXY_STARTED, [
                'created_at' => now()->setTime(8, 25),
                'entity_type' => 'iptv_channel',
                'entity_key' => 'channel-news-24',
                'context' => ['channel_name' => 'News 24'],
            ]);
            $this->storeAnalyticsEventRecord($viewer, AnalyticsEvent::FEATURE_IPTV, AnalyticsEvent::EVENT_IPTV_PROXY_FAILED, [
                'created_at' => now()->setTime(8, 26),
                'entity_type' => 'iptv_channel',
                'entity_key' => 'channel-news-24',
                'context' => ['channel_name' => 'News 24', 'stage' => 'playback'],
            ]);

            $from = now()->subDays(10)->toDateString();
            $to = now()->toDateString();

            $response = $this->getJson("/api/admin/dashboard?year=2026&date_from={$from}&date_to={$to}");

            $response
                ->assertOk()
                ->assertJsonPath('data.retention.dau', 3)
                ->assertJsonPath('data.retention.wau', 3)
                ->assertJsonPath('data.retention.mau', 3)
                ->assertJsonPath('data.retention.new_active_users_30d', 1)
                ->assertJsonPath('data.retention.returning_users_30d', 2)
                ->assertJsonPath('data.content.posts_total', 3)
                ->assertJsonPath('data.content.public_posts', 2)
                ->assertJsonPath('data.content.private_posts', 1)
                ->assertJsonPath('data.content.carousel_posts', 1)
                ->assertJsonPath('data.content.engagement_total', 3)
                ->assertJsonPath('data.content.views_total', 2)
                ->assertJsonPath('data.content.top_posts.0.id', $primaryPost->id)
                ->assertJsonPath('data.content.top_authors.0.user_id', $newActiveUser->id)
                ->assertJsonPath('data.chats.messages_total', 2)
                ->assertJsonPath('data.chats.active_chatters', 2)
                ->assertJsonPath('data.chats.attachments_total', 1)
                ->assertJsonPath('data.chats.avg_reply_minutes', 15)
                ->assertJsonPath('data.chats.median_reply_minutes', 15)
                ->assertJsonPath('data.media.uploads_total', 2)
                ->assertJsonPath('data.media.failed_uploads', 1)
                ->assertJsonPath('data.media.video_sessions', 1)
                ->assertJsonPath('data.media.video_completed_sessions', 1)
                ->assertJsonPath('data.media.video_completion_rate_percent', 100)
                ->assertJsonPath('data.media.video_watch_seconds', 95)
                ->assertJsonPath('data.media.theater_opens', 1)
                ->assertJsonPath('data.media.fullscreen_entries', 1)
                ->assertJsonPath('data.radio.sessions_started', 1)
                ->assertJsonPath('data.radio.failures_total', 1)
                ->assertJsonPath('data.radio.favorite_additions_period', 1)
                ->assertJsonPath('data.radio.top_stations.0.label', 'Synth FM')
                ->assertJsonPath('data.iptv.sessions_started', 2)
                ->assertJsonPath('data.iptv.failures_total', 1)
                ->assertJsonPath('data.iptv.saved_channels_period', 1)
                ->assertJsonPath('data.iptv.saved_playlists_period', 1)
                ->assertJsonPath('data.errors_and_moderation.total_tracked_failures', 3)
                ->assertJsonPath('data.errors_and_moderation.active_blocks_total', 1)
                ->assertJsonPath('data.errors_and_moderation.feedback_new_total', 1)
                ->assertJsonPath('data.errors_and_moderation.feedback_in_progress_total', 1)
                ->assertJsonPath('data.errors_and_moderation.feedback_resolved_total', 1);

            $payload = $response->json('data');
            $this->assertIsArray($payload);

            $cohort = collect($payload['retention']['cohorts'] ?? [])->firstWhere('month', 2);
            $this->assertIsArray($cohort);
            $this->assertSame(1, $cohort['new_users'] ?? null);
            $this->assertSame(1, $cohort['retained_users'] ?? null);
            $this->assertTrue((bool) ($cohort['partial'] ?? false));

            $modeSplit = collect($payload['iptv']['mode_split'] ?? []);
            $this->assertSame(1, $modeSplit->firstWhere('key', 'direct')['started'] ?? null);
            $this->assertSame(1, $modeSplit->firstWhere('key', 'proxy')['started'] ?? null);
            $this->assertSame(1, $modeSplit->firstWhere('key', 'proxy')['failed'] ?? null);
            $this->assertSame('News 24', $payload['iptv']['top_channels'][0]['label'] ?? null);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_feedback_is_available_for_guests(): void
    {
        $response = $this->postJson('/api/feedback', [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'message' => 'Please improve search and moderation tools.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonFragment([
                'message' => 'Спасибо! Ваше сообщение отправлено администрации.',
            ]);

        $this->assertDatabaseHas('feedback_messages', [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'status' => FeedbackMessage::STATUS_NEW,
        ]);
    }

    public function test_user_can_create_direct_chat_and_send_message(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        Sanctum::actingAs($firstUser);

        $conversationResponse = $this->postJson("/api/chats/direct/{$secondUser->id}");

        $conversationResponse
            ->assertOk()
            ->assertJsonPath('data.type', Conversation::TYPE_DIRECT);

        $conversationId = $conversationResponse->json('data.id');

        $sendResponse = $this->postJson("/api/chats/{$conversationId}/messages", [
            'body' => 'Привет! Проверка личного чата.',
        ]);

        $sendResponse
            ->assertStatus(201)
            ->assertJsonPath('data.body', 'Привет! Проверка личного чата.');

        $messagesResponse = $this->getJson("/api/chats/{$conversationId}/messages");

        $messagesResponse
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    protected function storeAnalyticsEventRecord(User $user, string $feature, string $eventName, array $attributes = []): void
    {
        AnalyticsEvent::query()->create([
            'user_id' => $user->id,
            'feature' => $feature,
            'event_name' => $eventName,
            'entity_type' => $attributes['entity_type'] ?? null,
            'entity_id' => $attributes['entity_id'] ?? null,
            'entity_key' => $attributes['entity_key'] ?? null,
            'session_id' => $attributes['session_id'] ?? null,
            'duration_seconds' => $attributes['duration_seconds'] ?? 0,
            'metric_value' => $attributes['metric_value'] ?? null,
            'context' => $attributes['context'] ?? null,
            'created_at' => $attributes['created_at'] ?? now(),
        ]);
    }
}
