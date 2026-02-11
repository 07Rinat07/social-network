<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h2 class="section-title">Радио</h2>
            <p class="section-subtitle">
                Ищите интернет-станции, слушайте прямо на сайте и сохраняйте любимые в избранное.
            </p>

            <form class="form-grid" @submit.prevent="searchStations">
                <input
                    class="input-field"
                    v-model.trim="filters.q"
                    type="text"
                    placeholder="Название станции или ключевое слово"
                >

                <div class="radio-filters-row">
                    <input class="input-field" v-model.trim="filters.country" type="text" placeholder="Страна (например Germany)">
                    <input class="input-field" v-model.trim="filters.language" type="text" placeholder="Язык (например English)">
                    <input class="input-field" v-model.trim="filters.tag" type="text" placeholder="Тег (rock, news, jazz...)">
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="submit" :disabled="isLoadingStations">
                        {{ isLoadingStations ? 'Поиск...' : 'Найти станции' }}
                    </button>
                    <button class="btn btn-outline" type="button" @click="resetFilters" :disabled="isLoadingStations">Сбросить фильтры</button>
                </div>
            </form>

            <p v-if="stationsError" class="error-text">{{ stationsError }}</p>
        </section>

        <section class="section-card radio-now-card" v-if="currentStation">
            <h3 class="section-title" style="font-size: 1.1rem; margin-bottom: 0.45rem;">Сейчас играет</h3>
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
                    <strong>{{ currentStation.name || 'Без названия' }}</strong>
                    <p class="muted" style="margin: 0.2rem 0 0; font-size: 0.82rem;">
                        {{ stationMeta(currentStation) }}
                    </p>
                </div>
            </div>

            <MediaPlayer
                type="audio"
                :src="currentStation.stream_url"
                player-class="media-audio"
                :mime-type="currentStation.codec ? `audio/${String(currentStation.codec).toLowerCase()}` : ''"
            ></MediaPlayer>

            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button class="btn btn-outline btn-sm" type="button" @click="toggleFavorite(currentStation)">
                    {{ isFavorite(currentStation.station_uuid) ? 'Убрать из избранного' : 'В избранное' }}
                </button>
                <a
                    v-if="currentStation.homepage"
                    :href="currentStation.homepage"
                    class="btn btn-outline btn-sm"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Сайт станции
                </a>
            </div>
        </section>

        <section class="section-card">
            <div class="radio-section-head">
                <h3 class="section-title" style="font-size: 1.1rem; margin: 0;">Найденные станции</h3>
                <span class="badge">{{ stations.length }}</span>
            </div>

            <p v-if="isLoadingStations" class="muted" style="margin: 0;">Загрузка станций...</p>
            <p v-else-if="stations.length === 0" class="muted" style="margin: 0;">По вашему запросу станции не найдены.</p>

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
                            <strong>{{ station.name || 'Без названия' }}</strong>
                            <p class="muted" style="margin: 0.15rem 0 0; font-size: 0.78rem;">{{ stationMeta(station) }}</p>
                        </div>
                    </div>

                    <p class="muted" style="margin: 0; font-size: 0.78rem;">
                        {{ station.tags || 'Без тегов' }}
                    </p>

                    <div class="radio-actions">
                        <button
                            class="btn btn-primary btn-sm"
                            type="button"
                            @click="playStation(station)"
                            :disabled="!station.stream_url"
                        >
                            Слушать
                        </button>
                        <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            @click="toggleFavorite(station)"
                            :disabled="isFavoriteSaving(station.station_uuid)"
                        >
                            {{ isFavorite(station.station_uuid) ? 'Убрать' : 'Избранное' }}
                        </button>
                    </div>
                </article>
            </div>
        </section>

        <section class="section-card">
            <div class="radio-section-head">
                <h3 class="section-title" style="font-size: 1.1rem; margin: 0;">Избранные станции</h3>
                <span class="badge">{{ favorites.length }}</span>
            </div>

            <p v-if="isLoadingFavorites" class="muted" style="margin: 0;">Загрузка избранного...</p>
            <p v-else-if="favorites.length === 0" class="muted" style="margin: 0;">Пока нет станций в избранном.</p>

            <div v-else class="simple-list">
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
                            <strong>{{ favorite.name || 'Без названия' }}</strong>
                            <p class="muted" style="margin: 0.15rem 0 0; font-size: 0.78rem;">{{ stationMeta(favorite) }}</p>
                        </div>
                    </div>

                    <div class="radio-actions">
                        <button class="btn btn-primary btn-sm" type="button" @click="playStation(favorite)">Слушать</button>
                        <button
                            class="btn btn-danger btn-sm"
                            type="button"
                            @click="toggleFavorite(favorite)"
                            :disabled="isFavoriteSaving(favorite.station_uuid)"
                        >
                            Удалить
                        </button>
                    </div>
                </div>
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
            stationsError: '',
            favoriteLoadingMap: {},
        }
    },

    async mounted() {
        await Promise.all([
            this.searchStations(),
            this.loadFavorites(),
        ])
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

            return meta.length > 0 ? meta.join(' · ') : 'Без метаданных'
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

            try {
                const response = await axios.get('/api/radio/stations', {
                    params: this.buildSearchParams(),
                })
                this.stations = response.data.data ?? []
            } catch (error) {
                this.stations = []
                this.stationsError = error.response?.data?.message || 'Не удалось получить станции.'
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
            this.searchStations()
        },

        playStation(station) {
            if (!station?.stream_url) {
                alert('У станции нет доступного потока для воспроизведения.')
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
                        name: station.name || 'Без названия',
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
                            name: station.name || 'Без названия',
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
                alert(error.response?.data?.message || 'Не удалось обновить избранное.')
            } finally {
                this.setFavoriteLoading(stationUuid, false)
            }
        },
    },
}
</script>
