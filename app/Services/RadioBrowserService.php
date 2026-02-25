<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RadioBrowserService
{
    public function searchStations(array $filters = []): array
    {
        $baseUrl = rtrim((string) config('services.radio_browser.base_url', 'https://all.api.radio-browser.info'), '/');
        $proxy = $this->resolveProxyOption();

        $query = [
            'hidebroken' => 1,
            'order' => 'votes',
            'reverse' => true,
            'limit' => max(1, min((int) ($filters['limit'] ?? 30), 100)),
            'offset' => max(0, (int) ($filters['offset'] ?? 0)),
        ];

        $name = trim((string) ($filters['q'] ?? ''));
        if ($name !== '') {
            $query['name'] = $name;
        }

        $country = trim((string) ($filters['country'] ?? ''));
        if ($country !== '') {
            $query['country'] = $country;
        }

        $language = trim((string) ($filters['language'] ?? ''));
        if ($language !== '') {
            $query['language'] = $language;
        }

        $tag = trim((string) ($filters['tag'] ?? ''));
        if ($tag !== '') {
            $query['tag'] = $tag;
        }

        $response = Http::acceptJson()
            ->withOptions([
                'proxy' => $proxy,
            ])
            ->timeout(12)
            ->retry(2, 300)
            ->get($baseUrl . '/json/stations/search', $query)
            ->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

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
