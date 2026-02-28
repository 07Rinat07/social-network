<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    public const FEATURE_MEDIA = 'media';
    public const FEATURE_RADIO = 'radio';
    public const FEATURE_IPTV = 'iptv';
    public const FEATURE_SOCIAL = 'social';
    public const FEATURE_CHATS = 'chats';

    public const EVENT_MEDIA_UPLOAD_FAILED = 'media_upload_failed';
    public const EVENT_VIDEO_SESSION = 'video_session';
    public const EVENT_VIDEO_THEATER_OPEN = 'video_theater_open';
    public const EVENT_VIDEO_FULLSCREEN_ENTER = 'video_fullscreen_enter';
    public const EVENT_RADIO_PLAY_STARTED = 'radio_play_started';
    public const EVENT_RADIO_PLAY_FAILED = 'radio_play_failed';
    public const EVENT_IPTV_DIRECT_STARTED = 'iptv_direct_started';
    public const EVENT_IPTV_DIRECT_FAILED = 'iptv_direct_failed';
    public const EVENT_IPTV_PROXY_STARTED = 'iptv_proxy_started';
    public const EVENT_IPTV_PROXY_FAILED = 'iptv_proxy_failed';
    public const EVENT_IPTV_RELAY_STARTED = 'iptv_relay_started';
    public const EVENT_IPTV_RELAY_FAILED = 'iptv_relay_failed';
    public const EVENT_IPTV_FFMPEG_STARTED = 'iptv_ffmpeg_started';
    public const EVENT_IPTV_FFMPEG_FAILED = 'iptv_ffmpeg_failed';

    public const ALLOWED_FEATURES = [
        self::FEATURE_MEDIA,
        self::FEATURE_RADIO,
        self::FEATURE_IPTV,
        self::FEATURE_SOCIAL,
        self::FEATURE_CHATS,
    ];

    public const ALLOWED_EVENTS = [
        self::EVENT_MEDIA_UPLOAD_FAILED,
        self::EVENT_VIDEO_SESSION,
        self::EVENT_VIDEO_THEATER_OPEN,
        self::EVENT_VIDEO_FULLSCREEN_ENTER,
        self::EVENT_RADIO_PLAY_STARTED,
        self::EVENT_RADIO_PLAY_FAILED,
        self::EVENT_IPTV_DIRECT_STARTED,
        self::EVENT_IPTV_DIRECT_FAILED,
        self::EVENT_IPTV_PROXY_STARTED,
        self::EVENT_IPTV_PROXY_FAILED,
        self::EVENT_IPTV_RELAY_STARTED,
        self::EVENT_IPTV_RELAY_FAILED,
        self::EVENT_IPTV_FFMPEG_STARTED,
        self::EVENT_IPTV_FFMPEG_FAILED,
    ];

    protected $fillable = [
        'user_id',
        'feature',
        'event_name',
        'entity_type',
        'entity_id',
        'entity_key',
        'session_id',
        'duration_seconds',
        'metric_value',
        'context',
        'created_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'user_id' => 'integer',
        'entity_id' => 'integer',
        'duration_seconds' => 'integer',
        'metric_value' => 'float',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
