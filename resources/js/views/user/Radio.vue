<template>
    <div class="page-wrap grid-layout">
        <section class="section-card radio-hero-card">
            <h2 class="section-title">{{ $t('radio.title') }}</h2>
            <p class="section-subtitle">{{ $t('radio.subtitle') }}</p>
            <p class="muted radio-legal-note">{{ $t('radio.legalNote') }}</p>

            <div class="radio-category-chips">
                <button
                    v-for="item in featuredCategoryOptions"
                    :key="`featured-category-${item.id}`"
                    type="button"
                    class="radio-category-chip"
                    :class="{ 'radio-category-chip--active': featuredCategory === item.id }"
                    @click="featuredCategory = item.id"
                >
                    {{ item.label }} ({{ item.count }})
                </button>
            </div>

            <div class="radio-featured-controls">
                <button
                    class="btn btn-outline btn-sm"
                    type="button"
                    :disabled="isCheckingFeatured"
                    @click="checkFeaturedPresets"
                >
                    {{ isCheckingFeatured ? $t('radio.featuredChecking') : $t('radio.featuredCheckAll') }}
                </button>
            </div>

            <div class="radio-featured-grid">
                <article
                    v-for="preset in visibleFeaturedPresets"
                    :key="`radio-preset-${preset.id}`"
                    class="radio-featured-card"
                >
                    <div class="radio-featured-head">
                        <strong>{{ preset.name }}</strong>
                        <span class="badge">{{ preset.shortLabel }}</span>
                    </div>

                    <p class="muted radio-featured-hint">{{ preset.hint }}</p>
                    <div
                        v-if="preset.country || preset.language || preset.tag"
                        class="radio-featured-meta"
                    >
                        <span v-if="preset.country" class="radio-featured-pill">{{ preset.country }}</span>
                        <span v-if="preset.language" class="radio-featured-pill">{{ preset.language }}</span>
                        <span v-if="preset.tag" class="radio-featured-pill">{{ preset.tag }}</span>
                    </div>
                    <p class="muted radio-featured-query">
                        {{ $t('radio.featuredQueryLabel') }}: {{ preset.query }}
                    </p>

                    <p
                        v-if="featuredErrorMap[preset.id]"
                        class="error-text radio-featured-status"
                    >
                        {{ featuredErrorMap[preset.id] }}
                    </p>
                    <p
                        v-else-if="featuredStationMap[preset.id]"
                        class="muted radio-featured-status"
                    >
                        {{ stationMeta(featuredStationMap[preset.id]) }}
                    </p>

                    <div class="radio-actions">
                        <button
                            class="btn btn-primary btn-sm"
                            type="button"
                            :disabled="isFeaturedLoading(preset.id)"
                            @click="playFeaturedPreset(preset)"
                        >
                            {{ isFeaturedLoading(preset.id) ? $t('radio.featuredLoading') : $t('radio.listen') }}
                        </button>
                        <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            :disabled="isFeaturedLoading(preset.id)"
                            @click="refreshFeaturedPreset(preset)"
                        >
                            {{ $t('radio.featuredRefresh') }}
                        </button>
                        <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            :disabled="isFeaturedLoading(preset.id)"
                            @click="toggleFeaturedFavorite(preset)"
                        >
                            {{
                                featuredStationMap[preset.id] && isFavorite(featuredStationMap[preset.id].station_uuid)
                                    ? $t('common.remove')
                                    : $t('common.favorites')
                            }}
                        </button>
                        <a
                            v-if="preset.homepage"
                            class="btn btn-outline btn-sm"
                            :href="preset.homepage"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ $t('radio.stationWebsite') }}
                        </a>
                    </div>
                </article>
            </div>

            <div class="radio-search-panel">
                <p class="radio-search-title">{{ $t('radio.searchPanelTitle') }}</p>

                <form class="form-grid" @submit.prevent="searchStations">
                    <input
                        class="input-field"
                        v-model.trim="filters.q"
                        type="text"
                        :placeholder="$t('radio.searchPlaceholder')"
                    >

                    <div class="radio-filters-row">
                        <input class="input-field" v-model.trim="filters.country" type="text" :placeholder="$t('radio.countryPlaceholder')">
                        <input class="input-field" v-model.trim="filters.language" type="text" :placeholder="$t('radio.languagePlaceholder')">
                        <input class="input-field" v-model.trim="filters.tag" type="text" :placeholder="$t('radio.tagPlaceholder')">
                    </div>

                    <div class="radio-search-actions">
                        <button class="btn btn-primary" type="submit" :disabled="isLoadingStations">
                            {{ isLoadingStations ? $t('radio.searching') : $t('radio.searchButton') }}
                        </button>
                        <button class="btn btn-outline" type="button" @click="resetFilters" :disabled="isLoadingStations">{{ $t('radio.resetFilters') }}</button>
                    </div>
                </form>
            </div>

            <p v-if="stationsError" class="error-text">{{ stationsError }}</p>
            <p v-if="radioNotice" class="muted radio-notice">{{ radioNotice }}</p>
        </section>

        <section class="section-card radio-now-card" v-if="currentStation">
            <h3 class="section-title" style="font-size: 1.1rem; margin-bottom: 0.45rem;">{{ $t('radio.nowPlaying') }}</h3>
            <div class="radio-now-top">
                <div class="radio-station-head">
                    <img
                        v-if="currentStation.favicon"
                        :src="currentStation.favicon"
                        alt="station icon"
                        class="radio-station-icon"
                        @error="hideBrokenIcon"
                    >
                    <span v-else class="avatar avatar-sm avatar-placeholder">♪</span>
                    <div>
                        <strong>{{ currentStation.name || $t('radio.untitled') }}</strong>
                        <p class="muted" style="margin: 0.2rem 0 0; font-size: 0.82rem;">
                            {{ stationMeta(currentStation) }}
                        </p>
                    </div>
                </div>

                <div class="radio-now-badges">
                    <span class="badge radio-now-status-badge" :class="`radio-now-status-badge--${playbackStatusTone}`">
                        {{ playbackStatusLabel }}
                    </span>
                    <span class="badge radio-now-info-badge">
                        {{ $t('radio.onSiteFor', { time: stationSessionLabel }) }}
                    </span>
                    <span class="badge radio-now-info-badge">
                        {{ playbackTimeLabel }}
                    </span>
                </div>
            </div>

            <div class="radio-now-meta-grid">
                <div v-if="currentStation.country" class="radio-now-meta-item">
                    <span class="radio-now-meta-label">{{ $t('radio.metaCountry') }}</span>
                    <strong>{{ currentStation.country }}</strong>
                </div>
                <div v-if="currentStation.language" class="radio-now-meta-item">
                    <span class="radio-now-meta-label">{{ $t('radio.metaLanguage') }}</span>
                    <strong>{{ currentStation.language }}</strong>
                </div>
                <div v-if="currentStation.codec" class="radio-now-meta-item">
                    <span class="radio-now-meta-label">{{ $t('radio.metaCodec') }}</span>
                    <strong>{{ currentStation.codec }}</strong>
                </div>
                <div v-if="Number(currentStation.bitrate || 0) > 0" class="radio-now-meta-item">
                    <span class="radio-now-meta-label">{{ $t('radio.metaBitrate') }}</span>
                    <strong>{{ currentStation.bitrate }} kbps</strong>
                </div>
                <div v-if="Number(currentStation.votes || 0) > 0" class="radio-now-meta-item">
                    <span class="radio-now-meta-label">{{ $t('radio.metaVotes') }}</span>
                    <strong>{{ currentStationVotesLabel }}</strong>
                </div>
                <div v-if="currentStationStreamHost" class="radio-now-meta-item">
                    <span class="radio-now-meta-label">{{ $t('radio.metaStreamHost') }}</span>
                    <strong>{{ currentStationStreamHost }}</strong>
                </div>
            </div>

            <p v-if="currentStationTagsText" class="muted radio-now-tags">
                {{ $t('radio.metaTags') }}: {{ currentStationTagsText }}
            </p>

            <MediaPlayer
                v-if="!isWidgetPlaybackBridgeEnabled"
                ref="radioPlayer"
                type="audio"
                :src="playableCurrentStationStreamUrl"
                player-class="media-audio"
                :mime-type="currentStation.codec ? `audio/${String(currentStation.codec).toLowerCase()}` : ''"
            ></MediaPlayer>

            <p v-else class="muted radio-sync-note">
                {{ $t('radio.syncedWithWidget') }}
            </p>

            <div class="radio-now-actions">
                <button class="btn btn-outline btn-sm" type="button" @click="toggleCurrentPlayback">
                    {{ playbackState.isPlaying ? $t('radio.pause') : $t('radio.play') }}
                </button>
                <button class="btn btn-outline btn-sm" type="button" @click="toggleFavorite(currentStation)">
                    {{ isFavorite(currentStation.station_uuid) ? $t('radio.removeFromFavorites') : $t('radio.addToFavorites') }}
                </button>
                <a
                    v-if="currentStation.homepage"
                    :href="currentStation.homepage"
                    class="btn btn-outline btn-sm"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ $t('radio.stationWebsite') }}
                </a>
            </div>

            <p v-if="autoplayNotice" class="muted" style="margin: 0.5rem 0 0; font-size: 0.78rem;">
                {{ autoplayNotice }}
            </p>
        </section>

        <section class="section-card radio-favorites-card">
            <div class="radio-section-head">
                <h3 class="section-title" style="font-size: 1.1rem; margin: 0;">{{ $t('radio.favoriteStations') }}</h3>
                <span class="badge">{{ favorites.length }}</span>
            </div>

            <p v-if="isLoadingFavorites" class="muted" style="margin: 0;">{{ $t('radio.loadingFavorites') }}</p>
            <p v-else-if="favorites.length === 0" class="muted" style="margin: 0;">{{ $t('radio.emptyFavorites') }}</p>

            <div v-else class="simple-list radio-favorites-list">
                <div v-for="favorite in favorites" :key="`favorite-${favorite.station_uuid}`" class="simple-item radio-favorite-item">
                    <div class="radio-station-head">
                        <img
                            v-if="favorite.favicon"
                            :src="favorite.favicon"
                            alt="favorite icon"
                            class="radio-station-icon"
                            @error="hideBrokenIcon"
                        >
                        <span v-else class="avatar avatar-sm avatar-placeholder">♪</span>
                        <div>
                            <strong>{{ favorite.name || $t('radio.untitled') }}</strong>
                            <p class="muted radio-favorite-meta">{{ stationMeta(favorite) }}</p>
                        </div>
                    </div>

                    <div class="radio-favorite-controls">
                        <div class="radio-actions">
                            <button class="btn btn-primary btn-sm" type="button" @click="playStation(favorite)">{{ $t('radio.listen') }}</button>
                            <button
                                class="btn btn-danger btn-sm"
                                type="button"
                                @click="toggleFavorite(favorite)"
                                :disabled="isFavoriteSaving(favorite.station_uuid)"
                            >
                                {{ $t('common.delete') }}
                            </button>
                        </div>

                        <div v-if="isCurrentStation(favorite)" class="radio-favorite-mini-player">
                            <button class="btn btn-outline btn-sm" type="button" @click="toggleCurrentPlayback">
                                {{ playbackState.isPlaying ? $t('radio.pause') : $t('radio.play') }}
                            </button>
                            <input
                                class="radio-mini-progress"
                                type="range"
                                :min="0"
                                :max="playbackSeekMax"
                                :value="playbackSeekValue"
                                step="1"
                                :disabled="!isPlaybackSeekEnabled"
                                @input="onPlaybackSeekInput"
                                @change="onPlaybackSeekInput"
                            >
                            <span class="muted radio-mini-time">{{ playbackTimeLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-card">
            <div class="radio-section-head">
                <h3 class="section-title" style="font-size: 1.1rem; margin: 0;">{{ $t('radio.foundStations') }}</h3>
                <span class="badge">{{ stations.length }}</span>
            </div>

            <p v-if="isLoadingStations" class="muted" style="margin: 0;">{{ $t('radio.loadingStations') }}</p>
            <p v-else-if="!hasSearchResults" class="muted" style="margin: 0;">{{ $t('radio.useSearchHint') }}</p>
            <p v-else-if="stations.length === 0" class="muted" style="margin: 0;">{{ $t('radio.emptySearch') }}</p>

            <div v-else class="radio-stations-grid">
                <article v-for="station in stations" :key="`station-${station.station_uuid}`" class="radio-station-card">
                    <div class="radio-station-head">
                        <img
                            v-if="station.favicon"
                            :src="station.favicon"
                            alt="station icon"
                            class="radio-station-icon"
                            @error="hideBrokenIcon"
                        >
                        <span v-else class="avatar avatar-sm avatar-placeholder">♪</span>
                        <div>
                            <strong>{{ station.name || $t('radio.untitled') }}</strong>
                            <p class="muted radio-station-meta">{{ stationMeta(station) }}</p>
                        </div>
                    </div>

                    <p class="muted radio-station-tags">
                        {{ station.tags || $t('radio.noTags') }}
                    </p>

                    <div class="radio-actions">
                        <button
                            class="btn btn-primary btn-sm"
                            type="button"
                            @click="playStation(station)"
                            :disabled="!station.stream_url"
                        >
                            {{ $t('radio.listen') }}
                        </button>
                        <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            @click="toggleFavorite(station)"
                            :disabled="isFavoriteSaving(station.station_uuid)"
                        >
                            {{ isFavorite(station.station_uuid) ? $t('common.remove') : $t('common.favorites') }}
                        </button>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>

<script>
import MediaPlayer from '../../components/MediaPlayer.vue'
import { RADIO_PRESET_CATALOG } from '../../data/radioPresetCatalog'

const RADIO_FEATURED_PRESETS = RADIO_PRESET_CATALOG

const RADIO_FAVORITES_SYNC_EVENT = 'social:radio:favorites-updated'
const RADIO_FAVORITES_SYNC_SOURCE = 'radio-page'
const RADIO_PLAYBACK_SYNC_EVENT = 'social:radio:playback-sync'
const RADIO_PLAYBACK_READY_EVENT = 'social:radio:playback-ready'
const RADIO_PLAYBACK_SOURCE_PAGE = 'radio-page'
const RADIO_PLAYBACK_SOURCE_WIDGET = 'widget-radio'

export default {
    name: 'Radio',

    components: {
        MediaPlayer,
    },

    data() {
        return {
            filters: {
                q: '',
                country: '',
                language: '',
                tag: '',
            },
            stations: [],
            favorites: [],
            currentStation: null,
            isLoadingStations: false,
            isLoadingFavorites: false,
            hasSearchResults: false,
            stationsError: '',
            favoriteLoadingMap: {},
            autoplayNotice: '',
            featuredCategory: 'all',
            featuredStationMap: {},
            featuredLoadingMap: {},
            featuredErrorMap: {},
            isCheckingFeatured: false,
            radioNotice: '',
            stationSessionStartedAt: 0,
            uiNowTimestamp: Date.now(),
            uiNowTimerId: null,
            playbackState: {
                isPlaying: false,
                currentTime: 0,
                duration: 0,
            },
            widgetPlaybackBridgeReady: false,
            boundPlayer: null,
            boundPlayerEvents: [],
        }
    },

    async mounted() {
        if (typeof window !== 'undefined') {
            window.addEventListener(RADIO_FAVORITES_SYNC_EVENT, this.handleWidgetFavoritesSync)
            window.addEventListener(RADIO_PLAYBACK_SYNC_EVENT, this.handleWidgetPlaybackSync)
            window.addEventListener(RADIO_PLAYBACK_READY_EVENT, this.handleWidgetPlaybackReady)
            this.widgetPlaybackBridgeReady = Boolean(window.__socialRadioWidgetReady)
        }

        this.startUiTicker()
        await this.loadFavorites()

        if (this.widgetPlaybackBridgeReady) {
            this.requestWidgetPlaybackState()
        }
    },

    beforeUnmount() {
        this.stopUiTicker()
        this.unbindPlayerStateEvents()

        if (typeof window !== 'undefined') {
            window.removeEventListener(RADIO_FAVORITES_SYNC_EVENT, this.handleWidgetFavoritesSync)
            window.removeEventListener(RADIO_PLAYBACK_SYNC_EVENT, this.handleWidgetPlaybackSync)
            window.removeEventListener(RADIO_PLAYBACK_READY_EVENT, this.handleWidgetPlaybackReady)
        }
    },

    computed: {
        featuredPresetList() {
            return RADIO_FEATURED_PRESETS
        },

        isWidgetPlaybackBridgeEnabled() {
            return this.widgetPlaybackBridgeReady
        },

        featuredCategoryOptions() {
            const labels = {
                all: this.$t('radio.categoryAll'),
                ru_kz: this.$t('radio.categoryRuKz'),
                dance: this.$t('radio.categoryDance'),
                rock_jazz: this.$t('radio.categoryRockJazz'),
                world: this.$t('radio.categoryWorld'),
            }

            return Object.keys(labels).map((categoryId) => {
                if (categoryId === 'all') {
                    return {
                        id: categoryId,
                        label: labels[categoryId],
                        count: this.featuredPresetList.length,
                    }
                }

                const count = this.featuredPresetList.filter((preset) => preset.category === categoryId).length
                return {
                    id: categoryId,
                    label: labels[categoryId],
                    count,
                }
            })
        },

        visibleFeaturedPresets() {
            if (this.featuredCategory === 'all') {
                return this.featuredPresetList
            }

            return this.featuredPresetList.filter((preset) => preset.category === this.featuredCategory)
        },

        isPlaybackSeekEnabled() {
            return Number.isFinite(this.playbackState.duration) && this.playbackState.duration > 0
        },

        playbackSeekMax() {
            return this.isPlaybackSeekEnabled ? Math.max(1, Math.floor(this.playbackState.duration)) : 100
        },

        playbackSeekValue() {
            if (!this.isPlaybackSeekEnabled) {
                return 100
            }

            const value = Math.floor(this.playbackState.currentTime)
            return Math.min(this.playbackSeekMax, Math.max(0, value))
        },

        playbackTimeLabel() {
            const current = this.formatPlaybackTime(this.playbackState.currentTime)
            if (!this.isPlaybackSeekEnabled) {
                return `${current} · ${this.$t('radio.live')}`
            }

            const duration = this.formatPlaybackTime(this.playbackState.duration)
            return `${current} / ${duration}`
        },

        playbackStatusTone() {
            return this.playbackState.isPlaying ? 'playing' : 'paused'
        },

        playbackStatusLabel() {
            return this.playbackState.isPlaying ? this.$t('radio.statusPlaying') : this.$t('radio.statusPaused')
        },

        stationSessionLabel() {
            if (!this.currentStation || this.stationSessionStartedAt <= 0) {
                return this.formatPlaybackTime(0)
            }

            const diffSeconds = Math.max(0, Math.floor((this.uiNowTimestamp - this.stationSessionStartedAt) / 1000))
            return this.formatPlaybackTime(diffSeconds)
        },

        currentStationStreamHost() {
            const rawUrl = String(this.currentStation?.stream_url || '').trim()
            if (rawUrl === '') {
                return ''
            }

            try {
                return String(new URL(rawUrl).host || '')
            } catch (_error) {
                return ''
            }
        },

        playableCurrentStationStreamUrl() {
            return this.buildPlayableStreamUrl(this.currentStation?.stream_url)
        },

        currentStationVotesLabel() {
            const votes = Number(this.currentStation?.votes || 0)
            if (!Number.isFinite(votes) || votes <= 0) {
                return ''
            }

            return votes.toLocaleString()
        },

        currentStationTagsText() {
            const tagsRaw = String(this.currentStation?.tags || '').trim()
            if (tagsRaw === '') {
                return ''
            }

            const normalizedTags = tagsRaw
                .split(/[;,]/)
                .map((item) => item.trim())
                .filter((item) => item !== '')

            if (normalizedTags.length === 0) {
                return ''
            }

            return normalizedTags.slice(0, 8).join(', ')
        },
    },

    methods: {
        setRadioNotice(message = '') {
            this.radioNotice = String(message || '').trim()
        },

        startUiTicker() {
            this.stopUiTicker()
            this.uiNowTimerId = window.setInterval(() => {
                this.uiNowTimestamp = Date.now()
            }, 1000)
        },

        stopUiTicker() {
            if (!this.uiNowTimerId) {
                return
            }

            window.clearInterval(this.uiNowTimerId)
            this.uiNowTimerId = null
        },

        formatPlaybackTime(value) {
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
        },

        getActivePlayer() {
            const componentRef = this.$refs.radioPlayer
            if (!componentRef || typeof componentRef !== 'object') {
                return null
            }

            return componentRef.player || null
        },

        updatePlaybackStateFromPlayer() {
            if (this.isWidgetPlaybackBridgeEnabled) {
                return
            }

            const player = this.getActivePlayer()
            if (!player) {
                this.playbackState = {
                    isPlaying: false,
                    currentTime: 0,
                    duration: 0,
                }
                return
            }

            const currentTime = Number(player.currentTime || 0)
            const durationRaw = Number(player.duration || 0)
            const duration = Number.isFinite(durationRaw) && durationRaw > 0 ? durationRaw : 0

            this.playbackState = {
                isPlaying: Boolean(player.playing),
                currentTime: Number.isFinite(currentTime) && currentTime > 0 ? currentTime : 0,
                duration,
            }
        },

        bindPlayerStateEvents() {
            if (this.isWidgetPlaybackBridgeEnabled) {
                this.unbindPlayerStateEvents()
                return
            }

            const player = this.getActivePlayer()
            if (!player || typeof player.on !== 'function') {
                return
            }

            if (this.boundPlayer === player) {
                this.updatePlaybackStateFromPlayer()
                return
            }

            this.unbindPlayerStateEvents()

            const events = ['ready', 'play', 'pause', 'timeupdate', 'loadedmetadata', 'ended', 'seeking', 'seeked']
            const handler = () => {
                this.updatePlaybackStateFromPlayer()
            }

            events.forEach((eventName) => {
                player.on(eventName, handler)
            })

            this.boundPlayer = player
            this.boundPlayerEvents = events.map((eventName) => ({
                eventName,
                handler,
            }))

            this.updatePlaybackStateFromPlayer()
        },

        unbindPlayerStateEvents() {
            if (this.boundPlayer && typeof this.boundPlayer.off === 'function' && Array.isArray(this.boundPlayerEvents)) {
                this.boundPlayerEvents.forEach(({ eventName, handler }) => {
                    this.boundPlayer.off(eventName, handler)
                })
            }

            this.boundPlayer = null
            this.boundPlayerEvents = []
        },

        isCurrentStation(station) {
            const currentUuid = String(this.currentStation?.station_uuid || '')
            const stationUuid = String(station?.station_uuid || '')
            return stationUuid !== '' && stationUuid === currentUuid
        },

        async toggleCurrentPlayback() {
            if (this.isWidgetPlaybackBridgeEnabled) {
                if (!this.currentStation?.stream_url) {
                    return
                }

                const command = this.playbackState.isPlaying ? 'pause' : 'play'
                this.dispatchWidgetPlaybackCommand(command, this.currentStation)
                this.playbackState = {
                    ...this.playbackState,
                    isPlaying: command === 'play',
                }
                this.autoplayNotice = ''
                return
            }

            const player = this.getActivePlayer()
            if (!player) {
                return
            }

            if (player.playing) {
                player.pause()
                this.updatePlaybackStateFromPlayer()
                return
            }

            try {
                const playResult = player.play()
                if (playResult && typeof playResult.then === 'function') {
                    await playResult
                }
                this.autoplayNotice = ''
            } catch (_error) {
                this.autoplayNotice = this.$t('radio.autoplayBlocked')
            }

            this.updatePlaybackStateFromPlayer()
        },

        onPlaybackSeekInput(event) {
            if (this.isWidgetPlaybackBridgeEnabled) {
                return
            }

            if (!this.isPlaybackSeekEnabled) {
                return
            }

            const player = this.getActivePlayer()
            if (!player) {
                return
            }

            const nextValue = Number(event?.target?.value)
            if (!Number.isFinite(nextValue)) {
                return
            }

            player.currentTime = Math.min(this.playbackSeekMax, Math.max(0, nextValue))
            this.updatePlaybackStateFromPlayer()
        },

        normalizeStationPayload(station) {
            return {
                station_uuid: String(station?.station_uuid || ''),
                name: String(station?.name || ''),
                stream_url: String(station?.stream_url || ''),
                homepage: String(station?.homepage || ''),
                favicon: String(station?.favicon || ''),
                country: String(station?.country || ''),
                language: String(station?.language || ''),
                tags: String(station?.tags || ''),
                codec: String(station?.codec || ''),
                bitrate: Number(station?.bitrate || 0),
                votes: Number(station?.votes || 0),
                is_favorite: Boolean(station?.is_favorite),
            }
        },

        buildPlayableStreamUrl(streamUrl) {
            const raw = String(streamUrl || '').trim()
            if (raw === '') {
                return ''
            }

            if (typeof window === 'undefined') {
                return raw
            }

            try {
                const parsed = new URL(raw, window.location.origin)
                const isSecurePage = window.location.protocol === 'https:'
                if (isSecurePage && parsed.protocol === 'http:') {
                    return `/api/radio/stream?url=${encodeURIComponent(parsed.href)}`
                }
            } catch (_error) {
                return raw
            }

            return raw
        },

        normalizeLookupText(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim()
        },

        sameHost(firstUrl, secondUrl) {
            try {
                const first = new URL(String(firstUrl || '').trim())
                const second = new URL(String(secondUrl || '').trim())
                return first.host !== '' && first.host === second.host
            } catch (_error) {
                return false
            }
        },

        isFeaturedLoading(presetId) {
            return Boolean(this.featuredLoadingMap[presetId])
        },

        setFeaturedLoading(presetId, isLoading) {
            this.featuredLoadingMap = {
                ...this.featuredLoadingMap,
                [presetId]: Boolean(isLoading),
            }
        },

        setFeaturedError(presetId, message = '') {
            this.featuredErrorMap = {
                ...this.featuredErrorMap,
                [presetId]: String(message || '').trim(),
            }
        },

        buildFeaturedLookupQueries(preset) {
            const rawQueries = [
                preset.query,
                preset.name,
                ...(Array.isArray(preset.keywords) ? preset.keywords : []),
            ]

            const unique = []
            rawQueries.forEach((query) => {
                const normalized = String(query || '').trim()
                if (normalized === '') {
                    return
                }
                if (!unique.includes(normalized)) {
                    unique.push(normalized)
                }
            })

            return unique.slice(0, 6)
        },

        buildFeaturedSearchParamsByQuery(query) {
            return {
                q: String(query || '').trim() || undefined,
                limit: 50,
                offset: 0,
            }
        },

        async fetchFeaturedStationsByParams(params) {
            const response = await axios.get('/api/radio/stations', {
                params,
            })

            return Array.isArray(response.data?.data) ? response.data.data : []
        },

        scoreStationForPreset(station, preset) {
            const lookup = this.normalizeLookupText([
                station.name,
                station.tags,
                station.country,
                station.language,
            ].join(' '))

            let score = 0
            const normalizedQuery = this.normalizeLookupText(preset.query)
            if (normalizedQuery !== '' && lookup.includes(normalizedQuery)) {
                score += 8
            }

            const keywords = Array.isArray(preset.keywords) ? preset.keywords : []
            keywords.forEach((keyword) => {
                const normalizedKeyword = this.normalizeLookupText(keyword)
                if (normalizedKeyword !== '' && lookup.includes(normalizedKeyword)) {
                    score += 3
                }
            })

            const normalizedCountry = this.normalizeLookupText(preset.country)
            if (normalizedCountry !== '' && this.normalizeLookupText(station.country).includes(normalizedCountry)) {
                score += 2.5
            }

            const normalizedLanguage = this.normalizeLookupText(preset.language)
            if (normalizedLanguage !== '' && this.normalizeLookupText(station.language).includes(normalizedLanguage)) {
                score += 1.5
            }

            const normalizedTag = this.normalizeLookupText(preset.tag)
            if (normalizedTag !== '' && this.normalizeLookupText(station.tags).includes(normalizedTag)) {
                score += 1.5
            }

            if (this.sameHost(station.homepage, preset.homepage)) {
                score += 2
            }

            score += Math.min(3, Math.max(0, Number(station.votes || 0) / 10000))
            score += Math.min(2, Math.max(0, Number(station.bitrate || 0) / 256))

            return score
        },

        pickFeaturedStation(stations, preset) {
            const normalizedStations = (Array.isArray(stations) ? stations : [])
                .map((station) => this.normalizeStationPayload(station))
                .filter((station) => station.station_uuid !== '' && station.stream_url !== '')

            if (normalizedStations.length === 0) {
                return null
            }

            const scored = normalizedStations
                .map((station) => ({
                    station,
                    score: this.scoreStationForPreset(station, preset),
                }))
                .sort((left, right) => right.score - left.score)

            return scored[0]?.station || null
        },

        async resolveFeaturedPresetStation(preset, options = {}) {
            const shouldPlay = Boolean(options?.play)
            const forceReload = Boolean(options?.force)
            const cached = this.featuredStationMap[preset.id]

            if (!forceReload && cached) {
                if (shouldPlay) {
                    await this.playStation(cached)
                }
                return cached
            }

            if (this.isFeaturedLoading(preset.id)) {
                return null
            }

            this.setFeaturedLoading(preset.id, true)
            this.setFeaturedError(preset.id, '')

            try {
                const stationByUuid = new Map()
                let hasSuccessfulRequest = false

                const lookupQueries = this.buildFeaturedLookupQueries(preset)
                for (const query of lookupQueries) {
                    try {
                        const stations = await this.fetchFeaturedStationsByParams(this.buildFeaturedSearchParamsByQuery(query))
                        hasSuccessfulRequest = true
                        stations.forEach((station) => {
                            const normalized = this.normalizeStationPayload(station)
                            if (normalized.station_uuid !== '' && normalized.stream_url !== '') {
                                stationByUuid.set(normalized.station_uuid, normalized)
                            }
                        })
                    } catch (_error) {
                        // continue with next query
                    }
                }

                if (stationByUuid.size === 0) {
                    try {
                        const stations = await this.fetchFeaturedStationsByParams({
                            q: undefined,
                            country: preset.country || undefined,
                            language: preset.language || undefined,
                            tag: preset.tag || undefined,
                            limit: 60,
                            offset: 0,
                        })
                        hasSuccessfulRequest = true
                        stations.forEach((station) => {
                            const normalized = this.normalizeStationPayload(station)
                            if (normalized.station_uuid !== '' && normalized.stream_url !== '') {
                                stationByUuid.set(normalized.station_uuid, normalized)
                            }
                        })
                    } catch (_error) {
                        // keep generic error below if no successful request
                    }
                }

                if (!hasSuccessfulRequest) {
                    this.setFeaturedError(preset.id, this.$t('radio.featuredLoadError'))
                    return null
                }

                const picked = this.pickFeaturedStation(Array.from(stationByUuid.values()), preset)

                if (!picked) {
                    this.setFeaturedError(preset.id, this.$t('radio.featuredNoStream'))
                    return null
                }

                this.featuredStationMap = {
                    ...this.featuredStationMap,
                    [preset.id]: picked,
                }
                this.stations = [
                    picked,
                    ...this.stations.filter((item) => item.station_uuid !== picked.station_uuid),
                ]
                this.hasSearchResults = true

                if (shouldPlay) {
                    await this.playStation(picked)
                }

                return picked
            } catch (_error) {
                this.setFeaturedError(preset.id, this.$t('radio.featuredLoadError'))
                return null
            } finally {
                this.setFeaturedLoading(preset.id, false)
            }
        },

        async playFeaturedPreset(preset) {
            await this.resolveFeaturedPresetStation(preset, { play: true })
        },

        async refreshFeaturedPreset(preset) {
            const station = await this.resolveFeaturedPresetStation(preset, { force: true, play: false })
            if (!station) {
                return
            }

            this.setRadioNotice(this.$t('radio.featuredRefreshed', { name: preset.name }))
        },

        async toggleFeaturedFavorite(preset) {
            const station = await this.resolveFeaturedPresetStation(preset, { play: false })
            if (!station) {
                return
            }

            await this.toggleFavorite(station)
        },

        async checkFeaturedPresets() {
            if (this.isCheckingFeatured) {
                return
            }

            this.isCheckingFeatured = true
            this.setRadioNotice('')

            let ok = 0
            let fail = 0

            try {
                for (const preset of this.featuredPresetList) {
                    const station = await this.resolveFeaturedPresetStation(preset, { force: true, play: false })
                    if (station) {
                        ok += 1
                    } else {
                        fail += 1
                    }
                }

                this.setRadioNotice(this.$t('radio.featuredCheckDone', { ok, fail }))
            } finally {
                this.isCheckingFeatured = false
            }
        },

        stationMeta(station) {
            const meta = []

            if (station.country) {
                meta.push(station.country)
            }
            if (station.language) {
                meta.push(station.language)
            }
            if (station.codec) {
                meta.push(station.codec)
            }
            if (Number(station.bitrate || 0) > 0) {
                meta.push(`${station.bitrate} kbps`)
            }

            return meta.length > 0 ? meta.join(' · ') : this.$t('radio.noMetadata')
        },

        hideBrokenIcon(event) {
            const image = event?.target
            if (!(image instanceof HTMLImageElement)) {
                return
            }

            image.style.display = 'none'
        },

        buildSearchParams() {
            return {
                q: this.filters.q || undefined,
                country: this.filters.country || undefined,
                language: this.filters.language || undefined,
                tag: this.filters.tag || undefined,
                limit: 40,
                offset: 0,
            }
        },

        async searchStations() {
            this.isLoadingStations = true
            this.stationsError = ''
            this.hasSearchResults = true

            try {
                const response = await axios.get('/api/radio/stations', {
                    params: this.buildSearchParams(),
                })
                this.stations = response.data.data ?? []
            } catch (error) {
                this.stations = []
                this.stationsError = error.response?.data?.message || this.$t('radio.loadStationsError')
            } finally {
                this.isLoadingStations = false
            }
        },

        async loadFavorites() {
            this.isLoadingFavorites = true

            try {
                const response = await axios.get('/api/radio/favorites')
                this.applyFavoritesSnapshot(response.data?.data ?? [])
            } finally {
                this.isLoadingFavorites = false
            }
        },

        normalizeFavoritesSnapshot(source) {
            const list = Array.isArray(source) ? source : []
            const seen = new Set()

            return list
                .map((item) => this.normalizeStationPayload(item))
                .filter((item) => {
                    const stationUuid = String(item?.station_uuid || '').trim()
                    if (stationUuid === '' || seen.has(stationUuid)) {
                        return false
                    }

                    seen.add(stationUuid)
                    return true
                })
        },

        applyFavoritesSnapshot(source) {
            this.favorites = this.normalizeFavoritesSnapshot(source)
            this.syncStationsFavoriteFlags()
        },

        syncStationsFavoriteFlags() {
            const favoriteStationIds = new Set(
                this.favorites
                    .map((item) => String(item?.station_uuid || '').trim())
                    .filter((stationUuid) => stationUuid !== '')
            )

            this.stations = this.stations.map((item) => {
                const stationUuid = String(item?.station_uuid || '').trim()
                return {
                    ...item,
                    is_favorite: stationUuid !== '' && favoriteStationIds.has(stationUuid),
                }
            })
        },

        handleWidgetFavoritesSync(event) {
            const source = String(event?.detail?.source || '')
            if (source === RADIO_FAVORITES_SYNC_SOURCE) {
                return
            }

            const snapshot = event?.detail?.favorites
            if (Array.isArray(snapshot)) {
                this.applyFavoritesSnapshot(snapshot)
                return
            }

            this.loadFavorites()
        },

        notifyFavoritesSync(options = {}) {
            if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
                return
            }

            window.dispatchEvent(new CustomEvent(RADIO_FAVORITES_SYNC_EVENT, {
                detail: {
                    source: RADIO_FAVORITES_SYNC_SOURCE,
                    action: String(options?.action || '').trim(),
                    stationUuid: String(options?.stationUuid || '').trim(),
                    favorites: this.favorites.map((item) => this.normalizeStationPayload(item)),
                    sentAt: Date.now(),
                },
            }))
        },

        pauseLocalPlaybackForBridgeSync() {
            const player = this.getActivePlayer()
            if (!player || typeof player.pause !== 'function') {
                return
            }

            if (player.playing) {
                player.pause()
            }
        },

        dispatchWidgetPlaybackCommand(command, station = null) {
            if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
                return
            }

            const normalizedCommand = String(command || '').trim()
            if (normalizedCommand === '') {
                return
            }

            const normalizedStation = this.normalizeStationPayload(station)
            const payloadStation = normalizedStation?.stream_url ? normalizedStation : this.currentStation

            window.dispatchEvent(new CustomEvent(RADIO_PLAYBACK_SYNC_EVENT, {
                detail: {
                    source: RADIO_PLAYBACK_SOURCE_PAGE,
                    type: 'command',
                    command: normalizedCommand,
                    station: payloadStation || null,
                    sentAt: Date.now(),
                },
            }))
        },

        requestWidgetPlaybackState() {
            this.dispatchWidgetPlaybackCommand('sync', this.currentStation)
        },

        handleWidgetPlaybackReady(event) {
            const source = String(event?.detail?.source || '')
            if (source !== RADIO_PLAYBACK_SOURCE_WIDGET) {
                return
            }

            const isReady = Boolean(event?.detail?.isReady)
            const wasReady = this.widgetPlaybackBridgeReady
            this.widgetPlaybackBridgeReady = isReady

            if (isReady) {
                this.unbindPlayerStateEvents()
            }

            if (!isReady || wasReady === isReady) {
                return
            }

            if (this.currentStation?.stream_url && this.playbackState.isPlaying) {
                this.pauseLocalPlaybackForBridgeSync()
                this.dispatchWidgetPlaybackCommand('play', this.currentStation)
                return
            }

            this.requestWidgetPlaybackState()
        },

        handleWidgetPlaybackSync(event) {
            const source = String(event?.detail?.source || '')
            const type = String(event?.detail?.type || '')
            if (source !== RADIO_PLAYBACK_SOURCE_WIDGET || type !== 'state') {
                return
            }

            this.widgetPlaybackBridgeReady = true
            this.unbindPlayerStateEvents()

            const normalizedStation = this.normalizeStationPayload(event?.detail?.station)
            if (normalizedStation?.stream_url) {
                const previousStationUuid = String(this.currentStation?.station_uuid || '')
                this.currentStation = normalizedStation

                if (normalizedStation.station_uuid !== previousStationUuid || this.stationSessionStartedAt <= 0) {
                    const syncedStart = Number(event?.detail?.sessionStartedAt || 0)
                    this.stationSessionStartedAt = Number.isFinite(syncedStart) && syncedStart > 0
                        ? syncedStart
                        : Date.now()
                }
            }

            const currentTime = Number(event?.detail?.currentTime || 0)
            const duration = Number(event?.detail?.duration || 0)

            this.playbackState = {
                isPlaying: Boolean(event?.detail?.isPlaying),
                currentTime: Number.isFinite(currentTime) && currentTime > 0 ? currentTime : 0,
                duration: Number.isFinite(duration) && duration > 0 ? duration : 0,
            }

            this.autoplayNotice = ''
            this.pauseLocalPlaybackForBridgeSync()
        },

        resetFilters() {
            this.filters = {
                q: '',
                country: '',
                language: '',
                tag: '',
            }
            this.stations = []
            this.stationsError = ''
            this.hasSearchResults = false
            this.setRadioNotice('')
        },

        async playStation(station) {
            const normalizedStation = this.normalizeStationPayload(station)
            if (!normalizedStation.stream_url) {
                this.setRadioNotice(this.$t('radio.noPlayableStream'))
                return
            }

            const previousStationUuid = String(this.currentStation?.station_uuid || '')
            this.currentStation = normalizedStation
            if (normalizedStation.station_uuid !== previousStationUuid || this.stationSessionStartedAt <= 0) {
                this.stationSessionStartedAt = Date.now()
            }

            this.autoplayNotice = ''
            this.setRadioNotice('')

            if (this.isWidgetPlaybackBridgeEnabled) {
                this.pauseLocalPlaybackForBridgeSync()
                this.playbackState = {
                    ...this.playbackState,
                    isPlaying: true,
                }
                this.dispatchWidgetPlaybackCommand('play', normalizedStation)
                return
            }

            await this.$nextTick()
            this.bindPlayerStateEvents()

            const player = this.$refs.radioPlayer
            if (!player || typeof player.play !== 'function') {
                return
            }

            const started = await player.play()
            if (!started) {
                this.autoplayNotice = this.$t('radio.autoplayBlocked')
            }

            this.updatePlaybackStateFromPlayer()
        },

        isFavorite(stationUuid) {
            if (!stationUuid) {
                return false
            }

            return this.favorites.some((item) => item.station_uuid === stationUuid)
        },

        isFavoriteSaving(stationUuid) {
            return Boolean(this.favoriteLoadingMap[stationUuid])
        },

        setFavoriteLoading(stationUuid, isLoading) {
            this.favoriteLoadingMap = {
                ...this.favoriteLoadingMap,
                [stationUuid]: isLoading,
            }
        },

        async toggleFavorite(station) {
            const stationUuid = station?.station_uuid
            if (!stationUuid) {
                return
            }

            if (this.isFavoriteSaving(stationUuid)) {
                return
            }

            this.setFavoriteLoading(stationUuid, true)

            try {
                const shouldRemove = this.isFavorite(stationUuid)

                if (shouldRemove) {
                    await axios.delete(`/api/radio/favorites/${encodeURIComponent(stationUuid)}`)
                    this.setRadioNotice(this.$t('radio.favoriteRemoved'))
                } else {
                    await axios.post('/api/radio/favorites', {
                        station_uuid: station.station_uuid,
                        name: station.name || this.$t('radio.untitled'),
                        stream_url: station.stream_url,
                        homepage: station.homepage || null,
                        favicon: station.favicon || null,
                        country: station.country || null,
                        language: station.language || null,
                        tags: station.tags || null,
                        codec: station.codec || null,
                        bitrate: Number(station.bitrate || 0) || null,
                        votes: Number(station.votes || 0) || null,
                    })
                    this.setRadioNotice(this.$t('radio.favoriteAdded'))
                }

                await this.loadFavorites()
                this.notifyFavoritesSync({
                    action: shouldRemove ? 'remove' : 'add',
                    stationUuid,
                })
            } catch (error) {
                this.setRadioNotice(error.response?.data?.message || this.$t('radio.updateFavoritesError'))
            } finally {
                this.setFavoriteLoading(stationUuid, false)
            }
        },
    },
}
</script>
