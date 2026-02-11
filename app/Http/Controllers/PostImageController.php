<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostImage\StoreRequest;
use App\Http\Resources\PostImage\PostImageResource;
use App\Models\PostImage;
use App\Services\SiteSettingService;
use Throwable;

class PostImageController extends Controller
{
    public function __construct(private readonly SiteSettingService $siteSettingService)
    {
    }

    public function store(StoreRequest $request): PostImageResource
    {
        $file = $request->file('file');
        $mimeType = $file->getMimeType() ?: '';
        $isVideo = str_starts_with($mimeType, 'video/');
        $folder = $isVideo ? 'media/videos' : 'media/images';
        $disk = $this->siteSettingService->resolveMediaDiskForUser($request->user());

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
}
