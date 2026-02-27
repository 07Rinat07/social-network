<?php

namespace App\Services;

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

        $cutoffDateTime = now()->subDays(30);
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
        ];
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
}
