<?php

namespace App\Http\Controllers;

use App\Services\IptvPlaylistService;
use App\Services\IptvProxyService;
use App\Services\IptvTranscodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class IptvController extends Controller
{
    public function __construct(
        private readonly IptvPlaylistService $iptvPlaylistService,
        private readonly IptvProxyService $iptvProxyService,
        private readonly IptvTranscodeService $iptvTranscodeService
    )
    {
    }

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

    public function transcodeCapabilities(): JsonResponse
    {
        $capabilities = $this->iptvTranscodeService->getCapabilities();

        return response()->json([
            'message' => 'Проверка режима совместимости выполнена.',
            'data' => $capabilities,
        ]);
    }

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

    public function startRelay(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $session = $this->iptvTranscodeService->startRelaySession($payload['url']);

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

    public function stopTranscode(string $session): JsonResponse
    {
        $this->iptvTranscodeService->stopSession($session);

        return response()->json([
            'message' => 'Совместимый режим остановлен.',
            'data' => [],
        ]);
    }

    public function stopProxy(string $session): JsonResponse
    {
        $this->iptvProxyService->stopSession($session);

        return response()->json([
            'message' => 'IPTV-прокси остановлен.',
            'data' => [],
        ]);
    }

    public function stopRelay(string $session): JsonResponse
    {
        $this->iptvTranscodeService->stopSession($session);

        return response()->json([
            'message' => 'Релейный режим остановлен.',
            'data' => [],
        ]);
    }
}
