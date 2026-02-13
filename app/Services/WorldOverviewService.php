<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WorldOverviewService
{
    protected const WEATHER_ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    public function overview(string $locale = 'ru'): array
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $cities = [];

        foreach ($this->cityDefinitions() as $city) {
            $weather = $this->fetchWeatherForCity($city);
            $timezone = (string) $city['timezone'];
            $now = Carbon::now($timezone);

            $cities[] = [
                'id' => $city['id'],
                'name' => $city['name'][$normalizedLocale] ?? $city['name']['ru'],
                'country' => $city['country'][$normalizedLocale] ?? $city['country']['ru'],
                'timezone' => $timezone,
                'time_iso' => $now->toIso8601String(),
                'weather' => [
                    'temperature_c' => $weather['temperature_c'],
                    'wind_speed_kmh' => $weather['wind_speed_kmh'],
                    'code' => $weather['code'],
                    'description' => $this->weatherDescription($weather['code'], $normalizedLocale, $weather['available']),
                    'icon' => $this->weatherIcon($weather['code']),
                    'available' => $weather['available'],
                ],
            ];
        }

        return [
            'locale' => $normalizedLocale,
            'updated_at' => now()->toIso8601String(),
            'source' => 'open-meteo.com',
            'cities' => $cities,
        ];
    }

    protected function normalizeLocale(string $locale): string
    {
        return strtolower(trim($locale)) === 'en' ? 'en' : 'ru';
    }

    protected function cityDefinitions(): array
    {
        return [
            [
                'id' => 'new_york',
                'name' => ['ru' => 'Нью-Йорк', 'en' => 'New York'],
                'country' => ['ru' => 'США', 'en' => 'USA'],
                'timezone' => 'America/New_York',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ],
            [
                'id' => 'moscow',
                'name' => ['ru' => 'Москва', 'en' => 'Moscow'],
                'country' => ['ru' => 'Россия', 'en' => 'Russia'],
                'timezone' => 'Europe/Moscow',
                'latitude' => 55.7558,
                'longitude' => 37.6173,
            ],
            [
                'id' => 'minsk',
                'name' => ['ru' => 'Минск', 'en' => 'Minsk'],
                'country' => ['ru' => 'Беларусь', 'en' => 'Belarus'],
                'timezone' => 'Europe/Minsk',
                'latitude' => 53.9006,
                'longitude' => 27.5590,
            ],
            [
                'id' => 'astana',
                'name' => ['ru' => 'Астана', 'en' => 'Astana'],
                'country' => ['ru' => 'Казахстан', 'en' => 'Kazakhstan'],
                'timezone' => 'Asia/Almaty',
                'latitude' => 51.1694,
                'longitude' => 71.4491,
            ],
            [
                'id' => 'ankara',
                'name' => ['ru' => 'Анкара', 'en' => 'Ankara'],
                'country' => ['ru' => 'Турция', 'en' => 'Turkey'],
                'timezone' => 'Europe/Istanbul',
                'latitude' => 39.9334,
                'longitude' => 32.8597,
            ],
            [
                'id' => 'uralsk',
                'name' => ['ru' => 'Уральск', 'en' => 'Uralsk'],
                'country' => ['ru' => 'Казахстан', 'en' => 'Kazakhstan'],
                'timezone' => 'Asia/Oral',
                'latitude' => 51.2300,
                'longitude' => 51.3667,
            ],
        ];
    }

    protected function fetchWeatherForCity(array $city): array
    {
        $cacheKey = sprintf('world_overview_weather:%s', (string) $city['id']);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($city) {
            try {
                $response = Http::acceptJson()
                    ->timeout(9)
                    ->retry(2, 300)
                    ->get(self::WEATHER_ENDPOINT, [
                        'latitude' => $city['latitude'],
                        'longitude' => $city['longitude'],
                        'current' => 'temperature_2m,weather_code,wind_speed_10m',
                        'wind_speed_unit' => 'kmh',
                        'timezone' => 'auto',
                        'forecast_days' => 1,
                    ])
                    ->throw();

                $payload = $response->json();
                $current = is_array($payload['current'] ?? null) ? $payload['current'] : [];

                $temperature = $this->normalizeFloat($current['temperature_2m'] ?? null);
                $windSpeed = $this->normalizeFloat($current['wind_speed_10m'] ?? null);
                $code = $this->normalizeInt($current['weather_code'] ?? null);

                return [
                    'available' => true,
                    'temperature_c' => $temperature,
                    'wind_speed_kmh' => $windSpeed,
                    'code' => $code,
                ];
            } catch (\Throwable $exception) {
                return [
                    'available' => false,
                    'temperature_c' => null,
                    'wind_speed_kmh' => null,
                    'code' => null,
                ];
            }
        });
    }

    protected function normalizeFloat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 1);
    }

    protected function normalizeInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    protected function weatherDescription(?int $code, string $locale, bool $available): string
    {
        if (!$available || $code === null) {
            return $locale === 'en' ? 'No weather data' : 'Нет данных о погоде';
        }

        $dictionary = [
            0 => ['ru' => 'Ясно', 'en' => 'Clear sky'],
            1 => ['ru' => 'Преимущественно ясно', 'en' => 'Mainly clear'],
            2 => ['ru' => 'Переменная облачность', 'en' => 'Partly cloudy'],
            3 => ['ru' => 'Пасмурно', 'en' => 'Overcast'],
            45 => ['ru' => 'Туман', 'en' => 'Fog'],
            48 => ['ru' => 'Изморозь', 'en' => 'Depositing rime fog'],
            51 => ['ru' => 'Мелкая морось', 'en' => 'Light drizzle'],
            53 => ['ru' => 'Морось', 'en' => 'Drizzle'],
            55 => ['ru' => 'Сильная морось', 'en' => 'Dense drizzle'],
            56 => ['ru' => 'Ледяная морось', 'en' => 'Light freezing drizzle'],
            57 => ['ru' => 'Сильная ледяная морось', 'en' => 'Dense freezing drizzle'],
            61 => ['ru' => 'Небольшой дождь', 'en' => 'Slight rain'],
            63 => ['ru' => 'Дождь', 'en' => 'Rain'],
            65 => ['ru' => 'Сильный дождь', 'en' => 'Heavy rain'],
            66 => ['ru' => 'Ледяной дождь', 'en' => 'Light freezing rain'],
            67 => ['ru' => 'Сильный ледяной дождь', 'en' => 'Heavy freezing rain'],
            71 => ['ru' => 'Небольшой снег', 'en' => 'Slight snow fall'],
            73 => ['ru' => 'Снег', 'en' => 'Snow fall'],
            75 => ['ru' => 'Сильный снег', 'en' => 'Heavy snow fall'],
            77 => ['ru' => 'Снежная крупа', 'en' => 'Snow grains'],
            80 => ['ru' => 'Ливневый дождь', 'en' => 'Rain showers'],
            81 => ['ru' => 'Умеренный ливень', 'en' => 'Moderate rain showers'],
            82 => ['ru' => 'Сильный ливень', 'en' => 'Violent rain showers'],
            85 => ['ru' => 'Снежные заряды', 'en' => 'Snow showers'],
            86 => ['ru' => 'Сильные снежные заряды', 'en' => 'Heavy snow showers'],
            95 => ['ru' => 'Гроза', 'en' => 'Thunderstorm'],
            96 => ['ru' => 'Гроза с градом', 'en' => 'Thunderstorm with hail'],
            99 => ['ru' => 'Сильная гроза с градом', 'en' => 'Heavy thunderstorm with hail'],
        ];

        if (!array_key_exists($code, $dictionary)) {
            return $locale === 'en' ? 'Weather update' : 'Погодные данные';
        }

        return $dictionary[$code][$locale] ?? $dictionary[$code]['ru'];
    }

    protected function weatherIcon(?int $code): string
    {
        if ($code === null) {
            return '🌡️';
        }

        return match (true) {
            $code === 0 => '☀️',
            in_array($code, [1], true) => '🌤️',
            in_array($code, [2], true) => '⛅',
            in_array($code, [3], true) => '☁️',
            in_array($code, [45, 48], true) => '🌫️',
            in_array($code, [51, 53, 55, 56, 57, 80, 81, 82], true) => '🌧️',
            in_array($code, [61, 63, 65, 66, 67], true) => '🌧️',
            in_array($code, [71, 73, 75, 77, 85, 86], true) => '🌨️',
            in_array($code, [95, 96, 99], true) => '⛈️',
            default => '🌤️',
        };
    }
}
