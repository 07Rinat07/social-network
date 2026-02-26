<?php

namespace App\Http\Controllers;

use App\Models\RadioFavorite;
use App\Services\IptvPlaylistService;
use App\Services\RadioBrowserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Radio aggregation and user favorites API.
 *
 * Responsibilities:
 * - fetch and normalize stations from Radio Browser service;
 * - proxy insecure/public streams through backend when needed;
 * - persist per-user favorite stations.
 */
class RadioController extends Controller
{
    public function __construct(
        private readonly RadioBrowserService $radioBrowserService,
        private readonly IptvPlaylistService $iptvPlaylistService
    )
    {
    }

    /**
     * Search radio stations and attach per-user favorite flag.
     */
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
        } catch (Throwable $exception) {
            Log::warning('Radio stations lookup failed.', [
                'filters' => $filters,
                'error' => $exception->getMessage(),
            ]);

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

    /**
     * Proxy external radio stream to the browser.
     *
     * Used to avoid mixed-content/CORS issues when frontend runs on HTTPS and
     * the source station stream is available only over plain HTTP.
     */
    public function stream(Request $request): JsonResponse|StreamedResponse
    {
        $payload = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $streamUrl = $this->iptvPlaylistService->validateExternalUrl($payload['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        try {
            $proxy = $this->resolveProxyOption();

            $upstreamResponse = Http::withOptions([
                'stream' => true,
                'connect_timeout' => 8,
                'read_timeout' => 20,
                'proxy' => $proxy,
            ])
                ->timeout(0)
                ->withHeaders([
                    'Accept' => '*/*',
                    'User-Agent' => 'SolidSocial-RadioProxy/1.0',
                ])
                ->get($streamUrl)
                ->throw();
        } catch (ConnectionException|RequestException|Throwable $exception) {
            Log::warning('Radio stream proxy failed.', [
                'url' => $streamUrl,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Не удалось подключиться к радиопотоку.',
            ], 503);
        }

        $contentType = trim((string) $upstreamResponse->header('Content-Type', 'audio/mpeg'));
        /** @var StreamInterface $stream */
        $stream = $upstreamResponse->toPsrResponse()->getBody();

        return response()->stream(function () use ($stream): void {
            try {
                // We stream chunks manually to keep memory usage stable.
                while (!$stream->eof()) {
                    $chunk = $stream->read(8192);
                    if ($chunk === '') {
                        usleep(30000);
                        continue;
                    }

                    echo $chunk;
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    flush();
                }
            } finally {
                if (method_exists($stream, 'close')) {
                    $stream->close();
                }
            }
        }, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Return current user's favorite stations.
     */
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

    /**
     * Upsert station into current user's favorites.
     */
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

    /**
     * Remove station from current user's favorites.
     */
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

    /**
     * Normalize external station payload to app-level contract.
     */
    protected function normalizeStationPayload(array $station): array
    {
        // Prefer `url_resolved` because Radio Browser can provide redirect-safe URL.
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

    /**
     * Resolve optional outbound HTTP proxy from config.
     *
     * Accepts string flags like "false"/"off"/"none" for easier env management.
     */
    private function resolveProxyOption(): string|array|bool
    {
        $proxy = config('services.radio_browser.proxy', false);

        if (!is_string($proxy)) {
            return $proxy ?: false;
        }

        $normalized = trim($proxy);
        if ($normalized === '') {
            return false;
        }

        $lower = strtolower($normalized);
        if (in_array($lower, ['false', 'off', 'none', 'no'], true)) {
            return false;
        }

        return $normalized;
    }
}
