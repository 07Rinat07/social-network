<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IptvSavedChannel extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'stream_url',
        'stream_url_hash',
        'group_title',
        'logo_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
