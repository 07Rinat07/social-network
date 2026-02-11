<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Rules\NoUnsafeMarkup;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteSettingController extends Controller
{
    public function __construct(private readonly SiteSettingService $siteSettingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $settings = SiteSetting::query()
            ->latest()
            ->paginate(max(1, min((int) $request->integer('per_page', 50), 200)));

        $settings->getCollection()->transform(function (SiteSetting $setting) {
            return $this->payload($setting);
        });

        return response()->json($settings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120', 'alpha_dash:ascii', Rule::unique('site_settings', 'key')],
            'type' => ['required', Rule::in(SiteSetting::AVAILABLE_TYPES)],
            'value' => ['nullable'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $setting = $this->siteSettingService->set(
            $validated['key'],
            $validated['type'],
            $validated['value'] ?? null,
            $validated['description'] ?? null
        );

        return response()->json([
            'message' => 'Setting created successfully.',
            'data' => $this->payload($setting->fresh()),
        ], 201);
    }

    public function update(SiteSetting $siteSetting, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['sometimes', 'required', 'string', 'max:120', 'alpha_dash:ascii', Rule::unique('site_settings', 'key')->ignore($siteSetting->id)],
            'type' => ['sometimes', 'required', Rule::in(SiteSetting::AVAILABLE_TYPES)],
            'value' => ['nullable'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $key = $validated['key'] ?? $siteSetting->key;
        $type = $validated['type'] ?? $siteSetting->type;
        $value = array_key_exists('value', $validated)
            ? $validated['value']
            : $this->siteSettingService->decodeValue($siteSetting);
        $description = array_key_exists('description', $validated)
            ? $validated['description']
            : $siteSetting->description;

        $updated = $this->siteSettingService->set($key, $type, $value, $description);

        return response()->json([
            'message' => 'Setting updated successfully.',
            'data' => $this->payload($updated->fresh()),
        ]);
    }

    public function destroy(SiteSetting $siteSetting): JsonResponse
    {
        $siteSetting->delete();
        $this->siteSettingService->allMap(true);

        return response()->json([
            'message' => 'Setting deleted successfully.',
        ]);
    }

    public function updateStorage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_storage_mode' => ['required', Rule::in([
                SiteSettingService::STORAGE_MODE_SERVER,
                SiteSettingService::STORAGE_MODE_CLOUD,
                SiteSettingService::STORAGE_MODE_USER_CHOICE,
            ])],
            'server_media_disk' => ['required', 'string', 'max:50'],
            'cloud_media_disk' => ['required', 'string', 'max:50'],
        ]);

        $this->siteSettingService->set(
            SiteSettingService::KEY_MEDIA_STORAGE_MODE,
            SiteSetting::TYPE_STRING,
            $validated['media_storage_mode'],
            'Media storage mode'
        );

        $this->siteSettingService->set(
            SiteSettingService::KEY_SERVER_MEDIA_DISK,
            SiteSetting::TYPE_STRING,
            $validated['server_media_disk'],
            'Disk for local media storage'
        );

        $this->siteSettingService->set(
            SiteSettingService::KEY_CLOUD_MEDIA_DISK,
            SiteSetting::TYPE_STRING,
            $validated['cloud_media_disk'],
            'Disk for cloud media storage'
        );

        return response()->json([
            'message' => 'Storage settings updated successfully.',
            'data' => $this->siteSettingService->settingsForClient($request->user()),
        ]);
    }

    public function homeContent(): JsonResponse
    {
        return response()->json([
            'data' => $this->siteSettingService->homePageContent(),
        ]);
    }

    public function updateHomeContent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'badge' => ['required', 'string', 'max:80', new NoUnsafeMarkup(false)],
            'hero_title' => ['required', 'string', 'max:300', new NoUnsafeMarkup(false)],
            'hero_note' => ['required', 'string', 'max:3000', new NoUnsafeMarkup()],
            'feature_items' => ['required', 'array', 'min:1', 'max:8'],
            'feature_items.*' => ['required', 'string', 'max:220', new NoUnsafeMarkup(false)],
            'feedback_title' => ['required', 'string', 'max:180', new NoUnsafeMarkup(false)],
            'feedback_subtitle' => ['required', 'string', 'max:500', new NoUnsafeMarkup()],
        ]);

        $normalizeSingleLine = function (string $value): string {
            $normalized = preg_replace('/\s+/u', ' ', trim($value));

            return $normalized === null ? '' : $normalized;
        };

        $normalizeMultiLine = fn (string $value): string => trim(str_replace(["\r\n", "\r"], "\n", $value));

        $payload = [
            'badge' => $normalizeSingleLine($validated['badge']),
            'hero_title' => $normalizeSingleLine($validated['hero_title']),
            'hero_note' => $normalizeMultiLine($validated['hero_note']),
            'feature_items' => array_values(array_filter(array_map($normalizeSingleLine, $validated['feature_items']), fn ($item) => $item !== '')),
            'feedback_title' => $normalizeSingleLine($validated['feedback_title']),
            'feedback_subtitle' => $normalizeMultiLine($validated['feedback_subtitle']),
        ];

        if ($payload['feature_items'] === []) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    'feature_items' => ['At least one feature item is required.'],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Home page content updated successfully.',
            'data' => $this->siteSettingService->setHomePageContent($payload),
        ]);
    }

    public function resetHomeContent(): JsonResponse
    {
        return response()->json([
            'message' => 'Home page content reset to defaults.',
            'data' => $this->siteSettingService->resetHomePageContent(),
        ]);
    }

    public function publicConfig(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->siteSettingService->settingsForClient($request->user()),
        ]);
    }

    public function updateUserStoragePreference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_storage_preference' => ['required', Rule::in([
                SiteSettingService::USER_STORAGE_SERVER,
                SiteSettingService::USER_STORAGE_CLOUD,
            ])],
        ]);

        if (!$this->siteSettingService->userChoiceEnabled()) {
            return response()->json([
                'message' => 'User storage preference is disabled by admin settings.',
            ], 422);
        }

        $request->user()->update([
            'media_storage_preference' => $validated['media_storage_preference'],
        ]);

        return response()->json([
            'message' => 'Storage preference updated successfully.',
            'data' => $this->siteSettingService->settingsForClient($request->user()->fresh()),
        ]);
    }

    protected function payload(SiteSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'key' => $setting->key,
            'type' => $setting->type,
            'value' => $this->siteSettingService->decodeValue($setting),
            'description' => $setting->description,
            'created_at' => $setting->created_at?->toIso8601String(),
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }
}
