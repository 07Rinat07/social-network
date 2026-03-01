<template>
    <div class="page-wrap grid-layout radio-page" :class="`radio-theme--${radioThemeMode}`">
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

            <div class="radio-motion-settings">
                <div class="radio-motion-settings__group">
                    <span class="muted radio-motion-settings__label">{{ $t('radio.carouselMotionLabel') }}</span>
                    <div class="radio-motion-settings__switch" role="group" :aria-label="$t('radio.carouselMotionLabel')">
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="carouselMotionMode === 'auto' ? 'btn-primary' : 'btn-outline'"
                            @click="setCarouselMotionMode('auto')"
                        >
                            {{ $t('radio.carouselMotionAuto') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="carouselMotionMode === 'manual' ? 'btn-primary' : 'btn-outline'"
                            @click="setCarouselMotionMode('manual')"
                        >
                            {{ $t('radio.carouselMotionManual') }}
                        </button>
                    </div>
                </div>

                <div class="radio-motion-settings__group">
                    <span class="muted radio-motion-settings__label">{{ $t('radio.carouselSpeedLabel') }}</span>
                    <div class="radio-motion-settings__switch radio-motion-settings__switch--triple" role="group" :aria-label="$t('radio.carouselSpeedLabel')">
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="carouselSpeedMode === 'slow' ? 'btn-primary' : 'btn-outline'"
                            @click="setCarouselSpeedMode('slow')"
                        >
                            {{ $t('radio.carouselSpeedSlow') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="carouselSpeedMode === 'normal' ? 'btn-primary' : 'btn-outline'"
                            @click="setCarouselSpeedMode('normal')"
                        >
                            {{ $t('radio.carouselSpeedNormal') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="carouselSpeedMode === 'fast' ? 'btn-primary' : 'btn-outline'"
                            @click="setCarouselSpeedMode('fast')"
                        >
                            {{ $t('radio.carouselSpeedFast') }}
                        </button>
                    </div>
                </div>

                <div class="radio-motion-settings__group">
                    <span class="muted radio-motion-settings__label">{{ $t('radio.themeLabel') }}</span>
                    <div class="radio-motion-settings__switch" role="group" :aria-label="$t('radio.themeLabel')">
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="radioThemeMode === 'transparent' ? 'btn-primary' : 'btn-outline'"
                            @click="setRadioThemeMode('transparent')"
                        >
                            {{ $t('radio.themeTransparent') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="radioThemeMode === 'contrast' ? 'btn-primary' : 'btn-outline'"
                            @click="setRadioThemeMode('contrast')"
                        >
                            {{ $t('radio.themeContrast') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="radio-featured-controls">
                <div
                    v-if="showFeaturedManualControls"
                    class="radio-carousel-actions"
                >
                    <button
                        class="btn btn-outline btn-sm radio-carousel-btn"
                        type="button"
                        :disabled="!canScrollFeaturedPrev"
                        :aria-label="$t('radio.carouselPrevious')"
                        @click="scrollFeaturedCarousel('prev')"
                    >
                        <span aria-hidden="true">‹</span>
                    </button>
                    <button
                        class="btn btn-outline btn-sm radio-carousel-btn"
                        type="button"
                        :disabled="!canScrollFeaturedNext"
                        :aria-label="$t('radio.carouselNext')"
                        @click="scrollFeaturedCarousel('next')"
                    >
                        <span aria-hidden="true">›</span>
                    </button>
                </div>
                <button
                    class="btn btn-outline btn-sm"
                    type="button"
                    :disabled="isCheckingFeatured"
                    @click="checkFeaturedPresets"
                >
                    {{ isCheckingFeatured ? $t('radio.featuredChecking') : $t('radio.featuredCheckAll') }}
                </button>
                <button
                    v-if="isMobileLayout"
                    class="btn btn-outline btn-sm radio-collapse-toggle"
                    type="button"
                    @click="toggleSectionCollapse('featured')"
                >
                    {{ sectionToggleLabel('featured') }}
                </button>
            </div>

            <div
                v-if="!isSectionCollapsed('featured')"
                class="radio-featured-carousel-shell"
                @mouseenter="pauseCarouselMotion('featured')"
                @mouseleave="resumeCarouselMotion('featured')"
                @focusin="pauseCarouselMotion('featured')"
                @focusout="resumeCarouselMotion('featured')"
            >
                <div
                    ref="featuredCarouselViewport"
                    class="radio-carousel-marquee"
                    :class="{ 'is-manual': !isCarouselAutoMotionEnabled }"
                    @scroll="syncFeaturedCarouselState"
                >
                    <div
                        class="radio-carousel-track radio-carousel-track--featured"
                        :class="{
                            'is-paused': isFeaturedCarouselPaused || !shouldLoopFeaturedCarousel,
                            'is-static': !shouldLoopFeaturedCarousel,
                            'is-manual': !isCarouselAutoMotionEnabled,
                        }"
                        :style="featuredCarouselTrackStyle"
                    >
                        <article
                            v-for="preset in featuredCarouselItems"
                            :key="preset._trackKey"
                            class="radio-carousel-card radio-featured-card"
                            :class="{ 'is-active': isFeaturedPresetCurrent(preset) }"
                        >
                            <div class="radio-card-title-row">
                                <strong>{{ preset.name }}</strong>
                                <div class="radio-card-title-row__badges">
                                    <span v-if="isFeaturedPresetCurrent(preset)" class="badge radio-live-pill">
                                        <span class="radio-live-pill__bars" aria-hidden="true">
                                            <i></i>
                                            <i></i>
                                            <i></i>
                                        </span>
                                        {{ $t('radio.live') }}
                                    </span>
                                    <span class="badge">{{ preset.shortLabel }}</span>
                                </div>
                            </div>

                            <p class="muted radio-card-meta">{{ featuredPresetMeta(preset) }}</p>

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
                                {{ featuredStatusLabel(preset) }}
                            </p>
                            <p
                                v-else
                                class="muted radio-featured-status"
                            >
                                {{ featuredStatusLabel(preset) }}
                            </p>

                            <div class="radio-actions radio-actions--compact radio-carousel-card__actions">
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
                                    @click="toggleFeaturedFavorite(preset)"
                                >
                                    {{
                                        featuredStationMap[preset.id] && isFavorite(featuredStationMap[preset.id].station_uuid)
                                            ? $t('common.remove')
                                            : $t('common.favorites')
                                    }}
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
            <p v-else class="muted radio-collapsed-note">{{ $t('radio.listCollapsedHint') }}</p>

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
            <div class="radio-now-top">
                <div class="radio-now-main">
                    <span class="radio-now-kicker">{{ $t('radio.nowPlaying') }}</span>
                    <div class="radio-station-head radio-station-head--now">
                        <img
                            v-if="currentStation.favicon"
                            :src="currentStation.favicon"
                            alt="station icon"
                            class="radio-station-icon"
                            @error="hideBrokenIcon"
                        >
                        <span v-else class="avatar avatar-sm avatar-placeholder">♪</span>
                        <div>
                            <div class="radio-card-title-row radio-card-title-row--now">
                                <strong>{{ currentStation.name || $t('radio.untitled') }}</strong>
                                <span class="badge radio-live-pill">
                                    <span class="radio-live-pill__bars" aria-hidden="true">
                                        <i></i>
                                        <i></i>
                                        <i></i>
                                    </span>
                                    {{ playbackStatusLabel }}
                                </span>
                            </div>
                            <p class="muted radio-now-inline-meta">
                                {{ stationEssentialMeta(currentStation, { includeCodec: true }) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="radio-now-badges">
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
                <div v-if="currentStationStreamHost" class="radio-now-meta-item radio-now-meta-item--host">
                    <span class="radio-now-meta-label">{{ $t('radio.metaStreamHost') }}</span>
                    <strong>{{ currentStationStreamHost }}</strong>
                </div>
            </div>

            <p v-if="currentStationTagsText" class="muted radio-now-tags">
                {{ $t('radio.metaTags') }}: {{ currentStationTagsText }}
            </p>

            <div class="radio-now-footer">
                <MediaPlayer
                    v-if="!isWidgetPlaybackBridgeEnabled"
                    ref="radioPlayer"
                    type="audio"
                    :src="playableCurrentStationStreamUrl"
                    player-class="media-audio"
                    :mime-type="resolveStationMimeType(currentStation)"
                    @playererror="handleRadioPlayerError"
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
            </div>

            <p v-if="autoplayNotice" class="muted" style="margin: 0.5rem 0 0; font-size: 0.78rem;">
                {{ autoplayNotice }}
            </p>
        </section>

        <section class="section-card radio-favorites-card">
            <div class="radio-section-head">
                <h3 class="section-title" style="font-size: 1.1rem; margin: 0;">{{ $t('radio.favoriteStations') }}</h3>
                <div class="radio-section-head-actions">
                    <div
                        v-if="showFavoritesManualControls"
                        class="radio-carousel-actions"
                    >
                        <button
                            class="btn btn-outline btn-sm radio-carousel-btn"
                            type="button"
                            :disabled="!canScrollFavoritesPrev"
                            :aria-label="$t('radio.carouselPrevious')"
                            @click="scrollFavoritesCarousel('prev')"
                        >
                            <span aria-hidden="true">‹</span>
                        </button>
                        <button
                            class="btn btn-outline btn-sm radio-carousel-btn"
                            type="button"
                            :disabled="!canScrollFavoritesNext"
                            :aria-label="$t('radio.carouselNext')"
                            @click="scrollFavoritesCarousel('next')"
                        >
                            <span aria-hidden="true">›</span>
                        </button>
                    </div>
                    <span class="badge">{{ favorites.length }}</span>
                    <button
                        v-if="isMobileLayout"
                        class="btn btn-outline btn-sm radio-collapse-toggle"
                        type="button"
                        @click="toggleSectionCollapse('favorites')"
                    >
                        {{ sectionToggleLabel('favorites') }}
                    </button>
                </div>
            </div>

            <p v-if="isSectionCollapsed('favorites')" class="muted radio-collapsed-note">{{ $t('radio.listCollapsedHint') }}</p>
            <p v-else-if="isLoadingFavorites" class="muted" style="margin: 0;">{{ $t('radio.loadingFavorites') }}</p>
            <p v-else-if="favorites.length === 0" class="muted" style="margin: 0;">{{ $t('radio.emptyFavorites') }}</p>

            <template v-else>
                <div class="radio-favorites-toolbar">
                    <div class="radio-toolbar-group">
                        <span class="muted radio-toolbar-label">{{ $t('radio.favoriteSortLabel') }}</span>
                        <div class="radio-toolbar-switch" role="group" :aria-label="$t('radio.favoriteSortLabel')">
                            <button
                                v-for="item in favoriteSortOptions"
                                :key="`favorite-sort-${item.id}`"
                                type="button"
                                class="btn btn-sm"
                                :class="favoriteSortMode === item.id ? 'btn-primary' : 'btn-outline'"
                                @click="favoriteSortMode = item.id"
                            >
                                {{ item.label }}
                            </button>
                        </div>
                    </div>

                    <div class="radio-toolbar-group">
                        <span class="muted radio-toolbar-label">{{ $t('radio.favoriteFilterLabel') }}</span>
                        <div class="radio-toolbar-switch" role="group" :aria-label="$t('radio.favoriteFilterLabel')">
                            <button
                                v-for="item in favoriteFilterOptions"
                                :key="`favorite-filter-${item.id}`"
                                type="button"
                                class="btn btn-sm"
                                :class="favoriteFilterMode === item.id ? 'btn-primary' : 'btn-outline'"
                                @click="favoriteFilterMode = item.id"
                            >
                                {{ item.label }} ({{ item.count }})
                            </button>
                        </div>
                    </div>
                </div>

                <p class="muted radio-toolbar-note">
                    {{ $t('radio.favoriteViewSummary', { shown: visibleFavoriteStations.length, total: favorites.length }) }}
                </p>

                <p v-if="visibleFavoriteStations.length === 0" class="muted" style="margin: 0;">{{ $t('radio.emptyFavoritesByView') }}</p>

                <div
                    v-else
                    class="radio-favorites-carousel-shell"
                    @mouseenter="pauseCarouselMotion('favorites')"
                    @mouseleave="resumeCarouselMotion('favorites')"
                    @focusin="pauseCarouselMotion('favorites')"
                    @focusout="resumeCarouselMotion('favorites')"
                >
                    <div
                        ref="favoritesCarouselViewport"
                        class="radio-carousel-marquee"
                        :class="{ 'is-manual': !isCarouselAutoMotionEnabled }"
                        @scroll="syncFavoritesCarouselState"
                    >
                        <div
                            class="radio-carousel-track radio-carousel-track--favorites"
                            :class="{
                                'is-paused': isFavoritesCarouselPaused || !shouldLoopFavoritesCarousel,
                                'is-static': !shouldLoopFavoritesCarousel,
                                'is-manual': !isCarouselAutoMotionEnabled,
                            }"
                            :style="favoritesCarouselTrackStyle"
                        >
                            <div
                                v-for="favorite in favoriteCarouselItems"
                                :key="favorite._trackKey"
                                class="radio-carousel-card radio-favorite-item radio-compact-item radio-favorite-card"
                                :class="{ 'is-active': isCurrentStation(favorite) }"
                            >
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
                                        <div class="radio-card-title-row">
                                            <strong>{{ favorite.name || $t('radio.untitled') }}</strong>
                                            <span v-if="isCurrentStation(favorite)" class="badge radio-live-pill">
                                                <span class="radio-live-pill__bars" aria-hidden="true">
                                                    <i></i>
                                                    <i></i>
                                                    <i></i>
                                                </span>
                                                {{ $t('radio.live') }}
                                            </span>
                                        </div>
                                        <p class="muted radio-favorite-meta">{{ stationEssentialMeta(favorite) }}</p>
                                    </div>
                                </div>

                                <div class="radio-favorite-controls">
                                    <div class="radio-actions radio-actions--compact radio-carousel-card__actions">
                                        <button class="btn btn-primary btn-sm" type="button" @click="playStation(favorite)">{{ $t('radio.listen') }}</button>
                                        <button
                                            class="btn btn-outline btn-sm"
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
                    </div>
                </div>
            </template>
        </section>

        <section class="section-card">
            <div class="radio-section-head">
                <h3 class="section-title" style="font-size: 1.1rem; margin: 0;">{{ $t('radio.foundStations') }}</h3>
                <div class="radio-section-head-actions">
                    <span class="badge">{{ stations.length }}</span>
                    <button
                        v-if="isSectionCollapsible('stations')"
                        class="btn btn-outline btn-sm radio-collapse-toggle"
                        type="button"
                        @click="toggleSectionCollapse('stations')"
                    >
                        {{ sectionToggleLabel('stations') }}
                    </button>
                </div>
            </div>

            <p v-if="isSectionCollapsed('stations')" class="muted radio-collapsed-note">{{ $t('radio.listCollapsedHint') }}</p>
            <p v-else-if="isLoadingStations" class="muted" style="margin: 0;">{{ $t('radio.loadingStations') }}</p>
            <p v-else-if="!hasSearchResults" class="muted" style="margin: 0;">{{ $t('radio.useSearchHint') }}</p>
            <p v-else-if="stations.length === 0" class="muted" style="margin: 0;">{{ $t('radio.emptySearch') }}</p>

            <div v-else class="radio-compact-list radio-stations-list">
                <article
                    v-for="station in stations"
                    :key="`station-${station.station_uuid}`"
                    class="radio-station-card radio-compact-item"
                    :class="{ 'is-active': isCurrentStation(station) }"
                >
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
                            <div class="radio-card-title-row">
                                <strong>{{ station.name || $t('radio.untitled') }}</strong>
                                <span v-if="isCurrentStation(station)" class="badge radio-live-pill">
                                    <span class="radio-live-pill__bars" aria-hidden="true">
                                        <i></i>
                                        <i></i>
                                        <i></i>
                                    </span>
                                    {{ $t('radio.live') }}
                                </span>
                            </div>
                            <p class="muted radio-station-meta">{{ stationEssentialMeta(station, { includeCodec: true }) }}</p>
                            <p v-if="station.tags" class="muted radio-compact-tags">
                                {{ station.tags }}
                            </p>
                        </div>
                    </div>

                    <div class="radio-actions radio-actions--compact">
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
import { ANALYTICS_EVENTS, ANALYTICS_FEATURES, reportAnalyticsEvent } from '../../utils/analyticsTracker.mjs'
import {
    formatPlaybackTime as formatPlaybackTimeHelper,
    isMobileViewport,
    resolveSiteSessionStartedAt as resolveSiteSessionStartedAtHelper,
    resolveStationSessionStateFromSnapshot,
} from '../../utils/radioSession.mjs'

const RADIO_FEATURED_PRESETS = RADIO_PRESET_CATALOG

// Shared events for page <-> persistent widget state synchronization.
const RADIO_FAVORITES_SYNC_EVENT = 'social:radio:favorites-updated'
const RADIO_FAVORITES_SYNC_SOURCE = 'radio-page'
const RADIO_PLAYBACK_SYNC_EVENT = 'social:radio:playback-sync'
const RADIO_PLAYBACK_READY_EVENT = 'social:radio:playback-ready'
const RADIO_PLAYBACK_SOURCE_PAGE = 'radio-page'
const RADIO_PLAYBACK_SOURCE_WIDGET = 'widget-radio'
const RADIO_CAROUSEL_MOTION_MODE_STORAGE_KEY = 'social-radio-carousel-motion-mode'
const RADIO_CAROUSEL_SPEED_STORAGE_KEY = 'social-radio-carousel-speed'
const RADIO_PLAY_COUNTS_STORAGE_KEY = 'social-radio-station-play-counts'
const RADIO_THEME_MODE_STORAGE_KEY = 'social-radio-theme-mode'

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
            siteSessionStartedAt: 0,
            stationSessionStartedAt: 0,
            stationSessionAccumulatedMs: 0,
            uiNowTimestamp: Date.now(),
            uiNowTimerId: null,
            isMobileLayout: false,
            carouselMotionMode: 'auto',
            carouselSpeedMode: 'normal',
            radioThemeMode: 'transparent',
            canScrollFeaturedPrev: false,
            canScrollFeaturedNext: false,
            canScrollFavoritesPrev: false,
            canScrollFavoritesNext: false,
            isFeaturedCarouselPaused: false,
            isFavoritesCarouselPaused: false,
            prefersReducedMotion: false,
            favoriteSortMode: 'recent',
            favoriteFilterMode: 'all',
            stationPlayCounts: {},
            collapsedSections: {
                featured: false,
                favorites: false,
                stations: true,
            },
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
            window.addEventListener('resize', this.syncMobileLayoutState)
            this.widgetPlaybackBridgeReady = Boolean(window.__socialRadioWidgetReady)
            this.prefersReducedMotion = Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches)
            this.restoreCarouselMotionMode()
            this.restoreCarouselSpeedMode()
            this.restoreRadioThemeMode()
            this.restoreStationPlayCounts()
            this.syncMobileLayoutState()
        }

        this.siteSessionStartedAt = this.resolveSiteSessionStartedAt()
        this.startUiTicker()
        await this.loadFavorites()
        this.$nextTick(() => {
            this.resetFeaturedCarouselPosition()
            this.resetFavoritesCarouselPosition()
        })

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
            window.removeEventListener('resize', this.syncMobileLayoutState)
        }
    },

    watch: {
        'playbackState.isPlaying'() {
            this.syncStationSessionTimer()
        },
        featuredCategory() {
            this.$nextTick(() => {
                this.resetFeaturedCarouselPosition()
            })
        },
        favorites() {
            this.$nextTick(() => {
                this.resetFavoritesCarouselPosition()
            })
        },
        favoriteSortMode() {
            this.$nextTick(() => {
                this.resetFavoritesCarouselPosition()
            })
        },
        favoriteFilterMode() {
            this.$nextTick(() => {
                this.resetFavoritesCarouselPosition()
            })
        },
        currentStation() {
            if (this.favoriteFilterMode !== 'active') {
                return
            }

            this.$nextTick(() => {
                this.resetFavoritesCarouselPosition()
            })
        },
        carouselMotionMode() {
            this.persistCarouselMotionMode()
            this.$nextTick(() => {
                this.resetFeaturedCarouselPosition()
                this.resetFavoritesCarouselPosition()
            })
        },
        carouselSpeedMode() {
            this.persistCarouselSpeedMode()
        },
        radioThemeMode() {
            this.persistRadioThemeMode()
        },
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

        isCarouselAutoMotionEnabled() {
            return this.carouselMotionMode === 'auto'
        },

        carouselDurationMultiplier() {
            if (this.carouselSpeedMode === 'slow') {
                return 1.35
            }

            if (this.carouselSpeedMode === 'fast') {
                return 0.72
            }

            return 1
        },

        shouldLoopFeaturedCarousel() {
            return this.isCarouselAutoMotionEnabled && this.visibleFeaturedPresets.length > 1
        },

        shouldLoopFavoritesCarousel() {
            return this.isCarouselAutoMotionEnabled && this.visibleFavoriteStations.length > 1
        },

        featuredCarouselItems() {
            const list = Array.isArray(this.visibleFeaturedPresets) ? this.visibleFeaturedPresets : []
            const loops = this.shouldLoopFeaturedCarousel ? [0, 1] : [0]

            return loops.flatMap((loop) => list.map((preset, index) => ({
                ...preset,
                _trackKey: `radio-featured-${loop}-${preset.id ?? 'preset'}-${index}`,
            })))
        },

        favoriteCarouselItems() {
            const list = Array.isArray(this.visibleFavoriteStations) ? this.visibleFavoriteStations : []
            const loops = this.shouldLoopFavoritesCarousel ? [0, 1] : [0]

            return loops.flatMap((loop) => list.map((station, index) => ({
                ...station,
                _trackKey: `radio-favorite-${loop}-${station.station_uuid ?? 'station'}-${index}`,
            })))
        },

        featuredCarouselTrackStyle() {
            const baseDuration = Math.max(18, Math.round(this.visibleFeaturedPresets.length * 3.1 * this.carouselDurationMultiplier))
            return {
                '--radio-carousel-duration': `${baseDuration}s`,
            }
        },

        favoritesCarouselTrackStyle() {
            const baseDuration = Math.max(20, Math.round(this.visibleFavoriteStations.length * 3.5 * this.carouselDurationMultiplier))
            return {
                '--radio-carousel-duration': `${baseDuration}s`,
            }
        },

        showFeaturedManualControls() {
            return !this.isCarouselAutoMotionEnabled && !this.isSectionCollapsed('featured') && this.visibleFeaturedPresets.length > 1
        },

        showFavoritesManualControls() {
            return !this.isCarouselAutoMotionEnabled && !this.isSectionCollapsed('favorites') && this.visibleFavoriteStations.length > 1
        },

        favoriteSortOptions() {
            return [
                { id: 'recent', label: this.$t('radio.favoriteSortRecent') },
                { id: 'frequent', label: this.$t('radio.favoriteSortFrequent') },
                { id: 'name', label: this.$t('radio.favoriteSortName') },
            ]
        },

        favoriteFilterOptions() {
            return [
                {
                    id: 'all',
                    label: this.$t('radio.favoriteFilterAll'),
                    count: this.favorites.length,
                },
                {
                    id: 'active',
                    label: this.$t('radio.favoriteFilterActive'),
                    count: this.favorites.filter((station) => this.matchesFavoriteFilter(station, 'active')).length,
                },
                {
                    id: 'website',
                    label: this.$t('radio.favoriteFilterWebsite'),
                    count: this.favorites.filter((station) => this.matchesFavoriteFilter(station, 'website')).length,
                },
            ]
        },

        visibleFavoriteStations() {
            const baseList = Array.isArray(this.favorites) ? [...this.favorites] : []
            const filtered = baseList.filter((station) => this.matchesFavoriteFilter(station, this.favoriteFilterMode))

            if (this.favoriteSortMode === 'name') {
                return filtered.sort((left, right) => this.compareStationsByName(left, right))
            }

            if (this.favoriteSortMode === 'frequent') {
                return filtered.sort((left, right) => {
                    const diff = this.getStationPlayCount(right?.station_uuid) - this.getStationPlayCount(left?.station_uuid)
                    if (diff !== 0) {
                        return diff
                    }

                    return this.compareStationsByName(left, right)
                })
            }

            return filtered
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
            if (!this.isPlaybackSeekEnabled) {
                return `${this.siteSessionLabel} · ${this.$t('radio.live')}`
            }

            const current = this.formatPlaybackTime(this.playbackState.currentTime)
            const duration = this.formatPlaybackTime(this.playbackState.duration)
            return `${current} / ${duration}`
        },

        playbackStatusTone() {
            return this.playbackState.isPlaying ? 'playing' : 'paused'
        },

        playbackStatusLabel() {
            return this.playbackState.isPlaying ? this.$t('radio.statusPlaying') : this.$t('radio.statusPaused')
        },

        siteSessionLabel() {
            const startedAt = Number(this.siteSessionStartedAt || 0)
            if (!Number.isFinite(startedAt) || startedAt <= 0) {
                return this.formatPlaybackTime(0)
            }

            const diffSeconds = Math.max(0, Math.floor((this.uiNowTimestamp - startedAt) / 1000))
            return this.formatPlaybackTime(diffSeconds)
        },

        stationSessionLabel() {
            if (!this.currentStation) {
                return this.formatPlaybackTime(0)
            }

            let elapsedMs = Math.max(0, Number(this.stationSessionAccumulatedMs || 0))
            if (this.playbackState.isPlaying && this.stationSessionStartedAt > 0) {
                elapsedMs += Math.max(0, this.uiNowTimestamp - this.stationSessionStartedAt)
            }

            const diffSeconds = Math.floor(elapsedMs / 1000)
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
        // Keep collapse controls active only for compact/mobile layout.
        syncMobileLayoutState() {
            if (typeof window === 'undefined') {
                this.isMobileLayout = false
                return
            }

            this.isMobileLayout = isMobileViewport(window.innerWidth)
            this.syncFeaturedCarouselState()
            this.syncFavoritesCarouselState()
        },

        normalizeCarouselMotionMode(value) {
            return String(value || '').trim().toLowerCase() === 'manual' ? 'manual' : 'auto'
        },

        normalizeCarouselSpeedMode(value) {
            const normalized = String(value || '').trim().toLowerCase()
            if (normalized === 'slow' || normalized === 'fast') {
                return normalized
            }

            return 'normal'
        },

        normalizeRadioThemeMode(value) {
            return String(value || '').trim().toLowerCase() === 'contrast' ? 'contrast' : 'transparent'
        },

        restoreCarouselMotionMode() {
            if (typeof window === 'undefined') {
                return
            }

            let nextMode = 'auto'
            try {
                const stored = window.localStorage?.getItem(RADIO_CAROUSEL_MOTION_MODE_STORAGE_KEY)
                if (stored) {
                    nextMode = this.normalizeCarouselMotionMode(stored)
                } else if (this.prefersReducedMotion) {
                    nextMode = 'manual'
                }
            } catch (_error) {
                nextMode = this.prefersReducedMotion ? 'manual' : 'auto'
            }

            this.carouselMotionMode = nextMode
        },

        restoreCarouselSpeedMode() {
            if (typeof window === 'undefined') {
                return
            }

            let nextSpeed = 'normal'
            try {
                const stored = window.localStorage?.getItem(RADIO_CAROUSEL_SPEED_STORAGE_KEY)
                if (stored) {
                    nextSpeed = this.normalizeCarouselSpeedMode(stored)
                }
            } catch (_error) {
                nextSpeed = 'normal'
            }

            this.carouselSpeedMode = nextSpeed
        },

        restoreRadioThemeMode() {
            if (typeof window === 'undefined') {
                return
            }

            let nextTheme = 'transparent'
            try {
                const stored = window.localStorage?.getItem(RADIO_THEME_MODE_STORAGE_KEY)
                if (stored) {
                    nextTheme = this.normalizeRadioThemeMode(stored)
                }
            } catch (_error) {
                nextTheme = 'transparent'
            }

            this.radioThemeMode = nextTheme
        },

        persistCarouselMotionMode() {
            if (typeof window === 'undefined') {
                return
            }

            try {
                window.localStorage?.setItem(RADIO_CAROUSEL_MOTION_MODE_STORAGE_KEY, this.carouselMotionMode)
            } catch (_error) {
                // Ignore storage failures and keep the in-memory preference.
            }
        },

        persistCarouselSpeedMode() {
            if (typeof window === 'undefined') {
                return
            }

            try {
                window.localStorage?.setItem(RADIO_CAROUSEL_SPEED_STORAGE_KEY, this.carouselSpeedMode)
            } catch (_error) {
                // Ignore storage failures and keep the in-memory preference.
            }
        },

        persistRadioThemeMode() {
            if (typeof window === 'undefined') {
                return
            }

            try {
                window.localStorage?.setItem(RADIO_THEME_MODE_STORAGE_KEY, this.radioThemeMode)
            } catch (_error) {
                // Ignore storage failures and keep the in-memory preference.
            }
        },

        setCarouselMotionMode(mode) {
            this.carouselMotionMode = this.normalizeCarouselMotionMode(mode)
            this.isFeaturedCarouselPaused = false
            this.isFavoritesCarouselPaused = false
        },

        setCarouselSpeedMode(mode) {
            this.carouselSpeedMode = this.normalizeCarouselSpeedMode(mode)
        },

        setRadioThemeMode(mode) {
            this.radioThemeMode = this.normalizeRadioThemeMode(mode)
        },

        restoreStationPlayCounts() {
            if (typeof window === 'undefined') {
                return
            }

            try {
                const raw = window.localStorage?.getItem(RADIO_PLAY_COUNTS_STORAGE_KEY)
                const parsed = JSON.parse(raw || '{}')
                const nextMap = Object.entries(parsed || {}).reduce((carry, [stationUuid, playCount]) => {
                    const normalizedStationUuid = String(stationUuid || '').trim()
                    const normalizedPlayCount = Number(playCount || 0)
                    if (normalizedStationUuid === '' || !Number.isFinite(normalizedPlayCount) || normalizedPlayCount <= 0) {
                        return carry
                    }

                    carry[normalizedStationUuid] = Math.floor(normalizedPlayCount)
                    return carry
                }, {})

                this.stationPlayCounts = nextMap
            } catch (_error) {
                this.stationPlayCounts = {}
            }
        },

        persistStationPlayCounts() {
            if (typeof window === 'undefined') {
                return
            }

            try {
                window.localStorage?.setItem(RADIO_PLAY_COUNTS_STORAGE_KEY, JSON.stringify(this.stationPlayCounts))
            } catch (_error) {
                // Ignore storage failures and keep the in-memory counters.
            }
        },

        markStationPlayed(station) {
            const stationUuid = String(station?.station_uuid || '').trim()
            if (stationUuid === '') {
                return
            }

            const currentCount = Math.max(0, Number(this.stationPlayCounts?.[stationUuid] || 0))
            this.stationPlayCounts = {
                ...this.stationPlayCounts,
                [stationUuid]: currentCount + 1,
            }
            this.persistStationPlayCounts()
            this.$nextTick(() => {
                this.resetFavoritesCarouselPosition()
            })
        },

        getStationPlayCount(stationUuid) {
            const normalizedStationUuid = String(stationUuid || '').trim()
            if (normalizedStationUuid === '') {
                return 0
            }

            const playCount = Number(this.stationPlayCounts?.[normalizedStationUuid] || 0)
            return Number.isFinite(playCount) && playCount > 0 ? playCount : 0
        },

        compareStationsByName(left, right) {
            return String(left?.name || '').localeCompare(
                String(right?.name || ''),
                this.$i18n?.locale || 'ru',
                { sensitivity: 'base' }
            )
        },

        matchesFavoriteFilter(station, filterMode = this.favoriteFilterMode) {
            const normalizedFilter = String(filterMode || 'all').trim().toLowerCase()

            if (normalizedFilter === 'active') {
                return this.isCurrentStation(station)
            }

            if (normalizedFilter === 'website') {
                return String(station?.homepage || '').trim() !== ''
            }

            return true
        },

        isSectionCollapsible(sectionKey) {
            return this.isMobileLayout || sectionKey === 'stations'
        },

        isSectionCollapsed(sectionKey) {
            if (!this.isSectionCollapsible(sectionKey)) {
                return false
            }

            return Boolean(this.collapsedSections?.[sectionKey])
        },

        toggleSectionCollapse(sectionKey) {
            if (!this.isSectionCollapsible(sectionKey)) {
                return
            }

            this.collapsedSections = {
                ...this.collapsedSections,
                [sectionKey]: !Boolean(this.collapsedSections?.[sectionKey]),
            }
        },

        sectionToggleLabel(sectionKey) {
            return this.isSectionCollapsed(sectionKey)
                ? this.$t('radio.expandList')
                : this.$t('radio.collapseList')
        },

        getFeaturedCarouselViewportElement() {
            const ref = this.$refs.featuredCarouselViewport
            if (!ref || typeof ref !== 'object') {
                return null
            }

            return ref
        },

        syncFeaturedCarouselState() {
            const viewport = this.getFeaturedCarouselViewportElement()
            if (!viewport || this.isCarouselAutoMotionEnabled) {
                this.canScrollFeaturedPrev = false
                this.canScrollFeaturedNext = false
                return
            }

            const maxScrollLeft = Math.max(0, viewport.scrollWidth - viewport.clientWidth)
            const scrollLeft = Math.max(0, Number(viewport.scrollLeft || 0))
            this.canScrollFeaturedPrev = scrollLeft > 8
            this.canScrollFeaturedNext = scrollLeft < maxScrollLeft - 8
        },

        getFavoritesCarouselViewportElement() {
            const ref = this.$refs.favoritesCarouselViewport
            if (!ref || typeof ref !== 'object') {
                return null
            }

            return ref
        },

        syncFavoritesCarouselState() {
            const viewport = this.getFavoritesCarouselViewportElement()
            if (!viewport || this.isCarouselAutoMotionEnabled) {
                this.canScrollFavoritesPrev = false
                this.canScrollFavoritesNext = false
                return
            }

            const maxScrollLeft = Math.max(0, viewport.scrollWidth - viewport.clientWidth)
            const scrollLeft = Math.max(0, Number(viewport.scrollLeft || 0))
            this.canScrollFavoritesPrev = scrollLeft > 8
            this.canScrollFavoritesNext = scrollLeft < maxScrollLeft - 8
        },

        resetFeaturedCarouselPosition() {
            const viewport = this.getFeaturedCarouselViewportElement()
            if (!viewport) {
                this.syncFeaturedCarouselState()
                return
            }

            viewport.scrollTo({
                left: 0,
                behavior: 'auto',
            })
            this.syncFeaturedCarouselState()
        },

        resetFavoritesCarouselPosition() {
            const viewport = this.getFavoritesCarouselViewportElement()
            if (!viewport) {
                this.syncFavoritesCarouselState()
                return
            }

            viewport.scrollTo({
                left: 0,
                behavior: 'auto',
            })
            this.syncFavoritesCarouselState()
        },

        scrollFeaturedCarousel(direction = 'next') {
            const viewport = this.getFeaturedCarouselViewportElement()
            if (!viewport || this.isCarouselAutoMotionEnabled) {
                return
            }

            const step = Math.max(240, Math.floor(viewport.clientWidth * 0.82))
            const offset = direction === 'prev' ? -step : step
            viewport.scrollBy({
                left: offset,
                behavior: 'smooth',
            })
        },

        scrollFavoritesCarousel(direction = 'next') {
            const viewport = this.getFavoritesCarouselViewportElement()
            if (!viewport || this.isCarouselAutoMotionEnabled) {
                return
            }

            const step = Math.max(260, Math.floor(viewport.clientWidth * 0.82))
            const offset = direction === 'prev' ? -step : step
            viewport.scrollBy({
                left: offset,
                behavior: 'smooth',
            })
        },

        pauseCarouselMotion(section) {
            if (!this.isCarouselAutoMotionEnabled) {
                return
            }

            if (section === 'featured') {
                this.isFeaturedCarouselPaused = true
                return
            }

            this.isFavoritesCarouselPaused = true
        },

        resumeCarouselMotion(section) {
            if (!this.isCarouselAutoMotionEnabled) {
                return
            }

            if (section === 'featured') {
                this.isFeaturedCarouselPaused = false
                return
            }

            this.isFavoritesCarouselPaused = false
        },

        setRadioNotice(message = '') {
            this.radioNotice = String(message || '').trim()
        },

        resolveSiteSessionStartedAt() {
            if (typeof window === 'undefined') {
                return resolveSiteSessionStartedAtHelper()
            }

            return resolveSiteSessionStartedAtHelper({
                storage: window.sessionStorage,
            })
        },

        resetStationSessionTimer() {
            this.stationSessionAccumulatedMs = 0
            this.stationSessionStartedAt = 0
        },

        // Maintain an "active segment" stopwatch while station is playing.
        syncStationSessionTimer() {
            const now = Date.now()
            if (!this.currentStation) {
                this.stationSessionStartedAt = 0
                return
            }

            if (this.playbackState.isPlaying) {
                if (this.stationSessionStartedAt <= 0) {
                    this.stationSessionStartedAt = now
                }
                return
            }

            if (this.stationSessionStartedAt > 0) {
                this.stationSessionAccumulatedMs += Math.max(0, now - this.stationSessionStartedAt)
                this.stationSessionStartedAt = 0
            }
        },

        // Align local session counters with playback snapshot received from widget bridge.
        syncStationSessionFromSnapshot(snapshot = {}) {
            const now = Date.now()
            const isPlaying = Boolean(snapshot?.isPlaying)
            const currentTime = Number(snapshot?.currentTime || 0)
            const sessionStartedAt = Number(snapshot?.sessionStartedAt || 0)
            const nextSession = resolveStationSessionStateFromSnapshot({
                hasCurrentStation: Boolean(this.currentStation),
                isPlaying,
                currentTime,
                sessionStartedAt,
                now,
            })

            this.stationSessionAccumulatedMs = nextSession.accumulatedMs
            this.stationSessionStartedAt = nextSession.startedAt
        },

        startUiTicker() {
            this.stopUiTicker()
            // Single heartbeat updates both UI clock and station session timer.
            this.uiNowTimerId = window.setInterval(() => {
                this.uiNowTimestamp = Date.now()
                this.syncStationSessionTimer()
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
            return formatPlaybackTimeHelper(value)
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
            this.syncStationSessionTimer()
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
                this.syncStationSessionTimer()
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

        resolveStationMimeType(station) {
            const codec = String(station?.codec || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9+]/g, '')

            if (codec === '') {
                return ''
            }

            if (codec.includes('mp3') || codec.includes('mpeg')) {
                return 'audio/mpeg'
            }

            if (codec.includes('aac')) {
                return 'audio/aac'
            }

            if (codec.includes('ogg') || codec.includes('opus') || codec.includes('vorbis')) {
                return 'audio/ogg'
            }

            if (codec.includes('flac')) {
                return 'audio/flac'
            }

            if (codec.includes('wav') || codec.includes('pcm')) {
                return 'audio/wav'
            }

            if (codec.includes('m4a') || codec.includes('mp4')) {
                return 'audio/mp4'
            }

            if (codec.includes('webm')) {
                return 'audio/webm'
            }

            return ''
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

        featuredPresetMeta(preset) {
            const meta = []

            if (preset?.country) {
                meta.push(preset.country)
            }
            if (preset?.language) {
                meta.push(preset.language)
            }

            return meta.length > 0 ? meta.join(' · ') : preset?.shortLabel || this.$t('radio.noMetadata')
        },

        featuredStatusLabel(preset) {
            if (this.isFeaturedLoading(preset?.id)) {
                return this.$t('radio.featuredLoading')
            }

            const station = this.featuredStationMap?.[preset?.id]
            if (station) {
                return this.stationEssentialMeta(station, { includeCodec: true })
            }

            return this.$t('radio.featuredTapHint')
        },

        isFeaturedPresetCurrent(preset) {
            return this.isCurrentStation(this.featuredStationMap?.[preset?.id])
        },

        stationEssentialMeta(station, options = {}) {
            const includeCodec = Boolean(options?.includeCodec)
            const meta = []

            if (station?.country) {
                meta.push(station.country)
            }
            if (station?.language) {
                meta.push(station.language)
            }
            if (Number(station?.bitrate || 0) > 0) {
                meta.push(`${station.bitrate} kbps`)
            }
            if (includeCodec && station?.codec) {
                meta.push(String(station.codec).toUpperCase())
            } else if (meta.length === 0 && station?.codec) {
                meta.push(String(station.codec).toUpperCase())
            }

            return meta.length > 0 ? meta.join(' · ') : this.$t('radio.noMetadata')
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

        async reportRadioAnalytics(eventName, station, extraContext = {}) {
            const normalizedStation = this.normalizeStationPayload(station)

            await reportAnalyticsEvent({
                feature: ANALYTICS_FEATURES.RADIO,
                event_name: eventName,
                entity_type: 'radio_station',
                entity_key: normalizedStation.station_uuid || normalizedStation.stream_url || null,
                context: {
                    station_name: normalizedStation.name || this.$t('radio.untitled'),
                    country: normalizedStation.country || '',
                    language: normalizedStation.language || '',
                    codec: normalizedStation.codec || '',
                    ...extraContext,
                },
            })
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
            this.$nextTick(() => {
                this.syncFavoritesCarouselState()
            })
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

            // Widget is source-of-truth when cross-page playback bridge is active.
            this.widgetPlaybackBridgeReady = true
            this.unbindPlayerStateEvents()

            const normalizedStation = this.normalizeStationPayload(event?.detail?.station)
            if (normalizedStation?.stream_url) {
                const previousStationUuid = String(this.currentStation?.station_uuid || '')
                this.currentStation = normalizedStation

                if (normalizedStation.station_uuid !== previousStationUuid) {
                    this.resetStationSessionTimer()
                }
            }

            const currentTime = Number(event?.detail?.currentTime || 0)
            const duration = Number(event?.detail?.duration || 0)
            const isPlaying = Boolean(event?.detail?.isPlaying)
            const sessionStartedAt = Number(event?.detail?.sessionStartedAt || 0)

            this.playbackState = {
                isPlaying,
                currentTime: Number.isFinite(currentTime) && currentTime > 0 ? currentTime : 0,
                duration: Number.isFinite(duration) && duration > 0 ? duration : 0,
            }

            this.syncStationSessionFromSnapshot({
                isPlaying,
                currentTime,
                sessionStartedAt,
            })
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

        async handleRadioPlayerError(payload = {}) {
            await this.reportRadioAnalytics(ANALYTICS_EVENTS.RADIO_PLAY_FAILED, this.currentStation, {
                reason: String(payload?.message || '').trim() || 'player-error',
                code: Number(payload?.code || 0),
            })
        },

        async playStation(station) {
            const normalizedStation = this.normalizeStationPayload(station)
            if (!normalizedStation.stream_url) {
                this.setRadioNotice(this.$t('radio.noPlayableStream'))
                return
            }

            const previousStationUuid = String(this.currentStation?.station_uuid || '')
            this.currentStation = normalizedStation
            if (normalizedStation.station_uuid !== previousStationUuid) {
                this.resetStationSessionTimer()
                this.syncStationSessionTimer()
            }

            this.autoplayNotice = ''
            this.setRadioNotice('')

            if (this.isWidgetPlaybackBridgeEnabled) {
                this.pauseLocalPlaybackForBridgeSync()
                this.playbackState = {
                    ...this.playbackState,
                    isPlaying: true,
                }
                this.markStationPlayed(normalizedStation)
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
                await this.reportRadioAnalytics(ANALYTICS_EVENTS.RADIO_PLAY_FAILED, normalizedStation, {
                    reason: 'autoplay-blocked',
                })
            } else {
                this.markStationPlayed(normalizedStation)
                await this.reportRadioAnalytics(ANALYTICS_EVENTS.RADIO_PLAY_STARTED, normalizedStation, {
                    source: 'radio-page',
                })
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

    updated() {
        this.syncFeaturedCarouselState()
        this.syncFavoritesCarouselState()
    },
}
</script>
