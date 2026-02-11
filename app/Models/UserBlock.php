<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'blocker_id',
        'blocked_user_id',
        'expires_at',
        'reason',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id', 'id');
    }

    public function blockedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public static function isBlockedBetween(int $firstUserId, int $secondUserId): bool
    {
        return self::query()
            ->where(function (Builder $builder) use ($firstUserId, $secondUserId) {
                $builder->where(function (Builder $pairQuery) use ($firstUserId, $secondUserId) {
                    $pairQuery->where('blocker_id', $firstUserId)
                        ->where('blocked_user_id', $secondUserId);
                })->orWhere(function (Builder $pairQuery) use ($firstUserId, $secondUserId) {
                    $pairQuery->where('blocker_id', $secondUserId)
                        ->where('blocked_user_id', $firstUserId);
                });
            })
            ->active()
            ->exists();
    }

    public function isActive(): bool
    {
        return !$this->expires_at || $this->expires_at->isFuture();
    }
}
