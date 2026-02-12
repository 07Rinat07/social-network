<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class IptvPlaylistService
{
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

        if (strlen($playlist) > 3 * 1024 * 1024) {
            throw new InvalidArgumentException('Плейлист слишком большой для MVP-режима (максимум 3 МБ).');
        }

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
}
