<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriberFollowing extends Model
{
    protected $table = 'subscriber_followings';

    protected $fillable = [
        'subscriber_id',
        'following_id',
    ];
}
