<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardService
{
    protected const TRACKED_FEATURES = ['social', 'chats', 'radio', 'iptv'];

    public function build(?int $year = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $currentYear = (int) now()->year;
        $availableYears = $this->availableYears($currentYear);
        $requestedRange = $this->parseDateRange($dateFrom, $dateTo);
        $selectedYearCandidate = $year ?? ($requestedRange['end']->year ?? null);
        $selectedYear = $this->resolveSelectedYear($selectedYearCandidate, $availableYears, $currentYear);
        $period = $this->resolvePeriodForYear($selectedYear, $requestedRange);

        $start = $period['start'];
        $end = $period['end'];
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $referenceEnd = $this->resolveReferenceMoment($end);

        $subscriptionsByMonth = $this->monthlyCounts('subscriber_followings', 'created_at', $start, $end);
        $registrationsByMonth = $this->monthlyCounts('users', 'created_at', $start, $end);

        $postsByMonth = $this->monthlyCounts('posts', 'created_at', $start, $end);
        $commentsByMonth = $this->monthlyCounts('comments', 'created_at', $start, $end);
        $likesByMonth = $this->monthlyCounts('liked_posts', 'created_at', $start, $end);
        $viewsByMonth = $this->monthlyCounts('post_views', 'viewed_on', $startDate, $endDate);
        $chatMessagesByMonth = $this->monthlyCounts('conversation_messages', 'created_at', $start, $end);
        $radioByMonth = $this->monthlyCounts('radio_favorites', 'created_at', $start, $end);
        $iptvChannelsByMonth = $this->monthlyCounts('iptv_saved_channels', 'created_at', $start, $end);
        $iptvPlaylistsByMonth = $this->monthlyCounts('iptv_saved_playlists', 'created_at', $start, $end);

        $socialByMonth = $this->sumMonthlySeries([
            $postsByMonth,
            $commentsByMonth,
            $likesByMonth,
            $viewsByMonth,
        ]);

        $iptvByMonth = $this->sumMonthlySeries([
            $iptvChannelsByMonth,
            $iptvPlaylistsByMonth,
        ]);

        $activityTrackingEnabled = $this->activityTrackingEnabled();
        $trackedSecondsByFeature = $activityTrackingEnabled
            ? $this->trackedSecondsByFeatureByMonth($startDate, $endDate)
            : $this->emptyFeatureMonthCounts();

        $trackedTotalSeconds = collect(self::TRACKED_FEATURES)
            ->map(fn (string $feature): float => array_sum($trackedSecondsByFeature[$feature] ?? []))
            ->sum();
        $useTrackedTime = $trackedTotalSeconds > 0;

        $moduleByMonth = [
            'social' => $useTrackedTime ? $this->secondsToMinutesSeries($trackedSecondsByFeature['social']) : $socialByMonth,
            'chats' => $useTrackedTime ? $this->secondsToMinutesSeries($trackedSecondsByFeature['chats']) : $chatMessagesByMonth,
            'radio' => $useTrackedTime ? $this->secondsToMinutesSeries($trackedSecondsByFeature['radio']) : $radioByMonth,
            'iptv' => $useTrackedTime ? $this->secondsToMinutesSeries($trackedSecondsByFeature['iptv']) : $iptvByMonth,
        ];

        $activityByMonth = [];
        $activityTotalsByMonth = $this->emptyMonthCounts();

        foreach (range(1, 12) as $month) {
            $social = (float) ($moduleByMonth['social'][$month] ?? 0);
            $chats = (float) ($moduleByMonth['chats'][$month] ?? 0);
            $radio = (float) ($moduleByMonth['radio'][$month] ?? 0);
            $iptv = (float) ($moduleByMonth['iptv'][$month] ?? 0);
            $total = $social + $chats + $radio + $iptv;

            $activityTotalsByMonth[$month] = $total;
            $activityByMonth[] = [
                'month' => $month,
                'social' => $this->normalizeMetric($social),
                'chats' => $this->normalizeMetric($chats),
                'radio' => $this->normalizeMetric($radio),
                'iptv' => $this->normalizeMetric($iptv),
                'total' => $this->normalizeMetric($total),
            ];
        }

        $subscriptionsYear = array_sum($subscriptionsByMonth);
        $periodMonths = max(1, $this->monthsSpan($start, $end));
        $subscriptionsPeak = $this->maxMonth($subscriptionsByMonth);
        $activityPeak = $this->maxMonth($activityTotalsByMonth);

        $previousYearStart = $period['mode'] === 'custom_range'
            ? $start->subYear()->startOfDay()
            : CarbonImmutable::create($selectedYear - 1, 1, 1, 0, 0, 0);
        $previousYearEnd = $period['mode'] === 'custom_range'
            ? $end->subYear()->endOfDay()
            : $previousYearStart->endOfYear();
        $subscriptionsPreviousYear = (int) DB::table('subscriber_followings')
            ->whereBetween('created_at', [$previousYearStart, $previousYearEnd])
            ->count();

        $subscriptionsChangePercent = null;
        if ($subscriptionsPreviousYear > 0) {
            $subscriptionsChangePercent = round((($subscriptionsYear - $subscriptionsPreviousYear) / $subscriptionsPreviousYear) * 100, 1);
        }

        $preferenceItems = [
            ['key' => 'social', 'value' => array_sum($moduleByMonth['social'])],
            ['key' => 'chats', 'value' => array_sum($moduleByMonth['chats'])],
            ['key' => 'radio', 'value' => array_sum($moduleByMonth['radio'])],
            ['key' => 'iptv', 'value' => array_sum($moduleByMonth['iptv'])],
        ];
        $preferenceTotal = array_sum(array_map(fn (array $item): float => (float) $item['value'], $preferenceItems));

        $preferenceItems = array_map(function (array $item) use ($preferenceTotal): array {
            $value = (float) ($item['value'] ?? 0);
            $share = $preferenceTotal > 0
                ? round(($value / $preferenceTotal) * 100, 1)
                : 0.0;

            return [
                'key' => $item['key'],
                'value' => $this->normalizeMetric($value),
                'share' => $share,
            ];
        }, $preferenceItems);

        $leaderKey = collect($preferenceItems)
            ->sortByDesc('value')
            ->values()
            ->first()['key'] ?? null;

        $cutoffDateTime = $referenceEnd->subDays(30)->startOfDay();
        $cutoffDate = $cutoffDateTime->toDateString();

        $socialUserIds = collect();
        $chatUserIds = collect();
        $radioUserIds = collect();
        $iptvUserIds = collect();

        $trackedSeconds30d = 0;
        if ($activityTrackingEnabled) {
            $trackedSeconds30d = (int) DB::table('user_activity_daily_stats')
                ->where('activity_date', '>=', $cutoffDate)
                ->sum('seconds_total');
        }

        if ($activityTrackingEnabled && $trackedSeconds30d > 0) {
            $socialUserIds = $this->trackedDistinctUsers('social', $cutoffDate);
            $chatUserIds = $this->trackedDistinctUsers('chats', $cutoffDate);
            $radioUserIds = $this->trackedDistinctUsers('radio', $cutoffDate);
            $iptvUserIds = $this->trackedDistinctUsers('iptv', $cutoffDate);
        } else {
            $socialUserIds = $this->collectDistinctUserIds([
                ['table' => 'posts', 'column' => 'created_at', 'cutoff' => $cutoffDateTime],
                ['table' => 'comments', 'column' => 'created_at', 'cutoff' => $cutoffDateTime],
                ['table' => 'liked_posts', 'column' => 'created_at', 'cutoff' => $cutoffDateTime],
                ['table' => 'post_views', 'column' => 'viewed_on', 'cutoff' => $cutoffDate],
            ]);

            $chatUserIds = $this->collectDistinctUserIds([
                ['table' => 'conversation_messages', 'column' => 'created_at', 'cutoff' => $cutoffDateTime],
            ]);

            $radioUserIds = $this->collectDistinctUserIds([
                ['table' => 'radio_favorites', 'column' => 'created_at', 'cutoff' => $cutoffDateTime],
            ]);

            $iptvUserIds = $this->collectDistinctUserIds([
                ['table' => 'iptv_saved_channels', 'column' => 'created_at', 'cutoff' => $cutoffDateTime],
                ['table' => 'iptv_saved_playlists', 'column' => 'created_at', 'cutoff' => $cutoffDateTime],
            ]);
        }

        $activeUsers30d = $socialUserIds
            ->merge($chatUserIds)
            ->merge($radioUserIds)
            ->merge($iptvUserIds)
            ->unique()
            ->count();

        $retention = $this->buildRetentionAnalytics($start, $end, $referenceEnd);
        $content = $this->buildContentAnalytics($start, $end, $startDate, $endDate);
        $chats = $this->buildChatAnalytics($start, $end);
        $media = $this->buildMediaAnalytics($start, $end);
        $radio = $this->buildRadioAnalytics($start, $end, $radioUserIds);
        $iptv = $this->buildIptvAnalytics($start, $end, $iptvUserIds);
        $errorsAndModeration = $this->buildErrorsAndModeration($start, $end);

        return [
            'selected_year' => $selectedYear,
            'available_years' => $availableYears,
            'period' => [
                'mode' => $period['mode'],
                'from' => $startDate,
                'to' => $endDate,
                'requested_from' => $period['requested_from'],
                'requested_to' => $period['requested_to'],
                'is_clamped' => (bool) $period['is_clamped'],
            ],
            'kpis' => [
                'users_total' => (int) DB::table('users')->count(),
                'users_new_year' => array_sum($registrationsByMonth),
                'users_new_period' => array_sum($registrationsByMonth),
                'subscriptions_total' => (int) DB::table('subscriber_followings')->count(),
                'subscriptions_year' => $subscriptionsYear,
                'subscriptions_period' => $subscriptionsYear,
                'subscriptions_previous_year' => $subscriptionsPreviousYear,
                'subscriptions_change_percent' => $subscriptionsChangePercent,
                'subscriptions_avg_month' => round($subscriptionsYear / $periodMonths, 1),
                'period_months' => $periodMonths,
                'subscriptions_peak_month' => [
                    'month' => $subscriptionsPeak['month'],
                    'value' => $subscriptionsPeak['value'],
                ],
                'tracked_minutes_year' => $useTrackedTime
                    ? $this->normalizeMetric($trackedTotalSeconds / 60)
                    : 0,
            ],
            'subscriptions_by_month' => $this->seriesToList($subscriptionsByMonth),
            'registrations_by_month' => $this->seriesToList($registrationsByMonth),
            'activity_by_month' => $activityByMonth,
            'preference' => [
                'method' => $useTrackedTime ? 'time_minutes' : 'actions',
                'total_actions' => $this->normalizeMetric($preferenceTotal),
                'leader_key' => $leaderKey,
                'items' => $preferenceItems,
            ],
            'engagement' => [
                'active_users_30d' => $activeUsers30d,
                'creators_30d' => (int) DB::table('posts')->where('created_at', '>=', $cutoffDateTime)->distinct()->count('user_id'),
                'chatters_30d' => $chatUserIds->count(),
                'new_users_30d' => (int) DB::table('users')->where('created_at', '>=', $cutoffDateTime)->count(),
                'social_active_users_30d' => $socialUserIds->count(),
                'chat_active_users_30d' => $chatUserIds->count(),
                'radio_active_users_30d' => $radioUserIds->count(),
                'iptv_active_users_30d' => $iptvUserIds->count(),
            ],
            'highlights' => [
                'subscriptions_peak_month' => $subscriptionsPeak['month'],
                'activity_peak_month' => $activityPeak['month'],
                'activity_peak_value' => $activityPeak['value'],
            ],
            'retention' => $retention,
            'content' => $content,
            'chats' => $chats,
            'media' => $media,
            'radio' => $radio,
            'iptv' => $iptv,
            'errors_and_moderation' => $errorsAndModeration,
        ];
    }

    protected function resolveReferenceMoment(CarbonImmutable $end): CarbonImmutable
    {
        $now = CarbonImmutable::instance(now());

        return $end->greaterThan($now) ? $now : $end;
    }

    protected function availableYears(int $currentYear): array
    {
        $earliestYear = $this->earliestDataYear();
        if ($earliestYear === null) {
            return [$currentYear];
        }

        $minYear = max($earliestYear, $currentYear - 7);
        if ($minYear > $currentYear) {
            $minYear = $currentYear;
        }

        $years = [];
        for ($year = $currentYear; $year >= $minYear; $year--) {
            $years[] = $year;
        }

        return $years === [] ? [$currentYear] : $years;
    }

    protected function earliestDataYear(): ?int
    {
        $definitions = [
            ['table' => 'users', 'column' => 'created_at'],
            ['table' => 'subscriber_followings', 'column' => 'created_at'],
            ['table' => 'posts', 'column' => 'created_at'],
            ['table' => 'comments', 'column' => 'created_at'],
            ['table' => 'liked_posts', 'column' => 'created_at'],
            ['table' => 'conversation_messages', 'column' => 'created_at'],
            ['table' => 'radio_favorites', 'column' => 'created_at'],
            ['table' => 'iptv_saved_channels', 'column' => 'created_at'],
            ['table' => 'iptv_saved_playlists', 'column' => 'created_at'],
            ['table' => 'post_views', 'column' => 'viewed_on'],
        ];
        if ($this->activityTrackingEnabled()) {
            $definitions[] = ['table' => 'user_activity_daily_stats', 'column' => 'activity_date'];
        }

        $years = collect($definitions)
            ->map(fn (array $definition): ?int => $this->minYearFromTable($definition['table'], $definition['column']))
            ->filter(fn (?int $year): bool => $year !== null);

        if ($years->isEmpty()) {
            return null;
        }

        return (int) $years->min();
    }

    protected function minYearFromTable(string $table, string $column): ?int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return null;
        }

        $value = DB::table($table)->min($column);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->year;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    protected function resolveSelectedYear(?int $requestedYear, array $availableYears, int $currentYear): int
    {
        if ($requestedYear === null) {
            return $currentYear;
        }

        if (in_array($requestedYear, $availableYears, true)) {
            return $requestedYear;
        }

        return $currentYear;
    }

    protected function parseDateRange(?string $dateFrom, ?string $dateTo): ?array
    {
        $normalizedFrom = trim((string) $dateFrom);
        $normalizedTo = trim((string) $dateTo);

        if ($normalizedFrom === '' || $normalizedTo === '') {
            return null;
        }

        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $normalizedFrom)->startOfDay();
            $end = CarbonImmutable::createFromFormat('Y-m-d', $normalizedTo)->endOfDay();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
            }

            return [
                'start' => $start,
                'end' => $end,
                'requested_from' => $normalizedFrom,
                'requested_to' => $normalizedTo,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolvePeriodForYear(int $selectedYear, ?array $requestedRange): array
    {
        $yearStart = CarbonImmutable::create($selectedYear, 1, 1, 0, 0, 0);
        $yearEnd = $yearStart->endOfYear();

        if ($requestedRange === null) {
            return [
                'start' => $yearStart,
                'end' => $yearEnd,
                'mode' => 'year',
                'requested_from' => null,
                'requested_to' => null,
                'is_clamped' => false,
            ];
        }

        $requestedStart = $requestedRange['start'];
        $requestedEnd = $requestedRange['end'];

        $start = $requestedStart->lessThan($yearStart) ? $yearStart : $requestedStart;
        $end = $requestedEnd->greaterThan($yearEnd) ? $yearEnd : $requestedEnd;

        if ($start->greaterThan($end)) {
            return [
                'start' => $yearStart,
                'end' => $yearEnd,
                'mode' => 'year',
                'requested_from' => $requestedRange['requested_from'],
                'requested_to' => $requestedRange['requested_to'],
                'is_clamped' => true,
            ];
        }

        return [
            'start' => $start,
            'end' => $end,
            'mode' => 'custom_range',
            'requested_from' => $requestedRange['requested_from'],
            'requested_to' => $requestedRange['requested_to'],
            'is_clamped' => $start->notEqualTo($requestedStart) || $end->notEqualTo($requestedEnd),
        ];
    }

    protected function monthsSpan(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return ($end->year - $start->year) * 12 + ($end->month - $start->month) + 1;
    }

    protected function monthlyCounts(string $table, string $dateColumn, mixed $start, mixed $end): array
    {
        $counts = $this->emptyMonthCounts();

        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $dateColumn)) {
            return $counts;
        }

        $expression = $this->monthKeyExpression($dateColumn);

        $rows = DB::table($table)
            ->selectRaw($expression . ' as month_key, COUNT(*) as aggregate')
            ->whereBetween($dateColumn, [$start, $end])
            ->groupBy(DB::raw($expression))
            ->orderBy(DB::raw($expression))
            ->get();

        foreach ($rows as $row) {
            $month = $this->extractMonth((string) ($row->month_key ?? ''));
            if ($month === null) {
                continue;
            }

            $counts[$month] = (int) ($row->aggregate ?? 0);
        }

        return $counts;
    }

    protected function trackedSecondsByFeatureByMonth(string $startDate, string $endDate): array
    {
        $series = $this->emptyFeatureMonthCounts();

        if (!Schema::hasTable('user_activity_daily_stats')) {
            return $series;
        }

        $expression = $this->monthKeyExpression('activity_date');

        $rows = DB::table('user_activity_daily_stats')
            ->selectRaw($expression . ' as month_key, feature, SUM(seconds_total) as aggregate')
            ->whereBetween('activity_date', [$startDate, $endDate])
            ->whereIn('feature', self::TRACKED_FEATURES)
            ->groupBy('feature', DB::raw($expression))
            ->orderBy(DB::raw($expression))
            ->get();

        foreach ($rows as $row) {
            $feature = (string) ($row->feature ?? '');
            $month = $this->extractMonth((string) ($row->month_key ?? ''));
            if ($month === null || !array_key_exists($feature, $series)) {
                continue;
            }

            $series[$feature][$month] = (int) ($row->aggregate ?? 0);
        }

        return $series;
    }

    protected function monthKeyExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlsrv' => "FORMAT({$column}, 'yyyy-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    protected function dateKeyExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            'sqlsrv' => "FORMAT({$column}, 'yyyy-MM-dd')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    protected function extractMonth(string $monthKey): ?int
    {
        if (!preg_match('/-(\d{2})$/', $monthKey, $matches)) {
            return null;
        }

        $month = (int) ($matches[1] ?? 0);

        return ($month >= 1 && $month <= 12) ? $month : null;
    }

    protected function emptyMonthCounts(): array
    {
        $counts = [];
        foreach (range(1, 12) as $month) {
            $counts[$month] = 0;
        }

        return $counts;
    }

    protected function emptyFeatureMonthCounts(): array
    {
        $result = [];
        foreach (self::TRACKED_FEATURES as $feature) {
            $result[$feature] = $this->emptyMonthCounts();
        }

        return $result;
    }

    protected function secondsToMinutesSeries(array $secondsByMonth): array
    {
        $minutes = $this->emptyMonthCounts();
        foreach (range(1, 12) as $month) {
            $seconds = (float) ($secondsByMonth[$month] ?? 0);
            $minutes[$month] = round($seconds / 60, 1);
        }

        return $minutes;
    }

    protected function sumMonthlySeries(array $series): array
    {
        $sum = $this->emptyMonthCounts();

        foreach ($series as $row) {
            foreach (range(1, 12) as $month) {
                $sum[$month] += (float) ($row[$month] ?? 0);
            }
        }

        return $sum;
    }

    protected function maxMonth(array $counts): array
    {
        $maxMonth = 1;
        $maxValue = 0.0;

        foreach (range(1, 12) as $month) {
            $value = (float) ($counts[$month] ?? 0);
            if ($value > $maxValue) {
                $maxMonth = $month;
                $maxValue = $value;
            }
        }

        return [
            'month' => $maxMonth,
            'value' => $this->normalizeMetric($maxValue),
        ];
    }

    protected function seriesToList(array $counts): array
    {
        $items = [];
        foreach (range(1, 12) as $month) {
            $items[] = [
                'month' => $month,
                'value' => (int) ($counts[$month] ?? 0),
            ];
        }

        return $items;
    }

    protected function collectDistinctUserIds(array $definitions): Collection
    {
        $ids = collect();

        foreach ($definitions as $definition) {
            $table = (string) ($definition['table'] ?? '');
            $column = (string) ($definition['column'] ?? '');
            $cutoff = $definition['cutoff'] ?? null;

            if ($table === '' || $column === '' || $cutoff === null) {
                continue;
            }

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || !Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $ids = $ids->merge(
                DB::table($table)
                    ->where($column, '>=', $cutoff)
                    ->pluck('user_id')
            );
        }

        return $ids
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    protected function collectDistinctUserIdsWithinRange(array $definitions): Collection
    {
        $ids = collect();

        foreach ($definitions as $definition) {
            $table = (string) ($definition['table'] ?? '');
            $column = (string) ($definition['column'] ?? '');
            $start = $definition['start'] ?? null;
            $end = $definition['end'] ?? null;

            if ($table === '' || $column === '' || $start === null || $end === null) {
                continue;
            }

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || !Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $ids = $ids->merge(
                DB::table($table)
                    ->whereBetween($column, [$start, $end])
                    ->pluck('user_id')
            );
        }

        return $ids
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    protected function trackedDistinctUsers(string $feature, string $cutoffDate): Collection
    {
        if (!Schema::hasTable('user_activity_daily_stats')) {
            return collect();
        }

        return DB::table('user_activity_daily_stats')
            ->where('feature', $feature)
            ->where('activity_date', '>=', $cutoffDate)
            ->where('seconds_total', '>', 0)
            ->pluck('user_id')
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    protected function trackedDistinctUsersBetween(string $feature, string $startDate, string $endDate): Collection
    {
        if (!Schema::hasTable('user_activity_daily_stats')) {
            return collect();
        }

        return DB::table('user_activity_daily_stats')
            ->where('feature', $feature)
            ->whereBetween('activity_date', [$startDate, $endDate])
            ->where('seconds_total', '>', 0)
            ->pluck('user_id')
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    protected function activeUserIdsBetween(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        if ($this->activityTrackingEnabled()) {
            $trackedIds = DB::table('user_activity_daily_stats')
                ->whereBetween('activity_date', [$startDate, $endDate])
                ->where('seconds_total', '>', 0)
                ->pluck('user_id')
                ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            if ($trackedIds->isNotEmpty()) {
                return $trackedIds;
            }
        }

        return $this->collectDistinctUserIdsWithinRange([
            ['table' => 'posts', 'column' => 'created_at', 'start' => $start, 'end' => $end],
            ['table' => 'comments', 'column' => 'created_at', 'start' => $start, 'end' => $end],
            ['table' => 'liked_posts', 'column' => 'created_at', 'start' => $start, 'end' => $end],
            ['table' => 'post_views', 'column' => 'viewed_on', 'start' => $startDate, 'end' => $endDate],
            ['table' => 'conversation_messages', 'column' => 'created_at', 'start' => $start, 'end' => $end],
            ['table' => 'radio_favorites', 'column' => 'created_at', 'start' => $start, 'end' => $end],
            ['table' => 'iptv_saved_channels', 'column' => 'created_at', 'start' => $start, 'end' => $end],
            ['table' => 'iptv_saved_playlists', 'column' => 'created_at', 'start' => $start, 'end' => $end],
        ]);
    }

    protected function buildRetentionAnalytics(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $referenceEnd): array
    {
        $dauIds = $this->activeUserIdsBetween($referenceEnd->startOfDay(), $referenceEnd->endOfDay());
        $wauIds = $this->activeUserIdsBetween($referenceEnd->subDays(6)->startOfDay(), $referenceEnd->endOfDay());
        $mauStart = $referenceEnd->subDays(29)->startOfDay();
        $mauIds = $this->activeUserIdsBetween($mauStart, $referenceEnd->endOfDay());

        $newActiveUsers30d = DB::table('users')
            ->whereIn('id', $mauIds)
            ->whereBetween('created_at', [$mauStart, $referenceEnd->endOfDay()])
            ->count();
        $returningUsers30d = $mauIds->count() - (int) $newActiveUsers30d;

        return [
            'dau' => $dauIds->count(),
            'wau' => $wauIds->count(),
            'mau' => $mauIds->count(),
            'stickiness_percent' => $mauIds->count() > 0
                ? round(($dauIds->count() / max($mauIds->count(), 1)) * 100, 1)
                : 0.0,
            'new_active_users_30d' => (int) $newActiveUsers30d,
            'returning_users_30d' => max(0, (int) $returningUsers30d),
            'cohorts' => $this->buildRetentionCohorts($start, $end, $referenceEnd),
        ];
    }

    protected function buildRetentionCohorts(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $referenceEnd): array
    {
        $users = DB::table('users')
            ->select(['id', 'created_at'])
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();

        if ($users->isEmpty()) {
            return [];
        }

        $activityDatesByUser = $this->collectUserActivityDateMap(
            $users->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $start->toDateString(),
            $referenceEnd->addDays(30)->toDateString()
        );

        $cohorts = [];
        foreach ($users as $user) {
            $registeredAt = CarbonImmutable::parse((string) $user->created_at);
            $month = (int) $registeredAt->month;
            $bucket = $cohorts[$month] ?? [
                'month' => $month,
                'new_users' => 0,
                'retained_users' => 0,
                'partial' => false,
            ];

            $bucket['new_users']++;

            $windowStart = $registeredAt->addDay()->startOfDay();
            $fullWindowEnd = $registeredAt->addDays(30)->endOfDay();
            $effectiveWindowEnd = $fullWindowEnd->greaterThan($referenceEnd->endOfDay())
                ? $referenceEnd->endOfDay()
                : $fullWindowEnd;

            if ($effectiveWindowEnd->lessThan($fullWindowEnd)) {
                $bucket['partial'] = true;
            }

            $activityDates = $activityDatesByUser[(int) $user->id] ?? [];
            foreach ($activityDates as $activityDate) {
                if ($activityDate < $windowStart->toDateString() || $activityDate > $effectiveWindowEnd->toDateString()) {
                    continue;
                }

                $bucket['retained_users']++;
                break;
            }

            $cohorts[$month] = $bucket;
        }

        ksort($cohorts);

        return collect($cohorts)
            ->map(function (array $item): array {
                $newUsers = (int) ($item['new_users'] ?? 0);
                $retainedUsers = min($newUsers, (int) ($item['retained_users'] ?? 0));

                return [
                    'month' => (int) ($item['month'] ?? 1),
                    'new_users' => $newUsers,
                    'retained_users' => $retainedUsers,
                    'retention_percent' => $newUsers > 0
                        ? round(($retainedUsers / $newUsers) * 100, 1)
                        : 0.0,
                    'partial' => (bool) ($item['partial'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    protected function collectUserActivityDateMap(array $userIds, string $startDate, string $endDate): array
    {
        $normalizedUserIds = collect($userIds)
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($normalizedUserIds->isEmpty()) {
            return [];
        }

        $map = [];

        if ($this->activityTrackingEnabled()) {
            $rows = DB::table('user_activity_daily_stats')
                ->select(['user_id', 'activity_date'])
                ->whereIn('user_id', $normalizedUserIds)
                ->whereBetween('activity_date', [$startDate, $endDate])
                ->where('seconds_total', '>', 0)
                ->get();

            foreach ($rows as $row) {
                $userId = (int) ($row->user_id ?? 0);
                $date = (string) ($row->activity_date ?? '');
                if ($userId <= 0 || $date === '') {
                    continue;
                }

                $map[$userId][$date] = true;
            }

            if ($map !== []) {
                return array_map(fn (array $dates): array => array_keys($dates), $map);
            }
        }

        foreach ([
            ['table' => 'posts', 'column' => 'created_at'],
            ['table' => 'comments', 'column' => 'created_at'],
            ['table' => 'liked_posts', 'column' => 'created_at'],
            ['table' => 'conversation_messages', 'column' => 'created_at'],
            ['table' => 'radio_favorites', 'column' => 'created_at'],
            ['table' => 'iptv_saved_channels', 'column' => 'created_at'],
            ['table' => 'iptv_saved_playlists', 'column' => 'created_at'],
            ['table' => 'post_views', 'column' => 'viewed_on'],
        ] as $definition) {
            $table = $definition['table'];
            $column = $definition['column'];

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'user_id') || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $expression = $this->dateKeyExpression($column);

            $rows = DB::table($table)
                ->selectRaw("user_id, {$expression} as activity_day")
                ->whereIn('user_id', $normalizedUserIds)
                ->whereBetween($column, [$startDate, $endDate])
                ->groupBy('user_id', DB::raw($expression))
                ->get();

            foreach ($rows as $row) {
                $userId = (int) ($row->user_id ?? 0);
                $date = (string) ($row->activity_day ?? '');
                if ($userId <= 0 || $date === '') {
                    continue;
                }

                $map[$userId][$date] = true;
            }
        }

        return array_map(fn (array $dates): array => array_keys($dates), $map);
    }

    protected function buildContentAnalytics(CarbonImmutable $start, CarbonImmutable $end, string $startDate, string $endDate): array
    {
        $postsTotal = (int) DB::table('posts')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $publicPosts = (int) DB::table('posts')
            ->whereBetween('created_at', [$start, $end])
            ->where('is_public', true)
            ->count();
        $carouselPosts = (int) DB::table('posts')
            ->whereBetween('created_at', [$start, $end])
            ->where('show_in_carousel', true)
            ->count();
        $likesTotal = (int) DB::table('liked_posts')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $commentsTotal = (int) DB::table('comments')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $viewsTotal = (int) DB::table('post_views')
            ->whereBetween('viewed_on', [$startDate, $endDate])
            ->count();
        $repostsTotal = (int) DB::table('posts')
            ->whereNotNull('reposted_id')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $engagementTotal = $likesTotal + $commentsTotal + $repostsTotal;

        $likesSub = DB::table('liked_posts')
            ->selectRaw('post_id, COUNT(*) as likes_count')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('post_id');
        $commentsSub = DB::table('comments')
            ->selectRaw('post_id, COUNT(*) as comments_count')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('post_id');
        $viewsSub = DB::table('post_views')
            ->selectRaw('post_id, COUNT(*) as views_count')
            ->whereBetween('viewed_on', [$startDate, $endDate])
            ->groupBy('post_id');
        $repostsSub = DB::table('posts')
            ->selectRaw('reposted_id as post_id, COUNT(*) as reposts_count')
            ->whereNotNull('reposted_id')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('reposted_id');

        $topPosts = DB::table('posts')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->leftJoinSub($likesSub, 'likes_stats', fn ($join) => $join->on('likes_stats.post_id', '=', 'posts.id'))
            ->leftJoinSub($commentsSub, 'comments_stats', fn ($join) => $join->on('comments_stats.post_id', '=', 'posts.id'))
            ->leftJoinSub($viewsSub, 'views_stats', fn ($join) => $join->on('views_stats.post_id', '=', 'posts.id'))
            ->leftJoinSub($repostsSub, 'reposts_stats', fn ($join) => $join->on('reposts_stats.post_id', '=', 'posts.id'))
            ->whereBetween('posts.created_at', [$start, $end])
            ->selectRaw('
                posts.id,
                posts.title,
                posts.is_public,
                posts.show_in_carousel,
                users.name as author_name,
                users.nickname as author_nickname,
                COALESCE(views_stats.views_count, 0) as views_count,
                COALESCE(likes_stats.likes_count, 0) as likes_count,
                COALESCE(comments_stats.comments_count, 0) as comments_count,
                COALESCE(reposts_stats.reposts_count, 0) as reposts_count,
                (COALESCE(likes_stats.likes_count, 0) + COALESCE(comments_stats.comments_count, 0) + COALESCE(reposts_stats.reposts_count, 0)) as engagement_score
            ')
            ->orderByDesc('engagement_score')
            ->orderByDesc('views_count')
            ->orderByDesc('posts.id')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => (string) ($row->title ?? ''),
                'author_name' => (string) ($row->author_name ?? ''),
                'author_nickname' => (string) ($row->author_nickname ?? ''),
                'is_public' => (bool) ($row->is_public ?? false),
                'show_in_carousel' => (bool) ($row->show_in_carousel ?? false),
                'views_count' => (int) ($row->views_count ?? 0),
                'likes_count' => (int) ($row->likes_count ?? 0),
                'comments_count' => (int) ($row->comments_count ?? 0),
                'reposts_count' => (int) ($row->reposts_count ?? 0),
                'engagement_score' => (int) ($row->engagement_score ?? 0),
            ])
            ->all();

        $topAuthors = DB::table('posts')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->leftJoinSub($likesSub, 'likes_stats', fn ($join) => $join->on('likes_stats.post_id', '=', 'posts.id'))
            ->leftJoinSub($commentsSub, 'comments_stats', fn ($join) => $join->on('comments_stats.post_id', '=', 'posts.id'))
            ->leftJoinSub($viewsSub, 'views_stats', fn ($join) => $join->on('views_stats.post_id', '=', 'posts.id'))
            ->leftJoinSub($repostsSub, 'reposts_stats', fn ($join) => $join->on('reposts_stats.post_id', '=', 'posts.id'))
            ->whereBetween('posts.created_at', [$start, $end])
            ->groupBy('posts.user_id', 'users.name', 'users.nickname')
            ->selectRaw('
                posts.user_id,
                users.name,
                users.nickname,
                COUNT(posts.id) as posts_count,
                SUM(COALESCE(views_stats.views_count, 0)) as views_count,
                SUM(COALESCE(likes_stats.likes_count, 0) + COALESCE(comments_stats.comments_count, 0) + COALESCE(reposts_stats.reposts_count, 0)) as engagement_total
            ')
            ->orderByDesc('engagement_total')
            ->orderByDesc('views_count')
            ->orderByDesc('posts_count')
            ->limit(5)
            ->get()
            ->map(function ($row): array {
                $postsCount = (int) ($row->posts_count ?? 0);
                $engagementTotalByAuthor = (int) ($row->engagement_total ?? 0);

                return [
                    'user_id' => (int) $row->user_id,
                    'name' => (string) ($row->name ?? ''),
                    'nickname' => (string) ($row->nickname ?? ''),
                    'posts_count' => $postsCount,
                    'views_count' => (int) ($row->views_count ?? 0),
                    'engagement_total' => $engagementTotalByAuthor,
                    'engagement_per_post' => $postsCount > 0
                        ? $this->normalizeMetric($engagementTotalByAuthor / $postsCount)
                        : 0,
                ];
            })
            ->all();

        return [
            'posts_total' => $postsTotal,
            'public_posts' => $publicPosts,
            'private_posts' => max(0, $postsTotal - $publicPosts),
            'carousel_posts' => $carouselPosts,
            'engagement_total' => $engagementTotal,
            'views_total' => $viewsTotal,
            'likes_total' => $likesTotal,
            'comments_total' => $commentsTotal,
            'reposts_total' => $repostsTotal,
            'engagement_per_post' => $postsTotal > 0
                ? $this->normalizeMetric($engagementTotal / $postsTotal)
                : 0,
            'avg_views_per_post' => $postsTotal > 0
                ? $this->normalizeMetric($viewsTotal / $postsTotal)
                : 0,
            'view_to_engagement_rate_percent' => $viewsTotal > 0
                ? round(($engagementTotal / $viewsTotal) * 100, 1)
                : 0.0,
            'top_posts' => $topPosts,
            'top_authors' => $topAuthors,
        ];
    }

    protected function buildChatAnalytics(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $messagesTotal = (int) DB::table('conversation_messages')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $activeChatters = (int) DB::table('conversation_messages')
            ->whereBetween('created_at', [$start, $end])
            ->distinct()
            ->count('user_id');

        $attachmentsBreakdown = collect(
            DB::table('conversation_message_attachments')
                ->selectRaw('type, COUNT(*) as aggregate')
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('type')
                ->get()
        )
            ->map(fn ($row): array => [
                'type' => (string) ($row->type ?? 'file'),
                'value' => (int) ($row->aggregate ?? 0),
            ])
            ->values()
            ->all();

        $replySamples = [];
        $messages = DB::table('conversation_messages')
            ->join('conversations', 'conversations.id', '=', 'conversation_messages.conversation_id')
            ->where('conversations.type', 'direct')
            ->whereBetween('conversation_messages.created_at', [$start, $end])
            ->orderBy('conversation_messages.conversation_id')
            ->orderBy('conversation_messages.created_at')
            ->get([
                'conversation_messages.conversation_id',
                'conversation_messages.user_id',
                'conversation_messages.created_at',
            ]);

        $lastMessageByConversation = [];
        foreach ($messages as $message) {
            $conversationId = (int) ($message->conversation_id ?? 0);
            $userId = (int) ($message->user_id ?? 0);
            $createdAt = CarbonImmutable::parse((string) $message->created_at);
            $previous = $lastMessageByConversation[$conversationId] ?? null;

            if ($previous && $previous['user_id'] !== $userId) {
                $diffMinutes = $previous['created_at']->diffInRealMinutes($createdAt);
                if ($diffMinutes >= 0 && $diffMinutes <= 10080) {
                    $replySamples[] = $diffMinutes;
                }
            }

            $lastMessageByConversation[$conversationId] = [
                'user_id' => $userId,
                'created_at' => $createdAt,
            ];
        }

        sort($replySamples);
        $replySamplesCount = count($replySamples);
        $medianReplyMinutes = 0;
        if ($replySamplesCount > 0) {
            $middle = (int) floor($replySamplesCount / 2);
            $medianReplyMinutes = $replySamplesCount % 2 === 0
                ? round((($replySamples[$middle - 1] ?? 0) + ($replySamples[$middle] ?? 0)) / 2, 1)
                : round((float) ($replySamples[$middle] ?? 0), 1);
        }

        return [
            'messages_total' => $messagesTotal,
            'active_chatters' => $activeChatters,
            'attachments_total' => array_sum(array_map(fn (array $item): int => (int) ($item['value'] ?? 0), $attachmentsBreakdown)),
            'attachment_breakdown' => $attachmentsBreakdown,
            'reply_samples' => $replySamplesCount,
            'avg_reply_minutes' => $replySamplesCount > 0
                ? round(array_sum($replySamples) / $replySamplesCount, 1)
                : 0.0,
            'median_reply_minutes' => $medianReplyMinutes,
        ];
    }

    protected function buildMediaAnalytics(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $postMedia = collect(
            DB::table('post_images')
                ->select(['id', 'type', 'size'])
                ->whereBetween('created_at', [$start, $end])
                ->get()
        );
        $chatAttachments = collect(
            DB::table('conversation_message_attachments')
                ->select(['id', 'type', 'size'])
                ->whereBetween('created_at', [$start, $end])
                ->get()
        );

        $successfulUploads = $postMedia->count() + $chatAttachments->count();
        $totalUploadedBytes = $postMedia->sum('size') + $chatAttachments->sum('size');
        $failedUploads = $this->analyticsEventsEnabled()
            ? (int) DB::table('analytics_events')
                ->where('event_name', AnalyticsEvent::EVENT_MEDIA_UPLOAD_FAILED)
                ->whereBetween('created_at', [$start, $end])
                ->count()
            : 0;

        $videoSessions = $this->analyticsEventsEnabled()
            ? DB::table('analytics_events')
                ->where('event_name', AnalyticsEvent::EVENT_VIDEO_SESSION)
                ->whereBetween('created_at', [$start, $end])
                ->get(['duration_seconds', 'metric_value', 'context'])
            : collect();

        $completedSessions = 0;
        foreach ($videoSessions as $session) {
            $context = is_string($session->context) ? json_decode($session->context, true) : $session->context;
            if (is_array($context) && !empty($context['completed'])) {
                $completedSessions++;
            }
        }

        return [
            'uploads_total' => $successfulUploads,
            'post_media_uploads' => $postMedia->count(),
            'chat_attachments_uploads' => $chatAttachments->count(),
            'images_uploaded' => $postMedia->where('type', 'image')->count() + $chatAttachments->where('type', 'image')->count(),
            'videos_uploaded' => $postMedia->where('type', 'video')->count() + $chatAttachments->where('type', 'video')->count(),
            'avg_upload_size_kb' => $successfulUploads > 0
                ? $this->normalizeMetric(($totalUploadedBytes / $successfulUploads) / 1024)
                : 0,
            'failed_uploads' => $failedUploads,
            'upload_failure_rate_percent' => ($successfulUploads + $failedUploads) > 0
                ? round(($failedUploads / ($successfulUploads + $failedUploads)) * 100, 1)
                : 0.0,
            'video_sessions' => $videoSessions->count(),
            'video_completed_sessions' => $completedSessions,
            'video_completion_rate_percent' => $videoSessions->count() > 0
                ? round(($completedSessions / $videoSessions->count()) * 100, 1)
                : 0.0,
            'video_watch_seconds' => (int) $videoSessions->sum('duration_seconds'),
            'avg_video_completion_percent' => $videoSessions->count() > 0
                ? round($videoSessions->avg('metric_value') ?? 0, 1)
                : 0.0,
            'theater_opens' => $this->collectNamedAnalyticsEvents(AnalyticsEvent::EVENT_VIDEO_THEATER_OPEN, $start, $end)->count(),
            'fullscreen_entries' => $this->collectNamedAnalyticsEvents(AnalyticsEvent::EVENT_VIDEO_FULLSCREEN_ENTER, $start, $end)->count(),
        ];
    }

    protected function buildRadioAnalytics(CarbonImmutable $start, CarbonImmutable $end, Collection $radioUserIds): array
    {
        $started = $this->collectNamedAnalyticsEvents(AnalyticsEvent::EVENT_RADIO_PLAY_STARTED, $start, $end);
        $failed = $this->collectNamedAnalyticsEvents(AnalyticsEvent::EVENT_RADIO_PLAY_FAILED, $start, $end);

        return [
            'active_users_period' => $radioUserIds->count(),
            'favorite_additions_period' => (int) DB::table('radio_favorites')
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'sessions_started' => $started->count(),
            'failures_total' => $failed->count(),
            'failure_rate_percent' => ($started->count() + $failed->count()) > 0
                ? round(($failed->count() / ($started->count() + $failed->count())) * 100, 1)
                : 0.0,
            'top_stations' => $this->groupAnalyticsEventsByEntity($started, 'station_name'),
        ];
    }

    protected function buildIptvAnalytics(CarbonImmutable $start, CarbonImmutable $end, Collection $iptvUserIds): array
    {
        $modeItems = [
            ['key' => 'direct', 'started' => AnalyticsEvent::EVENT_IPTV_DIRECT_STARTED, 'failed' => AnalyticsEvent::EVENT_IPTV_DIRECT_FAILED],
            ['key' => 'proxy', 'started' => AnalyticsEvent::EVENT_IPTV_PROXY_STARTED, 'failed' => AnalyticsEvent::EVENT_IPTV_PROXY_FAILED],
            ['key' => 'relay', 'started' => AnalyticsEvent::EVENT_IPTV_RELAY_STARTED, 'failed' => AnalyticsEvent::EVENT_IPTV_RELAY_FAILED],
            ['key' => 'ffmpeg', 'started' => AnalyticsEvent::EVENT_IPTV_FFMPEG_STARTED, 'failed' => AnalyticsEvent::EVENT_IPTV_FFMPEG_FAILED],
        ];

        $totalStarted = 0;
        $totalFailed = 0;
        $split = [];
        $topChannelsSource = collect();

        foreach ($modeItems as $item) {
            $started = $this->collectNamedAnalyticsEvents($item['started'], $start, $end);
            $failed = $this->collectNamedAnalyticsEvents($item['failed'], $start, $end);
            $startedCount = $started->count();
            $failedCount = $failed->count();

            $totalStarted += $startedCount;
            $totalFailed += $failedCount;
            $topChannelsSource = $topChannelsSource->concat($started);

            $split[] = [
                'key' => $item['key'],
                'started' => $startedCount,
                'failed' => $failedCount,
            ];
        }

        $split = collect($split)
            ->map(function (array $item) use ($totalStarted): array {
                return [
                    ...$item,
                    'share' => $totalStarted > 0
                        ? round(((int) $item['started'] / $totalStarted) * 100, 1)
                        : 0.0,
                ];
            })
            ->values()
            ->all();

        return [
            'active_users_period' => $iptvUserIds->count(),
            'saved_channels_period' => (int) DB::table('iptv_saved_channels')
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'saved_playlists_period' => (int) DB::table('iptv_saved_playlists')
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'sessions_started' => $totalStarted,
            'failures_total' => $totalFailed,
            'failure_rate_percent' => ($totalStarted + $totalFailed) > 0
                ? round(($totalFailed / ($totalStarted + $totalFailed)) * 100, 1)
                : 0.0,
            'mode_split' => $split,
            'top_channels' => $this->groupAnalyticsEventsByEntity($topChannelsSource, 'channel_name'),
        ];
    }

    protected function buildErrorsAndModeration(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $radioFailures = $this->collectNamedAnalyticsEvents(AnalyticsEvent::EVENT_RADIO_PLAY_FAILED, $start, $end)->count();
        $iptvFailures = $this->collectNamedAnalyticsEvents([
            AnalyticsEvent::EVENT_IPTV_DIRECT_FAILED,
            AnalyticsEvent::EVENT_IPTV_PROXY_FAILED,
            AnalyticsEvent::EVENT_IPTV_RELAY_FAILED,
            AnalyticsEvent::EVENT_IPTV_FFMPEG_FAILED,
        ], $start, $end)->count();
        $uploadFailures = $this->collectNamedAnalyticsEvents(AnalyticsEvent::EVENT_MEDIA_UPLOAD_FAILED, $start, $end)->count();

        return [
            'media_upload_failures' => $uploadFailures,
            'radio_failures' => $radioFailures,
            'iptv_failures' => $iptvFailures,
            'total_tracked_failures' => $uploadFailures + $radioFailures + $iptvFailures,
            'active_blocks_total' => (int) DB::table('user_blocks')
                ->where(function ($query): void {
                    $query
                        ->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->count(),
            'feedback_new_total' => (int) DB::table('feedback_messages')
                ->where('status', 'new')
                ->count(),
            'feedback_in_progress_total' => (int) DB::table('feedback_messages')
                ->where('status', 'in_progress')
                ->count(),
            'feedback_resolved_total' => (int) DB::table('feedback_messages')
                ->where('status', 'resolved')
                ->count(),
            'feedback_created_period' => (int) DB::table('feedback_messages')
                ->whereBetween('created_at', [$start, $end])
                ->count(),
        ];
    }

    protected function collectNamedAnalyticsEvents(string|array $eventNames, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        if (!$this->analyticsEventsEnabled()) {
            return collect();
        }

        $names = is_array($eventNames) ? $eventNames : [$eventNames];

        return DB::table('analytics_events')
            ->whereIn('event_name', $names)
            ->whereBetween('created_at', [$start, $end])
            ->get(['entity_id', 'entity_key', 'context', 'duration_seconds', 'metric_value', 'event_name']);
    }

    protected function groupAnalyticsEventsByEntity(Collection $events, string $labelContextKey): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        $grouped = [];
        foreach ($events as $event) {
            $entityKey = (string) ($event->entity_key ?? '');
            $entityId = (int) ($event->entity_id ?? 0);
            $bucketKey = $entityKey !== '' ? $entityKey : ($entityId > 0 ? (string) $entityId : '');
            if ($bucketKey === '') {
                continue;
            }

            $context = is_string($event->context) ? json_decode($event->context, true) : $event->context;
            $label = is_array($context) ? trim((string) ($context[$labelContextKey] ?? '')) : '';

            if (!isset($grouped[$bucketKey])) {
                $grouped[$bucketKey] = [
                    'entity_key' => $entityKey,
                    'entity_id' => $entityId > 0 ? $entityId : null,
                    'label' => $label,
                    'value' => 0,
                ];
            }

            if ($grouped[$bucketKey]['label'] === '' && $label !== '') {
                $grouped[$bucketKey]['label'] = $label;
            }

            $grouped[$bucketKey]['value']++;
        }

        uasort($grouped, function (array $left, array $right): int {
            if ((int) $left['value'] === (int) $right['value']) {
                return strcmp((string) $left['label'], (string) $right['label']);
            }

            return ((int) $right['value']) <=> ((int) $left['value']);
        });

        return collect($grouped)
            ->take(5)
            ->values()
            ->all();
    }

    protected function normalizeMetric(float|int $value): float|int
    {
        $rounded = round((float) $value, 1);
        $integer = (int) $rounded;

        if (abs($rounded - $integer) < 0.0001) {
            return $integer;
        }

        return $rounded;
    }

    protected function activityTrackingEnabled(): bool
    {
        static $enabled = null;

        if ($enabled !== null) {
            return (bool) $enabled;
        }

        $enabled = Schema::hasTable('user_activity_daily_stats');

        return (bool) $enabled;
    }

    protected function analyticsEventsEnabled(): bool
    {
        static $enabled = null;

        if ($enabled !== null) {
            return (bool) $enabled;
        }

        $enabled = Schema::hasTable('analytics_events');

        return (bool) $enabled;
    }
}
