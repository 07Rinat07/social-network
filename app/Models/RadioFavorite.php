<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'station_uuid',
        'name',
        'stream_url',
        'homepage',
        'favicon',
        'country',
        'language',
        'tags',
        'codec',
        'bitrate',
        'votes',
    ];

    protected $casts = [
        'bitrate' => 'integer',
        'votes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
