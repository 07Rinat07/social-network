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

    public function defaultHomePageContent(): array
    {
        return [
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
        ];
    }

    public function homePageContent(): array
    {
        $defaults = $this->defaultHomePageContent();
        $raw = $this->get(self::KEY_HOME_PAGE_CONTENT);

        if (!is_array($raw)) {
            return $defaults;
        }

        $sanitizeSingleLine = function (mixed $value): string {
            if (!is_string($value)) {
                return '';
            }

            $normalized = preg_replace('/\s+/u', ' ', trim($value));

            return $normalized === null ? '' : $normalized;
        };

        $sanitizeMultiLine = function (mixed $value): string {
            if (!is_string($value)) {
                return '';
            }

            return trim(str_replace(["\r\n", "\r"], "\n", $value));
        };

        $featureItems = collect(is_array($raw['feature_items'] ?? null) ? $raw['feature_items'] : [])
            ->map(fn ($item) => $sanitizeSingleLine($item))
            ->filter(fn ($item) => $item !== '')
            ->take(8)
            ->values()
            ->all();

        if ($featureItems === []) {
            $featureItems = $defaults['feature_items'];
        }

        return [
            'badge' => $sanitizeSingleLine($raw['badge'] ?? '') ?: $defaults['badge'],
            'hero_title' => $sanitizeSingleLine($raw['hero_title'] ?? '') ?: $defaults['hero_title'],
            'hero_note' => $sanitizeMultiLine($raw['hero_note'] ?? '') ?: $defaults['hero_note'],
            'feature_items' => $featureItems,
            'feedback_title' => $sanitizeSingleLine($raw['feedback_title'] ?? '') ?: $defaults['feedback_title'],
            'feedback_subtitle' => $sanitizeMultiLine($raw['feedback_subtitle'] ?? '') ?: $defaults['feedback_subtitle'],
        ];
    }

    public function setHomePageContent(array $content): array
    {
        $this->set(
            self::KEY_HOME_PAGE_CONTENT,
            SiteSetting::TYPE_JSON,
            $content,
            'Editable content for home page hero and feedback blocks'
        );

        return $this->homePageContent();
    }

    public function resetHomePageContent(): array
    {
        SiteSetting::query()
            ->where('key', self::KEY_HOME_PAGE_CONTENT)
            ->delete();

        self::$cache = null;

        return $this->defaultHomePageContent();
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
