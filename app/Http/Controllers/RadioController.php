<?php

namespace App\Http\Controllers;

use App\Models\RadioFavorite;
use App\Services\RadioBrowserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RadioController extends Controller
{
    public function __construct(private readonly RadioBrowserService $radioBrowserService)
    {
    }

    public function stations(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', 'string', 'max:120'],
            'tag' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0', 'max:5000'],
        ]);

        try {
            $stations = $this->radioBrowserService->searchStations($filters);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Не удалось получить список радиостанций. Попробуйте позже.',
                'data' => [],
            ], 503);
        }

        $normalizedStations = collect($stations)
            ->map(fn ($station) => $this->normalizeStationPayload(is_array($station) ? $station : []))
            ->filter(fn (array $station) => $station['station_uuid'] !== '' && $station['stream_url'] !== '')
            ->values();

        $favoriteUuids = RadioFavorite::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('station_uuid', $normalizedStations->pluck('station_uuid'))
            ->pluck('station_uuid')
            ->flip();

        return response()->json([
            'data' => $normalizedStations
                ->map(function (array $station) use ($favoriteUuids) {
                    $station['is_favorite'] = $favoriteUuids->has($station['station_uuid']);
                    return $station;
                })
                ->values(),
        ]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $favorites = RadioFavorite::query()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->get();

        return response()->json([
            'data' => $favorites->map(fn (RadioFavorite $favorite) => [
                'id' => $favorite->id,
                'station_uuid' => $favorite->station_uuid,
                'name' => $favorite->name,
                'stream_url' => $favorite->stream_url,
                'homepage' => $favorite->homepage,
                'favicon' => $favorite->favicon,
                'country' => $favorite->country,
                'language' => $favorite->language,
                'tags' => $favorite->tags,
                'codec' => $favorite->codec,
                'bitrate' => (int) ($favorite->bitrate ?? 0),
                'votes' => (int) ($favorite->votes ?? 0),
            ])->values(),
        ]);
    }

    public function storeFavorite(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'station_uuid' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'stream_url' => ['required', 'string', 'max:2000'],
            'homepage' => ['nullable', 'string', 'max:2000'],
            'favicon' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:500'],
            'codec' => ['nullable', 'string', 'max:64'],
            'bitrate' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'votes' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ]);

        $favorite = RadioFavorite::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'station_uuid' => $payload['station_uuid'],
            ],
            [
                'name' => $payload['name'],
                'stream_url' => $payload['stream_url'],
                'homepage' => $payload['homepage'] ?? null,
                'favicon' => $payload['favicon'] ?? null,
                'country' => $payload['country'] ?? null,
                'language' => $payload['language'] ?? null,
                'tags' => $payload['tags'] ?? null,
                'codec' => $payload['codec'] ?? null,
                'bitrate' => $payload['bitrate'] ?? null,
                'votes' => $payload['votes'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Станция добавлена в избранное.',
            'data' => [
                'id' => $favorite->id,
                'station_uuid' => $favorite->station_uuid,
            ],
        ], 201);
    }

    public function destroyFavorite(string $stationUuid, Request $request): JsonResponse
    {
        RadioFavorite::query()
            ->where('user_id', $request->user()->id)
            ->where('station_uuid', $stationUuid)
            ->delete();

        return response()->json([
            'message' => 'Станция удалена из избранного.',
        ]);
    }

    protected function normalizeStationPayload(array $station): array
    {
        $streamUrl = trim((string) ($station['url_resolved'] ?? ''));
        if ($streamUrl === '') {
            $streamUrl = trim((string) ($station['url'] ?? ''));
        }

        return [
            'station_uuid' => trim((string) ($station['stationuuid'] ?? '')),
            'name' => trim((string) ($station['name'] ?? '')),
            'stream_url' => $streamUrl,
            'homepage' => trim((string) ($station['homepage'] ?? '')),
            'favicon' => trim((string) ($station['favicon'] ?? '')),
            'country' => trim((string) ($station['country'] ?? '')),
            'language' => trim((string) ($station['language'] ?? '')),
            'tags' => trim((string) ($station['tags'] ?? '')),
            'codec' => trim((string) ($station['codec'] ?? '')),
            'bitrate' => (int) ($station['bitrate'] ?? 0),
            'votes' => (int) ($station['votes'] ?? 0),
        ];
    }
}
