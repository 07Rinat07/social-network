<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserBlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $blocks = UserBlock::query()
            ->active()
            ->where('blocker_id', $request->user()->id)
            ->with('blockedUser:id,name,email')
            ->latest()
            ->get();

        return response()->json([
            'data' => $blocks->map(fn (UserBlock $block) => $this->blockPayload($block))->values(),
        ]);
    }

    public function store(User $user, Request $request): JsonResponse
    {
        $viewer = $request->user();

        if ($viewer->id === $user->id) {
            return response()->json([
                'message' => 'You cannot block yourself.',
            ], 422);
        }

        $validated = $request->validate([
            'mode' => ['nullable', 'in:temporary,permanent'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $mode = $validated['mode'] ?? 'permanent';

        if ($mode === 'temporary' && empty($validated['duration_minutes'])) {
            return response()->json([
                'errors' => [
                    'duration_minutes' => ['Temporary block requires duration_minutes.'],
                ],
            ], 422);
        }

        $expiresAt = $mode === 'temporary'
            ? now()->addMinutes((int) $validated['duration_minutes'])
            : null;

        $block = UserBlock::query()->updateOrCreate(
            [
                'blocker_id' => $viewer->id,
                'blocked_user_id' => $user->id,
            ],
            [
                'expires_at' => $expiresAt,
                'reason' => $validated['reason'] ?? null,
            ]
        );

        $block->load('blockedUser:id,name,email');

        return response()->json([
            'message' => $mode === 'temporary'
                ? 'User blocked temporarily.'
                : 'User blocked permanently.',
            'data' => $this->blockPayload($block),
        ]);
    }

    public function destroy(User $user, Request $request): JsonResponse
    {
        $deletedRows = UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->where('blocked_user_id', $user->id)
            ->delete();

        if ($deletedRows === 0) {
            return response()->json([
                'message' => 'Block entry not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'User unblocked successfully.',
        ]);
    }

    protected function blockPayload(UserBlock $block): array
    {
        return [
            'id' => $block->id,
            'blocker_id' => $block->blocker_id,
            'blocked_user_id' => $block->blocked_user_id,
            'expires_at' => $block->expires_at?->toIso8601String(),
            'is_permanent' => $block->expires_at === null,
            'reason' => $block->reason,
            'blocked_user' => $block->relationLoaded('blockedUser') && $block->blockedUser
                ? [
                    'id' => $block->blockedUser->id,
                    'name' => $block->blockedUser->name,
                    'email' => $block->blockedUser->email,
                ]
                : null,
            'created_at' => $block->created_at?->toIso8601String(),
        ];
    }
}
