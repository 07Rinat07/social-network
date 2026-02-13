<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h2 class="section-title">{{ $t('radio.title') }}</h2>
            <p class="section-subtitle">{{ $t('radio.subtitle') }}</p>

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

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="submit" :disabled="isLoadingStations">
                        {{ isLoadingStations ? $t('radio.searching') : $t('radio.searchButton') }}
                    </button>
                    <button class="btn btn-outline" type="button" @click="resetFilters" :disabled="isLoadingStations">{{ $t('radio.resetFilters') }}</button>
                </div>
            </form>

            <p v-if="stationsError" class="error-text">{{ stationsError }}</p>
        </section>

        <section class="section-card radio-now-card" v-if="currentStation">
            <h3 class="section-title" style="font-size: 1.1rem; margin-bottom: 0.45rem;">{{ $t('radio.nowPlaying') }}</h3>
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

            <MediaPlayer
                ref="radioPlayer"
                type="audio"
                :src="currentStation.stream_url"
                player-class="media-audio"
                :mime-type="currentStation.codec ? `audio/${String(currentStation.codec).toLowerCase()}` : ''"
            ></MediaPlayer>

            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
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

        <section class="section-card">
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
                            <p class="muted" style="margin: 0.15rem 0 0; font-size: 0.78rem;">{{ stationMeta(station) }}</p>
                        </div>
                    </div>

                    <p class="muted" style="margin: 0; font-size: 0.78rem;">
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
        }
    },

    async mounted() {
        await this.loadFavorites()
    },

    methods: {
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
                this.favorites = response.data.data ?? []
            } finally {
                this.isLoadingFavorites = false
            }
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
        },

        async playStation(station) {
            if (!station?.stream_url) {
                alert(this.$t('radio.noPlayableStream'))
                return
            }

            this.currentStation = {
                station_uuid: station.station_uuid || '',
                name: station.name || '',
                stream_url: station.stream_url || '',
                homepage: station.homepage || '',
                favicon: station.favicon || '',
                country: station.country || '',
                language: station.language || '',
                tags: station.tags || '',
                codec: station.codec || '',
                bitrate: Number(station.bitrate || 0),
            }

            this.autoplayNotice = ''
            await this.$nextTick()

            const player = this.$refs.radioPlayer
            if (!player || typeof player.play !== 'function') {
                return
            }

            const started = await player.play()
            if (!started) {
                this.autoplayNotice = this.$t('radio.autoplayBlocked')
            }
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
                if (this.isFavorite(stationUuid)) {
                    await axios.delete(`/api/radio/favorites/${encodeURIComponent(stationUuid)}`)
                    this.favorites = this.favorites.filter((item) => item.station_uuid !== stationUuid)
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

                    if (!this.isFavorite(stationUuid)) {
                        this.favorites.unshift({
                            station_uuid: station.station_uuid,
                            name: station.name || this.$t('radio.untitled'),
                            stream_url: station.stream_url,
                            homepage: station.homepage || '',
                            favicon: station.favicon || '',
                            country: station.country || '',
                            language: station.language || '',
                            tags: station.tags || '',
                            codec: station.codec || '',
                            bitrate: Number(station.bitrate || 0),
                            votes: Number(station.votes || 0),
                        })
                    }
                }

                this.stations = this.stations.map((item) => ({
                    ...item,
                    is_favorite: item.station_uuid === stationUuid ? this.isFavorite(stationUuid) : item.is_favorite,
                }))
            } catch (error) {
                alert(error.response?.data?.message || this.$t('radio.updateFavoritesError'))
            } finally {
                this.setFavoriteLoading(stationUuid, false)
            }
        },
    },
}
</script>
