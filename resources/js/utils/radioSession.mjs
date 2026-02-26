export const SITE_SESSION_STARTED_AT_STORAGE_KEY = 'social:site-session-started-at'

/**
 * Normalize incoming "now" value used by deterministic helpers.
 * Falls back to real Date.now() when caller passes invalid value.
 */
function resolveNow(nowValue = Date.now()) {
    const normalized = Number(nowValue)
    if (!Number.isFinite(normalized) || normalized <= 0) {
        return Date.now()
    }

    return Math.floor(normalized)
}

/**
 * Validate that timestamp is finite, positive and not in the future.
 *
 * @param {number|string} value
 * @param {{ now?: number }} [options]
 * @returns {number} Valid millisecond timestamp or 0.
 */
export function normalizePastTimestamp(value, options = {}) {
    const now = resolveNow(options?.now)
    const parsed = Number(value)

    if (!Number.isFinite(parsed) || parsed <= 0 || parsed > now) {
        return 0
    }

    return Math.floor(parsed)
}

/**
 * Resolve site session start timestamp from sessionStorage.
 * Writes current timestamp when key is missing/invalid.
 *
 * @param {{ now?: number, key?: string, storage?: Storage | null }} [options]
 * @returns {number}
 */
export function resolveSiteSessionStartedAt(options = {}) {
    const now = resolveNow(options?.now)
    const key = String(options?.key || SITE_SESSION_STARTED_AT_STORAGE_KEY)
    const storage = options?.storage || null

    if (!storage || typeof storage.getItem !== 'function') {
        return now
    }

    try {
        const existing = normalizePastTimestamp(storage.getItem(key), {now})
        if (existing > 0) {
            return existing
        }

        if (typeof storage.setItem === 'function') {
            storage.setItem(key, String(now))
        }
    } catch (_error) {
        return now
    }

    return now
}

/**
 * Keep restored playback session only when station context exists.
 * Prevents stale cross-station timestamps from being reused.
 *
 * @param {number|string} value
 * @param {{ now?: number, hasCurrentStation?: boolean }} [options]
 * @returns {number}
 */
export function resolvePersistedPlaybackSessionStartedAt(value, options = {}) {
    const hasCurrentStation = Boolean(options?.hasCurrentStation)
    if (!hasCurrentStation) {
        return 0
    }

    return normalizePastTimestamp(value, {
        now: options?.now,
    })
}

/**
 * Derive station session counters from external playback snapshot.
 *
 * Result contract:
 * - accumulatedMs: already counted elapsed time;
 * - startedAt: active segment start timestamp when playback is currently running.
 *
 * @param {{
 *   hasCurrentStation?: boolean,
 *   now?: number,
 *   isPlaying?: boolean,
 *   currentTime?: number,
 *   sessionStartedAt?: number
 * }} [options]
 * @returns {{ accumulatedMs: number, startedAt: number }}
 */
export function resolveStationSessionStateFromSnapshot(options = {}) {
    const hasCurrentStation = Boolean(options?.hasCurrentStation)
    const now = resolveNow(options?.now)
    const isPlaying = Boolean(options?.isPlaying)
    const currentTime = Number(options?.currentTime || 0)
    const sessionStartedAt = normalizePastTimestamp(options?.sessionStartedAt, {now})

    if (!hasCurrentStation) {
        return {
            accumulatedMs: 0,
            startedAt: 0,
        }
    }

    if (sessionStartedAt > 0) {
        if (isPlaying) {
            return {
                accumulatedMs: 0,
                startedAt: sessionStartedAt,
            }
        }

        return {
            accumulatedMs: Math.max(0, now - sessionStartedAt),
            startedAt: 0,
        }
    }

    if (Number.isFinite(currentTime) && currentTime > 0) {
        return {
            accumulatedMs: Math.floor(currentTime * 1000),
            startedAt: isPlaying ? now : 0,
        }
    }

    return {
        accumulatedMs: 0,
        startedAt: isPlaying ? now : 0,
    }
}

/**
 * Format seconds as mm:ss or hh:mm:ss (when hours are present).
 *
 * @param {number|string} value
 * @returns {string}
 */
export function formatPlaybackTime(value) {
    const totalSeconds = Number(value)
    if (!Number.isFinite(totalSeconds) || totalSeconds < 0) {
        return '00:00'
    }

    const rounded = Math.floor(totalSeconds)
    const hours = Math.floor(rounded / 3600)
    const minutes = Math.floor((rounded % 3600) / 60)
    const seconds = rounded % 60

    if (hours > 0) {
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
    }

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

/**
 * Helper for consistent mobile-breakpoint checks in radio modules.
 *
 * @param {number|string} viewportWidth
 * @param {number} [breakpoint=760]
 * @returns {boolean}
 */
export function isMobileViewport(viewportWidth, breakpoint = 760) {
    const width = Number(viewportWidth)
    if (!Number.isFinite(width) || width <= 0) {
        return false
    }

    return width <= Number(breakpoint || 760)
}
