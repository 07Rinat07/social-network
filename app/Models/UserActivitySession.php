<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivitySession extends Model
{
    protected $fillable = [
        'user_id',
        'feature',
        'session_id',
        'total_seconds',
        'heartbeats_count',
        'started_at',
        'last_heartbeat_at',
        'ended_at',
        'is_active',
    ];

    protected $casts = [
        'total_seconds' => 'integer',
        'heartbeats_count' => 'integer',
        'started_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
