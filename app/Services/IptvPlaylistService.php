<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class IptvPlaylistService
{
    private const MAX_PLAYLIST_BYTES = 10 * 1024 * 1024;
    private const MAX_PLAYLIST_LINES = 250000;

    public function fetchFromUrl(string $url): string
    {
        $normalizedUrl = $this->validateExternalUrl($url);

        $response = Http::timeout(15)
            ->retry(1, 250)
            ->accept('application/x-mpegURL,application/vnd.apple.mpegurl,text/plain,*/*')
            ->withHeaders([
                'User-Agent' => 'SolidSocial-IPTV/1.0',
            ])
            ->get($normalizedUrl);

        if (!$response->ok()) {
            throw new RuntimeException('Не удалось загрузить IPTV-плейлист по ссылке.');
        }

        $playlist = trim((string) $response->body());
        if ($playlist === '') {
            throw new RuntimeException('Плейлист пустой или недоступен.');
        }

        if (strlen($playlist) > self::MAX_PLAYLIST_BYTES) {
            $maxMegabytes = intdiv(self::MAX_PLAYLIST_BYTES, 1024 * 1024);
            throw new InvalidArgumentException("Плейлист слишком большой (максимум {$maxMegabytes} МБ).");
        }

        $this->validatePlaylistBody($playlist);

        return $playlist;
    }

    public function validateExternalUrl(string $url): string
    {
        $normalizedUrl = trim($url);
        if ($normalizedUrl === '') {
            throw new InvalidArgumentException('Укажите ссылку на IPTV-плейлист.');
        }

        $parsed = parse_url($normalizedUrl);
        if (!is_array($parsed)) {
            throw new InvalidArgumentException('Некорректная ссылка на источник.');
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Поддерживаются только HTTP/HTTPS ссылки.');
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '' || $this->isBlockedHost($host)) {
            throw new InvalidArgumentException('Ссылка ведет на недопустимый хост.');
        }

        return $normalizedUrl;
    }

    protected function isBlockedHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }

    private function validatePlaylistBody(string $playlist): void
    {
        $normalized = str_replace("\r", '', trim($playlist));
        if ($normalized === '') {
            throw new InvalidArgumentException('Плейлист пустой или недоступен.');
        }

        if ($this->looksLikeHtml($normalized)) {
            throw new InvalidArgumentException('По ссылке вернулся HTML-документ, а не IPTV-плейлист.');
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $normalized) === 1) {
            throw new InvalidArgumentException('Плейлист содержит недопустимые бинарные данные.');
        }

        $lines = preg_split('/\n/', $normalized) ?: [];
        if (count($lines) > self::MAX_PLAYLIST_LINES) {
            throw new InvalidArgumentException('Плейлист содержит слишком много строк и отклонён по соображениям безопасности.');
        }

        $hasValidHttpStream = false;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $lowerLine = strtolower($line);
            if (
                str_starts_with($lowerLine, 'javascript:')
                || str_starts_with($lowerLine, 'data:')
                || str_starts_with($lowerLine, 'vbscript:')
                || str_starts_with($lowerLine, 'file:')
            ) {
                throw new InvalidArgumentException('Плейлист содержит небезопасные ссылки и был отклонён.');
            }

            $parsed = parse_url($line);
            if (!is_array($parsed)) {
                continue;
            }

            $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
            if (!in_array($scheme, ['http', 'https'], true)) {
                continue;
            }

            $host = strtolower((string) ($parsed['host'] ?? ''));
            if ($host === '' || $this->isBlockedHost($host)) {
                continue;
            }

            $hasValidHttpStream = true;
            break;
        }

        if (!$hasValidHttpStream) {
            throw new InvalidArgumentException('В плейлисте не найдено валидных HTTP/HTTPS-потоков.');
        }
    }

    private function looksLikeHtml(string $payload): bool
    {
        $head = strtolower(substr(ltrim($payload), 0, 4096));

        return str_contains($head, '<!doctype html')
            || str_contains($head, '<html')
            || str_contains($head, '<head')
            || str_contains($head, '<body')
            || str_contains($head, '<script')
            || str_contains($head, '<iframe');
    }
}
