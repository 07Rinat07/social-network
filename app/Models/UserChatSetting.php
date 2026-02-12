<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChatSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'save_text_messages',
        'save_media_attachments',
        'save_file_attachments',
        'retention_days',
        'auto_archive_enabled',
    ];

    protected $casts = [
        'save_text_messages' => 'boolean',
        'save_media_attachments' => 'boolean',
        'save_file_attachments' => 'boolean',
        'auto_archive_enabled' => 'boolean',
        'retention_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

