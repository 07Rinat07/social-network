<?php

namespace App\Services;

use App\Models\AnalyticsEvent;

class AnalyticsEventService
{
    public function __construct(
        private readonly SiteErrorLogService $siteErrorLogService
    )
    {
    }

    public function recordForUser(int $userId, string $feature, string $eventName, array $payload = []): ?AnalyticsEvent
    {
        if ($userId <= 0) {
            return null;
        }

        $normalizedFeature = trim(mb_strtolower($feature));
        $normalizedEvent = trim(mb_strtolower($eventName));

        if (
            !in_array($normalizedFeature, AnalyticsEvent::ALLOWED_FEATURES, true)
            || !in_array($normalizedEvent, AnalyticsEvent::ALLOWED_EVENTS, true)
        ) {
            return null;
        }

        $context = $this->sanitizeContext($payload['context'] ?? null);

        $event = AnalyticsEvent::query()->create([
            'user_id' => $userId,
            'feature' => $normalizedFeature,
            'event_name' => $normalizedEvent,
            'entity_type' => $this->sanitizeNullableString($payload['entity_type'] ?? null, 80),
            'entity_id' => $this->sanitizeNullableInt($payload['entity_id'] ?? null),
            'entity_key' => $this->sanitizeNullableString($payload['entity_key'] ?? null, 191),
            'session_id' => $this->sanitizeNullableString($payload['session_id'] ?? null, 120),
            'duration_seconds' => max(0, min((int) ($payload['duration_seconds'] ?? 0), 86400)),
            'metric_value' => $this->sanitizeNullableFloat($payload['metric_value'] ?? null),
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);

        if ($event && $this->siteErrorLogService->shouldLogAnalyticsFailure($event->event_name)) {
            try {
                $this->siteErrorLogService->logAnalyticsFailure($event);
            } catch (\Throwable $_loggingFailure) {
                // Analytics inserts should not fail because the text log is unavailable.
            }
        }

        return $event;
    }

    protected function sanitizeContext(mixed $value, int $depth = 0): array
    {
        if (!is_array($value) || $depth > 3) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $normalizedKey = $this->sanitizeNullableString((string) $key, 64);
            if ($normalizedKey === null) {
                continue;
            }

            if (is_array($item)) {
                $nested = $this->sanitizeContext($item, $depth + 1);
                if ($nested !== []) {
                    $result[$normalizedKey] = $nested;
                }
                continue;
            }

            if (is_bool($item) || is_int($item) || is_float($item)) {
                $result[$normalizedKey] = $item;
                continue;
            }

            if ($item === null) {
                continue;
            }

            $stringValue = $this->sanitizeNullableString((string) $item, 255);
            if ($stringValue !== null) {
                $result[$normalizedKey] = $stringValue;
            }
        }

        return $result;
    }

    protected function sanitizeNullableString(mixed $value, int $limit): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, $limit);
    }

    protected function sanitizeNullableInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    protected function sanitizeNullableFloat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
