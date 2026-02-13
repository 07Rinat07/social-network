<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Arr;

class SiteSettingService
{
    public const STORAGE_MODE_SERVER = 'server_local';
    public const STORAGE_MODE_CLOUD = 'cloud';
    public const STORAGE_MODE_USER_CHOICE = 'user_choice';

    public const USER_STORAGE_SERVER = 'server_local';
    public const USER_STORAGE_CLOUD = 'cloud';

    public const KEY_MEDIA_STORAGE_MODE = 'media_storage_mode';
    public const KEY_SERVER_MEDIA_DISK = 'server_media_disk';
    public const KEY_CLOUD_MEDIA_DISK = 'cloud_media_disk';
    public const KEY_HOME_PAGE_CONTENT = 'home_page_content';
    public const HOME_CONTENT_LOCALE_RU = 'ru';
    public const HOME_CONTENT_LOCALE_EN = 'en';
    public const HOME_CONTENT_DEFAULT_LOCALE = self::HOME_CONTENT_LOCALE_RU;
    public const HOME_CONTENT_LOCALES = [
        self::HOME_CONTENT_LOCALE_RU,
        self::HOME_CONTENT_LOCALE_EN,
    ];

    protected static ?array $cache = null;

    public function allMap(bool $forceReload = false): array
    {
        if ($forceReload || self::$cache === null) {
            self::$cache = SiteSetting::query()
                ->get()
                ->mapWithKeys(fn (SiteSetting $setting) => [$setting->key => $this->decodeValue($setting)])
                ->all();
        }

        return self::$cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->allMap(), $key, $default);
    }

    public function set(string $key, string $type, mixed $value, ?string $description = null): SiteSetting
    {
        $setting = SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'type' => $type,
                'value' => $this->encodeValue($type, $value),
                'description' => $description,
            ]
        );

        self::$cache = null;

        return $setting;
    }

    public function mediaStorageMode(): string
    {
        $mode = (string) $this->get(self::KEY_MEDIA_STORAGE_MODE, self::STORAGE_MODE_SERVER);

        if (!in_array($mode, [
            self::STORAGE_MODE_SERVER,
            self::STORAGE_MODE_CLOUD,
            self::STORAGE_MODE_USER_CHOICE,
        ], true)) {
            return self::STORAGE_MODE_SERVER;
        }

        return $mode;
    }

    public function serverDisk(): string
    {
        $disk = (string) $this->get(self::KEY_SERVER_MEDIA_DISK, 'public');

        return $this->isValidDisk($disk) ? $disk : 'public';
    }

    public function cloudDisk(): string
    {
        $disk = (string) $this->get(self::KEY_CLOUD_MEDIA_DISK, 's3');

        return $this->isValidDisk($disk) ? $disk : 'public';
    }

    public function userChoiceEnabled(): bool
    {
        return $this->mediaStorageMode() === self::STORAGE_MODE_USER_CHOICE;
    }

    public function resolveMediaDiskForUser(User $user): string
    {
        $mode = $this->mediaStorageMode();
        $fallbackDisk = 'public';

        if ($mode === self::STORAGE_MODE_SERVER) {
            return $this->resolvePublicMediaDisk($this->serverDisk(), $fallbackDisk);
        }

        if ($mode === self::STORAGE_MODE_CLOUD) {
            return $this->resolvePublicMediaDisk($this->cloudDisk(), $fallbackDisk);
        }

        $preference = (string) ($user->media_storage_preference ?: self::USER_STORAGE_SERVER);
        $disk = $preference === self::USER_STORAGE_CLOUD
            ? $this->cloudDisk()
            : $this->serverDisk();

        return $this->resolvePublicMediaDisk($disk, $fallbackDisk);
    }

    public function settingsForClient(User $user): array
    {
        return [
            'media_storage_mode' => $this->mediaStorageMode(),
            'allow_user_storage_choice' => $this->userChoiceEnabled(),
            'server_media_disk' => $this->serverDisk(),
            'cloud_media_disk' => $this->cloudDisk(),
            'user_media_storage_preference' => $user->media_storage_preference ?: self::USER_STORAGE_SERVER,
            'storage_mode_options' => [
                ['value' => self::STORAGE_MODE_SERVER, 'label' => 'Сервер сайта'],
                ['value' => self::STORAGE_MODE_CLOUD, 'label' => 'Облачное хранилище'],
                ['value' => self::STORAGE_MODE_USER_CHOICE, 'label' => 'Выбор пользователя'],
            ],
            'user_storage_options' => [
                ['value' => self::USER_STORAGE_SERVER, 'label' => 'Сервер сайта'],
                ['value' => self::USER_STORAGE_CLOUD, 'label' => 'Облако'],
            ],
            'available_disks' => array_values(array_keys(config('filesystems.disks', []))),
        ];
    }

    public function defaultHomePageContent(?string $locale = null): array
    {
        $normalizedLocale = $this->normalizeHomeContentLocale($locale);
        $locales = $this->defaultHomePageContentLocales();

        return $this->composeHomePageContentResponse($locales, $normalizedLocale);
    }

    public function defaultHomePageContentForLocale(string $locale): array
    {
        $normalizedLocale = $this->normalizeHomeContentLocale($locale);

        return match ($normalizedLocale) {
            self::HOME_CONTENT_LOCALE_EN => [
                'badge' => 'Social Network SPA',
                'hero_title' => 'Modern platform with posts, chats, media carousel, and flexible storage settings.',
                'hero_note' => 'Publish content, communicate, promote top posts, and manage visibility of your media. Administrator controls site settings and photo/video storage policy.',
                'feature_items' => [
                    'Public and private posts with flexible feed/carousel visibility.',
                    'Private and global chats with realtime delivery.',
                    'Admin panel with complete platform settings control.',
                ],
                'feedback_title' => 'Feedback for administration',
                'feedback_subtitle' => 'Send us a suggestion, complaint, or question. The message goes directly to the admin panel.',
            ],
            default => [
                'badge' => 'Социальная сеть SPA',
                'hero_title' => 'Современная платформа с постами, чатами, каруселью медиа и гибкими настройками хранения.',
                'hero_note' => 'Публикуйте контент, общайтесь, продвигайте лучшие посты и управляйте видимостью своих материалов. Администратор контролирует настройки сайта и политику хранения фото/видео.',
                'feature_items' => [
                    'Публичные и приватные посты с гибким показом в ленте/карусели.',
                    'Личные и общие чаты с realtime-доставкой.',
                    'Админ-панель с полным управлением настройками платформы.',
                ],
                'feedback_title' => 'Обратная связь для администрации',
                'feedback_subtitle' => 'Напишите нам предложение, жалобу или вопрос. Сообщение сразу попадёт в админ-панель.',
            ],
        };
    }

    public function defaultHomePageContentLocales(): array
    {
        $result = [];

        foreach (self::HOME_CONTENT_LOCALES as $locale) {
            $result[$locale] = $this->defaultHomePageContentForLocale($locale);
        }

        return $result;
    }

    public function homePageContent(?string $locale = null): array
    {
        $normalizedLocale = $this->normalizeHomeContentLocale($locale);
        $locales = $this->homePageContentLocales();

        return $this->composeHomePageContentResponse($locales, $normalizedLocale);
    }

    public function homePageContentLocales(): array
    {
        $raw = $this->get(self::KEY_HOME_PAGE_CONTENT);

        return $this->extractHomePageContentLocales($raw);
    }

    public function setHomePageContent(array $content, ?string $locale = null): array
    {
        $normalizedLocale = $this->normalizeHomeContentLocale($locale);
        $locales = $this->homePageContentLocales();

        if (is_array($content['locales'] ?? null)) {
            foreach (self::HOME_CONTENT_LOCALES as $localeCode) {
                if (!is_array($content['locales'][$localeCode] ?? null)) {
                    continue;
                }

                $locales[$localeCode] = $this->normalizeHomePageLocalePayload(
                    $content['locales'][$localeCode],
                    $this->defaultHomePageContentForLocale($localeCode)
                );
            }
        } else {
            $locales[$normalizedLocale] = $this->normalizeHomePageLocalePayload(
                $content,
                $this->defaultHomePageContentForLocale($normalizedLocale)
            );
        }

        $this->set(
            self::KEY_HOME_PAGE_CONTENT,
            SiteSetting::TYPE_JSON,
            ['locales' => $locales],
            'Editable content for home page hero and feedback blocks'
        );

        return $this->homePageContent($normalizedLocale);
    }

    public function resetHomePageContent(?string $locale = null): array
    {
        SiteSetting::query()
            ->where('key', self::KEY_HOME_PAGE_CONTENT)
            ->delete();

        self::$cache = null;

        return $this->defaultHomePageContent($locale);
    }

    protected function normalizeHomeContentLocale(?string $locale): string
    {
        $normalized = strtolower(trim((string) $locale));

        return in_array($normalized, self::HOME_CONTENT_LOCALES, true)
            ? $normalized
            : self::HOME_CONTENT_DEFAULT_LOCALE;
    }

    protected function extractHomePageContentLocales(mixed $raw): array
    {
        $defaults = $this->defaultHomePageContentLocales();
        $legacyPayload = is_array($raw) ? $raw : [];
        $hasLocalizedPayload = is_array($legacyPayload['locales'] ?? null);
        $localizedPayload = $hasLocalizedPayload ? $legacyPayload['locales'] : [];
        $result = [];

        foreach (self::HOME_CONTENT_LOCALES as $locale) {
            $candidatePayload = is_array($localizedPayload[$locale] ?? null)
                ? $localizedPayload[$locale]
                : [];

            if (!$hasLocalizedPayload && $locale === self::HOME_CONTENT_LOCALE_RU) {
                // Backward compatibility: old payload used a flat single-locale structure.
                $candidatePayload = $legacyPayload;
            }

            $result[$locale] = $this->normalizeHomePageLocalePayload($candidatePayload, $defaults[$locale]);
        }

        return $result;
    }

    protected function composeHomePageContentResponse(array $locales, string $locale): array
    {
        $normalizedLocale = $this->normalizeHomeContentLocale($locale);
        $activeContent = $locales[$normalizedLocale] ?? $this->defaultHomePageContentForLocale($normalizedLocale);

        return array_merge(
            ['locale' => $normalizedLocale],
            $activeContent,
            ['locales' => $locales]
        );
    }

    protected function normalizeHomePageSingleLine(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return $normalized === null ? '' : $normalized;
    }

    protected function normalizeHomePageMultiLine(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $value));
    }

    protected function normalizeHomePageLocalePayload(array $raw, array $fallback): array
    {
        $featureItems = collect(is_array($raw['feature_items'] ?? null) ? $raw['feature_items'] : [])
            ->map(fn ($item) => $this->normalizeHomePageSingleLine($item))
            ->filter(fn ($item) => $item !== '')
            ->take(8)
            ->values()
            ->all();

        if ($featureItems === []) {
            $featureItems = $fallback['feature_items'];
        }

        return [
            'badge' => $this->normalizeHomePageSingleLine($raw['badge'] ?? '') ?: $fallback['badge'],
            'hero_title' => $this->normalizeHomePageSingleLine($raw['hero_title'] ?? '') ?: $fallback['hero_title'],
            'hero_note' => $this->normalizeHomePageMultiLine($raw['hero_note'] ?? '') ?: $fallback['hero_note'],
            'feature_items' => $featureItems,
            'feedback_title' => $this->normalizeHomePageSingleLine($raw['feedback_title'] ?? '') ?: $fallback['feedback_title'],
            'feedback_subtitle' => $this->normalizeHomePageMultiLine($raw['feedback_subtitle'] ?? '') ?: $fallback['feedback_subtitle'],
        ];
    }

    public function decodeValue(SiteSetting $setting): mixed
    {
        $value = $setting->value;

        if ($value === null) {
            return null;
        }

        return match ($setting->type) {
            SiteSetting::TYPE_INTEGER => (int) $value,
            SiteSetting::TYPE_FLOAT => (float) $value,
            SiteSetting::TYPE_BOOLEAN => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
            SiteSetting::TYPE_JSON => json_decode((string) $value, true),
            default => (string) $value,
        };
    }

    public function encodeValue(string $type, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            SiteSetting::TYPE_INTEGER => (string) ((int) $value),
            SiteSetting::TYPE_FLOAT => (string) ((float) $value),
            SiteSetting::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0',
            SiteSetting::TYPE_JSON => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    protected function isValidDisk(string $disk): bool
    {
        return is_array(config('filesystems.disks.' . $disk));
    }

    protected function resolvePublicMediaDisk(string $disk, string $fallbackDisk = 'public'): string
    {
        if ($this->isPublicMediaDisk($disk)) {
            return $disk;
        }

        return $this->isPublicMediaDisk($fallbackDisk) ? $fallbackDisk : 'public';
    }

    protected function isPublicMediaDisk(string $disk): bool
    {
        $config = config('filesystems.disks.' . $disk);
        if (!is_array($config)) {
            return false;
        }

        $driver = (string) ($config['driver'] ?? '');

        if ($driver === 's3') {
            return true;
        }

        $url = trim((string) ($config['url'] ?? ''));
        if ($url !== '') {
            return true;
        }

        return (string) ($config['visibility'] ?? '') === 'public';
    }
}
