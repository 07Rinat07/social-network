<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityDailyStat extends Model
{
    protected $fillable = [
        'user_id',
        'feature',
        'activity_date',
        'seconds_total',
        'heartbeats_count',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'seconds_total' => 'integer',
        'heartbeats_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
