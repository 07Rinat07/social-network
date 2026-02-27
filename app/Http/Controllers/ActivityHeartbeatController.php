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
            $session = UserActivitySession::query()->firstOrNew([
                'user_id' => $user->id,
                'feature' => $feature,
                'session_id' => $sessionId,
            ]);

            if (!$session->exists) {
                $session->started_at = $now;
                $session->total_seconds = 0;
                $session->heartbeats_count = 0;
            }

            $session->last_heartbeat_at = $now;
            $session->total_seconds = (int) $session->total_seconds + $elapsedSeconds;
            $session->heartbeats_count = (int) $session->heartbeats_count + 1;
            $session->is_active = !$ended;
            $session->ended_at = $ended ? $now : null;
            $session->save();

            $daily = UserActivityDailyStat::query()
                ->where('user_id', $user->id)
                ->where('feature', $feature)
                ->whereDate('activity_date', $activityDate)
                ->first();

            if (!$daily) {
                $daily = new UserActivityDailyStat([
                    'user_id' => $user->id,
                    'feature' => $feature,
                    'activity_date' => $activityDate,
                    'seconds_total' => 0,
                    'heartbeats_count' => 0,
                ]);
            }

            $daily->seconds_total = (int) $daily->seconds_total + $elapsedSeconds;
            $daily->heartbeats_count = (int) $daily->heartbeats_count + 1;
            $daily->save();
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
}
