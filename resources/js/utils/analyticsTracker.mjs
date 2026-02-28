import axios from 'axios'

export const ANALYTICS_FEATURES = Object.freeze({
    MEDIA: 'media',
    RADIO: 'radio',
    IPTV: 'iptv',
    SOCIAL: 'social',
    CHATS: 'chats',
})

export const ANALYTICS_EVENTS = Object.freeze({
    MEDIA_UPLOAD_FAILED: 'media_upload_failed',
    VIDEO_SESSION: 'video_session',
    VIDEO_THEATER_OPEN: 'video_theater_open',
    VIDEO_FULLSCREEN_ENTER: 'video_fullscreen_enter',
    RADIO_PLAY_STARTED: 'radio_play_started',
    RADIO_PLAY_FAILED: 'radio_play_failed',
    IPTV_DIRECT_STARTED: 'iptv_direct_started',
    IPTV_DIRECT_FAILED: 'iptv_direct_failed',
    IPTV_PROXY_STARTED: 'iptv_proxy_started',
    IPTV_PROXY_FAILED: 'iptv_proxy_failed',
    IPTV_RELAY_STARTED: 'iptv_relay_started',
    IPTV_RELAY_FAILED: 'iptv_relay_failed',
    IPTV_FFMPEG_STARTED: 'iptv_ffmpeg_started',
    IPTV_FFMPEG_FAILED: 'iptv_ffmpeg_failed',
})

export function createAnalyticsSessionId(prefix = 'analytics') {
    const safePrefix = String(prefix || '')
        .replace(/[^a-z0-9_-]/gi, '')
        .toLowerCase()
        .slice(0, 20) || 'analytics'

    const timestampPart = Date.now().toString(36)
    let randomPart = ''

    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        randomPart = crypto.randomUUID().replace(/-/g, '').slice(0, 24)
    } else {
        randomPart = `${Math.random().toString(36).slice(2, 14)}${Math.random().toString(36).slice(2, 10)}`
    }

    return `${safePrefix}:${timestampPart}:${randomPart}`.slice(0, 120)
}

export async function reportAnalyticsEvent(payload = {}) {
    const normalizedPayload = normalizeAnalyticsEventPayload(payload)
    if (!normalizedPayload) {
        return false
    }

    try {
        await axios.post('/api/analytics/events', normalizedPayload)
        return true
    } catch (_error) {
        return false
    }
}

export function normalizeAnalyticsEventPayload(payload = {}) {
    const feature = String(payload?.feature || '').trim().toLowerCase()
    const eventName = String(payload?.event_name || '').trim().toLowerCase()

    if (!Object.values(ANALYTICS_FEATURES).includes(feature) || !Object.values(ANALYTICS_EVENTS).includes(eventName)) {
        return null
    }

    const sessionId = normalizeAnalyticsString(payload?.session_id, 120)
    const entityType = normalizeAnalyticsString(payload?.entity_type, 80)
    const entityKey = normalizeAnalyticsString(payload?.entity_key, 191)
    const numericEntityId = Number(payload?.entity_id)
    const entityId = Number.isInteger(numericEntityId) && numericEntityId > 0 ? numericEntityId : null
    const numericDuration = Number(payload?.duration_seconds)
    const durationSeconds = Number.isFinite(numericDuration)
        ? Math.max(0, Math.min(86400, Math.round(numericDuration)))
        : 0
    const numericMetricValue = Number(payload?.metric_value)
    const metricValue = Number.isFinite(numericMetricValue)
        ? Math.max(0, Number(numericMetricValue.toFixed(2)))
        : null

    return {
        feature,
        event_name: eventName,
        entity_type: entityType || null,
        entity_id: entityId,
        entity_key: entityKey || null,
        session_id: sessionId || null,
        duration_seconds: durationSeconds,
        metric_value: metricValue,
        context: normalizeAnalyticsContext(payload?.context),
    }
}

function normalizeAnalyticsContext(source) {
    if (!source || typeof source !== 'object' || Array.isArray(source)) {
        return {}
    }

    const result = {}
    for (const [key, value] of Object.entries(source)) {
        const normalizedKey = normalizeAnalyticsString(key, 64)
        if (!normalizedKey) {
            continue
        }

        if (typeof value === 'boolean' || typeof value === 'number') {
            result[normalizedKey] = value
            continue
        }

        if (value === null || value === undefined) {
            continue
        }

        if (Array.isArray(value)) {
            const normalizedArray = value
                .map((item) => normalizeAnalyticsString(item, 120))
                .filter(Boolean)
                .slice(0, 10)

            if (normalizedArray.length > 0) {
                result[normalizedKey] = normalizedArray
            }
            continue
        }

        const normalizedValue = normalizeAnalyticsString(value, 255)
        if (normalizedValue) {
            result[normalizedKey] = normalizedValue
        }
    }

    return result
}

function normalizeAnalyticsString(value, limit = 255) {
    const normalized = String(value ?? '').trim()
    if (normalized === '') {
        return ''
    }

    return normalized.slice(0, limit)
}
