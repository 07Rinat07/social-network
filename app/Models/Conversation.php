<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    public const TYPE_GLOBAL = 'global';
    public const TYPE_DIRECT = 'direct';
    public const TYPE_ARCHIVE = 'archive';

    protected $fillable = [
        'type',
        'title',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants', 'conversation_id', 'user_id')
            ->withPivot(['last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id', 'id');
    }

    public function moodStatuses(): HasMany
    {
        return $this->hasMany(ConversationMoodStatus::class, 'conversation_id', 'id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class, 'conversation_id', 'id')->latestOfMany();
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $builder) use ($userId) {
            $builder->where('type', self::TYPE_GLOBAL)
                ->orWhereHas('participants', fn (Builder $query) => $query->where('users.id', $userId));
        });
    }

    public function isAccessibleBy(int $userId): bool
    {
        if ($this->type === self::TYPE_GLOBAL) {
            return true;
        }

        return $this->participants()->where('users.id', $userId)->exists();
    }
}
