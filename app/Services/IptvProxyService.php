<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class IptvProxyService
{
    private const SESSIONS_DIR = 'app/iptv-proxy';
    private const SESSION_TTL_SECONDS = 7200;
    private const MAX_ACTIVE_SESSIONS = 12;
    private const SESSION_ID_LENGTH = 24;

    public function __construct(private readonly IptvPlaylistService $iptvPlaylistService)
    {
    }

    /**
     * @return array{session_id: string, source_url: string, created_at: int, last_access_at: int}
     */
    public function startSession(string $url): array
    {
        $sourceUrl = $this->iptvPlaylistService->validateExternalUrl($url);

        $this->ensureSessionsRootExists();
        $this->cleanupExpiredSessions();
        $this->enforceSessionLimit();

        $sessionId = $this->generateSessionId();
        $sessionDir = $this->sessionDir($sessionId);
        if (!mkdir($sessionDir, 0775, true) && !is_dir($sessionDir)) {
            throw new RuntimeException('Не удалось создать директорию IPTV-прокси.');
        }

        $now = time();
        $metadata = [
            'session_id' => $sessionId,
            'source_url' => $sourceUrl,
            'created_at' => $now,
            'last_access_at' => $now,
        ];

        $this->writeMetadata($sessionId, $metadata);
        return $metadata;
    }

    /**
     * @return array{body: string, content_type: string}|null
     */
    public function getPlaylist(string $sessionId): ?array
    {
        $metadata = $this->loadSessionMetadata($sessionId);
        if (!$metadata) {
            return null;
        }

        $sourceUrl = (string) ($metadata['source_url'] ?? '');
        if ($sourceUrl === '') {
            return null;
        }

        $response = $this->fetchRemoteContent($sourceUrl);
        $playlist = trim((string) $response->body());
        if ($playlist === '') {
            throw new RuntimeException('IPTV-прокси получил пустой HLS-плейлист.');
        }

        $this->touchSession($sessionId);

        return [
            'body' => $this->rewritePlaylistUrls($playlist, $sourceUrl, $sessionId),
            'content_type' => 'application/vnd.apple.mpegurl; charset=utf-8',
        ];
    }

    /**
     * @return array{body: string, content_type: string}|null
     */
    public function getSegment(string $sessionId, string $targetUrl): ?array
    {
        $metadata = $this->loadSessionMetadata($sessionId);
        if (!$metadata) {
            return null;
        }

        $baseUrl = (string) ($metadata['source_url'] ?? '');
        if ($baseUrl === '') {
            return null;
        }

        $resolvedUrl = $this->resolveUrl($baseUrl, $targetUrl);
        $resolvedUrl = $this->iptvPlaylistService->validateExternalUrl($resolvedUrl);

        $response = $this->fetchRemoteContent($resolvedUrl);
        $body = (string) $response->body();
        $contentType = $this->normalizeContentType((string) $response->header('Content-Type'), 'application/octet-stream');

        if ($this->isPlaylistLike($resolvedUrl, $contentType, $body)) {
            $body = $this->rewritePlaylistUrls($body, $resolvedUrl, $sessionId);
            $contentType = 'application/vnd.apple.mpegurl; charset=utf-8';
        }

        $this->touchSession($sessionId);

        return [
            'body' => $body,
            'content_type' => $contentType,
        ];
    }

    public function stopSession(string $sessionId): void
    {
        if (!$this->isValidSessionId($sessionId)) {
            return;
        }

        $this->deleteDirectory($this->sessionDir($sessionId));
    }

    public function cleanupExpiredSessions(): void
    {
        if (!is_dir($this->sessionsRoot())) {
            return;
        }

        $threshold = time() - self::SESSION_TTL_SECONDS;
        foreach ($this->listSessionIds() as $sessionId) {
            $metadata = $this->readMetadata($sessionId);
            if (!is_array($metadata)) {
                $this->stopSession($sessionId);
                continue;
            }

            $lastAccess = (int) ($metadata['last_access_at'] ?? 0);
            if ($lastAccess < $threshold) {
                $this->stopSession($sessionId);
            }
        }
    }

    private function fetchRemoteContent(string $url): Response
    {
        $origin = $this->originFromUrl($url);
        $referer = $origin !== '' ? rtrim($origin, '/') . '/' : '';

        $response = Http::timeout(20)
            ->retry(2, 300)
            ->accept('*/*')
            ->withHeaders([
                'User-Agent' => 'SolidSocial-IPTV-Proxy/1.0',
                'Accept-Language' => 'ru,en-US;q=0.9,en;q=0.8',
                'Referer' => $referer,
                'Origin' => $origin,
            ])
            ->get($url);

        if (!$response->ok()) {
            throw new RuntimeException('IPTV-прокси не смог получить ресурс потока.');
        }

        return $response;
    }

    private function originFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        return $scheme . '://' . $host . $port;
    }

    private function rewritePlaylistUrls(string $playlist, string $baseUrl, string $sessionId): string
    {
        $lines = preg_split('/\R/', str_replace("\r", '', $playlist)) ?: [];
        $result = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $result[] = '';
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                $result[] = $this->rewriteTagUriAttributes($line, $baseUrl, $sessionId);
                continue;
            }

            $resolved = $this->resolveUrl($baseUrl, $trimmed);
            try {
                $validated = $this->iptvPlaylistService->validateExternalUrl($resolved);
                $result[] = $this->proxySegmentUrl($sessionId, $validated);
            } catch (InvalidArgumentException) {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    private function rewriteTagUriAttributes(string $line, string $baseUrl, string $sessionId): string
    {
        return (string) preg_replace_callback('/URI="([^"]+)"/i', function (array $matches) use ($baseUrl, $sessionId): string {
            $uri = trim((string) ($matches[1] ?? ''));
            if ($uri === '') {
                return (string) $matches[0];
            }

            try {
                $resolved = $this->resolveUrl($baseUrl, $uri);
                $validated = $this->iptvPlaylistService->validateExternalUrl($resolved);
                return 'URI="' . $this->proxySegmentUrl($sessionId, $validated) . '"';
            } catch (InvalidArgumentException) {
                return (string) $matches[0];
            }
        }, $line);
    }

    private function proxySegmentUrl(string $sessionId, string $targetUrl): string
    {
        return route('api.iptv.proxy.segment', ['session' => $sessionId], false) . '?url=' . rawurlencode($targetUrl);
    }

    private function resolveUrl(string $baseUrl, string $reference): string
    {
        $candidate = trim($reference);
        if ($candidate === '') {
            return $baseUrl;
        }

        if (preg_match('#^https?://#i', $candidate)) {
            return $candidate;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base)) {
            throw new InvalidArgumentException('Некорректная базовая ссылка IPTV.');
        }

        $scheme = (string) ($base['scheme'] ?? 'https');
        $host = (string) ($base['host'] ?? '');
        $port = isset($base['port']) ? ':' . (int) $base['port'] : '';
        $basePath = (string) ($base['path'] ?? '/');

        if ($host === '') {
            throw new InvalidArgumentException('Некорректная базовая ссылка IPTV.');
        }

        if (str_starts_with($candidate, '//')) {
            return $scheme . ':' . $candidate;
        }

        if (str_starts_with($candidate, '?')) {
            $path = $basePath === '' ? '/' : $basePath;
            return $scheme . '://' . $host . $port . $path . $candidate;
        }

        $candidateWithoutFragment = $candidate;
        $fragment = '';
        if (str_contains($candidateWithoutFragment, '#')) {
            [$candidateWithoutFragment, $fragmentPart] = explode('#', $candidateWithoutFragment, 2);
            $fragment = '#' . $fragmentPart;
        }

        $query = '';
        if (str_contains($candidateWithoutFragment, '?')) {
            [$candidatePath, $queryPart] = explode('?', $candidateWithoutFragment, 2);
            $candidateWithoutFragment = $candidatePath;
            $query = '?' . $queryPart;
        }

        if ($candidateWithoutFragment === '') {
            $path = $basePath === '' ? '/' : $basePath;
            return $scheme . '://' . $host . $port . $path . $query . $fragment;
        }

        if (str_starts_with($candidateWithoutFragment, '/')) {
            $resolvedPath = $this->normalizePath($candidateWithoutFragment);
            return $scheme . '://' . $host . $port . $resolvedPath . $query . $fragment;
        }

        $baseDirectory = preg_replace('#/[^/]*$#', '/', $basePath);
        $baseDirectory = (string) ($baseDirectory ?: '/');
        $resolvedPath = $this->normalizePath($baseDirectory . $candidateWithoutFragment);

        return $scheme . '://' . $host . $port . $resolvedPath . $query . $fragment;
    }

    private function normalizePath(string $path): string
    {
        $segments = explode('/', $path);
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }

            $normalized[] = $segment;
        }

        return '/' . implode('/', $normalized);
    }

    private function isPlaylistLike(string $url, string $contentType, string $body): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        $normalizedContentType = strtolower($contentType);
        $trimmedBody = ltrim($body);

        return str_ends_with($path, '.m3u8')
            || str_contains($normalizedContentType, 'mpegurl')
            || str_starts_with($trimmedBody, '#EXTM3U');
    }

    private function normalizeContentType(string $contentType, string $fallback): string
    {
        $normalized = trim($contentType);
        if ($normalized === '') {
            return $fallback;
        }

        return preg_replace('/\s+/', ' ', $normalized) ?: $fallback;
    }

    /**
     * @return array{session_id: string, source_url: string, created_at: int, last_access_at: int}|null
     */
    private function loadSessionMetadata(string $sessionId): ?array
    {
        if (!$this->isValidSessionId($sessionId) || !$this->hasSession($sessionId)) {
            return null;
        }

        $metadata = $this->readMetadata($sessionId);
        if (!is_array($metadata)) {
            $this->stopSession($sessionId);
            return null;
        }

        return $metadata;
    }

    private function ensureSessionsRootExists(): void
    {
        $root = $this->sessionsRoot();
        if (is_dir($root)) {
            return;
        }

        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('Не удалось создать хранилище IPTV-прокси.');
        }
    }

    private function enforceSessionLimit(): void
    {
        $sessions = [];
        foreach ($this->listSessionIds() as $sessionId) {
            $metadata = $this->readMetadata($sessionId);
            if (!is_array($metadata)) {
                $this->stopSession($sessionId);
                continue;
            }

            $sessions[] = [
                'id' => $sessionId,
                'last_access_at' => (int) ($metadata['last_access_at'] ?? 0),
            ];
        }

        if (count($sessions) < self::MAX_ACTIVE_SESSIONS) {
            return;
        }

        usort($sessions, static fn (array $left, array $right): int => $left['last_access_at'] <=> $right['last_access_at']);
        $overflow = (count($sessions) - self::MAX_ACTIVE_SESSIONS) + 1;
        $toDrop = array_slice($sessions, 0, max(1, $overflow));

        foreach ($toDrop as $session) {
            $this->stopSession((string) $session['id']);
        }
    }

    private function generateSessionId(): string
    {
        do {
            $sessionId = strtolower(Str::random(self::SESSION_ID_LENGTH));
            $exists = is_dir($this->sessionDir($sessionId));
        } while ($exists);

        return $sessionId;
    }

    private function hasSession(string $sessionId): bool
    {
        return is_dir($this->sessionDir($sessionId));
    }

    private function isValidSessionId(string $sessionId): bool
    {
        return (bool) preg_match('/^[a-z0-9]{' . self::SESSION_ID_LENGTH . '}$/', $sessionId);
    }

    private function sessionsRoot(): string
    {
        return storage_path(self::SESSIONS_DIR);
    }

    private function sessionDir(string $sessionId): string
    {
        return $this->sessionsRoot() . DIRECTORY_SEPARATOR . $sessionId;
    }

    private function metadataPath(string $sessionId): string
    {
        return $this->sessionDir($sessionId) . DIRECTORY_SEPARATOR . 'metadata.json';
    }

    /**
     * @return string[]
     */
    private function listSessionIds(): array
    {
        $root = $this->sessionsRoot();
        if (!is_dir($root)) {
            return [];
        }

        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }

        $sessionIds = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!$this->isValidSessionId($entry)) {
                continue;
            }

            if (!is_dir($root . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            $sessionIds[] = $entry;
        }

        return $sessionIds;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMetadata(string $sessionId): ?array
    {
        $path = $this->metadataPath($sessionId);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function writeMetadata(string $sessionId, array $metadata): void
    {
        $json = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($json) || file_put_contents($this->metadataPath($sessionId), $json) === false) {
            throw new RuntimeException('Не удалось сохранить метаданные IPTV-прокси.');
        }
    }

    private function touchSession(string $sessionId): void
    {
        $metadata = $this->readMetadata($sessionId);
        if (!is_array($metadata)) {
            return;
        }

        $metadata['last_access_at'] = time();
        $this->writeMetadata($sessionId, $metadata);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            @rmdir($path);
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($entryPath)) {
                $this->deleteDirectory($entryPath);
                continue;
            }

            @unlink($entryPath);
        }

        @rmdir($path);
    }
}
