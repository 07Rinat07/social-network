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

            <div class="iptv-library-box">
                <div class="iptv-seed-head">
                    <strong>{{ $t('iptv.savedTitle') }}</strong>
                    <small class="muted">{{ $t('iptv.savedCounts', { playlists: savedPlaylistSources.length, channels: savedChannelSources.length }) }}</small>
                </div>

                <div class="iptv-library-actions">
                    <button class="btn btn-outline btn-sm" type="button" :disabled="isLoadingPlaylist || isLoadingSavedLibrary || isImportingSavedLibrary" @click="saveCurrentPlaylistSource">
                        {{ $t('iptv.savePlaylist') }}
                    </button>
                    <button class="btn btn-outline btn-sm" type="button" :disabled="isLoadingPlaylist || isLoadingSavedLibrary || isImportingSavedLibrary || !currentChannel" @click="saveCurrentChannelSource">
                        {{ $t('iptv.saveChannel') }}
                    </button>
                    <button class="btn btn-outline btn-sm" type="button" :disabled="savedPlaylistSources.length + savedChannelSources.length === 0 || isLoadingSavedLibrary || isImportingSavedLibrary" @click="exportSavedLibrary">
                        {{ $t('iptv.savedExport') }}
                    </button>
                    <button class="btn btn-outline btn-sm" type="button" :disabled="isLoadingSavedLibrary || isImportingSavedLibrary" @click="triggerSavedLibraryImport">
                        {{ isImportingSavedLibrary ? $t('common.loading') : $t('iptv.savedImport') }}
                    </button>
                    <input
                        ref="savedLibraryImportInput"
                        class="iptv-library-import-input"
                        type="file"
                        accept=".json,application/json,text/json,text/plain"
                        @change="importSavedLibraryFromFile"
                    >
                </div>

                <input
                    class="input-field iptv-library-search"
                    v-model.trim="savedLibraryQuery"
                    type="search"
                    :placeholder="$t('iptv.savedSearchPlaceholder')"
                >

                <p v-if="savedLibraryError" class="error-text">{{ savedLibraryError }}</p>
                <p v-else-if="savedLibraryInfo" class="success-text">{{ savedLibraryInfo }}</p>
                <p v-if="isLoadingSavedLibrary" class="muted iptv-library-empty">{{ $t('iptv.savedLoading') }}</p>

                <div class="iptv-library-columns">
                    <div class="iptv-library-group">
                        <p class="iptv-library-group-title">{{ $t('iptv.savedPlaylistsTitle') }}</p>
                        <div v-if="filteredSavedPlaylistSources.length > 0" class="iptv-custom-seed-list">
                            <div v-for="item in filteredSavedPlaylistSources" :key="item.id" class="iptv-custom-seed-item iptv-library-item">
                                <div class="iptv-custom-seed-main iptv-library-main">
                                    <strong>{{ item.name }}</strong>
                                    <small>{{ item.url }}</small>
                                    <small>{{ $t('iptv.savedPlaylistMeta', { count: item.channelsCount, date: formatSavedTimestamp(item.savedAt) }) }}</small>
                                </div>
                                <div class="iptv-library-item-actions">
                                    <button class="btn btn-outline btn-sm" type="button" :disabled="isLoadingPlaylist || isLoadingSavedLibrary" @click="loadSavedPlaylistSource(item.id)">
                                        {{ $t('iptv.savedLoad') }}
                                    </button>
                                    <button class="btn btn-outline btn-sm" type="button" :disabled="isLoadingSavedLibrary || isImportingSavedLibrary" @click="renameSavedPlaylistSource(item.id)">
                                        {{ $t('iptv.savedRename') }}
                                    </button>
                                    <button class="btn btn-outline btn-sm" type="button" @click="removeSavedPlaylistSource(item.id)">
                                        {{ $t('common.delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else-if="!isLoadingSavedLibrary && savedPlaylistSources.length === 0" class="muted iptv-library-empty">{{ $t('iptv.savedPlaylistsEmpty') }}</p>
                        <p v-else-if="!isLoadingSavedLibrary" class="muted iptv-library-empty">{{ $t('iptv.savedSearchEmpty') }}</p>
                    </div>

                    <div class="iptv-library-group">
                        <p class="iptv-library-group-title">{{ $t('iptv.savedChannelsTitle') }}</p>
                        <div v-if="filteredSavedChannelSources.length > 0" class="iptv-custom-seed-list">
                            <div v-for="item in filteredSavedChannelSources" :key="item.id" class="iptv-custom-seed-item iptv-library-item">
                                <div class="iptv-custom-seed-main iptv-library-main">
                                    <strong>{{ item.name }}</strong>
                                    <small>{{ item.url }}</small>
                                    <small>{{ item.group || $t('iptv.noMetadata') }} · {{ formatSavedTimestamp(item.savedAt) }}</small>
                                </div>
                                <div class="iptv-library-item-actions">
                                    <button class="btn btn-outline btn-sm" type="button" :disabled="isLoadingPlaylist || isLoadingSavedLibrary" @click="playSavedChannelSource(item.id)">
                                        {{ $t('iptv.savedOpenChannel') }}
                                    </button>
                                    <button class="btn btn-outline btn-sm" type="button" :disabled="isLoadingSavedLibrary || isImportingSavedLibrary" @click="renameSavedChannelSource(item.id)">
                                        {{ $t('iptv.savedRename') }}
                                    </button>
                                    <button class="btn btn-outline btn-sm" type="button" @click="removeSavedChannelSource(item.id)">
                                        {{ $t('common.delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else-if="!isLoadingSavedLibrary && savedChannelSources.length === 0" class="muted iptv-library-empty">{{ $t('iptv.savedChannelsEmpty') }}</p>
                        <p v-else-if="!isLoadingSavedLibrary" class="muted iptv-library-empty">{{ $t('iptv.savedSearchEmpty') }}</p>
                    </div>
                </div>
            </div>

            <div class="iptv-seed-box">
                <div class="iptv-seed-head">
                    <strong>{{ $t('iptv.seedsTitle') }}</strong>
                    <small class="muted">{{ $t('iptv.seedCounts', { builtin: builtinSeedSources.length, custom: customSeedSources.length }) }}</small>
                </div>

                <div class="iptv-seed-guide" role="note">
                    <p class="iptv-seed-guide-title">{{ $t('iptv.seedGuideTitle') }}</p>
                    <div class="iptv-seed-guide-line">
                        <span class="iptv-seed-guide-arrow" aria-hidden="true">➜</span>
                        <small>{{ $t('iptv.seedGuideClick') }}</small>
                    </div>
                    <div class="iptv-seed-guide-line">
                        <span class="iptv-seed-guide-arrow iptv-seed-guide-arrow--delay" aria-hidden="true">➜</span>
                        <small>{{ $t('iptv.seedGuideTap') }}</small>
                    </div>
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
                            <p v-if="relayStatusLine" class="muted" style="margin: 0; font-size: 0.8rem;">
                                {{ relayStatusLine }}
                            </p>
                            <p v-if="playerError" class="error-text" style="margin: 0;">{{ playerError }}</p>
                            <p v-if="transcodeError" class="error-text" style="margin: 0;">{{ transcodeError }}</p>
                            <p v-if="proxyError" class="error-text" style="margin: 0;">{{ proxyError }}</p>
                            <p v-if="relayError" class="error-text" style="margin: 0;">{{ relayError }}</p>
                        </div>
                    </details>

                    <IptvPlayer
                        ref="iptvPlayerRef"
                        :key="playerRenderKey"
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

        <div
            v-if="savedNameDialog.visible"
            class="iptv-save-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="iptv-save-modal-title"
            @click.self="cancelSavedItemName"
        >
            <div class="iptv-save-modal-card" @keydown.esc.prevent="cancelSavedItemName">
                <p id="iptv-save-modal-title" class="iptv-save-modal-title">{{ savedNameDialog.title }}</p>
                <input
                    ref="savedNameInput"
                    class="input-field"
                    v-model="savedNameDialog.value"
                    type="text"
                    maxlength="120"
                    :placeholder="$t('iptv.savedNamePlaceholder')"
                    @keydown.enter.prevent="confirmSavedItemName"
                    @keydown.esc.prevent="cancelSavedItemName"
                >
                <p v-if="savedNameDialog.error" class="error-text iptv-save-modal-error">{{ savedNameDialog.error }}</p>
                <div class="iptv-save-modal-actions">
                    <button class="btn btn-outline btn-sm" type="button" @click="cancelSavedItemName">
                        {{ $t('iptv.savedNameCancel') }}
                    </button>
                    <button class="btn btn-primary btn-sm" type="button" @click="confirmSavedItemName">
                        {{ $t('iptv.savedNameConfirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import IptvPlayer from '../../components/IptvPlayer.vue'
import {
    buildPersistedIptvState,
    IPTV_RECENT_LIMIT,
    IPTV_STATE_STORAGE_KEY,
    parsePersistedIptvState,
} from '../../utils/iptvSession.mjs'

const IPTV_CUSTOM_SEEDS_STORAGE_KEY = 'solid-social:iptv-custom-seeds:v1'
const IPTV_CUSTOM_SEEDS_LIMIT = 60
const IPTV_MAX_PLAYLIST_TEXT_BYTES = 10 * 1024 * 1024
const IPTV_MAX_PLAYLIST_LINES = 250000
const IPTV_MAX_PARSED_CHANNELS = 8000
const IPTV_MAX_SAVED_IMPORT_BYTES = 3 * 1024 * 1024
const IPTV_MAX_SAVED_IMPORT_PLAYLISTS = 200
const IPTV_MAX_SAVED_IMPORT_CHANNELS = 500
const IPTV_BUILTIN_SEED_SOURCES = []

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
            currentPlaylistUrl: '',
            savedPlaylistSources: [],
            savedChannelSources: [],
            savedLibraryInfo: '',
            savedLibraryError: '',
            isLoadingSavedLibrary: false,
            isImportingSavedLibrary: false,
            savedLibraryQuery: '',
            savedNameDialog: {
                visible: false,
                title: '',
                value: '',
                error: '',
            },
            savedNameDialogResolver: null,

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
            relayModeEnabled: false,
            relaySessionId: '',
            relayPlaybackUrl: '',
            relayBusy: false,
            relayError: '',
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
            autoRelayLastAttemptUrl: '',
            autoRelayLastAttemptAt: 0,
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
            isSwitchingSource: false,
            dynamicBuiltinSeeds: [],
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
            const staticSeeds = IPTV_BUILTIN_SEED_SOURCES.map((seed) => ({
                id: seed.id,
                url: seed.url,
                name: this.$t(seed.nameKey),
            }))
            return [...staticSeeds, ...this.dynamicBuiltinSeeds]
        },

        seedSources() {
            return [...this.builtinSeedSources, ...this.customSeedSources]
        },

        filteredSavedPlaylistSources() {
            const query = String(this.savedLibraryQuery || '').trim().toLowerCase()
            if (query === '') {
                return this.savedPlaylistSources
            }

            return this.savedPlaylistSources.filter((item) => {
                const haystack = `${item.name} ${item.url}`.toLowerCase()
                return haystack.includes(query)
            })
        },

        filteredSavedChannelSources() {
            const query = String(this.savedLibraryQuery || '').trim().toLowerCase()
            if (query === '') {
                return this.savedChannelSources
            }

            return this.savedChannelSources.filter((item) => {
                const haystack = `${item.name} ${item.url} ${item.group}`.toLowerCase()
                return haystack.includes(query)
            })
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

            if (this.relayModeEnabled) {
                return this.relayPlaybackUrl || ''
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

        relayStatusLine() {
            if (!this.relayModeEnabled) {
                return ''
            }

            const source = this.relaySessionId
                ? this.$t('iptv.relaySession', { id: this.relaySessionId })
                : this.$t('iptv.relayPreparing')

            return this.$t('iptv.relayEnabledLine', { source })
        },

        groupOptions() {
            const groups = Array.from(new Set(
                this.channels
                    .flatMap((channel) => (
                        Array.isArray(channel.groupTags) && channel.groupTags.length > 0
                            ? channel.groupTags
                            : this.resolveChannelGroups(channel.group).tags
                    ))
                    .map((group) => String(group || '').trim())
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
                if (this.selectedGroup !== 'all') {
                    const channelGroups = Array.isArray(channel.groupTags) && channel.groupTags.length > 0
                        ? channel.groupTags
                        : this.resolveChannelGroups(channel.group).tags

                    if (!channelGroups.includes(this.selectedGroup)) {
                        return false
                    }
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
                const groupA = Array.isArray(a.groupTags) && a.groupTags.length > 0
                    ? a.groupTags[0]
                    : String(a.group || '')
                const groupB = Array.isArray(b.groupTags) && b.groupTags.length > 0
                    ? b.groupTags[0]
                    : String(b.group || '')
                const groupCompare = String(groupA).localeCompare(String(groupB), 'ru')
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

            if (this.relayModeEnabled && this.relayPlaybackUrl) {
                return 'relay'
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
                relay: this.$t('iptv.modeRelay'),
                ffmpeg: this.$t('iptv.modeFfmpeg'),
            }

            return dictionary[this.playbackMode] || this.$t('iptv.modeDirect')
        },

        playerRenderKey() {
            const channelId = String(this.currentChannelId || 'no-channel')
            const source = String(this.activePlaybackUrl || 'no-source')
            const mode = String(this.playbackMode || 'direct')
            return `${channelId}::${mode}::${source}`
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
        playlistUrl() {
            this.persistPlayerState()
        },
        searchQuery() {
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
        currentPlaylistUrl() {
            this.persistPlayerState()
        },
        sourceLabel() {
            this.persistPlayerState()
        },
        currentChannelId() {
            this.persistPlayerState()
        },
        channels() {
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
            this.autoRelayLastAttemptUrl = ''
            this.autoRelayLastAttemptAt = 0
            this.compatRecoveryState = {
                sourceUrl: String(nextUrl || ''),
                attempts: 0,
            }

            if (nextUrl === '') {
                if (this.isSwitchingSource) {
                    return
                }

                this.stopServerTranscodeSession()
                this.stopServerProxySession()
                this.stopServerRelaySession()
                return
            }

            if (this.proxyModeEnabled && nextUrl !== previousUrl) {
                this.startServerProxyForCurrentChannel()
            }

            if (this.compatModeEnabled && nextUrl !== previousUrl) {
                this.startServerTranscodeForCurrentChannel()
            }

            if (this.relayModeEnabled && nextUrl !== previousUrl) {
                this.startServerRelayForCurrentChannel()
            }
        },
    },

    async mounted() {
        this.preferHttpsUpgrade = this.isPageHttps
        this.hideListLogosOnMobile = this.isMobileViewport()
        window.addEventListener('pagehide', this.handlePageHide)
        window.addEventListener('beforeunload', this.handleBeforeUnload)
        window.addEventListener('keydown', this.handleGlobalKeydown)
        window.addEventListener('resize', this.handleViewportChange)
        this.loadCustomSeedSources()
        const builtinSeedsPromise = this.loadBuiltinSeeds()
        const savedLibraryPromise = this.loadSavedLibrarySources()
        const transcodeCapabilitiesPromise = this.loadTranscodeCapabilities()
        await Promise.allSettled([builtinSeedsPromise, transcodeCapabilitiesPromise])
        await this.loadPersistedState()
        void savedLibraryPromise
    },

    beforeUnmount() {
        this.closeSavedNameDialog(null)
        this.teardownPlaybackOnExit()
        window.removeEventListener('pagehide', this.handlePageHide)
        window.removeEventListener('beforeunload', this.handleBeforeUnload)
        window.removeEventListener('keydown', this.handleGlobalKeydown)
        window.removeEventListener('resize', this.handleViewportChange)
        this.persistPlayerState()
    },

    beforeRouteLeave(_to, _from, next) {
        this.closeSavedNameDialog(null)
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
                preserveMode: true,
                useKeepalive: Boolean(options?.useKeepalive),
            })
            this.stopServerProxySession({
                preserveMode: true,
                useKeepalive: Boolean(options?.useKeepalive),
            })
            this.stopServerRelaySession({
                preserveMode: true,
                useKeepalive: Boolean(options?.useKeepalive),
            })
        },

        async stopPlaybackForSourceSwitch() {
            this.isSwitchingSource = true

            try {
                await Promise.allSettled([
                    this.stopServerTranscodeSession({ preserveMode: true, preserveError: true }),
                    this.stopServerProxySession({ preserveMode: true, preserveError: true }),
                    this.stopServerRelaySession({ preserveMode: true, preserveError: true }),
                ])

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
                this.compatRecoveryState = {
                    sourceUrl: '',
                    attempts: 0,
                }
            } finally {
                this.isSwitchingSource = false
            }
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

        sendStopRelayKeepalive(sessionId) {
            const normalizedSessionId = String(sessionId || '').trim()
            if (normalizedSessionId === '') {
                return
            }

            const url = `/api/iptv/relay/${encodeURIComponent(normalizedSessionId)}`
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

        async loadPersistedState() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return
            }

            const payload = parsePersistedIptvState(window.localStorage.getItem(IPTV_STATE_STORAGE_KEY))
            if (!payload) {
                return
            }

            this.viewMode = payload.viewMode
            this.playlistUrl = payload.playlistUrl
            this.searchQuery = payload.searchQuery
            this.selectedGroup = payload.selectedGroup
            this.sortMode = payload.sortMode
            this.secureOnly = payload.secureOnly
            this.preferHttpsUpgrade = payload.preferHttpsUpgrade
            this.fitMode = payload.fitMode
            this.bufferingMode = payload.bufferingMode
            this.autoStability = payload.autoStability
            this.compatModeEnabled = payload.compatModeEnabled
            this.compatProfile = payload.compatProfile
            this.autoCompatOnCodecError = payload.autoCompatOnCodecError
            this.keyboardEnabled = payload.keyboardEnabled
            this.activeSeedId = this.findSeedById(payload.activeSeedId)?.id || ''
            this.favoriteChannelIds = payload.favoriteChannelIds
            this.recentChannelIds = payload.recentChannelIds
            this.volumeLevel = this.normalizeVolume(payload.volumeLevel)
            this.muted = payload.muted

            const hasRemotePlaylist = this.isHttpUrl(payload.currentPlaylistUrl) || this.isHttpsUrl(payload.currentPlaylistUrl)
            const resolvedSourceLabel = payload.sourceLabel || this.$t('iptv.sourceNotSelected')

            if (hasRemotePlaylist) {
                this.currentPlaylistUrl = payload.currentPlaylistUrl
                this.sourceLabel = resolvedSourceLabel

                const restored = await this.fetchPlaylistByUrl(
                    payload.currentPlaylistUrl,
                    resolvedSourceLabel,
                    payload.activeSeedId,
                    {
                        preferredChannelId: payload.currentChannelId,
                        preserveSelectedGroup: true,
                    },
                )

                if (restored) {
                    return
                }
            }

            this.restoreChannelsFromSnapshot(payload.channelsSnapshot, {
                preferredChannelId: payload.currentChannelId,
                sourceLabel: resolvedSourceLabel,
                activeSeedId: payload.activeSeedId,
                currentPlaylistUrl: payload.currentPlaylistUrl,
            })
        },

        persistPlayerState() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return
            }

            const payload = buildPersistedIptvState({
                viewMode: this.viewMode,
                playlistUrl: this.playlistUrl,
                searchQuery: this.searchQuery,
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
                currentPlaylistUrl: this.currentPlaylistUrl,
                sourceLabel: this.sourceLabel,
                currentChannelId: this.currentChannelId,
                favoriteChannelIds: this.favoriteChannelIds,
                recentChannelIds: this.recentChannelIds,
                channels: this.channels,
            })

            try {
                window.localStorage.setItem(IPTV_STATE_STORAGE_KEY, JSON.stringify(payload))
            } catch (_error) {
                // ignore storage write failures
            }
        },

        restoreChannelsFromSnapshot(snapshot, options = {}) {
            if (!Array.isArray(snapshot) || snapshot.length === 0) {
                return false
            }

            const restoredChannels = snapshot
                .map((channel, index) => this.createChannel(channel, index))
                .filter((channel) => this.isHttpUrl(channel.url) || this.isHttpsUrl(channel.url))

            if (restoredChannels.length === 0) {
                return false
            }

            this.channels = restoredChannels
            this.sourceLabel = String(options?.sourceLabel || this.$t('iptv.unknownSource')).trim() || this.$t('iptv.unknownSource')
            this.activeSeedId = this.findSeedById(options?.activeSeedId)?.id || ''
            this.currentPlaylistUrl = this.isHttpUrl(options?.currentPlaylistUrl) || this.isHttpsUrl(options?.currentPlaylistUrl)
                ? String(options.currentPlaylistUrl).trim()
                : ''
            this.ensureSelectedGroupExists()
            this.syncSavedIdsWithPlaylist()

            const preferredChannelId = this.resolvePreferredChannelId(restoredChannels, options?.preferredChannelId)
            if (preferredChannelId !== '') {
                this.playChannel(preferredChannelId)
            }

            return true
        },

        resolvePreferredChannelId(channels, preferredChannelId = '') {
            const normalizedPreferredChannelId = String(preferredChannelId || '').trim()
            const resolvedChannel = channels.find((channel) => channel.id === normalizedPreferredChannelId)
                || channels.find((channel) => this.recentIdSet.has(channel.id))
                || channels[0]

            return resolvedChannel ? resolvedChannel.id : ''
        },

        ensureSelectedGroupExists() {
            if (this.selectedGroup === 'all') {
                return
            }

            if (!this.groupOptions.includes(this.selectedGroup)) {
                this.selectedGroup = 'all'
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
                } else if (this.transcodeCapabilities.ffmpegAvailable && this.playbackUrl !== '') {
                    if (this.proxyModeEnabled) {
                        this.startServerProxyForCurrentChannel()
                    }

                    if (this.relayModeEnabled) {
                        this.startServerRelayForCurrentChannel()
                    }

                    if (this.compatModeEnabled) {
                        this.startServerTranscodeForCurrentChannel()
                    }
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
                return false
            }

            if (force) {
                this.clearTranscodeUnavailableForSource(this.playbackUrl)
            }

            if (this.proxyModeEnabled) {
                await this.stopProxyMode()
            }
            if (this.relayModeEnabled) {
                await this.stopRelayMode()
            }

            this.compatModeEnabled = true
            return await this.startServerTranscodeForCurrentChannel({ force })
        },

        async stopCompatibilityMode() {
            this.compatModeEnabled = false
            await this.stopServerTranscodeSession()
        },

        async startProxyMode() {
            if (this.compatModeEnabled) {
                await this.stopCompatibilityMode()
            }
            if (this.relayModeEnabled) {
                await this.stopRelayMode()
            }
            this.proxyModeEnabled = true
            return await this.startServerProxyForCurrentChannel()
        },

        async stopProxyMode() {
            this.proxyModeEnabled = false
            await this.stopServerProxySession()
        },

        async startRelayMode() {
            if (!this.canUseServerTranscode) {
                this.relayError = this.$t('iptv.relayUnavailableNoFfmpeg')
                return false
            }

            if (this.compatModeEnabled) {
                await this.stopCompatibilityMode()
            }
            if (this.proxyModeEnabled) {
                await this.stopProxyMode()
            }

            this.relayModeEnabled = true
            return await this.startServerRelayForCurrentChannel()
        },

        async stopRelayMode() {
            this.relayModeEnabled = false
            await this.stopServerRelaySession()
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

        async startServerRelayForCurrentChannel() {
            if (!this.relayModeEnabled || !this.canUseServerTranscode) {
                return false
            }

            const sourceUrl = String(this.playbackUrl || '').trim()
            if (sourceUrl === '') {
                return false
            }

            if (this.relayBusy) {
                return false
            }

            this.relayBusy = true
            this.relayError = ''

            try {
                await this.stopServerRelaySession({ preserveMode: true, preserveError: true })

                const response = await axios.post('/api/iptv/relay/start', {
                    url: sourceUrl,
                })

                this.relaySessionId = String(response.data?.data?.session_id || '')
                this.relayPlaybackUrl = String(response.data?.data?.playlist_url || '')

                if (this.relaySessionId === '' || this.relayPlaybackUrl === '') {
                    throw new Error('Empty relay session payload')
                }

                return true
            } catch (error) {
                this.relaySessionId = ''
                this.relayPlaybackUrl = ''
                this.relayModeEnabled = false
                this.relayError = error.response?.data?.message || this.$t('iptv.relayStartFailed')
                return false
            } finally {
                this.relayBusy = false
            }
        },

        async stopServerRelaySession(options = {}) {
            const preserveMode = Boolean(options?.preserveMode)
            const preserveError = Boolean(options?.preserveError)
            const useKeepalive = Boolean(options?.useKeepalive)
            const sessionId = String(this.relaySessionId || '')

            if (sessionId !== '') {
                if (useKeepalive) {
                    this.sendStopRelayKeepalive(sessionId)
                } else {
                    try {
                        await axios.delete(`/api/iptv/relay/${encodeURIComponent(sessionId)}`)
                    } catch (_error) {
                        // ignore stop errors
                    }
                }
            }

            this.relaySessionId = ''
            this.relayPlaybackUrl = ''
            this.relayBusy = false

            if (!preserveMode) {
                this.relayModeEnabled = false
                this.autoRelayLastAttemptUrl = ''
                this.autoRelayLastAttemptAt = 0
            }

            if (!preserveError) {
                this.relayError = ''
            }
        },

        async tryAutoRelayFallback(sourceUrl) {
            const normalizedUrl = String(sourceUrl || '').trim()
            if (normalizedUrl === '' || !this.canUseServerTranscode || this.relayBusy) {
                return false
            }

            const now = Date.now()
            const sameSourceCooldown = this.autoRelayLastAttemptUrl === normalizedUrl
                && (now - Number(this.autoRelayLastAttemptAt || 0)) < 30000

            if (sameSourceCooldown) {
                return false
            }

            this.autoRelayLastAttemptUrl = normalizedUrl
            this.autoRelayLastAttemptAt = now
            this.relayError = this.$t('iptv.relayAutoEnable')
            const relayStarted = await this.startRelayMode()
            if (relayStarted) {
                this.relayError = ''
                this.proxyError = ''
                this.transcodeError = ''
                return true
            }

            return false
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

        async loadSavedLibrarySources() {
            this.isLoadingSavedLibrary = true

            try {
                const response = await axios.get('/api/iptv/saved')
                const payload = response.data?.data || {}

                const playlists = Array.isArray(payload.playlists) ? payload.playlists : []
                const channels = Array.isArray(payload.channels) ? payload.channels : []

                this.savedPlaylistSources = playlists
                    .map((item) => this.normalizeSavedPlaylistItem(item))
                    .filter(Boolean)

                this.savedChannelSources = channels
                    .map((item) => this.normalizeSavedChannelItem(item))
                    .filter(Boolean)

                if (this.savedLibraryError !== '') {
                    this.savedLibraryError = ''
                }
            } catch (error) {
                this.savedLibraryError = error.response?.data?.message || this.$t('iptv.savedLoadFailed')
            } finally {
                this.isLoadingSavedLibrary = false
            }
        },

        async loadBuiltinSeeds() {
            try {
                const response = await axios.get('/api/iptv/seeds')
                this.dynamicBuiltinSeeds = (response.data?.data || []).map((item) => ({
                    id: `builtin-${item.id}`,
                    name: item.name,
                    url: item.url,
                }))
            } catch (_error) {
                // ignore
            }
        },

        normalizeSavedPlaylistItem(item) {
            const url = String(item?.url || item?.source_url || '').trim()
            if (!this.isHttpUrl(url) && !this.isHttpsUrl(url)) {
                return null
            }

            const channelsCount = Number(item?.channels_count ?? item?.channelsCount ?? 0)

            return {
                id: String(item?.id || ''),
                name: String(item?.name || this.guessSeedName(url)).trim().slice(0, 120),
                url,
                channelsCount: Number.isFinite(channelsCount) && channelsCount > 0 ? Math.floor(channelsCount) : 0,
                savedAt: String(item?.updated_at || item?.savedAt || '').trim(),
            }
        },

        normalizeSavedChannelItem(item) {
            const url = String(item?.url || item?.stream_url || '').trim()
            if (!this.isHttpUrl(url) && !this.isHttpsUrl(url)) {
                return null
            }

            return {
                id: String(item?.id || ''),
                name: String(item?.name || this.$t('iptv.untitledChannel')).trim().slice(0, 120),
                url,
                group: String(item?.group || item?.group_title || '').trim().slice(0, 160),
                logo: this.normalizeLogoUrl(item?.logo),
                savedAt: String(item?.updated_at || item?.savedAt || '').trim(),
            }
        },

        clearSavedLibraryFeedback() {
            this.savedLibraryInfo = ''
            this.savedLibraryError = ''
        },

        exportSavedLibrary() {
            this.clearSavedLibraryFeedback()

            if (this.savedPlaylistSources.length === 0 && this.savedChannelSources.length === 0) {
                this.savedLibraryError = this.$t('iptv.savedExportEmpty')
                return
            }

            const payload = {
                format: 'solid-social-iptv-saved-library',
                version: 1,
                exported_at: new Date().toISOString(),
                playlists: this.savedPlaylistSources.map((item) => ({
                    name: item.name,
                    url: item.url,
                    channels_count: item.channelsCount,
                })),
                channels: this.savedChannelSources.map((item) => ({
                    name: item.name,
                    url: item.url,
                    group: item.group,
                    logo: item.logo,
                })),
            }

            const timestamp = new Date().toISOString().replace(/:/g, '-').replace(/\..+$/, '')
            const filename = `iptv-saved-${timestamp}.json`

            if (typeof window === 'undefined' || typeof document === 'undefined' || typeof Blob === 'undefined') {
                this.savedLibraryError = this.$t('iptv.savedExportFailed')
                return
            }

            try {
                const blob = new Blob([JSON.stringify(payload, null, 2)], {
                    type: 'application/json;charset=utf-8',
                })

                const objectUrl = window.URL?.createObjectURL ? window.URL.createObjectURL(blob) : ''
                if (objectUrl === '') {
                    throw new Error('Object URL unavailable')
                }

                const anchor = document.createElement('a')
                anchor.href = objectUrl
                anchor.download = filename
                anchor.style.display = 'none'
                document.body.appendChild(anchor)
                anchor.click()
                anchor.remove()
                window.URL.revokeObjectURL(objectUrl)

                this.savedLibraryInfo = this.$t('iptv.savedExportDone', {
                    playlists: this.savedPlaylistSources.length,
                    channels: this.savedChannelSources.length,
                })
            } catch (_error) {
                this.savedLibraryError = this.$t('iptv.savedExportFailed')
            }
        },

        triggerSavedLibraryImport() {
            const input = this.$refs.savedLibraryImportInput
            if (!input || typeof input.click !== 'function') {
                return
            }

            input.value = ''
            input.click()
        },

        buildSavedImportCollections(payload) {
            if (!payload || typeof payload !== 'object') {
                return {
                    playlists: [],
                    channels: [],
                    skipped: 0,
                }
            }

            const rawPlaylists = Array.isArray(payload.playlists) ? payload.playlists : []
            const rawChannels = Array.isArray(payload.channels) ? payload.channels : []
            const skippedByLimit = Math.max(0, rawPlaylists.length - IPTV_MAX_SAVED_IMPORT_PLAYLISTS)
                + Math.max(0, rawChannels.length - IPTV_MAX_SAVED_IMPORT_CHANNELS)

            const limitedPlaylists = rawPlaylists.slice(0, IPTV_MAX_SAVED_IMPORT_PLAYLISTS)
            const limitedChannels = rawChannels.slice(0, IPTV_MAX_SAVED_IMPORT_CHANNELS)

            const playlists = limitedPlaylists
                .map((item) => this.normalizeSavedPlaylistItem(item))
                .filter(Boolean)

            const channels = limitedChannels
                .map((item) => this.normalizeSavedChannelItem(item))
                .filter(Boolean)

            const skippedByInvalid = (limitedPlaylists.length - playlists.length) + (limitedChannels.length - channels.length)

            return {
                playlists,
                channels,
                skipped: skippedByLimit + skippedByInvalid,
            }
        },

        normalizeSavedChannelsCount(value) {
            const count = Number(value)
            if (!Number.isFinite(count) || count <= 0) {
                return 0
            }

            return Math.min(1000000, Math.floor(count))
        },

        async importSavedLibraryFromFile(event) {
            const input = event?.target
            const file = input?.files?.[0]
            if (!file) {
                return
            }

            this.clearSavedLibraryFeedback()

            if (Number(file.size || 0) > IPTV_MAX_SAVED_IMPORT_BYTES) {
                this.savedLibraryError = this.$t('iptv.savedImportTooLarge', {
                    max: Math.floor(IPTV_MAX_SAVED_IMPORT_BYTES / (1024 * 1024)),
                })
                if (input) {
                    input.value = ''
                }
                return
            }

            this.isImportingSavedLibrary = true

            try {
                const rawText = await file.text()
                const parsedPayload = JSON.parse(String(rawText || ''))
                const collections = this.buildSavedImportCollections(parsedPayload)

                const totalForImport = collections.playlists.length + collections.channels.length
                if (totalForImport === 0) {
                    this.savedLibraryError = this.$t('iptv.savedImportNoItems')
                    return
                }

                let importedPlaylists = 0
                let importedChannels = 0
                let skipped = collections.skipped

                for (const playlist of collections.playlists) {
                    try {
                        await axios.post('/api/iptv/saved/playlists', {
                            name: playlist.name,
                            url: playlist.url,
                            channels_count: this.normalizeSavedChannelsCount(playlist.channelsCount),
                        })
                        importedPlaylists += 1
                    } catch (_error) {
                        skipped += 1
                    }
                }

                for (const channel of collections.channels) {
                    try {
                        await axios.post('/api/iptv/saved/channels', {
                            name: channel.name,
                            url: channel.url,
                            group: channel.group,
                            logo: channel.logo,
                        })
                        importedChannels += 1
                    } catch (_error) {
                        skipped += 1
                    }
                }

                await this.loadSavedLibrarySources()

                this.savedLibraryInfo = this.$t('iptv.savedImportDone', {
                    playlists: importedPlaylists,
                    channels: importedChannels,
                    skipped,
                })
            } catch (_error) {
                this.savedLibraryError = this.$t('iptv.savedImportInvalid')
            } finally {
                this.isImportingSavedLibrary = false
                if (input) {
                    input.value = ''
                }
            }
        },

        openSavedNameDialog(title, suggestedName) {
            if (typeof this.savedNameDialogResolver === 'function') {
                this.savedNameDialogResolver(null)
                this.savedNameDialogResolver = null
            }

            this.savedNameDialog = {
                visible: true,
                title: String(title || ''),
                value: String(suggestedName || '').trim().slice(0, 120),
                error: '',
            }

            return new Promise((resolve) => {
                this.savedNameDialogResolver = resolve
                this.$nextTick(() => {
                    this.focusSavedNameInput()
                })
            })
        },

        closeSavedNameDialog(result = null) {
            const resolver = this.savedNameDialogResolver
            this.savedNameDialogResolver = null

            this.savedNameDialog.visible = false
            this.savedNameDialog.title = ''
            this.savedNameDialog.value = ''
            this.savedNameDialog.error = ''

            if (typeof resolver === 'function') {
                resolver(result)
            }
        },

        focusSavedNameInput() {
            const input = this.$refs.savedNameInput
            if (!input || typeof input.focus !== 'function') {
                return
            }

            input.focus()
            if (typeof input.select === 'function') {
                input.select()
            }
        },

        confirmSavedItemName() {
            const normalizedName = String(this.savedNameDialog.value || '').trim().slice(0, 120)
            if (normalizedName === '') {
                this.savedNameDialog.error = this.$t('iptv.savedNameRequired')
                this.$nextTick(() => {
                    this.focusSavedNameInput()
                })
                return
            }

            this.closeSavedNameDialog(normalizedName)
        },

        cancelSavedItemName() {
            this.closeSavedNameDialog(null)
        },

        resolveCurrentPlaylistUrl() {
            const fromCurrent = String(this.currentPlaylistUrl || '').trim()
            if (this.isHttpUrl(fromCurrent) || this.isHttpsUrl(fromCurrent)) {
                return fromCurrent
            }

            const fromInput = String(this.playlistUrl || '').trim()
            if (this.isHttpUrl(fromInput) || this.isHttpsUrl(fromInput)) {
                return fromInput
            }

            return ''
        },

        async promptSavedItemName(promptText, suggestedName) {
            const fallback = String(suggestedName || '').trim().slice(0, 120)

            if (typeof window === 'undefined') {
                return fallback
            }

            return this.openSavedNameDialog(String(promptText || ''), fallback)
        },

        async saveCurrentPlaylistSource() {
            this.clearSavedLibraryFeedback()
            const url = this.resolveCurrentPlaylistUrl()

            if (url === '') {
                this.savedLibraryError = this.$t('iptv.savedPlaylistNoUrl')
                return
            }

            const resolvedName = String(this.sourceLabel || '').trim()
            const sourceNotSelected = this.$t('iptv.sourceNotSelected')
            const suggestedName = (resolvedName && resolvedName !== sourceNotSelected ? resolvedName : this.guessSeedName(url)).slice(0, 120)
            const name = await this.promptSavedItemName(this.$t('iptv.savePlaylistPrompt'), suggestedName)
            if (name === null) {
                return
            }
            if (name === '') {
                this.savedLibraryError = this.$t('iptv.savedNameRequired')
                return
            }

            try {
                await axios.post('/api/iptv/saved/playlists', {
                    name,
                    url,
                    channels_count: this.channels.length,
                })

                await this.loadSavedLibrarySources()
                this.savedLibraryInfo = this.$t('iptv.savedPlaylistAdded')
            } catch (error) {
                this.savedLibraryError = error.response?.data?.message || this.$t('iptv.savedSaveFailed')
            }
        },

        async loadSavedPlaylistSource(savedId) {
            const source = this.savedPlaylistSources.find((item) => item.id === savedId)
            if (!source) {
                return
            }

            this.clearSavedLibraryFeedback()
            this.playlistUrl = source.url
            await this.fetchPlaylistByUrl(source.url, source.name)
        },

        async removeSavedPlaylistSource(savedId) {
            if (!savedId) {
                return
            }

            this.clearSavedLibraryFeedback()

            try {
                await axios.delete(`/api/iptv/saved/playlists/${encodeURIComponent(savedId)}`)
                this.savedPlaylistSources = this.savedPlaylistSources.filter((item) => item.id !== savedId)
            } catch (error) {
                this.savedLibraryError = error.response?.data?.message || this.$t('iptv.savedDeleteFailed')
            }
        },

        async renameSavedPlaylistSource(savedId) {
            const source = this.savedPlaylistSources.find((item) => item.id === savedId)
            if (!source) {
                return
            }

            this.clearSavedLibraryFeedback()

            const name = await this.promptSavedItemName(this.$t('iptv.savedPlaylistRenamePrompt'), source.name)
            if (name === null) {
                return
            }

            try {
                await axios.patch(`/api/iptv/saved/playlists/${encodeURIComponent(savedId)}`, {
                    name,
                })

                await this.loadSavedLibrarySources()
                this.savedLibraryInfo = this.$t('iptv.savedPlaylistRenamed')
            } catch (error) {
                this.savedLibraryError = error.response?.data?.message || this.$t('iptv.savedRenameFailed')
            }
        },

        async saveCurrentChannelSource() {
            this.clearSavedLibraryFeedback()
            const channel = this.currentChannel
            if (!channel) {
                this.savedLibraryError = this.$t('iptv.savedChannelNoSelection')
                return
            }

            const url = String(channel.url || '').trim()
            if (!this.isHttpUrl(url) && !this.isHttpsUrl(url)) {
                this.savedLibraryError = this.$t('iptv.savedChannelNoSelection')
                return
            }

            const suggestedName = String(channel.name || this.$t('iptv.untitledChannel')).trim().slice(0, 120)
            const name = await this.promptSavedItemName(this.$t('iptv.saveChannelPrompt'), suggestedName)
            if (name === null) {
                return
            }
            if (name === '') {
                this.savedLibraryError = this.$t('iptv.savedNameRequired')
                return
            }

            try {
                await axios.post('/api/iptv/saved/channels', {
                    name,
                    url,
                    group: String(channel.group || '').trim().slice(0, 160),
                    logo: this.normalizeLogoUrl(channel.logo),
                })

                await this.loadSavedLibrarySources()
                this.savedLibraryInfo = this.$t('iptv.savedChannelAdded')
            } catch (error) {
                this.savedLibraryError = error.response?.data?.message || this.$t('iptv.savedSaveFailed')
            }
        },

        async playSavedChannelSource(savedId) {
            const source = this.savedChannelSources.find((item) => item.id === savedId)
            if (!source) {
                return
            }

            this.clearSavedLibraryFeedback()
            await this.stopPlaybackForSourceSwitch()
            this.playlistError = ''

            const channel = this.createChannel({
                name: source.name,
                url: source.url,
                group: source.group,
                logo: source.logo,
            }, this.channels.length)

            const withoutDuplicate = this.channels.filter((item) => item.id !== channel.id)
            this.channels = [channel, ...withoutDuplicate]
            this.currentChannelId = channel.id
            this.sourceLabel = this.$t('iptv.savedChannelSource')
            this.currentPlaylistUrl = ''
            this.activeSeedId = ''
            this.selectedGroup = 'all'
            this.selectedQuality = -1
            this.markChannelRecent(channel.id)
            this.syncSavedIdsWithPlaylist()
        },

        async removeSavedChannelSource(savedId) {
            if (!savedId) {
                return
            }

            this.clearSavedLibraryFeedback()

            try {
                await axios.delete(`/api/iptv/saved/channels/${encodeURIComponent(savedId)}`)
                this.savedChannelSources = this.savedChannelSources.filter((item) => item.id !== savedId)
            } catch (error) {
                this.savedLibraryError = error.response?.data?.message || this.$t('iptv.savedDeleteFailed')
            }
        },

        async renameSavedChannelSource(savedId) {
            const source = this.savedChannelSources.find((item) => item.id === savedId)
            if (!source) {
                return
            }

            this.clearSavedLibraryFeedback()

            const name = await this.promptSavedItemName(this.$t('iptv.savedChannelRenamePrompt'), source.name)
            if (name === null) {
                return
            }

            try {
                await axios.patch(`/api/iptv/saved/channels/${encodeURIComponent(savedId)}`, {
                    name,
                })

                await this.loadSavedLibrarySources()
                this.savedLibraryInfo = this.$t('iptv.savedChannelRenamed')
            } catch (error) {
                this.savedLibraryError = error.response?.data?.message || this.$t('iptv.savedRenameFailed')
            }
        },

        formatSavedTimestamp(value) {
            const timestamp = String(value || '').trim()
            if (timestamp === '') {
                return this.$t('iptv.savedUnknownTime')
            }

            const date = new Date(timestamp)
            if (Number.isNaN(date.getTime())) {
                return this.$t('iptv.savedUnknownTime')
            }

            const locale = String(this.$i18n?.locale || '').trim() || undefined

            try {
                return new Intl.DateTimeFormat(locale, {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(date)
            } catch (_error) {
                return date.toLocaleString()
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

        normalizeGroupToken(value) {
            return String(value || '')
                .replace(/\s+/g, ' ')
                .trim()
        },

        translateGroupToken(value) {
            const token = this.normalizeGroupToken(value)
            if (token === '') {
                return ''
            }

            const tokenKey = token.toLowerCase()
            const groupLabelKeys = {
                animation: 'groupAnimation',
                classic: 'groupClassic',
                comedy: 'groupComedy',
                cooking: 'groupCooking',
                culture: 'groupCulture',
                documentary: 'groupDocumentary',
                education: 'groupEducation',
                entertainment: 'groupEntertainment',
                family: 'groupFamily',
                general: 'groupGeneral',
                kids: 'groupKids',
                movie: 'groupMovies',
                movies: 'groupMovies',
                music: 'groupMusic',
                news: 'groupNews',
                business: 'groupBusiness',
                auto: 'groupAuto',
                series: 'groupSeries',
                sport: 'groupSports',
                sports: 'groupSports',
                travel: 'groupTravel',
            }

            const labelKey = groupLabelKeys[tokenKey]
            if (!labelKey) {
                return token
            }

            return this.$t(`iptv.${labelKey}`)
        },

        resolveChannelGroups(value) {
            const rawValue = this.normalizeGroupToken(value)
            if (rawValue === '') {
                return {
                    tags: [],
                    label: '',
                }
            }

            const pieces = rawValue
                .split(/[;|,/]+/)
                .map((piece) => this.translateGroupToken(piece))
                .filter((piece) => piece !== '')

            const uniqueTags = []
            const seen = new Set()

            for (const piece of pieces.length > 0 ? pieces : [rawValue]) {
                const normalizedPiece = this.normalizeGroupToken(piece)
                if (normalizedPiece === '') {
                    continue
                }

                const normalizedKey = normalizedPiece.toLowerCase()
                if (seen.has(normalizedKey)) {
                    continue
                }

                seen.add(normalizedKey)
                uniqueTags.push(normalizedPiece)
            }

            return {
                tags: uniqueTags,
                label: uniqueTags.join(' · '),
            }
        },

        channelMeta(channel) {
            const parts = []

            const groupLabel = Array.isArray(channel.groupTags) && channel.groupTags.length > 0
                ? channel.groupTags.join(' · ')
                : this.resolveChannelGroups(channel.group).label

            if (groupLabel) {
                parts.push(groupLabel)
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
                    await this.tryAutoRelayFallback(sourceUrl)
                }

                return
            }

            if (this.relayModeEnabled) {
                if (sourceUrl === '' || this.relayBusy) {
                    return
                }

                await this.stopRelayMode()
                this.relayError = this.$t('iptv.relayFailedFallbackDirect')
                return
            }

            if (this.proxyModeEnabled && shouldUseProxyFallback && sourceUrl !== '') {
                await this.stopProxyMode()
                this.proxyError = this.$t('iptv.proxyFailedFallbackDirect')

                if (this.canUseServerTranscode && !this.transcodeBusy && !this.isTranscodeUnavailableForSource(sourceUrl)) {
                    this.transcodeError = this.$t('iptv.compatAutoEnableAfterProxy')
                    const compatStarted = await this.startCompatibilityMode()
                    if (compatStarted) {
                        return
                    }
                }

                await this.tryAutoRelayFallback(sourceUrl)
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
                const compatStarted = await this.startCompatibilityMode()
                if (!compatStarted) {
                    await this.tryAutoRelayFallback(sourceUrl)
                }
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

                if (this.canUseServerTranscode && !this.transcodeBusy && !this.isTranscodeUnavailableForSource(sourceUrl)) {
                    this.transcodeError = this.$t('iptv.compatAutoEnableAfterProxy')
                    const compatStarted = await this.startCompatibilityMode()
                    if (compatStarted) {
                        this.proxyError = ''
                        return
                    }
                }

                await this.tryAutoRelayFallback(sourceUrl)
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
            const compatStarted = await this.startCompatibilityMode()
            if (!compatStarted) {
                await this.tryAutoRelayFallback(sourceUrl)
            }
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

        async fetchPlaylistByUrl(url, sourceLabel = '', seedId = '', options = {}) {
            const normalizedUrl = String(url || '').trim()
            if (normalizedUrl === '') {
                this.playlistError = this.$t('iptv.playlistUrlRequired')
                return false
            }

            if (!this.isHttpUrl(normalizedUrl) && !this.isHttpsUrl(normalizedUrl)) {
                this.playlistError = this.$t('iptv.playlistUrlInvalid')
                return false
            }

            await this.stopPlaybackForSourceSwitch()
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
                this.applyParsedChannels(playlist, resolvedSourceLabel, options)
                this.currentPlaylistUrl = normalizedUrl
                return true
            } catch (error) {
                this.playlistError = error.response?.data?.message || this.$t('iptv.playlistLoadFailed')
                return false
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

            if (Number(file.size || 0) > IPTV_MAX_PLAYLIST_TEXT_BYTES) {
                this.playlistError = this.$t('iptv.playlistTooLarge', {
                    max: Math.floor(IPTV_MAX_PLAYLIST_TEXT_BYTES / (1024 * 1024)),
                })
                if (input) {
                    input.value = ''
                }
                return
            }

            await this.stopPlaybackForSourceSwitch()
            this.isLoadingPlaylist = true
            this.playlistError = ''

            try {
                const playlist = await file.text()
                this.applyParsedChannels(playlist, file.name || this.$t('iptv.localFile'))
                this.activeSeedId = ''
                this.currentPlaylistUrl = ''
            } catch (_error) {
                this.playlistError = this.$t('iptv.playlistReadFailed')
            } finally {
                this.isLoadingPlaylist = false
                if (input) {
                    input.value = ''
                }
            }
        },

        async playDirectStream() {
            const url = String(this.directStreamUrl || '').trim()

            if (!this.isHttpUrl(url) && !this.isHttpsUrl(url)) {
                this.playlistError = this.$t('iptv.directUrlInvalid')
                return
            }

            await this.stopPlaybackForSourceSwitch()
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
            this.currentPlaylistUrl = ''
            this.activeSeedId = ''
            this.selectedGroup = 'all'
            this.selectedQuality = -1
            this.markChannelRecent(channel.id)
            this.syncSavedIdsWithPlaylist()
        },

        clearPlaylist() {
            this.stopServerTranscodeSession()
            this.stopServerProxySession()
            this.stopServerRelaySession()
            this.channels = []
            this.currentChannelId = ''
            this.playlistError = ''
            this.searchQuery = ''
            this.sourceLabel = this.$t('iptv.sourceNotSelected')
            this.currentPlaylistUrl = ''
            this.activeSeedId = ''
            this.selectedGroup = 'all'
            this.selectedQuality = -1
            this.qualityOptions = []
            this.playerError = ''
            this.playerStatus = 'idle'
            this.playerStatusMessage = ''
            this.copiedChannelId = ''
            this.playerDiagnostics = null
            this.relayError = ''

            const fileInput = this.$refs.fileInput
            if (fileInput) {
                fileInput.value = ''
            }
        },

        applyParsedChannels(playlistText, sourceLabel, options = {}) {
            const validationError = this.validatePlaylistPayload(playlistText)
            if (validationError !== '') {
                this.channels = []
                this.currentChannelId = ''
                this.playlistError = validationError
                this.sourceLabel = sourceLabel || this.$t('iptv.sourceNotSelected')
                return
            }

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
            if (!options?.preserveSelectedGroup) {
                this.selectedGroup = 'all'
            } else {
                this.ensureSelectedGroupExists()
            }
            this.selectedQuality = -1
            this.playerError = ''
            this.playerStatus = 'idle'
            this.playerStatusMessage = ''
            this.copiedChannelId = ''

            this.syncSavedIdsWithPlaylist()

            const preferredChannelId = this.resolvePreferredChannelId(parsedChannels, options?.preferredChannelId)
            if (preferredChannelId !== '') {
                this.playChannel(preferredChannelId)
            }
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

                if (this.isBlockedStreamUrl(line)) {
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

                if (channels.length >= IPTV_MAX_PARSED_CHANNELS) {
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
            const resolvedGroups = this.resolveChannelGroups(meta?.group)

            return {
                id: this.buildStableChannelId(url),
                name,
                url,
                group: resolvedGroups.label,
                groupTags: resolvedGroups.tags,
                logo: this.normalizeLogoUrl(meta?.logo),
                domain: parsed.domain,
                protocol,
                isSecure: protocol === 'https',
            }
        },

        validatePlaylistPayload(playlistText) {
            const normalized = String(playlistText || '').replace(/\r/g, '').trim()
            if (normalized === '') {
                return this.$t('iptv.noValidChannels')
            }

            const payloadBytes = this.utf8ByteLength(normalized)
            if (payloadBytes > IPTV_MAX_PLAYLIST_TEXT_BYTES) {
                return this.$t('iptv.playlistTooLarge', {
                    max: Math.floor(IPTV_MAX_PLAYLIST_TEXT_BYTES / (1024 * 1024)),
                })
            }

            const head = normalized.slice(0, 4096).toLowerCase()
            if (
                head.includes('<!doctype html')
                || head.includes('<html')
                || head.includes('<head')
                || head.includes('<body')
                || head.includes('<script')
                || head.includes('<iframe')
            ) {
                return this.$t('iptv.playlistUnsafeContent')
            }

            const lines = normalized.split('\n')
            if (lines.length > IPTV_MAX_PLAYLIST_LINES) {
                return this.$t('iptv.playlistUnsafeContent')
            }

            let validStreamCount = 0
            for (const rawLine of lines) {
                const line = String(rawLine || '').trim()
                if (line === '' || line.startsWith('#')) {
                    continue
                }

                const lowered = line.toLowerCase()
                if (
                    lowered.startsWith('javascript:')
                    || lowered.startsWith('data:')
                    || lowered.startsWith('vbscript:')
                    || lowered.startsWith('file:')
                ) {
                    return this.$t('iptv.playlistUnsafeLinks')
                }

                if ((this.isHttpUrl(line) || this.isHttpsUrl(line)) && !this.isBlockedStreamUrl(line)) {
                    validStreamCount += 1
                    if (validStreamCount >= 1) {
                        break
                    }
                }
            }

            if (validStreamCount === 0) {
                return this.$t('iptv.noValidChannels')
            }

            return ''
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

        utf8ByteLength(value) {
            const text = String(value || '')
            try {
                if (typeof TextEncoder !== 'undefined') {
                    return new TextEncoder().encode(text).length
                }
            } catch (_error) {
                // fallback below
            }

            return text.length
        },

        isBlockedStreamUrl(value) {
            try {
                const parsed = new URL(String(value || '').trim())
                const protocol = String(parsed.protocol || '').replace(':', '').toLowerCase()
                if (!['http', 'https'].includes(protocol)) {
                    return true
                }

                const host = String(parsed.hostname || '').toLowerCase()
                if (host === '' || host === 'localhost' || host.endsWith('.local')) {
                    return true
                }

                if (host === '::1' || host === '0:0:0:0:0:0:0:1') {
                    return true
                }

                if (host.startsWith('fc') || host.startsWith('fd') || host.startsWith('fe80:')) {
                    return true
                }

                const ipv4 = this.parseIpv4Tuple(host)
                if (!ipv4) {
                    return false
                }

                const [a, b] = ipv4
                if (a === 10 || a === 127 || a === 0) {
                    return true
                }
                if (a === 169 && b === 254) {
                    return true
                }
                if (a === 172 && b >= 16 && b <= 31) {
                    return true
                }
                if (a === 192 && b === 168) {
                    return true
                }
                if (a === 100 && b >= 64 && b <= 127) {
                    return true
                }
                if (a >= 224) {
                    return true
                }

                return false
            } catch (_error) {
                return true
            }
        },

        parseIpv4Tuple(host) {
            const parts = String(host || '').split('.')
            if (parts.length !== 4) {
                return null
            }

            const numbers = parts.map((part) => Number(part))
            if (numbers.some((part) => !Number.isInteger(part) || part < 0 || part > 255)) {
                return null
            }

            return numbers
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

<style scoped>
</style>
