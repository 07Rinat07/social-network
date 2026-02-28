export const IPTV_STATE_STORAGE_KEY = 'solid-social:iptv-player-state:v3'
export const IPTV_RECENT_LIMIT = 40

const IPTV_FAVORITES_LIMIT = 500
const IPTV_CHANNEL_SNAPSHOT_LIMIT = 250
const IPTV_CHANNEL_SNAPSHOT_MAX_BYTES = 384 * 1024

const VIEW_MODES = new Set(['all', 'favorites', 'recent'])
const SORT_MODES = new Set(['group', 'name'])
const FIT_MODES = new Set(['contain', 'cover', 'fill'])
const BUFFERING_MODES = new Set(['auto', 'fast', 'balanced', 'stable'])
const COMPAT_PROFILES = new Set(['fast', 'balanced', 'stable'])

function normalizeString(value, maxLength = 0) {
    const normalized = String(value || '').trim()
    if (maxLength > 0) {
        return normalized.slice(0, maxLength)
    }

    return normalized
}

function normalizeBoolean(value, fallback = false) {
    return typeof value === 'boolean' ? value : fallback
}

function normalizeVolume(value) {
    const parsed = Number(value)
    if (!Number.isFinite(parsed)) {
        return 1
    }

    return Math.min(1, Math.max(0, parsed))
}

function normalizeList(values, limit) {
    if (!Array.isArray(values)) {
        return []
    }

    return values
        .map((value) => normalizeString(value, 512))
        .filter((value) => value !== '')
        .slice(0, limit)
}

function isHttpLikeUrl(value) {
    return /^https?:\/\//i.test(normalizeString(value, 4096))
}

function normalizeChannelSnapshotItem(channel) {
    const url = normalizeString(channel?.url, 4096)
    if (!isHttpLikeUrl(url)) {
        return null
    }

    return {
        name: normalizeString(channel?.name, 160),
        url,
        group: normalizeString(channel?.group, 160),
        logo: normalizeString(channel?.logo, 4096),
    }
}

function utf8ByteLength(value) {
    const normalized = String(value || '')

    if (typeof TextEncoder !== 'undefined') {
        return new TextEncoder().encode(normalized).length
    }

    return Buffer.byteLength(normalized, 'utf8')
}

function limitChannelSnapshot(channels) {
    let snapshot = channels
        .slice(0, IPTV_CHANNEL_SNAPSHOT_LIMIT)
        .map((channel) => normalizeChannelSnapshotItem(channel))
        .filter(Boolean)

    while (snapshot.length > 0 && utf8ByteLength(JSON.stringify(snapshot)) > IPTV_CHANNEL_SNAPSHOT_MAX_BYTES) {
        snapshot = snapshot.slice(0, -1)
    }

    return snapshot
}

export function buildPersistedIptvState(state) {
    const rawChannels = Array.isArray(state?.channels)
        ? state.channels
        : (Array.isArray(state?.channelsSnapshot) ? state.channelsSnapshot : [])
    const channelsSnapshot = limitChannelSnapshot(rawChannels)

    return {
        viewMode: VIEW_MODES.has(state?.viewMode) ? state.viewMode : 'all',
        selectedGroup: normalizeString(state?.selectedGroup, 160) || 'all',
        sortMode: SORT_MODES.has(state?.sortMode) ? state.sortMode : 'group',
        secureOnly: normalizeBoolean(state?.secureOnly),
        preferHttpsUpgrade: normalizeBoolean(state?.preferHttpsUpgrade),
        fitMode: FIT_MODES.has(state?.fitMode) ? state.fitMode : 'contain',
        bufferingMode: BUFFERING_MODES.has(state?.bufferingMode) ? state.bufferingMode : 'stable',
        autoStability: normalizeBoolean(state?.autoStability, true),
        compatModeEnabled: normalizeBoolean(state?.compatModeEnabled),
        compatProfile: COMPAT_PROFILES.has(state?.compatProfile) ? state.compatProfile : 'stable',
        autoCompatOnCodecError: normalizeBoolean(state?.autoCompatOnCodecError),
        keyboardEnabled: normalizeBoolean(state?.keyboardEnabled, true),
        volumeLevel: normalizeVolume(state?.volumeLevel),
        muted: normalizeBoolean(state?.muted),
        activeSeedId: normalizeString(state?.activeSeedId, 160),
        playlistUrl: normalizeString(state?.playlistUrl, 4096),
        currentPlaylistUrl: normalizeString(state?.currentPlaylistUrl, 4096),
        sourceLabel: normalizeString(state?.sourceLabel, 200),
        searchQuery: normalizeString(state?.searchQuery, 200),
        currentChannelId: normalizeString(state?.currentChannelId, 160),
        favoriteChannelIds: normalizeList(state?.favoriteChannelIds, IPTV_FAVORITES_LIMIT),
        recentChannelIds: normalizeList(state?.recentChannelIds, IPTV_RECENT_LIMIT),
        channelsSnapshot,
    }
}

export function parsePersistedIptvState(rawState) {
    if (rawState === null || typeof rawState === 'undefined' || rawState === '') {
        return null
    }

    let payload = rawState
    if (typeof rawState === 'string') {
        try {
            payload = JSON.parse(rawState)
        } catch (_error) {
            return null
        }
    }

    if (!payload || typeof payload !== 'object') {
        return null
    }

    return buildPersistedIptvState(payload)
}
