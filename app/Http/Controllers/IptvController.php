<?php

namespace App\Http\Controllers;

use App\Services\IptvPlaylistService;
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

    public function stopTranscode(string $session): JsonResponse
    {
        $this->iptvTranscodeService->stopSession($session);

        return response()->json([
            'message' => 'Совместимый режим остановлен.',
            'data' => [],
        ]);
    }
}
