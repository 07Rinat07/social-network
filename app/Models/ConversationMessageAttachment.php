<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ConversationMessageAttachment extends Model
{
    use HasFactory;

    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_GIF = 'gif';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_FILE = 'file';

    protected $fillable = [
        'conversation_message_id',
        'path',
        'storage_disk',
        'type',
        'mime_type',
        'size',
        'original_name',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id', 'id');
    }

    public function getUrlAttribute(): string
    {
        $disk = (string) ($this->storage_disk ?: 'public');

        if ($this->shouldServeThroughApi($disk)) {
            return route('media.chat-attachments.show', ['attachment' => $this->id]);
        }

        try {
            return Storage::disk($disk)->url($this->path);
        } catch (Throwable) {
            return route('media.chat-attachments.show', ['attachment' => $this->id]);
        }
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('media.chat-attachments.download', ['attachment' => $this->id]);
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
