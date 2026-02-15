<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IptvSavedPlaylist extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'source_url',
        'source_url_hash',
        'channels_count',
    ];

    protected $casts = [
        'channels_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
