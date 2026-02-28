<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostImage\StoreRequest;
use App\Http\Resources\PostImage\PostImageResource;
use App\Models\PostImage;
use App\Services\SiteSettingService;
use App\Services\UploadedVideoTranscodeService;
use Throwable;

class PostImageController extends Controller
{
    public function __construct(
        private readonly SiteSettingService $siteSettingService,
        private readonly UploadedVideoTranscodeService $uploadedVideoTranscodeService
    )
    {
    }

    public function store(StoreRequest $request): PostImageResource
    {
        $file = $request->file('file');
        $clientExtension = strtolower(trim((string) $file->getClientOriginalExtension()));
        $mimeType = $this->normalizeUploadedMimeType(
            $clientExtension,
            (string) ($file->getMimeType() ?: ''),
            (string) ($file->getClientMimeType() ?: '')
        );
        $isVideo = $this->isVideoUpload($clientExtension, $mimeType);
        $folder = $isVideo ? 'media/videos' : 'media/images';
        $disk = $this->siteSettingService->resolveMediaDiskForUser($request->user());
        $converted = null;

        if ($isVideo) {
            $converted = $this->uploadedVideoTranscodeService->maybeConvertToBrowserFriendlyMp4($file);
        }

        if ($converted !== null) {
            try {
                $stored = $this->uploadedVideoTranscodeService->storeTemporaryFileWithFallback(
                    $converted['path'],
                    $folder,
                    $disk
                );

                $image = PostImage::query()->create([
                    'path' => $stored['path'],
                    'storage_disk' => $stored['disk'],
                    'type' => PostImage::TYPE_VIDEO,
                    'mime_type' => (string) ($converted['mime_type'] ?? 'video/mp4'),
                    'size' => (int) ($converted['size'] ?? 0),
                    'original_name' => (string) ($converted['original_name'] ?? $file->getClientOriginalName()),
                    'user_id' => $request->user()->id,
                ]);

                return new PostImageResource($image);
            } finally {
                if (is_file($converted['path'])) {
                    @unlink($converted['path']);
                }
            }
        }

        try {
            $path = $file->store($folder, $disk);
        } catch (Throwable) {
            $disk = 'public';
            $path = $file->store($folder, $disk);
        }

        $image = PostImage::query()->create([
            'path' => $path,
            'storage_disk' => $disk,
            'type' => $isVideo ? PostImage::TYPE_VIDEO : PostImage::TYPE_IMAGE,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'user_id' => $request->user()->id,
        ]);

        return new PostImageResource($image);
    }

    protected function isVideoUpload(string $extension, string $mimeType): bool
    {
        $normalizedExtension = strtolower(trim($extension));
        $normalizedMimeType = strtolower(trim($mimeType));

        return str_starts_with($normalizedMimeType, 'video/')
            || in_array($normalizedExtension, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'], true);
    }

    protected function normalizeUploadedMimeType(string $extension, string $detectedMimeType, string $clientMimeType): string
    {
        $normalizedDetectedMimeType = strtolower(trim($detectedMimeType));
        $normalizedClientMimeType = strtolower(trim($clientMimeType));
        $normalizedExtension = strtolower(trim($extension));

        if ($normalizedDetectedMimeType !== '' && $normalizedDetectedMimeType !== 'application/octet-stream') {
            return $normalizedDetectedMimeType;
        }

        if ($normalizedClientMimeType !== '' && $normalizedClientMimeType !== 'application/octet-stream') {
            return $normalizedClientMimeType;
        }

        return match ($normalizedExtension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'm4v' => 'video/x-m4v',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            default => $normalizedDetectedMimeType !== '' ? $normalizedDetectedMimeType : $normalizedClientMimeType,
        };
    }
}
