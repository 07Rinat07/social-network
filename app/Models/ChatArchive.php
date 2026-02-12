<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatArchive extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'scope',
        'title',
        'payload',
        'messages_count',
        'restored_at',
        'restored_conversation_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'messages_count' => 'integer',
        'restored_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'id');
    }

    public function restoredConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'restored_conversation_id', 'id');
    }
}

