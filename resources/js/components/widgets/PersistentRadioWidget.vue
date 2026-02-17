<template>
    <aside
        ref="widgetRoot"
        class="side-widget side-widget--radio"
        :class="{'is-collapsed': !expanded, 'is-dragging': isDragging}"
        :style="floatingStyle"
    >
        <button
            v-show="!expanded"
            type="button"
            class="side-widget-mini-btn"
            :aria-label="collapsedButtonHint"
            :title="collapsedButtonHint"
            @click="expand"
        >
            <span class="side-widget-mini-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                    <path
                        fill="currentColor"
                        d="M10 4a1 1 0 0 1 2 0v9.35a3.5 3.5 0 1 1-2 0V4zm6.95 2.45a1 1 0 1 1 1.41-1.41A10 10 0 1 1 5.64 5.04a1 1 0 1 1 1.42 1.41A8 8 0 1 0 16.95 6.45z"
                    />
                </svg>
            </span>
            <span v-if="isPlaying" class="side-widget-mini-btn__live-dot"></span>
            <span class="side-widget-mini-btn__hint">{{ collapsedButtonHint }}</span>
        </button>

        <section v-show="expanded" class="side-widget-panel glass-panel">
            <header
                class="side-widget-panel__header side-widget-panel__header--draggable"
                :class="{'is-dragging': isDragging}"
                @pointerdown="startDrag"
            >
                <div class="side-widget-panel__title-wrap">
                    <strong class="side-widget-panel__title">{{ $t('nav.radio') }}</strong>
                    <span v-if="isPlaying" class="badge side-widget-panel__status">{{ $t('radio.live') }}</span>
                </div>

                <div class="side-widget-panel__actions">
                    <button
                        v-if="isMovableMode"
                        type="button"
                        class="side-widget-panel__pin-btn"
                        :class="{'is-active': isPinned}"
                        :aria-label="pinButtonHint"
                        :title="pinButtonHint"
                        @click.stop="togglePin"
                    >
                        <span aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                <path
                                    fill="currentColor"
                                    d="M14.41 3.59a2 2 0 0 1 2.83 0l3.17 3.17a2 2 0 0 1 0 2.83l-1.18 1.18a1 1 0 0 1-.71.29h-2.1l-3.45 3.46 1.67 5.58a1 1 0 0 1-1.69.96l-2.98-2.98-3.97 3.96a1 1 0 1 1-1.41-1.41l3.97-3.97-2.98-2.97a1 1 0 0 1 .96-1.69l5.58 1.67 3.46-3.46v-2.1a1 1 0 0 1 .29-.71l1.18-1.18z"
                                />
                            </svg>
                        </span>
                    </button>

                    <button
                        type="button"
                        class="side-widget-panel__collapse-btn"
                        :aria-label="$t('common.close')"
                        :title="collapseButtonHint"
                        @click.stop="collapse"
                    >
                        <span aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                <path
                                    fill="currentColor"
                                    d="M14.7 5.3a1 1 0 0 1 0 1.4L9.41 12l5.3 5.3a1 1 0 1 1-1.42 1.4l-6-6a1 1 0 0 1 0-1.4l6-6a1 1 0 0 1 1.42 0z"
                                />
                            </svg>
                        </span>
                    </button>
                </div>
            </header>

            <div class="side-widget-panel__body">
                <div v-if="currentStation" class="widget-radio-current">
                    <p class="widget-radio-current__name">{{ currentStation.name || $t('radio.untitled') }}</p>
                    <p class="widget-radio-current__meta muted">{{ stationMeta(currentStation) }}</p>

                    <MediaPlayer
                        ref="player"
                        type="audio"
                        :src="currentStation.stream_url"
                        player-class="widget-radio-player"
                        :mime-type="currentStation.codec ? `audio/${String(currentStation.codec).toLowerCase()}` : ''"
                    ></MediaPlayer>

                    <div class="widget-radio-current__actions">
                        <button class="btn btn-outline btn-sm" type="button" @click="togglePlayback">
                            {{ isPlaying ? $t('radio.pause') : $t('radio.play') }}
                        </button>
                        <button class="btn btn-outline btn-sm" type="button" @click="toggleFavorite(currentStation)">
                            {{ isFavorite(currentStation.station_uuid) ? $t('common.remove') : $t('common.favorites') }}
                        </button>
                    </div>

                    <p v-if="autoplayNotice" class="muted widget-radio-notice">{{ autoplayNotice }}</p>
                </div>

                <form class="widget-radio-search" @submit.prevent="searchStations">
                    <input
                        v-model.trim="searchQuery"
                        class="input-field"
                        type="text"
                        :placeholder="$t('radio.searchPlaceholder')"
                    >
                    <div class="widget-radio-search__actions">
                        <button class="btn btn-primary btn-sm" type="submit" :disabled="isLoadingSearch">
                            {{ isLoadingSearch ? $t('radio.searching') : $t('radio.searchButton') }}
                        </button>
                        <button
                            class="btn btn-sm"
                            type="button"
                            :class="showPremiumList ? 'btn-primary' : 'btn-outline'"
                            :disabled="isLoadingSearch"
                            @click="togglePremiumList"
                        >
                            {{ $t('radio.premiumList') }}
                        </button>
                    </div>
                </form>

                <p v-if="searchError" class="error-text widget-radio-notice">{{ searchError }}</p>

                <div class="widget-radio-list">
                    <div class="widget-radio-list__head">
                        <strong>{{ $t('radio.favoriteStations') }}</strong>
                        <span class="badge">{{ favorites.length }}</span>
                    </div>

                    <p v-if="isLoadingFavorites" class="muted">{{ $t('radio.loadingFavorites') }}</p>
                    <p v-else-if="favorites.length === 0" class="muted">{{ $t('radio.emptyFavorites') }}</p>

                    <div v-else class="widget-radio-list__items">
                        <button
                            v-for="station in favorites"
                            :key="`widget-favorite-${station.station_uuid}`"
                            type="button"
                            class="widget-radio-station"
                            :class="{'is-active': isCurrentStation(station)}"
                            @click="playStation(station)"
                        >
                            <span class="widget-radio-station__name">{{ station.name || $t('radio.untitled') }}</span>
                            <span class="widget-radio-station__meta">{{ stationMeta(station) }}</span>
                        </button>
                    </div>
                </div>

                <div class="widget-radio-list widget-radio-list--builtin" v-if="showPremiumList">
                    <div class="widget-radio-list__head">
                        <strong>{{ $t('radio.premiumList') }}</strong>
                        <span class="badge">{{ builtinStations.length }}</span>
                    </div>
                    <p class="muted widget-radio-notice">{{ $t('radio.premiumListSyncHint') }}</p>
                    <div class="widget-radio-filter-row">
                        <label for="widget-radio-genre-filter" class="widget-radio-filter-label">
                            {{ $t('radio.genreFilterLabel') }}
                        </label>
                        <select
                            id="widget-radio-genre-filter"
                            v-model="builtinCategory"
                            class="select-field widget-radio-category-select"
                        >
                            <option
                                v-for="item in builtinCategoryOptions"
                                :key="`widget-radio-category-${item.id}`"
                                :value="item.id"
                            >
                                {{ item.label }} ({{ item.count }})
                            </option>
                        </select>
                    </div>

                    <p
                        v-if="filteredBuiltinStations.length === 0"
                        class="muted widget-radio-notice"
                    >
                        {{ $t('radio.emptyBuiltinForCategory') }}
                    </p>

                    <div v-else class="widget-radio-list__items">
                        <article
                            v-for="preset in filteredBuiltinStations"
                            :key="`widget-builtin-${preset.id}`"
                            class="widget-radio-station"
                        >
                            <span class="widget-radio-station__name">{{ preset.name }}</span>
                            <span class="widget-radio-station__meta">{{ builtinStationMeta(preset) }}</span>
                            <div class="widget-radio-station__actions">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm widget-radio-station__action-btn"
                                    :disabled="isBuiltinLoading(preset.id)"
                                    @click="playBuiltinStation(preset)"
                                >
                                    {{ $t('radio.listen') }}
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline btn-sm widget-radio-station__action-btn"
                                    :disabled="isBuiltinLoading(preset.id)"
                                    @click="refreshBuiltinStation(preset)"
                                >
                                    {{ $t('radio.featuredRefresh') }}
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline btn-sm widget-radio-station__action-btn"
                                    :disabled="isBuiltinLoading(preset.id)"
                                    @click="toggleBuiltinFavorite(preset)"
                                >
                                    {{ isBuiltinFavorite(preset) ? $t('common.remove') : $t('common.favorites') }}
                                </button>
                            </div>
                            <span v-if="isBuiltinLoading(preset.id)" class="widget-radio-station__hint muted">
                                {{ $t('radio.featuredLoading') }}
                            </span>
                            <span v-else-if="builtinErrorMap[preset.id]" class="widget-radio-station__hint error-text">
                                {{ builtinErrorMap[preset.id] }}
                            </span>
                        </article>
                    </div>
                </div>

                <div class="widget-radio-list" v-if="searchStationsList.length > 0">
                    <div class="widget-radio-list__head">
                        <strong>{{ $t('radio.foundStations') }}</strong>
                        <span class="badge">{{ searchStationsList.length }}</span>
                    </div>

                    <div class="widget-radio-list__items">
                        <button
                            v-for="station in searchStationsList"
                            :key="`widget-search-${station.station_uuid}`"
                            type="button"
                            class="widget-radio-station"
                            :class="{'is-active': isCurrentStation(station)}"
                            @click="playStation(station)"
                        >
                            <span class="widget-radio-station__name">{{ station.name || $t('radio.untitled') }}</span>
                            <span class="widget-radio-station__meta">{{ stationMeta(station) }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</template>

<script>
import MediaPlayer from '../MediaPlayer.vue'
import { RADIO_PRESET_CATALOG } from '../../data/radioPresetCatalog'

const RADIO_WIDGET_STORAGE_PREFIX = 'social.widgets.radio'
const RADIO_FAVORITES_SYNC_EVENT = 'social:radio:favorites-updated'
const RADIO_FAVORITES_SYNC_SOURCE = 'widget-radio'
const RADIO_PLAYBACK_SYNC_EVENT = 'social:radio:playback-sync'
const RADIO_PLAYBACK_READY_EVENT = 'social:radio:playback-ready'
const RADIO_PLAYBACK_SOURCE_WIDGET = 'widget-radio'
const RADIO_PLAYBACK_SOURCE_PAGE = 'radio-page'
const DESKTOP_FLOATING_BREAKPOINT = 1241
const WIDGET_EDGE_GAP = 72
const RADIO_WIDGET_BUILTIN_STATIONS = RADIO_PRESET_CATALOG

export default {
    name: 'PersistentRadioWidget',

    components: {
        MediaPlayer,
    },

    props: {
        active: {
            type: Boolean,
            default: false,
        },
        user: {
            type: Object,
            default: null,
        },
    },

    data() {
        return {
            expanded: false,
            searchQuery: '',
            builtinCategory: 'all',
            showPremiumList: false,
            searchStationsList: [],
            favorites: [],
            currentStation: null,
            isPlaying: false,
            shouldResumePlayback: false,
            isLoadingSearch: false,
            isLoadingFavorites: false,
            searchError: '',
            autoplayNotice: '',
            boundPlayer: null,
            boundPlayerEvents: [],
            playbackSessionStartedAt: 0,
            isPinned: true,
            floatingPosition: {
                left: 0,
                top: 0,
            },
            isFloatingReady: false,
            isDragging: false,
            dragState: null,
            viewportWidth: typeof window !== 'undefined' ? window.innerWidth : 0,
            builtinResolvedMap: {},
            builtinLoadingMap: {},
            builtinErrorMap: {},
        }
    },

    watch: {
        active: {
            immediate: true,
            async handler(next) {
                if (next) {
                    await this.initializeWidgetState()
                    return
                }

                this.stopPlayback({preserveResumeIntent: true})
                this.persistWidgetState()
            },
        },

        userStorageKey() {
            if (!this.active) {
                return
            }

            this.resetRuntimeState()
            this.initializeWidgetState()
        },

        expanded() {
            this.persistWidgetState()
            this.refreshFloatingPosition()
        },

        currentStation() {
            this.persistWidgetState()
        },

        shouldResumePlayback() {
            this.persistWidgetState()
        },

        searchQuery() {
            this.persistWidgetState()
        },

        showPremiumList() {
            this.persistWidgetState()
        },

        builtinCategory() {
            this.persistWidgetState()
        },
    },

    computed: {
        userStorageKey() {
            const id = Number(this.user?.id ?? 0)
            return Number.isFinite(id) && id > 0 ? String(id) : 'guest'
        },

        storageKey() {
            return `${RADIO_WIDGET_STORAGE_PREFIX}.${this.userStorageKey}`
        },

        collapsedButtonHint() {
            return `${this.$t('nav.radio')} · ${this.$t('radio.favoriteStations')}`
        },

        collapseButtonHint() {
            return `${this.$t('common.close')} ${this.$t('nav.radio')}`
        },

        pinButtonHint() {
            return this.isPinned ? this.$t('common.unpin') : this.$t('common.pin')
        },

        isMovableMode() {
            return this.viewportWidth >= DESKTOP_FLOATING_BREAKPOINT
        },

        floatingStyle() {
            if (!this.isMovableMode) {
                return null
            }

            const basePosition = this.isFloatingReady
                ? this.floatingPosition
                : this.getDefaultFloatingPosition()

            return {
                left: `${Math.round(basePosition.left)}px`,
                top: `${Math.round(basePosition.top)}px`,
                position: 'fixed',
            }
        },

        builtinStations() {
            return RADIO_WIDGET_BUILTIN_STATIONS
        },

        builtinCategoryOptions() {
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
                        count: this.builtinStations.length,
                    }
                }

                const count = this.builtinStations.filter((preset) => preset.category === categoryId).length
                return {
                    id: categoryId,
                    label: labels[categoryId],
                    count,
                }
            })
        },

        filteredBuiltinStations() {
            if (this.builtinCategory === 'all') {
                return this.builtinStations
            }

            return this.builtinStations.filter((preset) => preset.category === this.builtinCategory)
        },
    },

    mounted() {
        if (typeof window !== 'undefined') {
            window.addEventListener(RADIO_FAVORITES_SYNC_EVENT, this.handleFavoritesSyncEvent)
            window.addEventListener(RADIO_PLAYBACK_SYNC_EVENT, this.handlePlaybackSyncEvent)
            window.addEventListener('resize', this.handleViewportResize)
            this.viewportWidth = window.innerWidth
            window.__socialRadioWidgetReady = true
            this.notifyPlaybackReady(true)
        }

        this.refreshFloatingPosition({forceDefault: !this.isPinned})
        this.notifyPlaybackState({reason: 'widget-mounted'})
    },

    beforeUnmount() {
        this.stopPlayback({preserveResumeIntent: true})
        this.unbindPlayerStateEvents()
        this.persistWidgetState()

        if (typeof window !== 'undefined') {
            window.removeEventListener(RADIO_FAVORITES_SYNC_EVENT, this.handleFavoritesSyncEvent)
            window.removeEventListener(RADIO_PLAYBACK_SYNC_EVENT, this.handlePlaybackSyncEvent)
            window.removeEventListener('resize', this.handleViewportResize)
            window.__socialRadioWidgetReady = false
            this.notifyPlaybackReady(false)
        }

        this.stopDragging()
    },

    methods: {
        collapse() {
            this.expanded = false
        },

        expand() {
            this.expanded = true
        },

        handleViewportResize() {
            if (typeof window === 'undefined') {
                return
            }

            this.viewportWidth = window.innerWidth
            this.refreshFloatingPosition()
        },

        getWidgetSize() {
            const element = this.$refs.widgetRoot
            if (!(element instanceof HTMLElement)) {
                return {
                    width: 320,
                    height: 420,
                }
            }

            return {
                width: Math.max(1, Number(element.offsetWidth || 0)),
                height: Math.max(1, Number(element.offsetHeight || 0)),
            }
        },

        clampFloatingPosition(position) {
            if (typeof window === 'undefined') {
                return {
                    left: 0,
                    top: 0,
                }
            }

            const size = this.getWidgetSize()
            const minLeft = WIDGET_EDGE_GAP
            const minTop = WIDGET_EDGE_GAP
            const maxLeft = Math.max(minLeft, window.innerWidth - size.width - WIDGET_EDGE_GAP)
            const maxTop = Math.max(minTop, window.innerHeight - size.height - WIDGET_EDGE_GAP)

            const nextLeft = Math.min(maxLeft, Math.max(minLeft, Number(position?.left || 0)))
            const nextTop = Math.min(maxTop, Math.max(minTop, Number(position?.top || 0)))

            return {
                left: nextLeft,
                top: nextTop,
            }
        },

        getDefaultFloatingPosition() {
            if (typeof window === 'undefined') {
                return {
                    left: 0,
                    top: 0,
                }
            }

            const size = this.getWidgetSize()
            const centeredTop = Math.max(0, Math.round((window.innerHeight - size.height) / 2))

            return this.clampFloatingPosition({
                left: WIDGET_EDGE_GAP,
                top: centeredTop,
            })
        },

        refreshFloatingPosition(options = {}) {
            if (!this.isMovableMode) {
                this.isFloatingReady = false
                return
            }

            const forceDefault = Boolean(options?.forceDefault)
            this.$nextTick(() => {
                const currentLeft = Number(this.floatingPosition?.left)
                const currentTop = Number(this.floatingPosition?.top)
                const hasStoredPosition = Number.isFinite(currentLeft) && Number.isFinite(currentTop) && currentLeft > 0 && currentTop > 0
                const useDefault = forceDefault || this.isPinned || !hasStoredPosition
                const basePosition = useDefault ? this.getDefaultFloatingPosition() : this.floatingPosition

                this.floatingPosition = this.clampFloatingPosition(basePosition)
                this.isFloatingReady = true
            })
        },

        togglePin() {
            this.isPinned = !this.isPinned

            this.persistWidgetState()
        },

        startDrag(event) {
            if (!this.isMovableMode || this.isPinned) {
                return
            }

            if (event?.pointerType === 'mouse' && event.button !== 0) {
                return
            }

            const target = event?.target
            if (target instanceof HTMLElement && target.closest('button')) {
                return
            }

            const startPosition = this.isFloatingReady
                ? this.floatingPosition
                : this.getDefaultFloatingPosition()

            this.floatingPosition = startPosition
            this.isFloatingReady = true
            this.isDragging = true
            this.dragState = {
                pointerId: Number(event?.pointerId || 0),
                startClientX: Number(event?.clientX || 0),
                startClientY: Number(event?.clientY || 0),
                startLeft: startPosition.left,
                startTop: startPosition.top,
            }

            window.addEventListener('pointermove', this.onDragMove)
            window.addEventListener('pointerup', this.stopDrag)
            window.addEventListener('pointercancel', this.stopDrag)
            event.preventDefault()
        },

        onDragMove(event) {
            if (!this.isDragging || !this.dragState) {
                return
            }

            if (this.dragState.pointerId > 0 && Number(event?.pointerId || 0) > 0 && this.dragState.pointerId !== Number(event.pointerId)) {
                return
            }

            const nextLeft = this.dragState.startLeft + (Number(event?.clientX || 0) - this.dragState.startClientX)
            const nextTop = this.dragState.startTop + (Number(event?.clientY || 0) - this.dragState.startClientY)

            this.floatingPosition = this.clampFloatingPosition({
                left: nextLeft,
                top: nextTop,
            })
        },

        stopDrag(event) {
            if (!this.isDragging) {
                return
            }

            if (this.dragState?.pointerId > 0 && Number(event?.pointerId || 0) > 0 && this.dragState.pointerId !== Number(event.pointerId)) {
                return
            }

            this.stopDragging()
        },

        stopDragging() {
            this.isDragging = false
            this.dragState = null
            if (typeof window !== 'undefined') {
                window.removeEventListener('pointermove', this.onDragMove)
                window.removeEventListener('pointerup', this.stopDrag)
                window.removeEventListener('pointercancel', this.stopDrag)
            }

            this.persistWidgetState()
        },

        resetRuntimeState() {
            this.searchStationsList = []
            this.favorites = []
            this.currentStation = null
            this.isPlaying = false
            this.shouldResumePlayback = false
            this.isLoadingSearch = false
            this.isLoadingFavorites = false
            this.searchError = ''
            this.autoplayNotice = ''
            this.unbindPlayerStateEvents()
            this.playbackSessionStartedAt = 0
            this.isPinned = true
            this.floatingPosition = {
                left: 0,
                top: 0,
            }
            this.isFloatingReady = false
            this.isDragging = false
            this.dragState = null
            this.showPremiumList = false
            this.builtinCategory = 'all'
            this.builtinResolvedMap = {}
            this.builtinLoadingMap = {}
            this.builtinErrorMap = {}
        },

        async initializeWidgetState() {
            if (!this.active) {
                return
            }

            this.loadWidgetState()
            await this.loadFavorites()

            if (this.currentStation && this.shouldResumePlayback) {
                await this.$nextTick()
                this.bindPlayerStateEvents()
                await this.tryPlayCurrentStation()
            }

            this.refreshFloatingPosition({forceDefault: !this.isPinned})
            this.notifyPlaybackState({reason: 'widget-init'})
        },

        loadWidgetState() {
            if (typeof localStorage === 'undefined') {
                return
            }

            try {
                const raw = localStorage.getItem(this.storageKey)
                if (!raw) {
                    return
                }

                const parsed = JSON.parse(raw)
                this.expanded = parsed?.expanded === true
                this.searchQuery = typeof parsed?.searchQuery === 'string' ? parsed.searchQuery : ''
                this.showPremiumList = parsed?.showPremiumList === true
                const parsedBuiltinCategory = String(parsed?.builtinCategory || 'all')
                this.builtinCategory = ['all', 'ru_kz', 'dance', 'rock_jazz', 'world'].includes(parsedBuiltinCategory)
                    ? parsedBuiltinCategory
                    : 'all'
                this.shouldResumePlayback = Boolean(parsed?.shouldResumePlayback)
                this.isPinned = parsed?.isPinned !== false

                const storedLeft = Number(parsed?.floatingPosition?.left)
                const storedTop = Number(parsed?.floatingPosition?.top)
                if (Number.isFinite(storedLeft) && Number.isFinite(storedTop)) {
                    this.floatingPosition = {
                        left: storedLeft,
                        top: storedTop,
                    }
                }

                const station = this.normalizeStationPayload(parsed?.currentStation)
                this.currentStation = station?.stream_url ? station : null
            } catch (_error) {
                this.expanded = false
                this.searchQuery = ''
                this.showPremiumList = false
                this.builtinCategory = 'all'
                this.shouldResumePlayback = false
                this.currentStation = null
                this.isPinned = true
                this.floatingPosition = {
                    left: 0,
                    top: 0,
                }
            }
        },

        persistWidgetState() {
            if (typeof localStorage === 'undefined') {
                return
            }

            try {
                localStorage.setItem(this.storageKey, JSON.stringify({
                    expanded: this.expanded,
                    searchQuery: this.searchQuery,
                    showPremiumList: this.showPremiumList,
                    builtinCategory: this.builtinCategory,
                    shouldResumePlayback: this.shouldResumePlayback,
                    currentStation: this.currentStation,
                    isPinned: this.isPinned,
                    floatingPosition: this.floatingPosition,
                }))
            } catch (_error) {
                // Ignore write failures.
            }
        },

        normalizeStationPayload(station) {
            if (!station || typeof station !== 'object') {
                return null
            }

            const stationUuid = String(station.station_uuid || '').trim()
            const streamUrl = String(station.stream_url || '').trim()

            return {
                station_uuid: stationUuid,
                name: String(station.name || '').trim(),
                stream_url: streamUrl,
                homepage: String(station.homepage || '').trim(),
                favicon: String(station.favicon || '').trim(),
                country: String(station.country || '').trim(),
                language: String(station.language || '').trim(),
                tags: String(station.tags || '').trim(),
                codec: String(station.codec || '').trim(),
                bitrate: Number(station.bitrate || 0),
                votes: Number(station.votes || 0),
            }
        },

        stationMeta(station) {
            const chunks = [station?.country, station?.language, station?.codec]
                .map((item) => String(item || '').trim())
                .filter((item) => item !== '')

            return chunks.join(' · ') || this.$t('radio.noMetadata')
        },

        isCurrentStation(station) {
            const currentUuid = String(this.currentStation?.station_uuid || '')
            const stationUuid = String(station?.station_uuid || '')

            if (currentUuid !== '' && stationUuid !== '') {
                return currentUuid === stationUuid
            }

            return String(this.currentStation?.stream_url || '') === String(station?.stream_url || '')
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

        builtinStationMeta(preset) {
            const chunks = [preset?.country, preset?.language, preset?.tag]
                .map((item) => String(item || '').trim())
                .filter((item) => item !== '')

            return chunks.join(' · ') || this.$t('radio.noMetadata')
        },

        isBuiltinLoading(stationId) {
            return Boolean(this.builtinLoadingMap[stationId])
        },

        setBuiltinLoading(stationId, isLoading) {
            this.builtinLoadingMap = {
                ...this.builtinLoadingMap,
                [stationId]: Boolean(isLoading),
            }
        },

        setBuiltinError(stationId, message = '') {
            this.builtinErrorMap = {
                ...this.builtinErrorMap,
                [stationId]: String(message || '').trim(),
            }
        },

        getResolvedBuiltinStation(preset) {
            const presetId = String(preset?.id || '').trim()
            if (presetId === '') {
                return null
            }

            const resolved = this.builtinResolvedMap[presetId]
            return resolved?.stream_url ? resolved : null
        },

        isBuiltinFavorite(preset) {
            const resolved = this.getResolvedBuiltinStation(preset)
            const stationUuid = String(resolved?.station_uuid || '').trim()
            if (stationUuid === '') {
                return false
            }

            return this.isFavorite(stationUuid)
        },

        buildBuiltinLookupQueries(preset) {
            const rawQueries = [
                preset?.query,
                preset?.name,
                ...(Array.isArray(preset?.keywords) ? preset.keywords : []),
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

        buildBuiltinSearchParamsByQuery(query) {
            return {
                q: String(query || '').trim() || undefined,
                limit: 50,
                offset: 0,
            }
        },

        async fetchBuiltinStationsByParams(params) {
            const response = await axios.get('/api/radio/stations', {
                params,
            })

            return Array.isArray(response?.data?.data) ? response.data.data : []
        },

        scoreBuiltinCandidate(station, preset) {
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

            const normalizedName = this.normalizeLookupText(preset.name)
            if (normalizedName !== '' && lookup.includes(normalizedName)) {
                score += 6
            }

            const keywords = Array.isArray(preset?.keywords) ? preset.keywords : []
            keywords.forEach((keyword) => {
                const normalizedKeyword = this.normalizeLookupText(keyword)
                if (normalizedKeyword !== '' && lookup.includes(normalizedKeyword)) {
                    score += 2.5
                }
            })

            const normalizedCountry = this.normalizeLookupText(preset.country)
            if (normalizedCountry !== '' && this.normalizeLookupText(station.country).includes(normalizedCountry)) {
                score += 2
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

            score += Math.min(2, Math.max(0, Number(station.votes || 0) / 10000))
            score += Math.min(2, Math.max(0, Number(station.bitrate || 0) / 256))

            return score
        },

        pickBuiltinStation(stations, preset) {
            const candidates = (Array.isArray(stations) ? stations : [])
                .map((station) => this.normalizeStationPayload(station))
                .filter((station) => station && station.stream_url)

            if (candidates.length === 0) {
                return null
            }

            const scored = candidates
                .map((station) => ({
                    station,
                    score: this.scoreBuiltinCandidate(station, preset),
                }))
                .sort((left, right) => right.score - left.score)

            return scored[0]?.station || null
        },

        async resolveBuiltinStation(preset, options = {}) {
            const presetId = String(preset?.id || '').trim()
            if (presetId === '' || this.isBuiltinLoading(presetId)) {
                return null
            }

            const forceReload = Boolean(options?.force)
            this.setBuiltinError(presetId, '')

            const cached = this.getResolvedBuiltinStation(preset)
            if (!forceReload && cached) {
                return cached
            }

            this.setBuiltinLoading(presetId, true)

            try {
                const stationByUuid = new Map()
                let hasSuccessfulRequest = false

                const lookupQueries = this.buildBuiltinLookupQueries(preset)
                for (const query of lookupQueries) {
                    try {
                        const stations = await this.fetchBuiltinStationsByParams(this.buildBuiltinSearchParamsByQuery(query))
                        hasSuccessfulRequest = true
                        stations.forEach((station) => {
                            const normalized = this.normalizeStationPayload(station)
                            if (normalized?.station_uuid && normalized?.stream_url) {
                                stationByUuid.set(normalized.station_uuid, normalized)
                            }
                        })
                    } catch (_error) {
                        // continue with next query
                    }
                }

                if (stationByUuid.size === 0) {
                    try {
                        const stations = await this.fetchBuiltinStationsByParams({
                            q: undefined,
                            country: String(preset?.country || '').trim() || undefined,
                            language: String(preset?.language || '').trim() || undefined,
                            tag: String(preset?.tag || '').trim() || undefined,
                            limit: 60,
                            offset: 0,
                        })
                        hasSuccessfulRequest = true
                        stations.forEach((station) => {
                            const normalized = this.normalizeStationPayload(station)
                            if (normalized?.station_uuid && normalized?.stream_url) {
                                stationByUuid.set(normalized.station_uuid, normalized)
                            }
                        })
                    } catch (_error) {
                        // keep generic error below if no successful request
                    }
                }

                if (!hasSuccessfulRequest) {
                    this.setBuiltinError(presetId, this.$t('radio.widgetBuiltinLoadError'))
                    return null
                }

                const picked = this.pickBuiltinStation(Array.from(stationByUuid.values()), preset)

                if (!picked) {
                    this.setBuiltinError(presetId, this.$t('radio.widgetBuiltinLoadError'))
                    return null
                }

                this.builtinResolvedMap = {
                    ...this.builtinResolvedMap,
                    [presetId]: picked,
                }

                return picked
            } catch (_error) {
                this.setBuiltinError(presetId, this.$t('radio.widgetBuiltinLoadError'))
                return null
            } finally {
                this.setBuiltinLoading(presetId, false)
            }
        },

        async playBuiltinStation(preset) {
            const station = await this.resolveBuiltinStation(preset)
            if (!station?.stream_url) {
                return
            }

            await this.playStation(station)
        },

        async refreshBuiltinStation(preset) {
            const previous = this.getResolvedBuiltinStation(preset)
            const station = await this.resolveBuiltinStation(preset, { force: true })
            if (!station?.stream_url) {
                return
            }

            if (previous && this.isCurrentStation(previous)) {
                await this.playStation(station)
            }
        },

        async toggleBuiltinFavorite(preset) {
            const station = await this.resolveBuiltinStation(preset)
            if (!station?.stream_url) {
                return
            }

            await this.toggleFavorite(station)
        },

        async loadFavorites() {
            this.isLoadingFavorites = true

            try {
                const response = await axios.get('/api/radio/favorites')
                this.applyFavoritesSnapshot(response?.data?.data ?? [])
            } catch (_error) {
                this.favorites = []
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
        },

        handleFavoritesSyncEvent(event) {
            const source = String(event?.detail?.source || '')
            if (!this.active || source === RADIO_FAVORITES_SYNC_SOURCE) {
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

        notifyPlaybackReady(isReady) {
            if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
                return
            }

            window.dispatchEvent(new CustomEvent(RADIO_PLAYBACK_READY_EVENT, {
                detail: {
                    source: RADIO_PLAYBACK_SOURCE_WIDGET,
                    isReady: Boolean(isReady),
                    sentAt: Date.now(),
                },
            }))
        },

        getPlaybackSnapshot() {
            const player = this.$refs.player?.player || null

            const currentTimeRaw = Number(player?.currentTime || 0)
            const durationRaw = Number(player?.duration || 0)

            return {
                isPlaying: Boolean(this.isPlaying),
                currentTime: Number.isFinite(currentTimeRaw) && currentTimeRaw > 0 ? currentTimeRaw : 0,
                duration: Number.isFinite(durationRaw) && durationRaw > 0 ? durationRaw : 0,
                sessionStartedAt: Number(this.playbackSessionStartedAt || 0),
            }
        },

        notifyPlaybackState(options = {}) {
            if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
                return
            }

            const snapshot = this.getPlaybackSnapshot()

            window.dispatchEvent(new CustomEvent(RADIO_PLAYBACK_SYNC_EVENT, {
                detail: {
                    source: RADIO_PLAYBACK_SOURCE_WIDGET,
                    type: 'state',
                    reason: String(options?.reason || 'state'),
                    station: this.currentStation,
                    isPlaying: snapshot.isPlaying,
                    currentTime: snapshot.currentTime,
                    duration: snapshot.duration,
                    sessionStartedAt: snapshot.sessionStartedAt,
                    sentAt: Date.now(),
                },
            }))
        },

        dispatchPlaybackCommandResponse(options = {}) {
            this.notifyPlaybackState({
                reason: String(options?.reason || 'command-response'),
            })
        },

        async handlePlaybackSyncEvent(event) {
            if (!this.active) {
                return
            }

            const source = String(event?.detail?.source || '')
            const type = String(event?.detail?.type || '')

            if (source !== RADIO_PLAYBACK_SOURCE_PAGE || type !== 'command') {
                return
            }

            const command = String(event?.detail?.command || '')
            const incomingStation = this.normalizeStationPayload(event?.detail?.station)

            if (command === 'sync') {
                this.dispatchPlaybackCommandResponse({reason: 'sync-request'})
                return
            }

            if (command === 'pause') {
                this.stopPlayback({silentSync: true})
                this.dispatchPlaybackCommandResponse({reason: 'remote-pause'})
                return
            }

            if (command === 'play') {
                const targetStation = incomingStation && incomingStation.stream_url
                    ? incomingStation
                    : this.currentStation

                if (!targetStation || !targetStation.stream_url) {
                    this.dispatchPlaybackCommandResponse({reason: 'remote-play-no-station'})
                    return
                }

                await this.playStation(targetStation, {silentSync: true})
                this.dispatchPlaybackCommandResponse({reason: 'remote-play'})
                return
            }

            if (command === 'toggle') {
                if (this.isPlaying) {
                    this.stopPlayback({silentSync: true})
                    this.dispatchPlaybackCommandResponse({reason: 'remote-toggle-pause'})
                    return
                }

                const targetStation = incomingStation && incomingStation.stream_url
                    ? incomingStation
                    : this.currentStation

                if (!targetStation || !targetStation.stream_url) {
                    this.dispatchPlaybackCommandResponse({reason: 'remote-toggle-no-station'})
                    return
                }

                await this.playStation(targetStation, {silentSync: true})
                this.dispatchPlaybackCommandResponse({reason: 'remote-toggle-play'})
            }
        },

        async searchStations() {
            const query = this.searchQuery.trim()
            if (query === '') {
                this.searchError = this.$t('radio.useSearchHint')
                this.searchStationsList = []
                return
            }

            this.isLoadingSearch = true
            this.searchError = ''

            try {
                const response = await axios.get('/api/radio/stations', {
                    params: {
                        q: query,
                        limit: 10,
                    },
                })

                const source = Array.isArray(response?.data?.data) ? response.data.data : []
                this.searchStationsList = source
                    .map((item) => this.normalizeStationPayload(item))
                    .filter((item) => item && item.stream_url)

                if (this.searchStationsList.length === 0) {
                    this.searchError = this.$t('radio.emptySearch')
                }
            } catch (error) {
                this.searchStationsList = []
                this.searchError = error?.response?.data?.message || this.$t('radio.loadStationsError')
            } finally {
                this.isLoadingSearch = false
            }
        },

        togglePremiumList() {
            this.showPremiumList = !this.showPremiumList
        },

        bindPlayerStateEvents() {
            const player = this.$refs.player?.player
            if (!player || typeof player.on !== 'function') {
                return
            }

            if (this.boundPlayer === player) {
                return
            }

            this.unbindPlayerStateEvents()

            const syncState = () => {
                const wasPlaying = this.isPlaying
                this.isPlaying = Boolean(player.playing)
                this.shouldResumePlayback = this.isPlaying

                if (this.isPlaying && this.playbackSessionStartedAt <= 0) {
                    this.playbackSessionStartedAt = Date.now()
                }

                if (wasPlaying !== this.isPlaying) {
                    this.notifyPlaybackState({reason: 'widget-player-event'})
                }
            }

            const events = ['ready', 'play', 'pause', 'ended']
            const listeners = events.map((eventName) => {
                const callback = () => syncState()
                player.on(eventName, callback)

                return {
                    eventName,
                    callback,
                }
            })

            this.boundPlayer = player
            this.boundPlayerEvents = listeners
            syncState()
        },

        unbindPlayerStateEvents() {
            if (this.boundPlayer && typeof this.boundPlayer.off === 'function') {
                this.boundPlayerEvents.forEach(({eventName, callback}) => {
                    this.boundPlayer.off(eventName, callback)
                })
            }

            this.boundPlayer = null
            this.boundPlayerEvents = []
        },

        async tryPlayCurrentStation(options = {}) {
            const playerComponent = this.$refs.player
            if (!playerComponent || typeof playerComponent.play !== 'function') {
                return
            }

            const silentSync = Boolean(options?.silentSync)

            const started = await playerComponent.play()
            this.isPlaying = Boolean(started)
            this.shouldResumePlayback = Boolean(started)
            this.playbackSessionStartedAt = started ? Date.now() : this.playbackSessionStartedAt
            this.autoplayNotice = started ? '' : this.$t('radio.autoplayBlocked')
            this.persistWidgetState()

            if (!silentSync) {
                this.notifyPlaybackState({reason: 'widget-play-attempt'})
            }
        },

        async playStation(station, options = {}) {
            const normalized = this.normalizeStationPayload(station)
            if (!normalized || !normalized.stream_url) {
                return
            }

            const silentSync = Boolean(options?.silentSync)
            const previousStationUuid = String(this.currentStation?.station_uuid || '')

            this.currentStation = normalized
            this.shouldResumePlayback = true
            this.autoplayNotice = ''
            if (normalized.station_uuid !== previousStationUuid || this.playbackSessionStartedAt <= 0) {
                this.playbackSessionStartedAt = Date.now()
            }

            await this.$nextTick()
            this.bindPlayerStateEvents()
            await this.tryPlayCurrentStation({silentSync})
        },

        async togglePlayback(options = {}) {
            if (!this.currentStation?.stream_url) {
                return
            }

            const silentSync = Boolean(options?.silentSync)

            const playerComponent = this.$refs.player
            if (!playerComponent) {
                return
            }

            if (this.isPlaying) {
                this.stopPlayback({silentSync})
                return
            }

            this.shouldResumePlayback = true
            await this.tryPlayCurrentStation({silentSync})
        },

        stopPlayback(options = {}) {
            const preserveResumeIntent = Boolean(options?.preserveResumeIntent)
            const silentSync = Boolean(options?.silentSync)
            const resumeIntent = preserveResumeIntent ? this.shouldResumePlayback : false

            const playerComponent = this.$refs.player
            if (playerComponent && typeof playerComponent.pause === 'function') {
                playerComponent.pause()
            }

            this.isPlaying = false
            this.shouldResumePlayback = resumeIntent
            this.autoplayNotice = ''
            this.persistWidgetState()

            if (!silentSync) {
                this.notifyPlaybackState({reason: 'widget-pause'})
            }
        },

        isFavorite(stationUuid) {
            if (!stationUuid) {
                return false
            }

            return this.favorites.some((item) => item.station_uuid === stationUuid)
        },

        async toggleFavorite(station) {
            const normalized = this.normalizeStationPayload(station)
            const stationUuid = normalized?.station_uuid
            if (!stationUuid || !normalized.stream_url) {
                return
            }

            try {
                const shouldRemove = this.isFavorite(stationUuid)

                if (shouldRemove) {
                    await axios.delete(`/api/radio/favorites/${encodeURIComponent(stationUuid)}`)
                } else {
                    await axios.post('/api/radio/favorites', {
                        station_uuid: normalized.station_uuid,
                        name: normalized.name || this.$t('radio.untitled'),
                        stream_url: normalized.stream_url,
                        homepage: normalized.homepage || null,
                        favicon: normalized.favicon || null,
                        country: normalized.country || null,
                        language: normalized.language || null,
                        tags: normalized.tags || null,
                        codec: normalized.codec || null,
                        bitrate: Number(normalized.bitrate || 0) || null,
                        votes: Number(normalized.votes || 0) || null,
                    })
                }

                await this.loadFavorites()
                this.notifyFavoritesSync({
                    action: shouldRemove ? 'remove' : 'add',
                    stationUuid,
                })
            } catch (_error) {
                // Keep widget quiet on temporary network/API issues.
            }
        },
    },
}
</script>
