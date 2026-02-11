<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Post extends Model
{
    public const API_RELATIONS = [
        'media',
        'user',
        'repostedPost.media',
        'repostedPost.user',
    ];

    public const API_COUNTS = [
        'likedUsers',
        'comments',
        'repostedByPosts',
    ];

    protected $table = 'posts';

    protected $fillable = [
        'title',
        'content',
        'user_id',
        'reposted_id',
        'is_public',
        'show_in_feed',
        'show_in_carousel',
        'views_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'show_in_feed' => 'boolean',
        'show_in_carousel' => 'boolean',
        'views_count' => 'integer',
    ];

    public function image(): HasOne
    {
        return $this->hasOne(PostImage::class, 'post_id', 'id')
            ->where('type', 'image')
            ->oldestOfMany();
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostImage::class, 'post_id', 'id')
            ->orderBy('id');
    }

    public function likedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'liked_posts', 'post_id', 'user_id');
    }

    public function getDateAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function repostedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'reposted_id', 'id');
    }

    public function repostedByPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'reposted_id', 'id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
