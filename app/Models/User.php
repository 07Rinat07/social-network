<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'is_admin',
        'media_storage_preference',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];

    protected $appends = [
        'display_name',
        'avatar_url',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }

    public function followings(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subscriber_followings', 'subscriber_id', 'following_id');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subscriber_followings', 'following_id', 'subscriber_id');
    }

    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'liked_posts', 'user_id', 'post_id');
    }

    public function feedbackMessages(): HasMany
    {
        return $this->hasMany(FeedbackMessage::class, 'user_id', 'id');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants', 'user_id', 'conversation_id')
            ->withTimestamps();
    }

    public function conversationMessages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'user_id', 'id');
    }

    public function conversationMessageReactions(): HasMany
    {
        return $this->hasMany(ConversationMessageReaction::class, 'user_id', 'id');
    }

    public function conversationMoodStatuses(): HasMany
    {
        return $this->hasMany(ConversationMoodStatus::class, 'user_id', 'id');
    }

    public function chatSetting(): HasOne
    {
        return $this->hasOne(UserChatSetting::class, 'user_id', 'id');
    }

    public function chatArchives(): HasMany
    {
        return $this->hasMany(ChatArchive::class, 'user_id', 'id');
    }

    public function blocksGiven(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id', 'id');
    }

    public function blocksReceived(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_user_id', 'id');
    }

    public function iptvSavedPlaylists(): HasMany
    {
        return $this->hasMany(IptvSavedPlaylist::class, 'user_id', 'id');
    }

    public function iptvSavedChannels(): HasMany
    {
        return $this->hasMany(IptvSavedChannel::class, 'user_id', 'id');
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function getDisplayNameAttribute(): string
    {
        $nickname = trim((string) ($this->attributes['nickname'] ?? ''));

        return $nickname !== '' ? $nickname : (string) ($this->attributes['name'] ?? '');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $path = (string) ($this->attributes['avatar_path'] ?? '');

        if ($path === '' || $path === '0') {
            return null;
        }

        $url = route('media.avatars.show', ['user' => $this->getKey()], false);
        $version = $this->updated_at?->getTimestamp();

        if (is_int($version) && $version > 0) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'v=' . $version;
        }

        return $url;
    }
}
