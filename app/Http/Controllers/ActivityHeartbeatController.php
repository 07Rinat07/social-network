<?php

namespace App\Http\Controllers;

use App\Models\UserActivityDailyStat;
use App\Models\UserActivitySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActivityHeartbeatController extends Controller
{
    private const FEATURES = ['social', 'chats', 'radio', 'iptv'];
    private const DEFAULT_HEARTBEAT_SECONDS = 30;
    private const MIN_HEARTBEAT_SECONDS = 1;
    private const MAX_HEARTBEAT_SECONDS = 300;

    /**
     * Persist periodic activity heartbeat and update current user session aggregates.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature' => ['required', 'string', Rule::in(self::FEATURES)],
            'session_id' => ['required', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'elapsed_seconds' => ['nullable', 'integer', 'min:' . self::MIN_HEARTBEAT_SECONDS, 'max:' . self::MAX_HEARTBEAT_SECONDS],
            'ended' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $feature = (string) $validated['feature'];
        $sessionId = trim((string) $validated['session_id']);
        $elapsedSeconds = max(
            self::MIN_HEARTBEAT_SECONDS,
            min((int) ($validated['elapsed_seconds'] ?? self::DEFAULT_HEARTBEAT_SECONDS), self::MAX_HEARTBEAT_SECONDS)
        );
        $ended = (bool) ($validated['ended'] ?? false);
        $now = now();
        $activityDate = $now->toDateString();

        DB::transaction(function () use ($activityDate, $elapsedSeconds, $ended, $feature, $now, $sessionId, $user): void {
            $this->upsertActivitySession(
                userId: (int) $user->id,
                feature: $feature,
                sessionId: $sessionId,
                elapsedSeconds: $elapsedSeconds,
                ended: $ended,
                now: $now,
            );

            $this->upsertDailyActivityStat(
                userId: (int) $user->id,
                feature: $feature,
                activityDate: $activityDate,
                elapsedSeconds: $elapsedSeconds,
                now: $now,
            );
        });

        return response()->json([
            'message' => 'Activity heartbeat accepted.',
            'data' => [
                'feature' => $feature,
                'session_id' => $sessionId,
                'elapsed_seconds' => $elapsedSeconds,
                'ended' => $ended,
                'tracked_at' => $now->toIso8601String(),
            ],
        ]);
    }

    private function upsertActivitySession(
        int $userId,
        string $feature,
        string $sessionId,
        int $elapsedSeconds,
        bool $ended,
        $now
    ): void {
        UserActivitySession::query()->upsert(
            [[
                'user_id' => $userId,
                'feature' => $feature,
                'session_id' => $sessionId,
                'started_at' => $now,
                'last_heartbeat_at' => $now,
                'total_seconds' => $elapsedSeconds,
                'heartbeats_count' => 1,
                'is_active' => !$ended,
                'ended_at' => $ended ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['user_id', 'feature', 'session_id'],
            [
                'last_heartbeat_at' => $now,
                'total_seconds' => $this->incrementExpression('total_seconds', $elapsedSeconds),
                'heartbeats_count' => $this->incrementExpression('heartbeats_count', 1),
                'is_active' => !$ended,
                'ended_at' => $ended ? $now : null,
                'updated_at' => $now,
            ]
        );
    }

    private function upsertDailyActivityStat(
        int $userId,
        string $feature,
        string $activityDate,
        int $elapsedSeconds,
        $now
    ): void {
        UserActivityDailyStat::query()->upsert(
            [[
                'user_id' => $userId,
                'feature' => $feature,
                'activity_date' => $activityDate,
                'seconds_total' => $elapsedSeconds,
                'heartbeats_count' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['user_id', 'feature', 'activity_date'],
            [
                'seconds_total' => $this->incrementExpression('seconds_total', $elapsedSeconds),
                'heartbeats_count' => $this->incrementExpression('heartbeats_count', 1),
                'updated_at' => $now,
            ]
        );
    }

    private function incrementExpression(string $column, int $amount)
    {
        return DB::raw(sprintf('%s + %d', $column, max(0, $amount)));
    }
}
