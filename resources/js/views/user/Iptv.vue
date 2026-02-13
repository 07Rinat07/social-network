<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h2 class="section-title">IPTV</h2>
            <p class="section-subtitle">
                {{ $t('iptv.subtitle') }}
            </p>

            <form class="form-grid" @submit.prevent="loadPlaylistFromUrl">
                <div class="iptv-source-row">
                    <input
                        class="input-field"
                        v-model.trim="playlistUrl"
                        type="url"
                        placeholder="https://example.com/playlist.m3u"
                    >
                    <button class="btn btn-primary" type="submit" :disabled="isLoadingPlaylist">
                        {{ isLoadingPlaylist ? $t('iptv.loadingPlaylist') : $t('iptv.loadUrl') }}
                    </button>
                </div>

                <div class="iptv-file-row">
                    <input
                        ref="fileInput"
                        class="input-field iptv-file-field"
                        type="file"
                        accept=".m3u,.m3u8,text/plain,application/vnd.apple.mpegurl"
                        @change="loadPlaylistFromFile"
                    >
                    <button class="btn btn-outline" type="button" @click="clearPlaylist" :disabled="isLoadingPlaylist">
                        {{ $t('iptv.clear') }}
                    </button>
                </div>

                <div class="iptv-source-row">
                    <input
                        class="input-field"
                        v-model.trim="directStreamUrl"
                        type="url"
                        :placeholder="$t('iptv.directStreamPlaceholder')"
                    >
                    <button class="btn btn-outline" type="button" @click="playDirectStream">
                        {{ $t('iptv.openStream') }}
                    </button>
                </div>
            </form>

            <div class="iptv-seed-box">
                <div class="iptv-seed-head">
                    <strong>{{ $t('iptv.seedsTitle') }}</strong>
                    <small class="muted">{{ $t('iptv.seedCounts', { builtin: builtinSeedSources.length, custom: customSeedSources.length }) }}</small>
                </div>

                <div class="iptv-seed-grid">
                    <button
                        v-for="seed in seedSources"
                        :key="seed.id"
                        type="button"
                        class="iptv-seed-btn"
                        :class="{ 'iptv-seed-btn--active': seed.id === activeSeedId }"
                        :disabled="isLoadingPlaylist"
                        @click="loadPlaylistFromSeed(seed.id)"
                    >
                        <span>{{ seed.name }}</span>
                        <small>{{ seed.url }}</small>
                    </button>
                </div>

                <form class="iptv-seed-form" @submit.prevent="addCustomSeedSource">
                    <input
                        class="input-field"
                        v-model.trim="newSeedName"
                        type="text"
                        maxlength="80"
                        :placeholder="$t('iptv.newSeedNamePlaceholder')"
                    >
                    <input
                        class="input-field"
                        v-model.trim="newSeedUrl"
                        type="url"
                        placeholder="https://example.com/playlist.m3u"
                    >
                    <button class="btn btn-outline" type="submit" :disabled="isLoadingPlaylist">
                        {{ $t('iptv.addSeed') }}
                    </button>
                </form>

                <div v-if="customSeedSources.length > 0" class="iptv-custom-seed-list">
                    <div v-for="seed in customSeedSources" :key="`custom-${seed.id}`" class="iptv-custom-seed-item">
                        <div class="iptv-custom-seed-main">
                            <strong>{{ seed.name }}</strong>
                            <small>{{ seed.url }}</small>
                        </div>
                        <button class="btn btn-outline btn-sm" type="button" @click="removeCustomSeedSource(seed.id)">
                            {{ $t('common.delete') }}
                        </button>
                    </div>
                </div>
            </div>

            <p class="muted" style="margin: 0;">
                {{ $t('iptv.source') }}:
                <strong>{{ sourceLabel }}</strong>
                <span v-if="channels.length > 0"> · {{ $t('iptv.channelsCount', { count: channels.length }) }}</span>
            </p>
            <p v-if="playlistError" class="error-text">{{ playlistError }}</p>
        </section>

        <section class="section-card iptv-televiso-shell">
            <aside class="iptv-tv-sidebar">
                <div class="iptv-sidebar-head">
                    <h3 class="section-title" style="font-size: 1rem; margin: 0;">{{ $t('iptv.channels') }}</h3>
                    <span class="badge">{{ visibleChannels.length }}</span>
                </div>

                <div class="iptv-mode-switch">
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="viewMode === 'all' ? 'btn-primary' : 'btn-outline'"
                        @click="setViewMode('all')"
                    >
                        {{ $t('iptv.viewAll', { count: channels.length }) }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="viewMode === 'favorites' ? 'btn-primary' : 'btn-outline'"
                        @click="setViewMode('favorites')"
                    >
                        {{ $t('iptv.viewFavorites', { count: favoritesCount }) }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="viewMode === 'recent' ? 'btn-primary' : 'btn-outline'"
                        @click="setViewMode('recent')"
                    >
                        {{ $t('iptv.viewRecent', { count: recentCount }) }}
                    </button>
                </div>

                <input
                    class="input-field"
                    v-model.trim="searchQuery"
                    type="search"
                    :placeholder="$t('iptv.searchPlaceholder')"
                >

                <div class="iptv-sidebar-filters">
                    <select class="input-field iptv-select" v-model="selectedGroup">
                        <option value="all">{{ $t('iptv.allGroups') }}</option>
                        <option v-for="group in groupOptions" :key="`group-${group}`" :value="group">
                            {{ group }}
                        </option>
                    </select>

                    <select class="input-field iptv-select" v-model="sortMode" :disabled="viewMode === 'recent'">
                        <option value="group">{{ $t('iptv.sortGroup') }}</option>
                        <option value="name">{{ $t('iptv.sortName') }}</option>
                    </select>

                    <label class="iptv-toggle">
                        <input type="checkbox" v-model="secureOnly">
                        {{ $t('iptv.onlyHttps') }}
                    </label>
                </div>

                <p v-if="channels.length === 0" class="muted" style="margin: 0;">
                    {{ $t('iptv.loadPlaylistHint') }}
                </p>
                <p v-else-if="visibleChannels.length === 0" class="muted" style="margin: 0;">
                    {{ $t('iptv.emptyByFilter') }}
                </p>

                <div v-else class="iptv-channel-list">
                    <button
                        v-for="channel in visibleChannels"
                        :key="channel.id"
                        type="button"
                        class="iptv-channel-row"
                        :class="{ 'iptv-channel-row--active': channel.id === currentChannelId }"
                        @click="playChannel(channel.id)"
                    >
                        <img
                            v-if="!hideListLogosOnMobile && channel.logo"
                            :src="channel.logo"
                            alt="logo"
                            class="radio-station-icon"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            @error="hideBrokenIcon"
                        >
                        <span v-else class="avatar avatar-sm avatar-placeholder">TV</span>

                        <span class="iptv-channel-row-main">
                            <strong>{{ channel.name }}</strong>
                            <small>{{ channelMeta(channel) }}</small>
                        </span>

                        <span class="iptv-channel-row-side">
                            <span class="badge" v-if="channel.protocol">{{ channel.protocol.toUpperCase() }}</span>
                            <button
                                type="button"
                                class="iptv-star-btn"
                                :title="isFavorite(channel.id) ? $t('iptv.removeFromFavorites') : $t('iptv.addToFavorites')"
                                @click.stop="toggleFavorite(channel.id)"
                            >
                                {{ isFavorite(channel.id) ? '★' : '☆' }}
                            </button>
                        </span>
                    </button>
                </div>
            </aside>

            <div class="iptv-tv-stage">
                <template v-if="currentChannel">
                    <div class="iptv-player-head">
                        <div class="radio-station-head">
                            <img
                                v-if="currentChannel.logo"
                                :src="currentChannel.logo"
                                alt="channel logo"
                                class="radio-station-icon"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                @error="hideBrokenIcon"
                            >
                            <span v-else class="avatar avatar-sm avatar-placeholder">TV</span>
                            <div>
                                <strong>{{ currentChannel.name }}</strong>
                                <p class="muted" style="margin: 0.2rem 0 0; font-size: 0.82rem;">
                                    {{ channelMeta(currentChannel) }} · {{ currentChannelPositionLabel }}
                                </p>
                            </div>
                        </div>

                        <div class="iptv-player-head-badges">
                            <span class="badge iptv-mode-badge" :class="`iptv-mode-badge--${playbackMode}`">
                                {{ playbackModeLabel }}
                            </span>
                            <span class="badge iptv-status-badge" :class="`iptv-status-badge--${playerStatus}`">
                                {{ playerStatusLabel }}
                            </span>
                        </div>
                    </div>

                    <details class="iptv-tech-panel">
                        <summary class="iptv-tech-panel-summary">
                            <span>{{ $t('iptv.techPanelTitle') }}</span>
                            <small>{{ $t('iptv.techPanelHint') }}</small>
                        </summary>

                        <div class="iptv-tech-panel-body">
                            <div class="iptv-toolbar">
                                <button class="btn btn-outline btn-sm" type="button" @click="playPreviousChannel">{{ $t('iptv.previous') }}</button>
                                <button class="btn btn-outline btn-sm" type="button" @click="playNextChannel">{{ $t('iptv.next') }}</button>

                                <button class="btn btn-outline btn-sm" type="button" @click="toggleFavorite(currentChannel.id)">
                                    {{ isFavorite(currentChannel.id) ? $t('iptv.inFavorites') : $t('iptv.toFavorites') }}
                                </button>

                                <button class="btn btn-outline btn-sm" type="button" @click="copyStreamUrl(currentChannel)">
                                    {{ copiedChannelId === currentChannel.id ? $t('iptv.copied') : $t('iptv.copyUrl') }}
                                </button>
                            </div>

                            <div class="iptv-toolbar">
                                <select class="input-field iptv-select" v-model.number="selectedQuality" :disabled="qualityOptions.length === 0">
                                    <option :value="-1">{{ $t('iptv.autoQuality') }}</option>
                                    <option v-for="option in qualityOptions" :key="`quality-${option.value}`" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>

                                <select class="input-field iptv-select" v-model="bufferingMode">
                                    <option value="auto">{{ $t('iptv.bufferAuto') }}</option>
                                    <option value="fast">{{ $t('iptv.bufferFast') }}</option>
                                    <option value="balanced">{{ $t('iptv.bufferBalanced') }}</option>
                                    <option value="stable">{{ $t('iptv.bufferStable') }}</option>
                                </select>

                                <select class="input-field iptv-select" v-model="fitMode">
                                    <option value="contain">{{ $t('iptv.fitContain') }}</option>
                                    <option value="cover">{{ $t('iptv.fitCover') }}</option>
                                    <option value="fill">{{ $t('iptv.fitFill') }}</option>
                                </select>

                                <label class="iptv-toggle">
                                    <input type="checkbox" v-model="preferHttpsUpgrade">
                                    {{ $t('iptv.tryHttps') }}
                                </label>

                                <label class="iptv-toggle">
                                    <input type="checkbox" v-model="keyboardEnabled">
                                    {{ $t('iptv.hotkeys') }}
                                </label>

                                <label class="iptv-toggle">
                                    <input type="checkbox" v-model="autoStability">
                                    {{ $t('iptv.autoStability') }}
                                </label>

                                <label class="iptv-toggle" :title="canUseServerTranscode ? $t('iptv.compatTitleEnabled') : $t('iptv.compatTitleDisabled')">
                                    <input type="checkbox" v-model="autoCompatOnCodecError" :disabled="!canUseServerTranscode">
                                    {{ $t('iptv.autoCompat') }}
                                </label>

                                <select class="input-field iptv-select" v-model="compatProfile" :disabled="transcodeBusy || !canUseServerTranscode">
                                    <option value="fast">{{ $t('iptv.compatFast') }}</option>
                                    <option value="balanced">{{ $t('iptv.compatBalanced') }}</option>
                                    <option value="stable">{{ $t('iptv.compatStable') }}</option>
                                </select>

                                <label class="iptv-volume">
                                    {{ $t('iptv.volume') }}
                                    <input
                                        type="range"
                                        min="0"
                                        max="1"
                                        step="0.05"
                                        :value="volumeLevel"
                                        @input="handleVolumeSlider"
                                    >
                                </label>

                                <button class="btn btn-outline btn-sm" type="button" @click="toggleMuteAction">
                                    {{ muted ? $t('iptv.unmute') : $t('iptv.mute') }}
                                </button>
                            </div>

                            <p v-if="videoMetaLabel" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ $t('iptv.video') }}: {{ videoMetaLabel }}
                            </p>
                            <p v-if="playerDiagnosticsEngineLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ playerDiagnosticsEngineLine }}
                            </p>
                            <p v-if="playerDiagnosticsModulesLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ playerDiagnosticsModulesLine }}
                            </p>
                            <p v-if="playerDiagnosticsBufferLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ playerDiagnosticsBufferLine }}
                            </p>
                            <p v-if="playerDiagnosticsCodecsLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ playerDiagnosticsCodecsLine }}
                            </p>
                            <p v-if="playerMpegtsFeaturesLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ playerMpegtsFeaturesLine }}
                            </p>
                            <p v-if="playerStreamCodecsLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ playerStreamCodecsLine }}
                            </p>
                            <p v-if="playerHint" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ playerHint }}
                            </p>
                            <p v-if="compatStatusLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ compatStatusLine }}
                            </p>
                            <p v-if="proxyStatusLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ proxyStatusLine }}
                            </p>
                            <p v-if="playerError" class="error-text" style="margin: 0;">{{ playerError }}</p>
                            <p v-if="transcodeError" class="error-text" style="margin: 0;">{{ transcodeError }}</p>
                            <p v-if="proxyError" class="error-text" style="margin: 0;">{{ proxyError }}</p>
                        </div>
                    </details>

                    <IptvPlayer
                        ref="iptvPlayerRef"
                        :src="activePlaybackUrl"
                        :autoplay="true"
                        :fit-mode="fitMode"
                        :selected-quality="selectedQuality"
                        :muted="muted"
                        :volume="volumeLevel"
                        :buffering-mode="bufferingMode"
                        :auto-stability="autoStability"
                        @status-change="handlePlayerStatus"
                        @qualities-change="handleQualityOptions"
                        @error="handlePlayerError"
                        @video-meta="handleVideoMeta"
                        @volume-change="handlePlayerVolume"
                        @diagnostics-change="handlePlayerDiagnostics"
                    ></IptvPlayer>

                    <div class="iptv-player-actions">
                        <a
                            v-if="openInNewTabUrl !== ''"
                            class="btn btn-outline btn-sm"
                            :href="openInNewTabUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            @click="playlistError = ''"
                        >
                            {{ $t('iptv.openInNewTab') }}
                        </a>
                        <button
                            v-else
                            class="btn btn-outline btn-sm"
                            type="button"
                            disabled
                        >
                            {{ $t('iptv.openInNewTab') }}
                        </button>
                        <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            :disabled="transcodeBusy || !canUseServerTranscode"
                            @click="toggleCompatibilityMode"
                        >
                            {{ compatModeEnabled ? $t('iptv.compatOff') : $t('iptv.compatOn') }}
                        </button>
                        <button class="btn btn-outline btn-sm" type="button" @click="toggleFullscreenAction">
                            {{ $t('iptv.fullscreen') }}
                        </button>
                    </div>

                    <p class="muted iptv-shortcuts" v-if="keyboardEnabled">
                        {{ $t('iptv.shortcutsHelp') }}
                    </p>
                </template>

                <div v-else class="iptv-empty-stage">
                    <h3 class="section-title" style="font-size: 1rem; margin-bottom: 0.4rem;">{{ $t('iptv.playerReady') }}</h3>
                    <p class="muted" style="margin: 0;">
                        {{ $t('iptv.playerReadyHint') }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
import IptvPlayer from '../../components/IptvPlayer.vue'

const IPTV_STATE_STORAGE_KEY = 'solid-social:iptv-player-state:v3'
const IPTV_CUSTOM_SEEDS_STORAGE_KEY = 'solid-social:iptv-custom-seeds:v1'
const IPTV_RECENT_LIMIT = 40
const IPTV_CUSTOM_SEEDS_LIMIT = 60
const IPTV_BUILTIN_SEED_SOURCES = [
    {
        id: 'dimonovich-tv',
        nameKey: 'iptv.seedPlaylistTv',
        url: 'https://raw.githubusercontent.com/Dimonovich/TV/Dimonovich/FREE/TV',
    },
    {
        id: 'dimonovich-camera',
        nameKey: 'iptv.seedWebcams',
        url: 'https://raw.githubusercontent.com/Dimonovich/TV/Dimonovich/FREE/CAMERA',
    },
    {
        id: 'dimonovich-zarub',
        nameKey: 'iptv.seedInternational',
        url: 'https://raw.githubusercontent.com/Dimonovich/TV/Dimonovich/FREE/ZARUB',
    },
    {
        id: 'voxlist-tv',
        nameKey: 'iptv.seedVoxlist',
        url: 'https://raw.githubusercontent.com/Voxlist/voxlist/refs/heads/main/voxlist.m3u',
    },
]

export default {
    name: 'Iptv',

    components: {
        IptvPlayer,
    },

    data() {
        return {
            playlistUrl: '',
            directStreamUrl: '',
            searchQuery: '',
            channels: [],
            currentChannelId: '',
            isLoadingPlaylist: false,
            playlistError: '',
            sourceLabel: this.$t('iptv.sourceNotSelected'),
            activeSeedId: '',
            newSeedName: '',
            newSeedUrl: '',
            customSeedSources: [],

            viewMode: 'all',
            selectedGroup: 'all',
            sortMode: 'group',
            secureOnly: false,

            fitMode: 'contain',
            selectedQuality: -1,
            qualityOptions: [],
            bufferingMode: 'stable',
            autoStability: true,
            compatModeEnabled: false,
            compatProfile: 'stable',
            autoCompatOnCodecError: false,
            transcodeSessionId: '',
            transcodePlaybackUrl: '',
            transcodeBusy: false,
            transcodeError: '',
            transcodeUnavailableSourceUrls: [],
            proxyModeEnabled: false,
            proxySessionId: '',
            proxyPlaybackUrl: '',
            proxyBusy: false,
            proxyError: '',
            hideListLogosOnMobile: false,
            transcodeCapabilities: {
                checked: false,
                ffmpegAvailable: false,
                ffmpegVersion: '',
            },
            autoCompatLastAttemptUrl: '',
            autoCompatLastAttemptAt: 0,
            autoProxyLastAttemptUrl: '',
            autoProxyLastAttemptAt: 0,
            compatRecoveryState: {
                sourceUrl: '',
                attempts: 0,
            },
            playerStatus: 'idle',
            playerStatusMessage: '',
            playerError: '',
            playerDiagnostics: null,
            videoMeta: {
                width: 0,
                height: 0,
            },

            preferHttpsUpgrade: false,
            keyboardEnabled: true,
            volumeLevel: 1,
            muted: false,

            favoriteChannelIds: [],
            recentChannelIds: [],
            copiedChannelId: '',
            exitCleanupDone: false,
        }
    },

    computed: {
        isPageHttps() {
            if (typeof window === 'undefined') {
                return false
            }

            return String(window.location?.protocol || '') === 'https:'
        },

        builtinSeedSources() {
            return IPTV_BUILTIN_SEED_SOURCES.map((seed) => ({
                id: seed.id,
                url: seed.url,
                name: this.$t(seed.nameKey),
            }))
        },

        seedSources() {
            return [...this.builtinSeedSources, ...this.customSeedSources]
        },

        favoriteIdSet() {
            return new Set(this.favoriteChannelIds)
        },

        recentIdSet() {
            return new Set(this.recentChannelIds)
        },

        recentRankMap() {
            const map = new Map()
            this.recentChannelIds.forEach((id, index) => {
                map.set(id, index)
            })

            return map
        },

        currentChannel() {
            return this.channels.find((channel) => channel.id === this.currentChannelId) || null
        },

        playbackUrl() {
            const originalUrl = String(this.currentChannel?.url || '').trim()
            if (originalUrl === '') {
                return ''
            }

            if (this.shouldUpgradeToHttps(originalUrl)) {
                return this.forceHttpsUrl(originalUrl)
            }

            return originalUrl
        },

        activePlaybackUrl() {
            if (this.compatModeEnabled && this.transcodePlaybackUrl) {
                return this.transcodePlaybackUrl
            }

            if (this.proxyModeEnabled) {
                return this.proxyPlaybackUrl || ''
            }

            return this.playbackUrl
        },

        openInNewTabUrl() {
            const rawUrl = String(this.activePlaybackUrl || '').trim()
            if (rawUrl === '') {
                return ''
            }

            if (this.isHttpUrl(rawUrl) || this.isHttpsUrl(rawUrl)) {
                return rawUrl
            }

            if (rawUrl.startsWith('/')) {
                if (typeof window === 'undefined') {
                    return rawUrl
                }

                return `${window.location.origin}${rawUrl}`
            }

            return ''
        },

        canUseServerTranscode() {
            return Boolean(this.transcodeCapabilities.ffmpegAvailable)
        },

        compatStatusLine() {
            if (!this.transcodeCapabilities.checked) {
                return this.$t('iptv.compatChecking')
            }

            if (!this.canUseServerTranscode) {
                return this.$t('iptv.compatUnavailableNoFfmpeg')
            }

            if (this.compatModeEnabled) {
                const source = this.transcodeSessionId
                    ? this.$t('iptv.compatSession', { id: this.transcodeSessionId })
                    : this.$t('iptv.compatPreparing')
                const version = this.transcodeCapabilities.ffmpegVersion ? ` · ${this.transcodeCapabilities.ffmpegVersion}` : ''
                return this.$t('iptv.compatEnabledLine', {
                    profile: this.compatProfile,
                    source,
                    version,
                })
            }

            return ''
        },

        proxyStatusLine() {
            if (!this.proxyModeEnabled) {
                return ''
            }

            const source = this.proxySessionId
                ? this.$t('iptv.proxySession', { id: this.proxySessionId })
                : this.$t('iptv.proxyPreparing')

            return this.$t('iptv.proxyEnabledLine', { source })
        },

        groupOptions() {
            const groups = Array.from(new Set(
                this.channels
                    .map((channel) => String(channel.group || '').trim())
                    .filter((group) => group !== '')
            ))

            return groups.sort((a, b) => a.localeCompare(b, 'ru'))
        },

        favoritesCount() {
            return this.channels.filter((channel) => this.favoriteIdSet.has(channel.id)).length
        },

        recentCount() {
            return this.channels.filter((channel) => this.recentIdSet.has(channel.id)).length
        },

        visibleChannels() {
            const query = this.searchQuery.toLowerCase()

            let result = this.channels.filter((channel) => {
                if (this.selectedGroup !== 'all' && channel.group !== this.selectedGroup) {
                    return false
                }

                if (this.secureOnly && !channel.isSecure) {
                    return false
                }

                if (this.viewMode === 'favorites' && !this.favoriteIdSet.has(channel.id)) {
                    return false
                }

                if (this.viewMode === 'recent' && !this.recentIdSet.has(channel.id)) {
                    return false
                }

                if (!query) {
                    return true
                }

                const haystack = [
                    channel.name,
                    channel.group,
                    channel.domain,
                    channel.url,
                ].join(' ').toLowerCase()

                return haystack.includes(query)
            })

            if (this.viewMode === 'recent') {
                return result.sort((a, b) => {
                    const rankA = this.recentRankMap.get(a.id)
                    const rankB = this.recentRankMap.get(b.id)

                    const normalizedRankA = Number.isInteger(rankA) ? rankA : Number.MAX_SAFE_INTEGER
                    const normalizedRankB = Number.isInteger(rankB) ? rankB : Number.MAX_SAFE_INTEGER

                    if (normalizedRankA !== normalizedRankB) {
                        return normalizedRankA - normalizedRankB
                    }

                    return a.name.localeCompare(b.name, 'ru')
                })
            }

            if (this.sortMode === 'name') {
                return result.sort((a, b) => a.name.localeCompare(b.name, 'ru'))
            }

            return result.sort((a, b) => {
                const groupCompare = String(a.group || '').localeCompare(String(b.group || ''), 'ru')
                if (groupCompare !== 0) {
                    return groupCompare
                }

                return a.name.localeCompare(b.name, 'ru')
            })
        },

        playerStatusLabel() {
            const dictionary = {
                idle: this.$t('iptv.statusIdle'),
                loading: this.$t('iptv.statusLoading'),
                buffering: this.$t('iptv.statusBuffering'),
                ready: this.$t('iptv.statusReady'),
                playing: this.$t('iptv.statusPlaying'),
                paused: this.$t('iptv.statusPaused'),
                error: this.$t('iptv.statusError'),
            }

            return dictionary[this.playerStatus] || this.$t('iptv.statusIdle')
        },

        playbackMode() {
            if (this.compatModeEnabled && this.transcodePlaybackUrl) {
                return 'ffmpeg'
            }

            if (this.proxyModeEnabled && this.proxyPlaybackUrl) {
                return 'proxy'
            }

            return 'direct'
        },

        playbackModeLabel() {
            const dictionary = {
                direct: this.$t('iptv.modeDirect'),
                proxy: this.$t('iptv.modeProxy'),
                ffmpeg: this.$t('iptv.modeFfmpeg'),
            }

            return dictionary[this.playbackMode] || this.$t('iptv.modeDirect')
        },

        videoMetaLabel() {
            const width = Number(this.videoMeta.width || 0)
            const height = Number(this.videoMeta.height || 0)

            if (width <= 0 || height <= 0) {
                return ''
            }

            return `${width}x${height}`
        },

        playerDiagnosticsEngineLine() {
            const engine = String(this.playerDiagnostics?.engine || '')
            if (engine === '') {
                return ''
            }

            const sourceType = String(this.playerDiagnostics?.sourceType || '')
            const labels = {
                'idle': this.$t('iptv.engineIdle'),
                'hls.js': 'HLS.js',
                'native-hls': this.$t('iptv.engineNativeHls'),
                'dash.js': 'dash.js',
                'mpegts.js': 'mpegts.js',
                'native': this.$t('iptv.engineNativeHtml5'),
            }

            const engineLabel = labels[engine] || engine
            return sourceType
                ? this.$t('iptv.engineLineWithType', { engine: engineLabel, type: sourceType })
                : this.$t('iptv.engineLine', { engine: engineLabel })
        },

        playerDiagnosticsModulesLine() {
            const modules = this.playerDiagnostics?.modules
            if (!modules) {
                return ''
            }

            return this.$t('iptv.modulesLine', {
                nativeHls: this.boolLabel(modules.nativeHls),
                hlsjs: this.boolLabel(modules.hlsjs),
                dashjs: this.boolLabel(modules.dashjs),
                dashjsLoaded: this.boolLabel(modules.dashjsLoaded),
                mpegtsjs: this.boolLabel(modules.mpegtsjs),
                mpegtsjsLoaded: this.boolLabel(modules.mpegtsjsLoaded),
            })
        },

        playerDiagnosticsBufferLine() {
            const requested = String(this.playerDiagnostics?.requestedBufferingMode || '')
            const activeProfile = String(this.playerDiagnostics?.activeBufferProfile || '')
            const autoStability = this.playerDiagnostics?.autoStability
            const stabilityEvents = Number(this.playerDiagnostics?.stability?.totalEvents || 0)

            if (requested === '' && activeProfile === '') {
                return ''
            }

            const requestedLabel = requested || 'auto'
            const activeLabel = activeProfile || 'balanced'
            return this.$t('iptv.bufferLine', {
                requested: requestedLabel,
                active: activeLabel,
                autoStability: this.boolLabel(autoStability),
                recoveries: stabilityEvents,
            })
        },

        playerDiagnosticsCodecsLine() {
            const codecs = this.playerDiagnostics?.codecs
            if (!codecs) {
                return ''
            }

            return this.$t('iptv.browserCodecsLine', {
                h264: this.boolLabel(codecs.h264),
                h265: this.boolLabel(codecs.h265),
                av1: this.boolLabel(codecs.av1),
                aac: this.boolLabel(codecs.aac),
            })
        },

        playerMpegtsFeaturesLine() {
            const features = this.playerDiagnostics?.mpegtsFeatures
            if (!features) {
                return ''
            }

            return `mpegts.js: MSE=${this.boolLabel(features.msePlayback)}, LiveMSE=${this.boolLabel(features.mseLivePlayback)}, HEVC=${this.boolLabel(features.mseH265Playback)}`
        },

        playerStreamCodecsLine() {
            const streamCodecs = this.playerDiagnostics?.streamCodecs
            if (!Array.isArray(streamCodecs) || streamCodecs.length === 0) {
                return ''
            }

            return this.$t('iptv.streamCodecsLine', { codecs: streamCodecs.join(', ') })
        },

        currentChannelPositionLabel() {
            const position = this.visibleChannels.findIndex((channel) => channel.id === this.currentChannelId)
            if (position < 0) {
                return this.$t('iptv.channelsCount', { count: this.visibleChannels.length })
            }

            return `${position + 1}/${this.visibleChannels.length}`
        },

        playerHint() {
            const originalUrl = String(this.currentChannel?.url || '').trim()

            if (originalUrl !== '' && this.isPageHttps && this.isHttpUrl(originalUrl) && !this.preferHttpsUpgrade) {
                return this.$t('iptv.httpOnHttpsHint')
            }

            if (originalUrl !== '' && this.shouldUpgradeToHttps(originalUrl)) {
                return this.$t('iptv.tryingSecureStream', { url: this.playbackUrl })
            }

            return this.playerStatus === 'error' ? '' : this.playerStatusMessage
        },
    },

    watch: {
        viewMode() {
            this.persistPlayerState()
        },
        selectedGroup() {
            this.persistPlayerState()
        },
        sortMode() {
            this.persistPlayerState()
        },
        secureOnly() {
            this.persistPlayerState()
        },
        fitMode() {
            this.persistPlayerState()
        },
        bufferingMode() {
            this.persistPlayerState()
        },
        autoStability() {
            this.persistPlayerState()
        },
        compatModeEnabled() {
            this.persistPlayerState()
        },
        compatProfile() {
            this.persistPlayerState()

            if (this.compatModeEnabled && this.playbackUrl) {
                this.startServerTranscodeForCurrentChannel()
            }
        },
        autoCompatOnCodecError() {
            this.persistPlayerState()
        },
        preferHttpsUpgrade() {
            this.persistPlayerState()
        },
        keyboardEnabled() {
            this.persistPlayerState()
        },
        volumeLevel() {
            this.persistPlayerState()
        },
        muted() {
            this.persistPlayerState()
        },
        activeSeedId() {
            this.persistPlayerState()
        },
        favoriteChannelIds: {
            deep: true,
            handler() {
                this.persistPlayerState()
            },
        },
        recentChannelIds: {
            deep: true,
            handler() {
                this.persistPlayerState()
            },
        },
        playbackUrl(nextUrl, previousUrl) {
            this.autoCompatLastAttemptUrl = ''
            this.autoCompatLastAttemptAt = 0
            this.autoProxyLastAttemptUrl = ''
            this.autoProxyLastAttemptAt = 0
            this.compatRecoveryState = {
                sourceUrl: String(nextUrl || ''),
                attempts: 0,
            }

            if (nextUrl === '') {
                this.stopServerTranscodeSession()
                this.stopServerProxySession()
                return
            }

            if (this.proxyModeEnabled && nextUrl !== previousUrl) {
                this.startServerProxyForCurrentChannel()
            }

            if (this.compatModeEnabled && nextUrl !== previousUrl) {
                this.startServerTranscodeForCurrentChannel()
            }
        },
    },

    mounted() {
        this.preferHttpsUpgrade = this.isPageHttps
        this.hideListLogosOnMobile = this.isMobileViewport()
        this.loadCustomSeedSources()
        this.loadPersistedState()

        this.loadTranscodeCapabilities()
        window.addEventListener('pagehide', this.handlePageHide)
        window.addEventListener('beforeunload', this.handleBeforeUnload)
        window.addEventListener('keydown', this.handleGlobalKeydown)
        window.addEventListener('resize', this.handleViewportChange)
    },

    beforeUnmount() {
        this.teardownPlaybackOnExit()
        window.removeEventListener('pagehide', this.handlePageHide)
        window.removeEventListener('beforeunload', this.handleBeforeUnload)
        window.removeEventListener('keydown', this.handleGlobalKeydown)
        window.removeEventListener('resize', this.handleViewportChange)
        this.persistPlayerState()
    },

    beforeRouteLeave(_to, _from, next) {
        this.teardownPlaybackOnExit()
        next()
    },

    methods: {
        handlePageHide() {
            this.teardownPlaybackOnExit({ useKeepalive: true })
        },

        handleBeforeUnload() {
            this.teardownPlaybackOnExit({ useKeepalive: true })
        },

        teardownPlaybackOnExit(options = {}) {
            if (this.exitCleanupDone) {
                return
            }

            this.exitCleanupDone = true

            const player = this.playerRef()
            if (player && typeof player.destroyPlayer === 'function') {
                player.destroyPlayer()
            }

            this.currentChannelId = ''
            this.selectedQuality = -1
            this.qualityOptions = []
            this.playerError = ''
            this.playerStatus = 'idle'
            this.playerStatusMessage = ''
            this.playerDiagnostics = null
            this.videoMeta = {
                width: 0,
                height: 0,
            }

            this.stopServerTranscodeSession({
                useKeepalive: Boolean(options?.useKeepalive),
            })
            this.stopServerProxySession({
                useKeepalive: Boolean(options?.useKeepalive),
            })
        },

        resolveCsrfToken() {
            if (typeof document === 'undefined') {
                return ''
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            return String(token || '').trim()
        },

        sendStopTranscodeKeepalive(sessionId) {
            const normalizedSessionId = String(sessionId || '').trim()
            if (normalizedSessionId === '') {
                return
            }

            const url = `/api/iptv/transcode/${encodeURIComponent(normalizedSessionId)}`
            const csrfToken = this.resolveCsrfToken()

            if (typeof window !== 'undefined' && typeof window.fetch === 'function') {
                try {
                    const headers = {
                        'Accept': 'application/json',
                    }

                    if (csrfToken !== '') {
                        headers['X-CSRF-TOKEN'] = csrfToken
                    }

                    window.fetch(url, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        keepalive: true,
                        headers,
                    })
                    return
                } catch (_error) {
                    // fallback to beacon
                }
            }

            if (typeof navigator === 'undefined' || typeof navigator.sendBeacon !== 'function') {
                return
            }

            try {
                const payload = new FormData()
                payload.append('_method', 'DELETE')
                if (csrfToken !== '') {
                    payload.append('_token', csrfToken)
                }

                navigator.sendBeacon(url, payload)
            } catch (_error) {
                // ignore beacon failures
            }
        },

        sendStopProxyKeepalive(sessionId) {
            const normalizedSessionId = String(sessionId || '').trim()
            if (normalizedSessionId === '') {
                return
            }

            const url = `/api/iptv/proxy/${encodeURIComponent(normalizedSessionId)}`
            const csrfToken = this.resolveCsrfToken()

            if (typeof window !== 'undefined' && typeof window.fetch === 'function') {
                try {
                    const headers = {
                        'Accept': 'application/json',
                    }

                    if (csrfToken !== '') {
                        headers['X-CSRF-TOKEN'] = csrfToken
                    }

                    window.fetch(url, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        keepalive: true,
                        headers,
                    })
                    return
                } catch (_error) {
                    // fallback to beacon
                }
            }

            if (typeof navigator === 'undefined' || typeof navigator.sendBeacon !== 'function') {
                return
            }

            try {
                const payload = new FormData()
                payload.append('_method', 'DELETE')
                if (csrfToken !== '') {
                    payload.append('_token', csrfToken)
                }

                navigator.sendBeacon(url, payload)
            } catch (_error) {
                // ignore beacon failures
            }
        },

        setViewMode(mode) {
            if (!['all', 'favorites', 'recent'].includes(mode)) {
                return
            }

            this.viewMode = mode
        },

        loadPersistedState() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return
            }

            try {
                const raw = window.localStorage.getItem(IPTV_STATE_STORAGE_KEY)
                if (!raw) {
                    return
                }

                const payload = JSON.parse(raw)

                if (['all', 'favorites', 'recent'].includes(payload?.viewMode)) {
                    this.viewMode = payload.viewMode
                }
                if (typeof payload?.selectedGroup === 'string') {
                    this.selectedGroup = payload.selectedGroup
                }
                if (['group', 'name'].includes(payload?.sortMode)) {
                    this.sortMode = payload.sortMode
                }
                if (typeof payload?.secureOnly === 'boolean') {
                    this.secureOnly = payload.secureOnly
                }
                if (typeof payload?.preferHttpsUpgrade === 'boolean') {
                    this.preferHttpsUpgrade = payload.preferHttpsUpgrade
                }
                if (['contain', 'cover', 'fill'].includes(payload?.fitMode)) {
                    this.fitMode = payload.fitMode
                }
                if (['auto', 'fast', 'balanced', 'stable'].includes(payload?.bufferingMode)) {
                    this.bufferingMode = payload.bufferingMode
                }
                if (typeof payload?.autoStability === 'boolean') {
                    this.autoStability = payload.autoStability
                }
                if (typeof payload?.compatModeEnabled === 'boolean') {
                    this.compatModeEnabled = payload.compatModeEnabled
                }
                if (['fast', 'balanced', 'stable'].includes(payload?.compatProfile)) {
                    this.compatProfile = payload.compatProfile
                }
                if (typeof payload?.autoCompatOnCodecError === 'boolean') {
                    this.autoCompatOnCodecError = payload.autoCompatOnCodecError
                }
                if (typeof payload?.keyboardEnabled === 'boolean') {
                    this.keyboardEnabled = payload.keyboardEnabled
                }
                if (typeof payload?.activeSeedId === 'string' && this.findSeedById(payload.activeSeedId)) {
                    this.activeSeedId = payload.activeSeedId
                }
                if (Array.isArray(payload?.favoriteChannelIds)) {
                    this.favoriteChannelIds = payload.favoriteChannelIds.map((id) => String(id)).slice(0, 500)
                }
                if (Array.isArray(payload?.recentChannelIds)) {
                    this.recentChannelIds = payload.recentChannelIds.map((id) => String(id)).slice(0, IPTV_RECENT_LIMIT)
                }

                const normalizedVolume = this.normalizeVolume(payload?.volumeLevel)
                this.volumeLevel = normalizedVolume

                if (typeof payload?.muted === 'boolean') {
                    this.muted = payload.muted
                }
            } catch (_error) {
                // ignore broken local state
            }
        },

        persistPlayerState() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return
            }

            const payload = {
                viewMode: this.viewMode,
                selectedGroup: this.selectedGroup,
                sortMode: this.sortMode,
                secureOnly: this.secureOnly,
                preferHttpsUpgrade: this.preferHttpsUpgrade,
                fitMode: this.fitMode,
                bufferingMode: this.bufferingMode,
                autoStability: this.autoStability,
                compatModeEnabled: this.compatModeEnabled,
                compatProfile: this.compatProfile,
                autoCompatOnCodecError: this.autoCompatOnCodecError,
                keyboardEnabled: this.keyboardEnabled,
                volumeLevel: this.normalizeVolume(this.volumeLevel),
                muted: this.muted,
                activeSeedId: this.activeSeedId,
                favoriteChannelIds: this.favoriteChannelIds.slice(0, 500),
                recentChannelIds: this.recentChannelIds.slice(0, IPTV_RECENT_LIMIT),
            }

            try {
                window.localStorage.setItem(IPTV_STATE_STORAGE_KEY, JSON.stringify(payload))
            } catch (_error) {
                // ignore storage write failures
            }
        },

        async loadTranscodeCapabilities() {
            try {
                const response = await axios.get('/api/iptv/transcode/capabilities')
                const payload = response.data?.data || {}

                this.transcodeCapabilities = {
                    checked: true,
                    ffmpegAvailable: Boolean(payload.ffmpeg_available),
                    ffmpegVersion: String(payload.ffmpeg_version || ''),
                }

                if (!this.transcodeCapabilities.ffmpegAvailable && this.compatModeEnabled) {
                    this.compatModeEnabled = false
                    this.transcodeError = this.$t('iptv.compatDisabledNoFfmpeg')
                }
            } catch (_error) {
                this.transcodeCapabilities = {
                    checked: true,
                    ffmpegAvailable: false,
                    ffmpegVersion: '',
                }
                this.transcodeError = this.$t('iptv.compatCheckFailed')
            }
        },

        isCodecCompatibilityError(message) {
            const text = String(message || '').toLowerCase()
            return text.includes('codec')
                || text.includes('decode')
                || text.includes('unsupported')
                || text.includes('not supported')
                || text.includes('bufferaddcodecerror')
                || text.includes('m2v')
                || text.includes('mpeg-2')
                || text.includes('mpeg2')
        },

        isCorsLikeError(message) {
            const text = String(message || '').toLowerCase()
            return text.includes('cors')
                || text.includes('cross-origin')
                || text.includes('cross origin')
                || text.includes('access-control-allow-origin')
                || text.includes('blocked by cors policy')
                || text.includes('networkerror')
                || text.includes('network_error')
                || text.includes('manifestloaderror')
                || text.includes('manifest_load_error')
                || text.includes('manifestloadtimeout')
                || text.includes('manifest_load_timeout')
                || text.includes('fragloaderror')
                || text.includes('frag_load_error')
                || text.includes('fragloadtimeout')
                || text.includes('frag_load_timeout')
                || text.includes('levelloaderror')
                || text.includes('level_load_error')
                || text.includes('levelloadtimeout')
                || text.includes('level_load_timeout')
                || text.includes('net::err_failed')
                || text.includes('err_failed')
        },

        isOriginBlockedLikeError(message) {
            const text = String(message || '').toLowerCase()
            const hasSegmentError = text.includes('fragloaderror')
                || text.includes('frag_load_error')
                || text.includes('levelloaderror')
                || text.includes('level_load_error')
                || text.includes('keyloaderror')
                || text.includes('key_load_error')
                || text.includes('hls-origin-blocked')
                || text.includes('hls-segment-exhausted')

            if (!hasSegmentError) {
                return false
            }

            return text.includes('code=403')
                || text.includes('code=404')
                || text.includes('forbidden')
                || text.includes('not found')
                || text.includes('origin')
                || text.includes('anti-hotlink')
        },

        isMobileViewport() {
            if (typeof window === 'undefined' || !window.matchMedia) {
                return false
            }

            return window.matchMedia('(max-width: 980px)').matches
        },

        handleViewportChange() {
            this.hideListLogosOnMobile = this.isMobileViewport()
        },

        sourceKey(url) {
            return String(url || '').trim().toLowerCase()
        },

        isTranscodeUnavailableForSource(url) {
            const key = this.sourceKey(url)
            if (key === '') {
                return false
            }

            return this.transcodeUnavailableSourceUrls.includes(key)
        },

        rememberTranscodeUnavailableForSource(url) {
            const key = this.sourceKey(url)
            if (key === '') {
                return
            }

            if (this.transcodeUnavailableSourceUrls.includes(key)) {
                return
            }

            this.transcodeUnavailableSourceUrls = [key, ...this.transcodeUnavailableSourceUrls].slice(0, 300)
        },

        clearTranscodeUnavailableForSource(url) {
            const key = this.sourceKey(url)
            if (key === '') {
                return
            }

            this.transcodeUnavailableSourceUrls = this.transcodeUnavailableSourceUrls.filter((item) => item !== key)
        },

        async toggleCompatibilityMode() {
            if (this.compatModeEnabled) {
                await this.stopCompatibilityMode()
                return
            }

            await this.startCompatibilityMode({ force: true })
        },

        async startCompatibilityMode(options = {}) {
            const force = Boolean(options?.force)
            if (!this.canUseServerTranscode) {
                this.transcodeError = this.$t('iptv.compatUnavailableNoFfmpeg')
                return
            }

            if (force) {
                this.clearTranscodeUnavailableForSource(this.playbackUrl)
            }

            if (this.proxyModeEnabled) {
                await this.stopProxyMode()
            }

            this.compatModeEnabled = true
            await this.startServerTranscodeForCurrentChannel({ force })
        },

        async stopCompatibilityMode() {
            this.compatModeEnabled = false
            await this.stopServerTranscodeSession()
        },

        async startProxyMode() {
            this.proxyModeEnabled = true
            return await this.startServerProxyForCurrentChannel()
        },

        async stopProxyMode() {
            this.proxyModeEnabled = false
            await this.stopServerProxySession()
        },

        async startServerProxyForCurrentChannel() {
            if (!this.proxyModeEnabled) {
                return false
            }

            const sourceUrl = String(this.playbackUrl || '').trim()
            if (sourceUrl === '') {
                return false
            }

            if (this.proxyBusy) {
                return false
            }

            this.proxyBusy = true
            this.proxyError = ''

            try {
                await this.stopServerProxySession({ preserveMode: true, preserveError: true })

                const response = await axios.post('/api/iptv/proxy/start', {
                    url: sourceUrl,
                })

                this.proxySessionId = String(response.data?.data?.session_id || '')
                this.proxyPlaybackUrl = String(response.data?.data?.playlist_url || '')

                if (this.proxySessionId === '' || this.proxyPlaybackUrl === '') {
                    throw new Error('Empty proxy session payload')
                }

                return true
            } catch (error) {
                this.proxySessionId = ''
                this.proxyPlaybackUrl = ''
                this.proxyModeEnabled = false
                this.proxyError = error.response?.data?.message || this.$t('iptv.proxyStartFailed')
                return false
            } finally {
                this.proxyBusy = false
            }
        },

        async stopServerProxySession(options = {}) {
            const preserveMode = Boolean(options?.preserveMode)
            const preserveError = Boolean(options?.preserveError)
            const useKeepalive = Boolean(options?.useKeepalive)
            const sessionId = String(this.proxySessionId || '')

            if (sessionId !== '') {
                if (useKeepalive) {
                    this.sendStopProxyKeepalive(sessionId)
                } else {
                    try {
                        await axios.delete(`/api/iptv/proxy/${encodeURIComponent(sessionId)}`)
                    } catch (_error) {
                        // ignore stop errors
                    }
                }
            }

            this.proxySessionId = ''
            this.proxyPlaybackUrl = ''
            this.proxyBusy = false
            this.autoProxyLastAttemptUrl = ''
            this.autoProxyLastAttemptAt = 0

            if (!preserveMode) {
                this.proxyModeEnabled = false
            }

            if (!preserveError) {
                this.proxyError = ''
            }
        },

        async startServerTranscodeForCurrentChannel(options = {}) {
            const force = Boolean(options?.force)
            if (!this.compatModeEnabled || !this.canUseServerTranscode) {
                return false
            }

            const sourceUrl = String(this.playbackUrl || '').trim()
            if (sourceUrl === '') {
                return false
            }

            if (!force && this.isTranscodeUnavailableForSource(sourceUrl)) {
                this.compatModeEnabled = false
                this.transcodeError = this.$t('iptv.compatUnavailableForChannel')
                return false
            }

            if (this.transcodeBusy) {
                return false
            }

            this.transcodeBusy = true
            this.transcodeError = ''

            try {
                await this.stopServerTranscodeSession({ preserveMode: true, preserveError: true })

                const response = await axios.post('/api/iptv/transcode/start', {
                    url: sourceUrl,
                    profile: this.compatProfile,
                })

                this.transcodeSessionId = String(response.data?.data?.session_id || '')
                this.transcodePlaybackUrl = String(response.data?.data?.playlist_url || '')
                this.compatRecoveryState = {
                    sourceUrl,
                    attempts: 0,
                }

                if (this.transcodeSessionId === '' || this.transcodePlaybackUrl === '') {
                    throw new Error('Empty transcode session payload')
                }
                return true
            } catch (error) {
                this.transcodeSessionId = ''
                this.transcodePlaybackUrl = ''
                this.compatModeEnabled = false
                this.rememberTranscodeUnavailableForSource(sourceUrl)
                const statusCode = Number(error?.response?.status || 0)
                if (statusCode === 503 && this.autoCompatOnCodecError) {
                    this.autoCompatOnCodecError = false
                    this.transcodeError = `${error.response?.data?.message || this.$t('iptv.compatStartFailed')} ${this.$t('iptv.autoCompatDisabledAfterFail')}`
                } else {
                    this.transcodeError = error.response?.data?.message || this.$t('iptv.compatStartFailed')
                }
                return false
            } finally {
                this.transcodeBusy = false
            }
        },

        async stopServerTranscodeSession(options = {}) {
            const preserveMode = Boolean(options?.preserveMode)
            const preserveError = Boolean(options?.preserveError)
            const useKeepalive = Boolean(options?.useKeepalive)
            const sessionId = String(this.transcodeSessionId || '')

            if (sessionId !== '') {
                if (useKeepalive) {
                    this.sendStopTranscodeKeepalive(sessionId)
                } else {
                    try {
                        await axios.delete(`/api/iptv/transcode/${encodeURIComponent(sessionId)}`)
                    } catch (_error) {
                        // ignore stop errors
                    }
                }
            }

            this.transcodeSessionId = ''
            this.transcodePlaybackUrl = ''
            this.transcodeBusy = false
            this.autoCompatLastAttemptUrl = ''
            this.autoCompatLastAttemptAt = 0
            this.compatRecoveryState = {
                sourceUrl: '',
                attempts: 0,
            }

            if (!preserveMode) {
                this.compatModeEnabled = false
            }

            if (!preserveError) {
                this.transcodeError = ''
            }
        },

        loadCustomSeedSources() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return
            }

            try {
                const raw = window.localStorage.getItem(IPTV_CUSTOM_SEEDS_STORAGE_KEY)
                if (!raw) {
                    return
                }

                const payload = JSON.parse(raw)
                if (!Array.isArray(payload)) {
                    return
                }

                const normalized = payload
                    .slice(0, IPTV_CUSTOM_SEEDS_LIMIT)
                    .map((item) => {
                        const url = String(item?.url || '').trim()
                        if (!this.isHttpUrl(url) && !this.isHttpsUrl(url)) {
                            return null
                        }

                        const name = String(item?.name || '').trim() || this.guessSeedName(url)
                        return {
                            id: String(item?.id || `custom-${this.buildStableChannelId(url)}`),
                            name: name.slice(0, 80),
                            url,
                        }
                    })
                    .filter(Boolean)

                this.customSeedSources = normalized
            } catch (_error) {
                // ignore broken local state
            }
        },

        persistCustomSeedSources() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return
            }

            try {
                window.localStorage.setItem(
                    IPTV_CUSTOM_SEEDS_STORAGE_KEY,
                    JSON.stringify(this.customSeedSources.slice(0, IPTV_CUSTOM_SEEDS_LIMIT))
                )
            } catch (_error) {
                // ignore storage write failures
            }
        },

        findSeedById(seedId) {
            return this.seedSources.find((seed) => seed.id === seedId) || null
        },

        findSeedByUrl(url) {
            return this.seedSources.find((seed) => seed.url === url) || null
        },

        guessSeedName(url) {
            try {
                const parsed = new URL(String(url || '').trim())
                const slug = parsed.pathname.split('/').filter(Boolean).pop()
                return slug ? this.$t('iptv.seedWithSlug', { slug }) : parsed.host
            } catch (_error) {
                return this.$t('iptv.customSeed')
            }
        },

        async loadPlaylistFromSeed(seedId) {
            const source = this.findSeedById(seedId)
            if (!source) {
                return
            }

            this.playlistUrl = source.url
            await this.fetchPlaylistByUrl(source.url, source.name, source.id)
        },

        addCustomSeedSource() {
            const url = String(this.newSeedUrl || '').trim()
            const name = String(this.newSeedName || '').trim() || this.guessSeedName(url)

            if (!this.isHttpUrl(url) && !this.isHttpsUrl(url)) {
                this.playlistError = this.$t('iptv.seedUrlInvalid')
                return
            }

            const duplicateByUrl = this.findSeedByUrl(url)
            if (duplicateByUrl) {
                this.playlistError = this.$t('iptv.seedAlreadyAdded')
                this.activeSeedId = duplicateByUrl.id
                return
            }

            const seedId = `custom-${this.buildStableChannelId(url)}`
            this.customSeedSources = [
                {
                    id: seedId,
                    name: name.slice(0, 80),
                    url,
                },
                ...this.customSeedSources,
            ].slice(0, IPTV_CUSTOM_SEEDS_LIMIT)

            this.newSeedName = ''
            this.newSeedUrl = ''
            this.playlistError = ''
            this.persistCustomSeedSources()
        },

        removeCustomSeedSource(seedId) {
            if (!seedId) {
                return
            }

            this.customSeedSources = this.customSeedSources.filter((seed) => seed.id !== seedId)

            if (this.activeSeedId === seedId) {
                this.activeSeedId = ''
            }

            this.persistCustomSeedSources()
        },

        normalizeVolume(value) {
            const parsed = Number(value)
            if (!Number.isFinite(parsed)) {
                return 1
            }

            return Math.min(1, Math.max(0, parsed))
        },

        boolLabel(value) {
            return value ? this.$t('iptv.yes') : this.$t('iptv.no')
        },

        handleGlobalKeydown(event) {
            if (!this.keyboardEnabled || !this.currentChannel) {
                return
            }

            if (this.isTypingContext(event)) {
                return
            }

            const key = String(event.key || '').toLowerCase()

            if (key === ' ') {
                event.preventDefault()
                this.togglePlaybackAction()
                return
            }

            if (key === 'arrowleft') {
                event.preventDefault()
                this.playPreviousChannel()
                return
            }

            if (key === 'arrowright') {
                event.preventDefault()
                this.playNextChannel()
                return
            }

            if (key === 'f') {
                event.preventDefault()
                this.toggleFullscreenAction()
                return
            }

            if (key === 'm') {
                event.preventDefault()
                this.toggleMuteAction()
            }
        },

        isTypingContext(event) {
            const target = event?.target
            if (!(target instanceof HTMLElement)) {
                return false
            }

            if (target.isContentEditable) {
                return true
            }

            const tag = String(target.tagName || '').toLowerCase()
            return tag === 'input' || tag === 'textarea' || tag === 'select'
        },

        playerRef() {
            return this.$refs.iptvPlayerRef || null
        },

        togglePlaybackAction() {
            this.playerRef()?.togglePlayback?.()
        },

        toggleMuteAction() {
            const player = this.playerRef()
            if (player?.toggleMute) {
                player.toggleMute()
                return
            }

            this.muted = !this.muted
        },

        toggleFullscreenAction() {
            this.playerRef()?.toggleFullscreen?.()
        },

        hideBrokenIcon(event) {
            const image = event?.target
            if (!(image instanceof HTMLImageElement)) {
                return
            }

            image.style.display = 'none'
        },

        channelMeta(channel) {
            const parts = []

            if (channel.group) {
                parts.push(channel.group)
            }

            if (channel.domain) {
                parts.push(channel.domain)
            }

            return parts.length > 0 ? parts.join(' · ') : this.$t('iptv.noMetadata')
        },

        isFavorite(channelId) {
            return this.favoriteIdSet.has(channelId)
        },

        toggleFavorite(channelId) {
            if (!channelId) {
                return
            }

            if (this.favoriteIdSet.has(channelId)) {
                this.favoriteChannelIds = this.favoriteChannelIds.filter((id) => id !== channelId)
                return
            }

            this.favoriteChannelIds = [channelId, ...this.favoriteChannelIds]
        },

        markChannelRecent(channelId) {
            if (!channelId) {
                return
            }

            this.recentChannelIds = [channelId, ...this.recentChannelIds.filter((id) => id !== channelId)]
                .slice(0, IPTV_RECENT_LIMIT)
        },

        syncSavedIdsWithPlaylist() {
            const ids = new Set(this.channels.map((channel) => channel.id))
            this.favoriteChannelIds = this.favoriteChannelIds.filter((id) => ids.has(id))
            this.recentChannelIds = this.recentChannelIds.filter((id) => ids.has(id))
        },

        playChannel(channelId) {
            const channel = this.channels.find((item) => item.id === channelId)
            if (!channel) {
                return
            }

            this.currentChannelId = channel.id
            this.markChannelRecent(channel.id)

            this.playerError = ''
            this.playerStatusMessage = ''
            this.selectedQuality = -1
            this.videoMeta = {
                width: 0,
                height: 0,
            }
            this.compatRecoveryState = {
                sourceUrl: String(this.playbackUrl || ''),
                attempts: 0,
            }
        },

        playNextChannel() {
            if (this.visibleChannels.length === 0) {
                return
            }

            const currentIndex = this.visibleChannels.findIndex((item) => item.id === this.currentChannelId)
            const nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % this.visibleChannels.length
            const nextChannel = this.visibleChannels[nextIndex]
            this.playChannel(nextChannel.id)
        },

        playPreviousChannel() {
            if (this.visibleChannels.length === 0) {
                return
            }

            const currentIndex = this.visibleChannels.findIndex((item) => item.id === this.currentChannelId)
            const previousIndex = currentIndex < 0
                ? 0
                : (currentIndex - 1 + this.visibleChannels.length) % this.visibleChannels.length

            const previousChannel = this.visibleChannels[previousIndex]
            this.playChannel(previousChannel.id)
        },

        handlePlayerStatus(payload) {
            this.playerStatus = String(payload?.status || 'idle')
            this.playerStatusMessage = String(payload?.message || '')

            if (this.playerStatus !== 'error') {
                this.playerError = ''
            }
        },

        handleQualityOptions(options) {
            this.qualityOptions = Array.isArray(options) ? options : []

            if (!this.qualityOptions.some((item) => item.value === this.selectedQuality)) {
                this.selectedQuality = -1
            }
        },

        async handlePlayerError(payload) {
            this.playerError = String(payload?.message || this.$t('iptv.playbackError'))
            const errorMessage = String(payload?.message || '')
            const errorDetails = String(payload?.details || '')
            const errorType = String(payload?.type || '')
            const sourceUrl = String(this.playbackUrl || '').trim()

            const isCorsLikeError = this.isCorsLikeError(errorMessage)
                || this.isCorsLikeError(errorDetails)
                || this.isCorsLikeError(errorType)
            const isOriginBlockedError = this.isOriginBlockedLikeError(errorMessage)
                || this.isOriginBlockedLikeError(errorDetails)
                || this.isOriginBlockedLikeError(errorType)
            const shouldUseProxyFallback = isCorsLikeError || isOriginBlockedError

            const isCompatibilityLikeError = this.isCodecCompatibilityError(errorMessage)
                || this.isCodecCompatibilityError(errorDetails)
                || this.isCodecCompatibilityError(errorType)
                || errorType === 'video-frame-timeout'
                || errorType === 'mixed-content-blocked'
            const shouldAutoCompatFallback = isCompatibilityLikeError && errorType !== 'video-frame-timeout'

            if (this.compatModeEnabled) {
                if (!isCompatibilityLikeError || sourceUrl === '' || this.transcodeBusy) {
                    return
                }

                if (this.compatRecoveryState.sourceUrl !== sourceUrl) {
                    this.compatRecoveryState = {
                        sourceUrl,
                        attempts: 0,
                    }
                }

                const attempts = Number(this.compatRecoveryState.attempts || 0)

                if (attempts === 0 && this.compatProfile !== 'stable') {
                    this.compatRecoveryState = {
                        sourceUrl,
                        attempts: 1,
                    }
                    this.transcodeError = this.$t('iptv.compatRetryStable')
                    this.compatProfile = 'stable'
                    return
                }

                if (attempts <= 1) {
                    this.compatRecoveryState = {
                        sourceUrl,
                        attempts: 2,
                    }
                    this.transcodeError = this.$t('iptv.compatNoVideoFallback')
                    await this.stopCompatibilityMode()
                }

                return
            }

            if (this.proxyModeEnabled && shouldUseProxyFallback && sourceUrl !== '') {
                await this.stopProxyMode()
                this.proxyError = this.$t('iptv.proxyFailedFallbackDirect')
                return
            }

            if (this.proxyModeEnabled && shouldAutoCompatFallback && this.autoCompatOnCodecError && this.canUseServerTranscode && !this.transcodeBusy && sourceUrl !== '') {
                const now = Date.now()
                if ((now - Number(this.autoCompatLastAttemptAt || 0)) < 20000) {
                    return
                }

                if (this.isTranscodeUnavailableForSource(sourceUrl)) {
                    this.transcodeError = this.$t('iptv.compatUnavailableForChannel')
                    return
                }

                this.transcodeError = this.$t('iptv.compatAutoEnableCodec')
                this.autoCompatLastAttemptAt = now
                await this.startCompatibilityMode()
                return
            }

            if (
                shouldUseProxyFallback
                && !this.proxyModeEnabled
                && !this.proxyBusy
                && sourceUrl !== ''
                && (
                    this.autoProxyLastAttemptUrl !== sourceUrl
                    || (Date.now() - Number(this.autoProxyLastAttemptAt || 0)) > 12000
                )
            ) {
                this.autoProxyLastAttemptUrl = sourceUrl
                this.autoProxyLastAttemptAt = Date.now()
                this.proxyError = this.$t('iptv.proxyAutoEnable')
                const proxyStarted = await this.startProxyMode()
                if (proxyStarted) {
                    this.proxyError = ''
                    return
                }
            }

            if (!this.autoCompatOnCodecError || !this.canUseServerTranscode) {
                return
            }

            if (sourceUrl === '' || this.autoCompatLastAttemptUrl === sourceUrl) {
                return
            }

            const shouldFallback = shouldAutoCompatFallback

            if (!shouldFallback) {
                return
            }

            if (this.isTranscodeUnavailableForSource(sourceUrl)) {
                this.transcodeError = this.$t('iptv.compatUnavailableForChannel')
                return
            }

            const now = Date.now()
            if ((now - Number(this.autoCompatLastAttemptAt || 0)) < 20000) {
                return
            }

            this.autoCompatLastAttemptUrl = sourceUrl
            this.autoCompatLastAttemptAt = now
            this.transcodeError = this.$t('iptv.compatAutoEnableCodec')
            await this.startCompatibilityMode()
        },

        handlePlayerDiagnostics(payload) {
            this.playerDiagnostics = payload && typeof payload === 'object' ? payload : null
        },

        handleVideoMeta(payload) {
            this.videoMeta = {
                width: Number(payload?.width || 0),
                height: Number(payload?.height || 0),
            }
        },

        handlePlayerVolume(payload) {
            this.volumeLevel = this.normalizeVolume(payload?.volume)
            this.muted = Boolean(payload?.muted)
        },

        handleVolumeSlider(event) {
            const nextVolume = this.normalizeVolume(event?.target?.value)
            this.volumeLevel = nextVolume

            if (nextVolume > 0 && this.muted) {
                this.muted = false
            }
        },

        shouldUpgradeToHttps(url) {
            return this.preferHttpsUpgrade && this.isPageHttps && this.isHttpUrl(url)
        },

        forceHttpsUrl(url) {
            const normalized = String(url || '')
            if (!this.isHttpUrl(normalized)) {
                return normalized
            }

            return `https://${normalized.slice('http://'.length)}`
        },

        async fetchPlaylistByUrl(url, sourceLabel = '', seedId = '') {
            const normalizedUrl = String(url || '').trim()
            if (normalizedUrl === '') {
                this.playlistError = this.$t('iptv.playlistUrlRequired')
                return
            }

            if (!this.isHttpUrl(normalizedUrl) && !this.isHttpsUrl(normalizedUrl)) {
                this.playlistError = this.$t('iptv.playlistUrlInvalid')
                return
            }

            this.isLoadingPlaylist = true
            this.playlistError = ''

            try {
                const response = await axios.post('/api/iptv/playlist/fetch', {
                    url: normalizedUrl,
                })

                const playlist = String(response.data?.data?.playlist || '')
                const matchedSeed = seedId ? this.findSeedById(seedId) : this.findSeedByUrl(normalizedUrl)

                this.activeSeedId = matchedSeed?.id || ''
                const resolvedSourceLabel = String(sourceLabel || matchedSeed?.name || normalizedUrl)
                this.applyParsedChannels(playlist, resolvedSourceLabel)
            } catch (error) {
                this.playlistError = error.response?.data?.message || this.$t('iptv.playlistLoadFailed')
            } finally {
                this.isLoadingPlaylist = false
            }
        },

        async loadPlaylistFromUrl() {
            await this.fetchPlaylistByUrl(this.playlistUrl, this.playlistUrl)
        },

        async loadPlaylistFromFile(event) {
            const input = event?.target
            const file = input?.files?.[0]

            if (!file) {
                return
            }

            this.isLoadingPlaylist = true
            this.playlistError = ''

            try {
                const playlist = await file.text()
                this.applyParsedChannels(playlist, file.name || this.$t('iptv.localFile'))
                this.activeSeedId = ''
            } catch (_error) {
                this.playlistError = this.$t('iptv.playlistReadFailed')
            } finally {
                this.isLoadingPlaylist = false
                if (input) {
                    input.value = ''
                }
            }
        },

        playDirectStream() {
            const url = String(this.directStreamUrl || '').trim()

            if (!this.isHttpUrl(url) && !this.isHttpsUrl(url)) {
                this.playlistError = this.$t('iptv.directUrlInvalid')
                return
            }

            this.playlistError = ''

            const channel = this.createChannel({
                name: this.$t('iptv.directStreamName'),
                url,
                group: this.$t('iptv.manualGroup'),
                logo: '',
            }, this.channels.length)

            const withoutDuplicate = this.channels.filter((item) => item.id !== channel.id)
            this.channels = [channel, ...withoutDuplicate]
            this.currentChannelId = channel.id
            this.sourceLabel = this.$t('iptv.directLinkSource')
            this.activeSeedId = ''
            this.selectedGroup = 'all'
            this.selectedQuality = -1
            this.markChannelRecent(channel.id)
            this.syncSavedIdsWithPlaylist()
        },

        clearPlaylist() {
            this.stopServerTranscodeSession()
            this.stopServerProxySession()
            this.channels = []
            this.currentChannelId = ''
            this.playlistError = ''
            this.searchQuery = ''
            this.sourceLabel = this.$t('iptv.sourceNotSelected')
            this.activeSeedId = ''
            this.selectedGroup = 'all'
            this.selectedQuality = -1
            this.qualityOptions = []
            this.playerError = ''
            this.playerStatus = 'idle'
            this.playerStatusMessage = ''
            this.copiedChannelId = ''
            this.playerDiagnostics = null

            const fileInput = this.$refs.fileInput
            if (fileInput) {
                fileInput.value = ''
            }
        },

        applyParsedChannels(playlistText, sourceLabel) {
            const parsedChannels = this.parseM3uPlaylist(playlistText)

            if (parsedChannels.length === 0) {
                this.channels = []
                this.currentChannelId = ''
                this.playlistError = this.$t('iptv.noValidChannels')
                this.sourceLabel = sourceLabel || this.$t('iptv.sourceNotSelected')
                return
            }

            this.channels = parsedChannels
            this.sourceLabel = sourceLabel || this.$t('iptv.unknownSource')
            this.selectedGroup = 'all'
            this.selectedQuality = -1
            this.playerError = ''
            this.playerStatus = 'idle'
            this.playerStatusMessage = ''
            this.copiedChannelId = ''

            this.syncSavedIdsWithPlaylist()

            const preferredChannel = parsedChannels.find((channel) => this.recentIdSet.has(channel.id)) || parsedChannels[0]
            this.playChannel(preferredChannel.id)
        },

        parseM3uPlaylist(playlistText) {
            const lines = String(playlistText || '')
                .replace(/\r/g, '')
                .split('\n')

            const channels = []
            let pendingMeta = null
            let pendingGroup = ''

            for (const rawLine of lines) {
                const line = rawLine.trim()

                if (line === '') {
                    continue
                }

                if (line.startsWith('#EXTGRP:')) {
                    pendingGroup = line.slice('#EXTGRP:'.length).trim()
                    continue
                }

                if (line.startsWith('#EXTINF')) {
                    pendingMeta = this.parseExtinfLine(line, pendingGroup)
                    pendingGroup = ''
                    continue
                }

                if (line.startsWith('#')) {
                    continue
                }

                if (!this.isHttpUrl(line) && !this.isHttpsUrl(line)) {
                    pendingMeta = null
                    pendingGroup = ''
                    continue
                }

                const nextChannel = this.createChannel({
                    ...(pendingMeta || {}),
                    group: String(pendingMeta?.group || pendingGroup || ''),
                    url: line,
                }, channels.length)

                channels.push(nextChannel)
                pendingMeta = null
                pendingGroup = ''

                if (channels.length >= 2000) {
                    break
                }
            }

            const uniqueByUrl = new Map()
            for (const channel of channels) {
                if (!uniqueByUrl.has(channel.url)) {
                    uniqueByUrl.set(channel.url, channel)
                }
            }

            return Array.from(uniqueByUrl.values())
        },

        parseExtinfLine(line, fallbackGroup = '') {
            const payload = String(line || '')
            const separatorIndex = payload.indexOf(',')

            const metaPart = separatorIndex === -1 ? payload : payload.slice(0, separatorIndex)
            const titlePart = separatorIndex === -1 ? '' : payload.slice(separatorIndex + 1).trim()

            const attributes = {}
            const attributePattern = /([\w-]+)="([^"]*)"/g

            let match = null
            while ((match = attributePattern.exec(metaPart)) !== null) {
                attributes[String(match[1]).toLowerCase()] = String(match[2]).trim()
            }

            return {
                name: titlePart || attributes['tvg-name'] || this.$t('iptv.untitledChannel'),
                group: attributes['group-title'] || fallbackGroup || '',
                logo: attributes['tvg-logo'] || '',
            }
        },

        createChannel(meta, index) {
            const url = String(meta?.url || '').trim()
            const parsed = this.parseUrlParts(url)
            const name = String(meta?.name || '').trim() || this.$t('iptv.channelWithIndex', { index: index + 1 })
            const protocol = parsed.protocol

            return {
                id: this.buildStableChannelId(url),
                name,
                url,
                group: String(meta?.group || '').trim(),
                logo: this.normalizeLogoUrl(meta?.logo),
                domain: parsed.domain,
                protocol,
                isSecure: protocol === 'https',
            }
        },

        normalizeLogoUrl(value) {
            const logoUrl = String(value || '').trim()
            if (logoUrl === '') {
                return ''
            }

            if (!this.isHttpUrl(logoUrl) && !this.isHttpsUrl(logoUrl)) {
                return ''
            }

            if (this.isPageHttps && this.isHttpUrl(logoUrl)) {
                return ''
            }

            return logoUrl
        },

        buildStableChannelId(url) {
            const source = String(url || '')
            let hash = 0

            for (let index = 0; index < source.length; index += 1) {
                hash = ((hash << 5) - hash) + source.charCodeAt(index)
                hash |= 0
            }

            const suffix = Math.abs(hash).toString(36)
            return `ch-${suffix}-${source.length.toString(36)}`
        },

        parseUrlParts(url) {
            try {
                const parsed = new URL(String(url || '').trim())
                return {
                    domain: parsed.host,
                    protocol: String(parsed.protocol || '').replace(':', ''),
                }
            } catch (_error) {
                return {
                    domain: '',
                    protocol: '',
                }
            }
        },

        isHttpUrl(value) {
            try {
                const parsed = new URL(String(value || '').trim())
                return parsed.protocol === 'http:'
            } catch (_error) {
                return false
            }
        },

        isHttpsUrl(value) {
            try {
                const parsed = new URL(String(value || '').trim())
                return parsed.protocol === 'https:'
            } catch (_error) {
                return false
            }
        },

        async copyStreamUrl(channel) {
            const url = String(channel?.url || '').trim()
            if (url === '') {
                return
            }

            try {
                if (typeof navigator === 'undefined' || !navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                    throw new Error('Clipboard API unavailable')
                }

                await navigator.clipboard.writeText(url)
                this.copiedChannelId = channel.id
                window.setTimeout(() => {
                    if (this.copiedChannelId === channel.id) {
                        this.copiedChannelId = ''
                    }
                }, 1200)
            } catch (_error) {
                this.playlistError = this.$t('iptv.copyFailed')
            }
        },
    },
}
</script>
