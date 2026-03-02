<?php

namespace App\Http\Controllers;

use App\Models\IptvSavedChannel;
use App\Models\IptvSavedPlaylist;
use App\Models\IptvSeed;
use App\Services\IptvPlaylistService;
use App\Services\IptvProxyService;
use App\Services\IptvTranscodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * IPTV API controller for playlist import, personal library and playback sessions.
 *
 * Playback modes:
 * - proxy: remote playlist/segments are proxied as-is;
 * - transcode: ffmpeg builds compatible HLS output;
 * - relay: lightweight hls relay session.
 */
class IptvController extends Controller
{
    public function __construct(
        private readonly IptvPlaylistService $iptvPlaylistService,
        private readonly IptvProxyService $iptvProxyService,
        private readonly IptvTranscodeService $iptvTranscodeService
    )
    {
    }

    /**
     * Fetch and validate remote M3U/M3U8 playlist content.
     */
    public function fetchPlaylist(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $playlist = $this->iptvPlaylistService->fetchFromUrl($payload['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [
                    'playlist' => '',
                ],
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Не удалось загрузить IPTV-плейлист. Попробуйте другую ссылку позже.',
                'data' => [
                    'playlist' => '',
                ],
            ], 503);
        }

        return response()->json([
            'message' => 'Плейлист загружен.',
            'data' => [
                'playlist' => $playlist,
                'source_url' => $payload['url'],
                'size' => strlen($playlist),
            ],
        ]);
    }

    /**
     * Return active IPTV seed list configured by admins.
     */
    public function seeds(): JsonResponse
    {
        $seeds = IptvSeed::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'url']);

        return response()->json([
            'data' => $seeds,
        ]);
    }

    /**
     * Return current user's saved playlists/channels.
     */
    public function savedLibrary(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $playlists = IptvSavedPlaylist::query()
            ->where('user_id', $userId)
            ->latest('updated_at')
            ->limit(200)
            ->get();

        $channels = IptvSavedChannel::query()
            ->where('user_id', $userId)
            ->latest('updated_at')
            ->limit(500)
            ->get();

        return response()->json([
            'data' => [
                'playlists' => $playlists->map(fn (IptvSavedPlaylist $item) => $this->serializeSavedPlaylist($item))->values(),
                'channels' => $channels->map(fn (IptvSavedChannel $item) => $this->serializeSavedChannel($item))->values(),
            ],
        ]);
    }

    /**
     * Create or update saved playlist for the current user.
     */
    public function storeSavedPlaylist(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2000'],
            'channels_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        try {
            $sourceUrl = $this->iptvPlaylistService->validateExternalUrl($payload['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $normalizedUrlHash = hash('sha256', mb_strtolower(trim($sourceUrl)));
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = $this->guessNameFromUrl($sourceUrl);
        }

        IptvSavedPlaylist::query()->upsert(
            [[
                'user_id' => (int) $request->user()->id,
                'source_url_hash' => $normalizedUrlHash,
                'name' => mb_substr($name, 0, 120),
                'source_url' => $sourceUrl,
                'channels_count' => (int) ($payload['channels_count'] ?? 0),
            ]],
            ['user_id', 'source_url_hash'],
            ['name', 'source_url', 'channels_count']
        );

        $playlist = IptvSavedPlaylist::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('source_url_hash', $normalizedUrlHash)
            ->firstOrFail();

        $this->trimSavedPlaylists((int) $request->user()->id, 200);

        return response()->json([
            'message' => 'Плейлист сохранен.',
            'data' => $this->serializeSavedPlaylist($playlist),
        ], 201);
    }

    /**
     * Delete saved playlist by id.
     */
    public function destroySavedPlaylist(int $playlistId, Request $request): JsonResponse
    {
        IptvSavedPlaylist::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('id', $playlistId)
            ->delete();

        return response()->json([
            'message' => 'Сохраненный плейлист удален.',
        ]);
    }

    /**
     * Rename saved playlist.
     */
    public function updateSavedPlaylist(int $playlistId, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return response()->json([
                'message' => 'Имя не должно быть пустым.',
            ], 422);
        }

        $playlist = IptvSavedPlaylist::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('id', $playlistId)
            ->first();

        if (!$playlist) {
            return response()->json([
                'message' => 'Сохраненный плейлист не найден.',
            ], 404);
        }

        $playlist->name = mb_substr($name, 0, 120);
        $playlist->save();

        return response()->json([
            'message' => 'Имя плейлиста обновлено.',
            'data' => $this->serializeSavedPlaylist($playlist),
        ]);
    }

    /**
     * Create or update saved channel for the current user.
     */
    public function storeSavedChannel(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:4000'],
            'group' => ['nullable', 'string', 'max:160'],
            'logo' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $streamUrl = $this->iptvPlaylistService->validateExternalUrl($payload['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $logoUrl = trim((string) ($payload['logo'] ?? ''));
        if ($logoUrl !== '' && !$this->isHttpOrHttpsUrl($logoUrl)) {
            return response()->json([
                'message' => 'Ссылка логотипа должна быть в формате http/https.',
            ], 422);
        }

        $normalizedUrlHash = hash('sha256', mb_strtolower(trim($streamUrl)));

        IptvSavedChannel::query()->upsert(
            [[
                'user_id' => (int) $request->user()->id,
                'stream_url_hash' => $normalizedUrlHash,
                'name' => mb_substr(trim((string) $payload['name']), 0, 120),
                'stream_url' => $streamUrl,
                'group_title' => mb_substr(trim((string) ($payload['group'] ?? '')), 0, 160),
                'logo_url' => $logoUrl === '' ? null : $logoUrl,
            ]],
            ['user_id', 'stream_url_hash'],
            ['name', 'stream_url', 'group_title', 'logo_url']
        );

        $channel = IptvSavedChannel::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('stream_url_hash', $normalizedUrlHash)
            ->firstOrFail();

        $this->trimSavedChannels((int) $request->user()->id, 500);

        return response()->json([
            'message' => 'Канал сохранен.',
            'data' => $this->serializeSavedChannel($channel),
        ], 201);
    }

    /**
     * Delete saved channel by id.
     */
    public function destroySavedChannel(int $channelId, Request $request): JsonResponse
    {
        IptvSavedChannel::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('id', $channelId)
            ->delete();

        return response()->json([
            'message' => 'Сохраненный канал удален.',
        ]);
    }

    /**
     * Rename saved channel.
     */
    public function updateSavedChannel(int $channelId, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return response()->json([
                'message' => 'Имя не должно быть пустым.',
            ], 422);
        }

        $channel = IptvSavedChannel::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('id', $channelId)
            ->first();

        if (!$channel) {
            return response()->json([
                'message' => 'Сохраненный канал не найден.',
            ], 404);
        }

        $channel->name = mb_substr($name, 0, 120);
        $channel->save();

        return response()->json([
            'message' => 'Имя канала обновлено.',
            'data' => $this->serializeSavedChannel($channel),
        ]);
    }

    /**
     * Return current transcoding capabilities (ffmpeg availability/profile support).
     */
    public function transcodeCapabilities(): JsonResponse
    {
        $capabilities = $this->iptvTranscodeService->getCapabilities();

        return response()->json([
            'message' => 'Проверка режима совместимости выполнена.',
            'data' => $capabilities,
        ]);
    }

    /**
     * Start proxy mode session and return playback URL.
     */
    public function startProxy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'url' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $session = $this->iptvProxyService->startSession($payload['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [],
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Не удалось запустить IPTV-прокси. Попробуйте позже.',
                'data' => [],
            ], 503);
        }

        return response()->json([
            'message' => 'IPTV-прокси запущен.',
            'data' => [
                'session_id' => $session['session_id'],
                'source_url' => $session['source_url'],
                'playlist_url' => route('api.iptv.proxy.playlist', ['session' => $session['session_id']], false),
            ],
        ]);
    }

    /**
     * Start transcode session and wait until HLS playlist is ready.
     */
    public function startTranscode(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'profile' => ['nullable', 'string', 'in:fast,balanced,stable'],
        ]);

        try {
            $session = $this->iptvTranscodeService->startSession(
                $payload['url'],
                (string) ($payload['profile'] ?? 'balanced')
            );

            // Warm-up: avoid returning session that is not yet playable by the client.
            $playlistReady = $this->iptvTranscodeService->waitForPlaylist($session['session_id'], 12);
            if (!$playlistReady) {
                $this->iptvTranscodeService->stopSession($session['session_id']);
                throw new RuntimeException('Совместимый режим не успел подготовить поток. Попробуйте другой канал или профиль FFmpeg "устойчивый".');
            }
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [],
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Не удалось запустить совместимый режим IPTV. Попробуйте позже.',
                'data' => [],
            ], 503);
        }

        return response()->json([
            'message' => 'Совместимый режим запущен.',
            'data' => [
                'session_id' => $session['session_id'],
                'profile' => $session['profile'],
                'source_url' => $session['source_url'],
                'playlist_url' => route('api.iptv.transcode.playlist', ['session' => $session['session_id']], false),
            ],
        ]);
    }

    /**
     * Start relay session and wait until HLS playlist is ready.
     */
    public function startRelay(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $session = $this->iptvTranscodeService->startRelaySession($payload['url']);

            // Warm-up: relay should respond with an already prepared playlist.
            $playlistReady = $this->iptvTranscodeService->waitForPlaylist($session['session_id'], 10);
            if (!$playlistReady) {
                $this->iptvTranscodeService->stopSession($session['session_id']);
                throw new RuntimeException('Релейный режим не успел подготовить поток. Попробуйте другой канал или вернитесь в прямой режим.');
            }
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [],
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Не удалось запустить релейный режим IPTV. Попробуйте позже.',
                'data' => [],
            ], 503);
        }

        return response()->json([
            'message' => 'Релейный режим запущен.',
            'data' => [
                'session_id' => $session['session_id'],
                'source_url' => $session['source_url'],
                'playlist_url' => route('api.iptv.relay.playlist', ['session' => $session['session_id']], false),
            ],
        ]);
    }

    /**
     * Stream generated transcode HLS playlist file.
     */
    public function transcodePlaylist(string $session)
    {
        $path = $this->iptvTranscodeService->getPlaylistPath($session);
        if (!$path) {
            return response()->json([
                'message' => 'HLS-плейлист совместимого режима не найден или истек.',
            ], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Stream proxied playlist body.
     */
    public function proxyPlaylist(string $session)
    {
        try {
            $playlist = $this->iptvProxyService->getPlaylist($session);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        if (!$playlist) {
            return response()->json([
                'message' => 'Прокси-плейлист не найден или истек.',
            ], 404);
        }

        return response((string) ($playlist['body'] ?? ''), 200, [
            'Content-Type' => (string) ($playlist['content_type'] ?? 'application/vnd.apple.mpegurl; charset=utf-8'),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Stream generated relay HLS playlist file.
     */
    public function relayPlaylist(string $session)
    {
        $path = $this->iptvTranscodeService->getPlaylistPath($session);
        if (!$path) {
            return response()->json([
                'message' => 'HLS-плейлист релейного режима не найден или истек.',
            ], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Stream generated transcode HLS segment file.
     */
    public function transcodeSegment(string $session, string $segment)
    {
        $path = $this->iptvTranscodeService->getSegmentPath($session, $segment);
        if (!$path) {
            return response()->json([
                'message' => 'HLS-сегмент не найден или истек.',
            ], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'video/mp2t',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Stream generated relay HLS segment file.
     */
    public function relaySegment(string $session, string $segment)
    {
        $path = $this->iptvTranscodeService->getSegmentPath($session, $segment);
        if (!$path) {
            return response()->json([
                'message' => 'HLS-сегмент релейного режима не найден или истек.',
            ], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'video/mp2t',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Stream proxied segment content for active proxy session.
     */
    public function proxySegment(Request $request, string $session)
    {
        $url = trim((string) $request->query('url', ''));
        if ($url === '') {
            return response()->json([
                'message' => 'Не указан URL сегмента для прокси.',
            ], 422);
        }

        try {
            $segment = $this->iptvProxyService->getSegment($session, $url);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        if (!$segment) {
            return response()->json([
                'message' => 'Прокси-сегмент не найден или истек.',
            ], 404);
        }

        return response((string) ($segment['body'] ?? ''), 200, [
            'Content-Type' => (string) ($segment['content_type'] ?? 'application/octet-stream'),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Normalize playlist entity to API payload contract.
     */
    private function serializeSavedPlaylist(IptvSavedPlaylist $playlist): array
    {
        return [
            'id' => (int) $playlist->id,
            'name' => (string) $playlist->name,
            'url' => (string) $playlist->source_url,
            'channels_count' => (int) $playlist->channels_count,
            'updated_at' => $playlist->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Normalize channel entity to API payload contract.
     */
    private function serializeSavedChannel(IptvSavedChannel $channel): array
    {
        return [
            'id' => (int) $channel->id,
            'name' => (string) $channel->name,
            'url' => (string) $channel->stream_url,
            'group' => (string) ($channel->group_title ?? ''),
            'logo' => (string) ($channel->logo_url ?? ''),
            'updated_at' => $channel->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Keep only most recent playlists within configured cap.
     */
    private function trimSavedPlaylists(int $userId, int $limit): void
    {
        $idsToKeep = IptvSavedPlaylist::query()
            ->where('user_id', $userId)
            ->latest('updated_at')
            ->latest('id')
            ->limit($limit)
            ->pluck('id');

        if ($idsToKeep->isEmpty()) {
            return;
        }

        IptvSavedPlaylist::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }

    /**
     * Keep only most recent channels within configured cap.
     */
    private function trimSavedChannels(int $userId, int $limit): void
    {
        $idsToKeep = IptvSavedChannel::query()
            ->where('user_id', $userId)
            ->latest('updated_at')
            ->latest('id')
            ->limit($limit)
            ->pluck('id');

        if ($idsToKeep->isEmpty()) {
            return;
        }

        IptvSavedChannel::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }

    /**
     * Derive readable playlist name from URL host.
     */
    private function guessNameFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return 'IPTV playlist';
    }

    /**
     * Lightweight URL scheme guard for logo links.
     */
    private function isHttpOrHttpsUrl(string $url): bool
    {
        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if (!is_string($validated) || $validated === '') {
            return false;
        }

        $scheme = (string) parse_url($validated, PHP_URL_SCHEME);
        $normalizedScheme = strtolower($scheme);

        return $normalizedScheme === 'http' || $normalizedScheme === 'https';
    }

    /**
     * Stop transcode session.
     */
    public function stopTranscode(string $session): JsonResponse
    {
        $this->iptvTranscodeService->stopSession($session);

        return response()->json([
            'message' => 'Совместимый режим остановлен.',
            'data' => [],
        ]);
    }

    /**
     * Stop proxy session.
     */
    public function stopProxy(string $session): JsonResponse
    {
        $this->iptvProxyService->stopSession($session);

        return response()->json([
            'message' => 'IPTV-прокси остановлен.',
            'data' => [],
        ]);
    }

    /**
     * Stop relay session.
     */
    public function stopRelay(string $session): JsonResponse
    {
        $this->iptvTranscodeService->stopSession($session);

        return response()->json([
            'message' => 'Релейный режим остановлен.',
            'data' => [],
        ]);
    }
}
