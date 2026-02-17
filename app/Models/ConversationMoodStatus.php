<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMoodStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'text',
        'is_visible_to_all',
        'hidden_for_user_ids',
    ];

    protected $casts = [
        'is_visible_to_all' => 'boolean',
        'hidden_for_user_ids' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

