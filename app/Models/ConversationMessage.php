<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ConversationMessageAttachment::class, 'conversation_message_id', 'id')
            ->orderBy('id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ConversationMessageReaction::class, 'conversation_message_id', 'id')
            ->orderBy('id');
    }

    public function getDateAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}
