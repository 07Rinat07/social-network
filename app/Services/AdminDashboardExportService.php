<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardExportService
{
    private const FEATURES = ['social', 'chats', 'radio', 'iptv'];

    public function __construct(
        private readonly AdminDashboardService $adminDashboardService
    )
    {
    }

    public function buildPayload(?int $year = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $period = $this->resolvePeriod($year, $dateFrom, $dateTo);

        $dashboard = $this->adminDashboardService->build(
            $period['year_context'],
            $period['start_date'],
            $period['end_date']
        );

        $users = User::query()
            ->select(['id', 'name', 'nickname', 'email', 'is_admin', 'email_verified_at', 'created_at'])
            ->orderBy('id')
            ->get();

        $postsTotal = $this->countByUser('posts');
        $commentsTotal = $this->countByUser('comments');
        $likesTotal = $this->countByUser('liked_posts');
        $followersTotal = $this->countByUser('subscriber_followings', 'following_id');
        $followingsTotal = $this->countByUser('subscriber_followings', 'subscriber_id');

        $postsPeriod = $this->countByUserWithinRange('posts', 'created_at', $period['start'], $period['end']);
        $commentsPeriod = $this->countByUserWithinRange('comments', 'created_at', $period['start'], $period['end']);
        $likesPeriod = $this->countByUserWithinRange('liked_posts', 'created_at', $period['start'], $period['end']);
        $viewsPeriod = $this->countByUserWithinRange('post_views', 'viewed_on', $period['start_date'], $period['end_date']);
        $chatsPeriod = $this->countByUserWithinRange('conversation_messages', 'created_at', $period['start'], $period['end']);
        $radioPeriod = $this->countByUserWithinRange('radio_favorites', 'created_at', $period['start'], $period['end']);
        $iptvChannelsPeriod = $this->countByUserWithinRange('iptv_saved_channels', 'created_at', $period['start'], $period['end']);
        $iptvPlaylistsPeriod = $this->countByUserWithinRange('iptv_saved_playlists', 'created_at', $period['start'], $period['end']);

        $socialActionsPeriod = $this->sumUserMaps($postsPeriod, $commentsPeriod, $likesPeriod, $viewsPeriod);
        $iptvActionsPeriod = $this->sumUserMaps($iptvChannelsPeriod, $iptvPlaylistsPeriod);

        $heartbeat = $this->heartbeatMetrics($period['start_date'], $period['end_date']);
        $lastHeartbeatByUser = $this->lastHeartbeatByUser();

        $userRows = [];

        foreach ($users as $user) {
            $userId = (int) $user->id;

            $socialActions = (int) ($socialActionsPeriod[$userId] ?? 0);
            $chatActions = (int) ($chatsPeriod[$userId] ?? 0);
            $radioActions = (int) ($radioPeriod[$userId] ?? 0);
            $iptvActions = (int) ($iptvActionsPeriod[$userId] ?? 0);
            $totalActions = $socialActions + $chatActions + $radioActions + $iptvActions;

            $secondsByFeature = $heartbeat['seconds_by_user_feature'][$userId] ?? [];
            $socialMinutes = $this->roundMetric(((float) ($secondsByFeature['social'] ?? 0)) / 60);
            $chatMinutes = $this->roundMetric(((float) ($secondsByFeature['chats'] ?? 0)) / 60);
            $radioMinutes = $this->roundMetric(((float) ($secondsByFeature['radio'] ?? 0)) / 60);
            $iptvMinutes = $this->roundMetric(((float) ($secondsByFeature['iptv'] ?? 0)) / 60);
            $totalMinutes = $this->roundMetric($socialMinutes + $chatMinutes + $radioMinutes + $iptvMinutes);

            $preferredFeature = $this->resolvePreferredFeature(
                $totalMinutes > 0
                    ? [
                        'social' => (float) $socialMinutes,
                        'chats' => (float) $chatMinutes,
                        'radio' => (float) $radioMinutes,
                        'iptv' => (float) $iptvMinutes,
                    ]
                    : [
                        'social' => (float) $socialActions,
                        'chats' => (float) $chatActions,
                        'radio' => (float) $radioActions,
                        'iptv' => (float) $iptvActions,
                    ]
            );

            $userRows[] = [
                'user_id' => $userId,
                'name' => (string) $user->name,
                'nickname' => (string) ($user->nickname ?? ''),
                'email' => (string) $user->email,
                'is_admin' => (bool) $user->is_admin,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'registered_at' => $user->created_at?->toIso8601String(),
                'posts_total' => (int) ($postsTotal[$userId] ?? 0),
                'comments_total' => (int) ($commentsTotal[$userId] ?? 0),
                'likes_total' => (int) ($likesTotal[$userId] ?? 0),
                'followers_total' => (int) ($followersTotal[$userId] ?? 0),
                'followings_total' => (int) ($followingsTotal[$userId] ?? 0),
                'social_actions_period' => $socialActions,
                'chats_actions_period' => $chatActions,
                'radio_actions_period' => $radioActions,
                'iptv_actions_period' => $iptvActions,
                'total_actions_period' => $totalActions,
                'social_minutes_period' => $socialMinutes,
                'chats_minutes_period' => $chatMinutes,
                'radio_minutes_period' => $radioMinutes,
                'iptv_minutes_period' => $iptvMinutes,
                'total_minutes_period' => $totalMinutes,
                'heartbeat_events_period' => (int) ($heartbeat['heartbeats_by_user'][$userId] ?? 0),
                'activity_method' => $totalMinutes > 0 ? 'time_minutes' : 'actions',
                'preferred_feature_period' => $preferredFeature,
                'last_heartbeat_at' => $lastHeartbeatByUser[$userId] ?? null,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'from' => $period['start_date'],
                'to' => $period['end_date'],
                'mode' => $period['mode'],
            ],
            'selected_year' => (int) ($dashboard['selected_year'] ?? $period['year_context']),
            'dashboard' => $dashboard,
            'users' => $userRows,
        ];
    }

    public function toJson(array $payload): string
    {
        return (string) json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }

    public function toXls(array $payload, string $locale = 'en'): string
    {
        $locale = $this->normalizeExportLocale($locale);
        $dashboard = (array) ($payload['dashboard'] ?? []);
        $kpis = (array) ($dashboard['kpis'] ?? []);
        $engagement = (array) ($dashboard['engagement'] ?? []);
        $highlight = (array) ($dashboard['highlights'] ?? []);
        $period = (array) ($payload['period'] ?? []);
        $retention = (array) ($dashboard['retention'] ?? []);
        $content = (array) ($dashboard['content'] ?? []);
        $chats = (array) ($dashboard['chats'] ?? []);
        $media = (array) ($dashboard['media'] ?? []);
        $radio = (array) ($dashboard['radio'] ?? []);
        $iptv = (array) ($dashboard['iptv'] ?? []);
        $errorsAndModeration = (array) ($dashboard['errors_and_moderation'] ?? []);
        $preferenceItems = is_array($dashboard['preference']['items'] ?? null)
            ? $dashboard['preference']['items']
            : [];
        $subscriptionsByMonth = is_array($dashboard['subscriptions_by_month'] ?? null)
            ? $dashboard['subscriptions_by_month']
            : [];
        $activityByMonth = is_array($dashboard['activity_by_month'] ?? null)
            ? $dashboard['activity_by_month']
            : [];
        $cohorts = is_array($retention['cohorts'] ?? null) ? $retention['cohorts'] : [];
        $topPosts = is_array($content['top_posts'] ?? null) ? $content['top_posts'] : [];
        $topAuthors = is_array($content['top_authors'] ?? null) ? $content['top_authors'] : [];
        $chatAttachmentBreakdown = is_array($chats['attachment_breakdown'] ?? null) ? $chats['attachment_breakdown'] : [];
        $radioTopStations = is_array($radio['top_stations'] ?? null) ? $radio['top_stations'] : [];
        $iptvModeSplit = is_array($iptv['mode_split'] ?? null) ? $iptv['mode_split'] : [];
        $iptvTopChannels = is_array($iptv['top_channels'] ?? null) ? $iptv['top_channels'] : [];
        $users = is_array($payload['users'] ?? null) ? $payload['users'] : [];

        $metaRows = [
            ['Generated at', (string) ($payload['generated_at'] ?? '')],
            ['Period from', (string) ($period['from'] ?? '')],
            ['Period to', (string) ($period['to'] ?? '')],
            ['Period mode', (string) ($period['mode'] ?? 'year')],
            ['Dashboard year context', (string) ($payload['selected_year'] ?? '')],
            ['Preference model', (string) ($dashboard['preference']['method'] ?? 'actions')],
            ['Users total', (string) ($kpis['users_total'] ?? 0)],
            ['Users new (dashboard year)', (string) ($kpis['users_new_year'] ?? 0)],
            ['Subscriptions (dashboard year)', (string) ($kpis['subscriptions_year'] ?? 0)],
            ['Subscriptions avg/month (dashboard year)', (string) ($kpis['subscriptions_avg_month'] ?? 0)],
            ['Subscriptions peak month', (string) (($kpis['subscriptions_peak_month']['month'] ?? 0))],
            ['Subscriptions peak value', (string) (($kpis['subscriptions_peak_month']['value'] ?? 0))],
            ['Activity peak month', (string) ($highlight['activity_peak_month'] ?? 0)],
            ['Activity peak value', (string) ($highlight['activity_peak_value'] ?? 0)],
            ['Active users 30d', (string) ($engagement['active_users_30d'] ?? 0)],
            ['Creators 30d', (string) ($engagement['creators_30d'] ?? 0)],
            ['Chatters 30d', (string) ($engagement['chatters_30d'] ?? 0)],
            ['DAU', (string) ($retention['dau'] ?? 0)],
            ['WAU', (string) ($retention['wau'] ?? 0)],
            ['MAU', (string) ($retention['mau'] ?? 0)],
            ['Stickiness %', (string) ($retention['stickiness_percent'] ?? 0)],
            ['Content engagement/post', (string) ($content['engagement_per_post'] ?? 0)],
            ['Media upload failure rate %', (string) ($media['upload_failure_rate_percent'] ?? 0)],
            ['Radio failure rate %', (string) ($radio['failure_rate_percent'] ?? 0)],
            ['IPTV failure rate %', (string) ($iptv['failure_rate_percent'] ?? 0)],
            ['Tracked failures total', (string) ($errorsAndModeration['total_tracked_failures'] ?? 0)],
        ];

        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html><head><meta charset="UTF-8">';
        $html[] = '<style>table{border-collapse:collapse;margin-bottom:18px;}th,td{border:1px solid #7f8c8d;padding:4px 6px;font-size:12px;}th{background:#eef4fb;}h2{font-size:14px;margin:12px 0 6px;}body{font-family:Arial,sans-serif;}</style>';
        $html[] = '</head><body>';
        $html[] = '<h2>Admin Dashboard Export</h2>';

        $html[] = '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        foreach ($metaRows as $row) {
            $html[] = '<tr><td>' . $this->escape($row[0]) . '</td><td>' . $this->escape($row[1]) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Subscriptions By Month (Dashboard Year)</h2>';
        $html[] = '<table><thead><tr><th>Month</th><th>Value</th></tr></thead><tbody>';
        foreach ($subscriptionsByMonth as $item) {
            $html[] = '<tr><td>' . $this->escape($item['month'] ?? '') . '</td><td>' . $this->escape($item['value'] ?? 0) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Monthly Activity By Feature (Dashboard Year)</h2>';
        $html[] = '<table><thead><tr><th>Month</th><th>Social</th><th>Chats</th><th>Radio</th><th>IPTV</th><th>Total</th></tr></thead><tbody>';
        foreach ($activityByMonth as $item) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($item['month'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['social'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['chats'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['radio'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['iptv'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['total'] ?? 0) . '</td>'
                . '</tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Preference Distribution (Dashboard Year)</h2>';
        $html[] = '<table><thead><tr><th>Feature</th><th>Value</th><th>Share %</th></tr></thead><tbody>';
        foreach ($preferenceItems as $item) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($item['key'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['value'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['share'] ?? 0) . '</td>'
                . '</tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Retention</h2>';
        $html[] = '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        foreach ([
            ['DAU', $retention['dau'] ?? 0],
            ['WAU', $retention['wau'] ?? 0],
            ['MAU', $retention['mau'] ?? 0],
            ['Stickiness %', $retention['stickiness_percent'] ?? 0],
            ['New active users 30d', $retention['new_active_users_30d'] ?? 0],
            ['Returning users 30d', $retention['returning_users_30d'] ?? 0],
        ] as $row) {
            $html[] = '<tr><td>' . $this->escape($row[0]) . '</td><td>' . $this->escape($row[1]) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Retention Cohorts</h2>';
        $html[] = '<table><thead><tr><th>Month</th><th>New Users</th><th>Retained Users</th><th>Retention %</th><th>Partial</th></tr></thead><tbody>';
        foreach ($cohorts as $item) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($item['month'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['new_users'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['retained_users'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['retention_percent'] ?? 0) . '</td>'
                . '<td>' . $this->escape(!empty($item['partial']) ? '1' : '0') . '</td>'
                . '</tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Content</h2>';
        $html[] = '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        foreach ([
            ['Posts Total', $content['posts_total'] ?? 0],
            ['Public Posts', $content['public_posts'] ?? 0],
            ['Private Posts', $content['private_posts'] ?? 0],
            ['Carousel Posts', $content['carousel_posts'] ?? 0],
            ['Engagement Total', $content['engagement_total'] ?? 0],
            ['Views Total', $content['views_total'] ?? 0],
            ['Likes Total', $content['likes_total'] ?? 0],
            ['Comments Total', $content['comments_total'] ?? 0],
            ['Reposts Total', $content['reposts_total'] ?? 0],
            ['Engagement / Post', $content['engagement_per_post'] ?? 0],
            ['Avg Views / Post', $content['avg_views_per_post'] ?? 0],
            ['View -> Engagement Rate %', $content['view_to_engagement_rate_percent'] ?? 0],
        ] as $row) {
            $html[] = '<tr><td>' . $this->escape($row[0]) . '</td><td>' . $this->escape($row[1]) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Top Posts</h2>';
        $html[] = '<table><thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Views</th><th>Likes</th><th>Comments</th><th>Reposts</th><th>Engagement</th><th>Public</th><th>Carousel</th></tr></thead><tbody>';
        foreach ($topPosts as $item) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($item['id'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['title'] ?? '') . '</td>'
                . '<td>' . $this->escape(trim(((string) ($item['author_name'] ?? '')) . ' @' . ((string) ($item['author_nickname'] ?? '')))) . '</td>'
                . '<td>' . $this->escape($item['views_count'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['likes_count'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['comments_count'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['reposts_count'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['engagement_score'] ?? 0) . '</td>'
                . '<td>' . $this->escape(!empty($item['is_public']) ? '1' : '0') . '</td>'
                . '<td>' . $this->escape(!empty($item['show_in_carousel']) ? '1' : '0') . '</td>'
                . '</tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Top Authors</h2>';
        $html[] = '<table><thead><tr><th>User ID</th><th>Name</th><th>Nickname</th><th>Posts</th><th>Views</th><th>Engagement Total</th><th>Engagement / Post</th></tr></thead><tbody>';
        foreach ($topAuthors as $item) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($item['user_id'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['name'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['nickname'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['posts_count'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['views_count'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['engagement_total'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['engagement_per_post'] ?? 0) . '</td>'
                . '</tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Chats</h2>';
        $html[] = '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        foreach ([
            ['Messages Total', $chats['messages_total'] ?? 0],
            ['Active Chatters', $chats['active_chatters'] ?? 0],
            ['Attachments Total', $chats['attachments_total'] ?? 0],
            ['Reply Samples', $chats['reply_samples'] ?? 0],
            ['Avg Reply Minutes', $chats['avg_reply_minutes'] ?? 0],
            ['Median Reply Minutes', $chats['median_reply_minutes'] ?? 0],
        ] as $row) {
            $html[] = '<tr><td>' . $this->escape($row[0]) . '</td><td>' . $this->escape($row[1]) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Chat Attachment Breakdown</h2>';
        $html[] = '<table><thead><tr><th>Type</th><th>Value</th></tr></thead><tbody>';
        foreach ($chatAttachmentBreakdown as $item) {
            $html[] = '<tr><td>' . $this->escape($item['type'] ?? '') . '</td><td>' . $this->escape($item['value'] ?? 0) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Media</h2>';
        $html[] = '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        foreach ([
            ['Uploads Total', $media['uploads_total'] ?? 0],
            ['Post Media Uploads', $media['post_media_uploads'] ?? 0],
            ['Chat Attachment Uploads', $media['chat_attachments_uploads'] ?? 0],
            ['Images Uploaded', $media['images_uploaded'] ?? 0],
            ['Videos Uploaded', $media['videos_uploaded'] ?? 0],
            ['Avg Upload Size KB', $media['avg_upload_size_kb'] ?? 0],
            ['Failed Uploads', $media['failed_uploads'] ?? 0],
            ['Upload Failure Rate %', $media['upload_failure_rate_percent'] ?? 0],
            ['Video Sessions', $media['video_sessions'] ?? 0],
            ['Video Completed Sessions', $media['video_completed_sessions'] ?? 0],
            ['Video Completion Rate %', $media['video_completion_rate_percent'] ?? 0],
            ['Video Watch Seconds', $media['video_watch_seconds'] ?? 0],
            ['Avg Video Completion %', $media['avg_video_completion_percent'] ?? 0],
            ['Theater Opens', $media['theater_opens'] ?? 0],
            ['Fullscreen Entries', $media['fullscreen_entries'] ?? 0],
        ] as $row) {
            $html[] = '<tr><td>' . $this->escape($row[0]) . '</td><td>' . $this->escape($row[1]) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Radio</h2>';
        $html[] = '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        foreach ([
            ['Active Users Period', $radio['active_users_period'] ?? 0],
            ['Favorite Additions Period', $radio['favorite_additions_period'] ?? 0],
            ['Sessions Started', $radio['sessions_started'] ?? 0],
            ['Failures Total', $radio['failures_total'] ?? 0],
            ['Failure Rate %', $radio['failure_rate_percent'] ?? 0],
        ] as $row) {
            $html[] = '<tr><td>' . $this->escape($row[0]) . '</td><td>' . $this->escape($row[1]) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Top Radio Stations</h2>';
        $html[] = '<table><thead><tr><th>Entity Key</th><th>Entity ID</th><th>Label</th><th>Value</th></tr></thead><tbody>';
        foreach ($radioTopStations as $item) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($item['entity_key'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['entity_id'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['label'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['value'] ?? 0) . '</td>'
                . '</tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>IPTV</h2>';
        $html[] = '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        foreach ([
            ['Active Users Period', $iptv['active_users_period'] ?? 0],
            ['Saved Channels Period', $iptv['saved_channels_period'] ?? 0],
            ['Saved Playlists Period', $iptv['saved_playlists_period'] ?? 0],
            ['Sessions Started', $iptv['sessions_started'] ?? 0],
            ['Failures Total', $iptv['failures_total'] ?? 0],
            ['Failure Rate %', $iptv['failure_rate_percent'] ?? 0],
        ] as $row) {
            $html[] = '<tr><td>' . $this->escape($row[0]) . '</td><td>' . $this->escape($row[1]) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>IPTV Mode Split</h2>';
        $html[] = '<table><thead><tr><th>Mode</th><th>Started</th><th>Failed</th><th>Share %</th></tr></thead><tbody>';
        foreach ($iptvModeSplit as $item) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($item['key'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['started'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['failed'] ?? 0) . '</td>'
                . '<td>' . $this->escape($item['share'] ?? 0) . '</td>'
                . '</tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Top IPTV Channels</h2>';
        $html[] = '<table><thead><tr><th>Entity Key</th><th>Entity ID</th><th>Label</th><th>Value</th></tr></thead><tbody>';
        foreach ($iptvTopChannels as $item) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($item['entity_key'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['entity_id'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['label'] ?? '') . '</td>'
                . '<td>' . $this->escape($item['value'] ?? 0) . '</td>'
                . '</tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Errors / Moderation</h2>';
        $html[] = '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        foreach ([
            ['Media Upload Failures', $errorsAndModeration['media_upload_failures'] ?? 0],
            ['Radio Failures', $errorsAndModeration['radio_failures'] ?? 0],
            ['IPTV Failures', $errorsAndModeration['iptv_failures'] ?? 0],
            ['Tracked Failures Total', $errorsAndModeration['total_tracked_failures'] ?? 0],
            ['Active Blocks Total', $errorsAndModeration['active_blocks_total'] ?? 0],
            ['Feedback New Total', $errorsAndModeration['feedback_new_total'] ?? 0],
            ['Feedback In Progress Total', $errorsAndModeration['feedback_in_progress_total'] ?? 0],
            ['Feedback Resolved Total', $errorsAndModeration['feedback_resolved_total'] ?? 0],
            ['Feedback Created Period', $errorsAndModeration['feedback_created_period'] ?? 0],
        ] as $row) {
            $html[] = '<tr><td>' . $this->escape($row[0]) . '</td><td>' . $this->escape($row[1]) . '</td></tr>';
        }
        $html[] = '</tbody></table>';

        $html[] = '<h2>Users Activity And Statistics (Selected Period)</h2>';
        $html[] = '<table><thead><tr>'
            . '<th>User ID</th><th>Name</th><th>Nickname</th><th>Email</th><th>Is Admin</th><th>Email Verified</th><th>Registered At</th>'
            . '<th>Posts Total</th><th>Comments Total</th><th>Likes Total</th><th>Followers Total</th><th>Followings Total</th>'
            . '<th>Social Actions Period</th><th>Chats Actions Period</th><th>Radio Actions Period</th><th>IPTV Actions Period</th><th>Total Actions Period</th>'
            . '<th>Social Minutes Period</th><th>Chats Minutes Period</th><th>Radio Minutes Period</th><th>IPTV Minutes Period</th><th>Total Minutes Period</th>'
            . '<th>Heartbeat Events Period</th><th>Activity Method</th><th>Preferred Feature Period</th><th>Last Heartbeat At</th>'
            . '</tr></thead><tbody>';

        foreach ($users as $user) {
            $html[] = '<tr>'
                . '<td>' . $this->escape($user['user_id'] ?? '') . '</td>'
                . '<td>' . $this->escape($user['name'] ?? '') . '</td>'
                . '<td>' . $this->escape($user['nickname'] ?? '') . '</td>'
                . '<td>' . $this->escape($user['email'] ?? '') . '</td>'
                . '<td>' . $this->escape(($user['is_admin'] ?? false) ? '1' : '0') . '</td>'
                . '<td>' . $this->escape($user['email_verified_at'] ?? '') . '</td>'
                . '<td>' . $this->escape($user['registered_at'] ?? '') . '</td>'
                . '<td>' . $this->escape($user['posts_total'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['comments_total'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['likes_total'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['followers_total'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['followings_total'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['social_actions_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['chats_actions_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['radio_actions_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['iptv_actions_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['total_actions_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['social_minutes_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['chats_minutes_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['radio_minutes_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['iptv_minutes_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['total_minutes_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['heartbeat_events_period'] ?? 0) . '</td>'
                . '<td>' . $this->escape($user['activity_method'] ?? '') . '</td>'
                . '<td>' . $this->escape($user['preferred_feature_period'] ?? '') . '</td>'
                . '<td>' . $this->escape($user['last_heartbeat_at'] ?? '') . '</td>'
                . '</tr>';
        }

        $html[] = '</tbody></table>';
        $html[] = '</body></html>';

        $document = implode('', $html);

        return "\xEF\xBB\xBF" . $this->localizeXlsDocument($document, $locale);
    }

    protected function resolvePeriod(?int $year, ?string $dateFrom, ?string $dateTo): array
    {
        $normalizedFrom = trim((string) $dateFrom);
        $normalizedTo = trim((string) $dateTo);

        if ($normalizedFrom !== '' && $normalizedTo !== '') {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', $normalizedFrom)->startOfDay();
                $end = CarbonImmutable::createFromFormat('Y-m-d', $normalizedTo)->endOfDay();

                if ($start->greaterThan($end)) {
                    [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
                }

                return [
                    'start' => $start,
                    'end' => $end,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'mode' => 'custom_range',
                    'year_context' => $year ?? (int) $end->year,
                ];
            } catch (\Throwable) {
                // Fallback to yearly period below.
            }
        }

        $resolvedYear = $year ?? (int) now()->year;
        $start = CarbonImmutable::create($resolvedYear, 1, 1, 0, 0, 0);
        $end = $start->endOfYear();

        return [
            'start' => $start,
            'end' => $end,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'mode' => 'year',
            'year_context' => $resolvedYear,
        ];
    }

    protected function countByUser(string $table, string $userColumn = 'user_id'): array
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $userColumn)) {
            return [];
        }

        return DB::table($table)
            ->selectRaw("{$userColumn} as user_id, COUNT(*) as aggregate")
            ->groupBy($userColumn)
            ->pluck('aggregate', 'user_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    protected function countByUserWithinRange(string $table, string $dateColumn, mixed $start, mixed $end): array
    {
        if (
            !Schema::hasTable($table)
            || !Schema::hasColumn($table, 'user_id')
            || !Schema::hasColumn($table, $dateColumn)
        ) {
            return [];
        }

        return DB::table($table)
            ->selectRaw('user_id, COUNT(*) as aggregate')
            ->whereBetween($dateColumn, [$start, $end])
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    protected function sumUserMaps(array ...$maps): array
    {
        $result = [];

        foreach ($maps as $map) {
            foreach ($map as $userId => $value) {
                $userKey = (int) $userId;
                if (!isset($result[$userKey])) {
                    $result[$userKey] = 0;
                }

                $result[$userKey] += (int) $value;
            }
        }

        return $result;
    }

    protected function heartbeatMetrics(string $startDate, string $endDate): array
    {
        $secondsByUserFeature = [];
        $heartbeatsByUser = [];

        if (!Schema::hasTable('user_activity_daily_stats')) {
            return [
                'seconds_by_user_feature' => $secondsByUserFeature,
                'heartbeats_by_user' => $heartbeatsByUser,
            ];
        }

        $rows = DB::table('user_activity_daily_stats')
            ->selectRaw('user_id, feature, SUM(seconds_total) as seconds_total, SUM(heartbeats_count) as heartbeats_total')
            ->whereBetween('activity_date', [$startDate, $endDate])
            ->whereIn('feature', self::FEATURES)
            ->groupBy('user_id', 'feature')
            ->get();

        foreach ($rows as $row) {
            $userId = (int) ($row->user_id ?? 0);
            $feature = (string) ($row->feature ?? '');

            if ($userId <= 0 || !in_array($feature, self::FEATURES, true)) {
                continue;
            }

            if (!isset($secondsByUserFeature[$userId])) {
                $secondsByUserFeature[$userId] = [];
            }

            $secondsByUserFeature[$userId][$feature] = (float) ($row->seconds_total ?? 0);

            if (!isset($heartbeatsByUser[$userId])) {
                $heartbeatsByUser[$userId] = 0;
            }

            $heartbeatsByUser[$userId] += (int) ($row->heartbeats_total ?? 0);
        }

        return [
            'seconds_by_user_feature' => $secondsByUserFeature,
            'heartbeats_by_user' => $heartbeatsByUser,
        ];
    }

    protected function lastHeartbeatByUser(): array
    {
        if (!Schema::hasTable('user_activity_sessions')) {
            return [];
        }

        return DB::table('user_activity_sessions')
            ->selectRaw('user_id, MAX(last_heartbeat_at) as last_heartbeat_at')
            ->groupBy('user_id')
            ->pluck('last_heartbeat_at', 'user_id')
            ->map(fn ($value): ?string => $value ? (string) $value : null)
            ->all();
    }

    protected function resolvePreferredFeature(array $scores): ?string
    {
        $maxFeature = null;
        $maxValue = 0.0;

        foreach (self::FEATURES as $feature) {
            $value = (float) ($scores[$feature] ?? 0);
            if ($value > $maxValue) {
                $maxFeature = $feature;
                $maxValue = $value;
            }
        }

        return $maxValue > 0 ? $maxFeature : null;
    }

    protected function roundMetric(float $value): int|float
    {
        $rounded = round($value, 1);
        $integer = (int) $rounded;

        if (abs($rounded - $integer) < 0.0001) {
            return $integer;
        }

        return $rounded;
    }

    protected function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function normalizeExportLocale(string $locale): string
    {
        return trim(mb_strtolower($locale)) === 'ru' ? 'ru' : 'en';
    }

    protected function localizeXlsDocument(string $document, string $locale): string
    {
        if ($locale !== 'ru') {
            return $document;
        }

        return strtr($document, $this->xlsRussianReplacements());
    }

    protected function xlsRussianReplacements(): array
    {
        return [
            'Admin Dashboard Export' => 'Экспорт аналитики платформы',
            'Users Activity And Statistics (Selected Period)' => 'Пользователи и активность за период',
            'Subscriptions By Month (Dashboard Year)' => 'Подписки по месяцам (год дашборда)',
            'Monthly Activity By Feature (Dashboard Year)' => 'Месячная активность по модулям (год дашборда)',
            'Preference Distribution (Dashboard Year)' => 'Распределение предпочтений (год дашборда)',
            'Retention Cohorts' => 'Когорты удержания',
            'Top Radio Stations' => 'Топ радиостанций',
            'Top IPTV Channels' => 'Топ IPTV-каналов',
            'IPTV Mode Split' => 'Режимы IPTV',
            'Errors / Moderation' => 'Ошибки / модерация',
            'Top Authors' => 'Топ авторов',
            'Top Posts' => 'Топ постов',
            'Chat Attachment Breakdown' => 'Структура вложений чатов',
            '>Retention<' => '>Удержание<',
            '>Content<' => '>Контент<',
            '>Chats<' => '>Чаты<',
            '>Media<' => '>Медиа<',
            '>Radio<' => '>Радио<',
            '>IPTV<' => '>IPTV / ТВ<',
            '>Metric<' => '>Метрика<',
            '>Value<' => '>Значение<',
            '>Month<' => '>Месяц<',
            '>Feature<' => '>Модуль<',
            '>Share %<' => '>Доля %<',
            '>New Users<' => '>Новые пользователи<',
            '>Retained Users<' => '>Удержанные пользователи<',
            '>Retention %<' => '>Retention %<',
            '>Partial<' => '>Неполное окно<',
            '>ID<' => '>ID<',
            '>Title<' => '>Заголовок<',
            '>Author<' => '>Автор<',
            '>Views<' => '>Просмотры<',
            '>Likes<' => '>Лайки<',
            '>Comments<' => '>Комментарии<',
            '>Reposts<' => '>Репосты<',
            '>Engagement<' => '>Вовлеченность<',
            '>Public<' => '>Публичный<',
            '>Carousel<' => '>Карусель<',
            '>User ID<' => '>ID пользователя<',
            '>Name<' => '>Имя<',
            '>Nickname<' => '>Никнейм<',
            '>Posts<' => '>Посты<',
            '>Type<' => '>Тип<',
            '>Entity Key<' => '>Ключ сущности<',
            '>Entity ID<' => '>ID сущности<',
            '>Label<' => '>Подпись<',
            '>Mode<' => '>Режим<',
            '>Started<' => '>Запущено<',
            '>Failed<' => '>Ошибок<',
            '>Total<' => '>Всего<',
            '>Email<' => '>Email<',
            '>Admin<' => '>Админ<',
            '>Email Verified At<' => '>Email подтвержден<',
            '>Registered At<' => '>Дата регистрации<',
            '>Followers Total<' => '>Подписчиков всего<',
            '>Followings Total<' => '>Подписок всего<',
            '>Social Actions Period<' => '>Действия соцсети за период<',
            '>Chats Actions Period<' => '>Действия чатов за период<',
            '>Radio Actions Period<' => '>Действия радио за период<',
            '>IPTV Actions Period<' => '>Действия IPTV за период<',
            '>Total Actions Period<' => '>Всего действий за период<',
            '>Social Minutes Period<' => '>Минуты соцсети за период<',
            '>Chats Minutes Period<' => '>Минуты чатов за период<',
            '>Radio Minutes Period<' => '>Минуты радио за период<',
            '>IPTV Minutes Period<' => '>Минуты IPTV за период<',
            '>Total Minutes Period<' => '>Всего минут за период<',
            '>Heartbeat Events Period<' => '>Heartbeat событий за период<',
            '>Activity Method<' => '>Метод активности<',
            '>Preferred Feature Period<' => '>Предпочитаемый модуль за период<',
            '>Last Heartbeat At<' => '>Последний heartbeat<',
            'Generated at' => 'Сформировано',
            'Period from' => 'Период с',
            'Period to' => 'Период по',
            'Period mode' => 'Режим периода',
            'Dashboard year context' => 'Контекстный год дашборда',
            'Preference model' => 'Модель предпочтений',
            'Users total' => 'Пользователей всего',
            'Users new (dashboard year)' => 'Новых пользователей (год дашборда)',
            'Subscriptions (dashboard year)' => 'Подписок (год дашборда)',
            'Subscriptions avg/month (dashboard year)' => 'Подписок в среднем за месяц (год дашборда)',
            'Subscriptions peak month' => 'Пиковый месяц подписок',
            'Subscriptions peak value' => 'Пиковое значение подписок',
            'Activity peak month' => 'Пиковый месяц активности',
            'Activity peak value' => 'Пиковое значение активности',
            'Active users 30d' => 'Активные пользователи 30д',
            'Creators 30d' => 'Авторы постов 30д',
            'Chatters 30d' => 'Пишут в чатах 30д',
            'Stickiness %' => 'Stickiness %',
            'Content engagement/post' => 'Вовлеченность на пост',
            'Media upload failure rate %' => 'Доля ошибок загрузки медиа %',
            'Radio failure rate %' => 'Доля ошибок радио %',
            'IPTV failure rate %' => 'Доля ошибок IPTV %',
            'Tracked failures total' => 'Всего отслеженных ошибок',
            'New active users 30d' => 'Новые активные пользователи 30д',
            'Returning users 30d' => 'Вернувшиеся пользователи 30д',
            'Posts Total' => 'Постов всего',
            'Public Posts' => 'Публичных постов',
            'Private Posts' => 'Приватных постов',
            'Carousel Posts' => 'Постов в карусели',
            'Engagement Total' => 'Суммарная вовлеченность',
            'Views Total' => 'Просмотров всего',
            'Likes Total' => 'Лайков всего',
            'Comments Total' => 'Комментариев всего',
            'Reposts Total' => 'Репостов всего',
            'Engagement / Post' => 'Вовлеченность / пост',
            'Avg Views / Post' => 'Средние просмотры / пост',
            'View -> Engagement Rate %' => 'Конверсия просмотр -> вовлеченность %',
            'Messages Total' => 'Сообщений всего',
            'Active Chatters' => 'Активных участников чатов',
            'Attachments Total' => 'Вложений всего',
            'Reply Samples' => 'Замеров ответа',
            'Avg Reply Minutes' => 'Среднее время ответа, мин',
            'Median Reply Minutes' => 'Медианное время ответа, мин',
            'Uploads Total' => 'Загрузок всего',
            'Post Media Uploads' => 'Загрузки медиа постов',
            'Chat Attachment Uploads' => 'Загрузки вложений чатов',
            'Images Uploaded' => 'Загружено изображений',
            'Videos Uploaded' => 'Загружено видео',
            'Avg Upload Size KB' => 'Средний размер загрузки, KB',
            'Failed Uploads' => 'Неудачных загрузок',
            'Upload Failure Rate %' => 'Доля ошибок загрузки %',
            'Video Sessions' => 'Видео-сессии',
            'Video Completed Sessions' => 'Завершенные видео-сессии',
            'Video Completion Rate %' => 'Доля завершения видео %',
            'Video Watch Seconds' => 'Секунды просмотра видео',
            'Avg Video Completion %' => 'Средний completion %',
            'Theater Opens' => 'Открытия режима кино',
            'Fullscreen Entries' => 'Переходы в полноэкранный режим',
            'Active Users Period' => 'Активные пользователи за период',
            'Favorite Additions Period' => 'Добавления в избранное за период',
            'Sessions Started' => 'Стартов сессий',
            'Failures Total' => 'Ошибок всего',
            'Failure Rate %' => 'Доля ошибок %',
            'Saved Channels Period' => 'Сохранено каналов за период',
            'Saved Playlists Period' => 'Сохранено плейлистов за период',
            'Media Upload Failures' => 'Ошибки загрузки медиа',
            'Radio Failures' => 'Ошибки радио',
            'IPTV Failures' => 'Ошибки IPTV',
            'Active Blocks Total' => 'Активных блокировок',
            'Feedback New Total' => 'Новых обращений',
            'Feedback In Progress Total' => 'Обращений в работе',
            'Feedback Resolved Total' => 'Решенных обращений',
            'Feedback Created Period' => 'Обращений за период',
            '<td>social</td>' => '<td>Соцсеть</td>',
            '<td>chats</td>' => '<td>Чаты</td>',
            '<td>radio</td>' => '<td>Радио</td>',
            '<td>iptv</td>' => '<td>IPTV / ТВ</td>',
            '<td>actions</td>' => '<td>действия</td>',
            '<td>time_minutes</td>' => '<td>время, минуты</td>',
        ];
    }
}
