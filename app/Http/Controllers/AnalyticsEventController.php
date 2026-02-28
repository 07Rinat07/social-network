<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsEventController extends Controller
{
    public function __construct(
        private readonly AnalyticsEventService $analyticsEventService
    )
    {
    }

    /**
     * Persist a lightweight client analytics event for authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature' => ['required', 'string', Rule::in(AnalyticsEvent::ALLOWED_FEATURES)],
            'event_name' => ['required', 'string', Rule::in(AnalyticsEvent::ALLOWED_EVENTS)],
            'entity_type' => ['nullable', 'string', 'max:80'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'entity_key' => ['nullable', 'string', 'max:191'],
            'session_id' => ['nullable', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'metric_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'context' => ['nullable', 'array'],
        ]);

        $event = $this->analyticsEventService->recordForUser(
            (int) $request->user()->id,
            (string) $validated['feature'],
            (string) $validated['event_name'],
            $validated
        );

        return response()->json([
            'message' => 'Analytics event accepted.',
            'data' => [
                'id' => (int) ($event?->id ?? 0),
            ],
        ], 201);
    }
}
