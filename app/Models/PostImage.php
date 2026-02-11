<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PostImage extends Model
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';

    protected $table = 'post_images';

    protected $fillable = [
        'path',
        'storage_disk',
        'type',
        'mime_type',
        'size',
        'original_name',
        'post_id',
        'user_id',
        'status',
    ];

    protected $casts = [
        'size' => 'integer',
        'status' => 'boolean',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    public function getUrlAttribute(): string
    {
        $disk = (string) ($this->storage_disk ?: 'public');

        if ($this->shouldServeThroughApi($disk)) {
            return route('media.post-images.show', ['postImage' => $this->id]);
        }

        try {
            return Storage::disk($disk)->url($this->path);
        } catch (Throwable) {
            return route('media.post-images.show', ['postImage' => $this->id]);
        }
    }

    public static function clearStorageForUser(int $userId): void
    {
        $images = PostImage::query()
            ->where('user_id', $userId)
            ->whereNull('post_id')
            ->get();

        foreach ($images as $image) {
            Storage::disk($image->storage_disk ?: 'public')->delete($image->path);
            $image->delete();
        }
    }

    protected function shouldServeThroughApi(string $disk): bool
    {
        $config = config('filesystems.disks.' . $disk);
        if (!is_array($config)) {
            return true;
        }

        $driver = (string) ($config['driver'] ?? '');
        return $driver !== 's3';
    }
}
