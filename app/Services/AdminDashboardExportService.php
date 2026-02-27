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

    public function toXls(array $payload): string
    {
        $dashboard = (array) ($payload['dashboard'] ?? []);
        $kpis = (array) ($dashboard['kpis'] ?? []);
        $engagement = (array) ($dashboard['engagement'] ?? []);
        $highlight = (array) ($dashboard['highlights'] ?? []);
        $period = (array) ($payload['period'] ?? []);
        $preferenceItems = is_array($dashboard['preference']['items'] ?? null)
            ? $dashboard['preference']['items']
            : [];
        $subscriptionsByMonth = is_array($dashboard['subscriptions_by_month'] ?? null)
            ? $dashboard['subscriptions_by_month']
            : [];
        $activityByMonth = is_array($dashboard['activity_by_month'] ?? null)
            ? $dashboard['activity_by_month']
            : [];
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

        return "\xEF\xBB\xBF" . implode('', $html);
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
}
