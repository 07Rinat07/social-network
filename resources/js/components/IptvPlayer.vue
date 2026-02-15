<template>
    <div ref="playerShell" class="iptv-player-shell">
        <video
            ref="videoElement"
            :class="computedPlayerClass"
            :muted="muted"
            :playsinline="true"
        ></video>

        <div v-if="overlayMessage" class="iptv-player-overlay">
            {{ overlayMessage }}
        </div>
    </div>
</template>

<script>
import Plyr from 'plyr'
import Hls from 'hls.js'
import 'plyr/dist/plyr.css'

export default {
    name: 'IptvPlayer',

    emits: ['status-change', 'qualities-change', 'error', 'video-meta', 'volume-change', 'diagnostics-change'],

    props: {
        src: {
            type: String,
            default: '',
        },
        autoplay: {
            type: Boolean,
            default: false,
        },
        muted: {
            type: Boolean,
            default: false,
        },
        volume: {
            type: Number,
            default: 1,
        },
        playerClass: {
            type: String,
            default: 'media-video media-video-iptv',
        },
        fitMode: {
            type: String,
            default: 'contain',
            validator(value) {
                return ['contain', 'cover', 'fill'].includes(value)
            },
        },
        selectedQuality: {
            type: Number,
            default: -1,
        },
        bufferingMode: {
            type: String,
            default: 'auto',
            validator(value) {
                return ['auto', 'fast', 'balanced', 'stable'].includes(value)
            },
        },
        autoStability: {
            type: Boolean,
            default: true,
        },
    },

    data() {
        return {
            player: null,
            hls: null,
            dashPlayer: null,
            mpegtsPlayer: null,
            dashLibrary: null,
            mpegtsLibrary: null,
            dashLibraryPromise: null,
            mpegtsLibraryPromise: null,
            sourceLoadToken: 0,
            sourceUrl: '',
            playerState: 'idle',
            playerMessage: '',
            lastError: '',
            qualityOptions: [],
            activeEngine: 'idle',
            activeBufferProfile: 'balanced',
            runtimeBufferProfileOverride: '',
            runtimeBufferEscalationCount: 0,
            lastRuntimeBufferEscalationAt: 0,
            streamCodecs: [],
            hlsMinVideoLevel: 0,
            hlsAutoPreferredLevel: -1,
            hlsNativeFallbackAttempted: false,
            hlsLastSourceUrl: '',
            stabilityState: {
                totalEvents: 0,
                lastRecoveryAt: 0,
            },
            retryState: {
                network: 0,
                media: 0,
                levelSwitch: 0,
            },
            playbackWatchdogTimer: null,
            watchdogRecoveryAttempts: 0,
            videoFrameMonitorTimer: null,
            videoFrameMonitorState: {
                lastFrameCount: -1,
                stagnantTicks: 0,
                lastPlaybackTime: 0,
            },
            lastWaitingEventAt: 0,
            fullscreenEscapeHandler: null,
            fullscreenStateHandler: null,
        }
    },

    computed: {
        computedPlayerClass() {
            return `${this.playerClass} iptv-fit-${this.fitMode}`
        },

        overlayMessage() {
            if (!this.sourceUrl) {
                return 'Выберите канал из списка, чтобы начать просмотр.'
            }

            if (this.lastError) {
                return this.lastError
            }

            if (this.playerState === 'loading' || this.playerState === 'buffering') {
                return this.playerMessage || 'Загрузка потока...'
            }

            return ''
        },
    },

    mounted() {
        this.bindVideoEvents()
        this.bindFullscreenEscapeHandler()
        this.bindFullscreenStateListeners()
        this.initPlayer()
        this.normalizeFullscreenClasses()
    },

    beforeUnmount() {
        this.unbindVideoEvents()
        this.unbindFullscreenEscapeHandler()
        this.unbindFullscreenStateListeners()
        this.destroyPlayer()
    },

    watch: {
        src() {
            this.applySource()
        },

        muted(value) {
            const video = this.$refs.videoElement
            if (video) {
                video.muted = Boolean(value)
            }
        },

        volume(value) {
            const video = this.$refs.videoElement
            if (!video) {
                return
            }

            video.volume = this.normalizeVolume(value)
        },

        selectedQuality() {
            this.applySelectedQuality()
        },

        bufferingMode() {
            this.applySource()
        },

        autoStability() {
            this.applySource()
        },
    },

    methods: {
        bindVideoEvents() {
            const video = this.$refs.videoElement
            if (!video) {
                return
            }

            video.addEventListener('loadedmetadata', this.handleLoadedMetadata)
            video.addEventListener('playing', this.handlePlaying)
            video.addEventListener('pause', this.handlePause)
            video.addEventListener('waiting', this.handleWaiting)
            video.addEventListener('stalled', this.handleWaiting)
            video.addEventListener('error', this.handleNativeError)
            video.addEventListener('volumechange', this.handleVolumeChange)
        },

        unbindVideoEvents() {
            const video = this.$refs.videoElement
            if (!video) {
                return
            }

            video.removeEventListener('loadedmetadata', this.handleLoadedMetadata)
            video.removeEventListener('playing', this.handlePlaying)
            video.removeEventListener('pause', this.handlePause)
            video.removeEventListener('waiting', this.handleWaiting)
            video.removeEventListener('stalled', this.handleWaiting)
            video.removeEventListener('error', this.handleNativeError)
            video.removeEventListener('volumechange', this.handleVolumeChange)
        },

        initPlayer() {
            const element = this.$refs.videoElement
            if (!element) {
                return
            }

            element.muted = this.muted
            element.volume = this.normalizeVolume(this.volume)

            try {
                this.player = new Plyr(element, {
                    controls: ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'settings', 'pip', 'airplay'],
                    settings: ['speed'],
                    fullscreen: {
                        enabled: false,
                        fallback: false,
                        iosNative: false,
                    },
                    keyboard: {
                        focused: true,
                        global: false,
                    },
                })
            } catch (error) {
                this.setError('Плеер не удалось инициализировать. Обновите страницу и попробуйте снова.')
                this.$emit('error', {
                    message: 'Плеер не удалось инициализировать. Обновите страницу и попробуйте снова.',
                    type: 'player-init',
                    details: String(error?.message || error || ''),
                })
                return
            }

            this.applySource()
        },

        destroyPlayer() {
            this.sourceLoadToken += 1
            this.clearPlaybackWatchdog()
            this.resetVideoFrameMonitor()
            this.destroyHls()
            this.destroyDash()
            this.destroyMpegts()
            this.exitPictureInPicture()
            this.clearVideoElementSource()

            if (this.player) {
                this.exitFullscreen()
                this.player.destroy()
                this.player = null
            }

            this.sourceUrl = ''
            this.setStatus('idle')
            this.playerMessage = ''
            this.lastError = ''
            this.qualityOptions = []
            this.activeEngine = 'idle'
            this.activeBufferProfile = 'balanced'
            this.runtimeBufferProfileOverride = ''
            this.runtimeBufferEscalationCount = 0
            this.lastRuntimeBufferEscalationAt = 0
            this.streamCodecs = []
            this.hlsMinVideoLevel = 0
            this.hlsAutoPreferredLevel = -1
            this.hlsNativeFallbackAttempted = false
            this.hlsLastSourceUrl = ''
            this.lastWaitingEventAt = 0
            this.stabilityState = {
                totalEvents: 0,
                lastRecoveryAt: 0,
            }
            this.$emit('qualities-change', [])
            this.emitDiagnostics()
        },

        destroyHls() {
            if (!this.hls) {
                return
            }

            this.hls.destroy()
            this.hls = null
        },

        destroyDash() {
            if (!this.dashPlayer) {
                return
            }

            try {
                this.dashPlayer.reset()
            } catch (_error) {
                // ignore destroy errors
            }
            this.dashPlayer = null
        },

        destroyMpegts() {
            if (!this.mpegtsPlayer) {
                return
            }

            try {
                this.mpegtsPlayer.destroy()
            } catch (_error) {
                // ignore destroy errors
            }
            this.mpegtsPlayer = null
        },

        handleLoadedMetadata() {
            const video = this.$refs.videoElement
            if (!video) {
                return
            }

            this.$emit('video-meta', {
                width: Number(video.videoWidth || 0),
                height: Number(video.videoHeight || 0),
                duration: Number(video.duration || 0),
            })

            if (!this.lastError) {
                this.setStatus('ready')
            }
        },

        handlePlaying() {
            this.setStatus('playing')
        },

        handlePause() {
            if (this.playerState !== 'error') {
                this.setStatus('paused')
            }
        },

        handleWaiting() {
            if (this.playerState === 'error') {
                return
            }

            const video = this.getVideoElement()
            const bufferedAheadSeconds = this.getBufferedAheadSeconds(video)
            const now = Date.now()

            if (
                bufferedAheadSeconds >= 2.2
                && (now - Number(this.lastWaitingEventAt || 0)) < 1200
            ) {
                this.setStatus('buffering', 'Короткий сетевой лаг, удерживаем текущий буфер...')
                return
            }

            this.lastWaitingEventAt = now
            this.setStatus('buffering', 'Буферизация потока...')
            this.tryAdaptiveRecovery('waiting')
        },

        handleNativeError() {
            if (this.lastError !== '') {
                return
            }

            this.tryAdaptiveRecovery('native-error')
            this.setError('Поток не удалось декодировать в браузере. Проверьте ссылку канала или попробуйте другой источник.')
        },

        handleVolumeChange() {
            const video = this.$refs.videoElement
            if (!video) {
                return
            }

            this.$emit('volume-change', {
                volume: this.normalizeVolume(video.volume),
                muted: Boolean(video.muted),
            })
        },

        setStatus(status, message = '') {
            this.playerState = status
            this.playerMessage = message

            if (status === 'loading' || status === 'buffering') {
                this.schedulePlaybackWatchdog()
            } else {
                this.clearPlaybackWatchdog()

                if (status === 'idle' || status === 'ready' || status === 'playing' || status === 'paused') {
                    this.watchdogRecoveryAttempts = 0

                    if (status === 'ready' || status === 'playing') {
                        this.retryState.levelSwitch = 0
                    }
                }
            }

            if (status === 'ready' || status === 'playing' || status === 'buffering') {
                this.scheduleVideoFrameMonitor()
            } else if (status === 'idle' || status === 'error') {
                this.clearVideoFrameMonitor()
            }

            this.$emit('status-change', {
                status,
                message,
            })
        },

        setError(message, details = {}) {
            const text = String(message || '').trim() || 'Ошибка воспроизведения потока.'
            this.lastError = text
            this.clearPlaybackWatchdog()
            this.clearVideoFrameMonitor()
            this.setStatus('error', text)
            this.emitDiagnostics({
                lastError: text,
            })
            this.$emit('error', {
                message: text,
                ...details,
            })
        },

        resetRuntimeState() {
            this.clearPlaybackWatchdog()
            this.resetVideoFrameMonitor()
            this.retryState = {
                network: 0,
                media: 0,
                levelSwitch: 0,
            }
            this.watchdogRecoveryAttempts = 0
            this.lastError = ''
            this.qualityOptions = []
            this.streamCodecs = []
            this.hlsMinVideoLevel = 0
            this.hlsNativeFallbackAttempted = false
            this.hlsLastSourceUrl = ''
            this.activeEngine = 'idle'
            this.activeBufferProfile = 'balanced'
            this.lastWaitingEventAt = 0
            this.stabilityState = {
                totalEvents: 0,
                lastRecoveryAt: 0,
            }
            this.$emit('qualities-change', [])
            this.emitDiagnostics()
        },

        async applySource() {
            if (!this.player) {
                return
            }

            const video = this.$refs.videoElement
            if (!video) {
                return
            }

            const incomingSourceUrl = String(this.src || '').trim()
            const sourceChanged = incomingSourceUrl !== String(this.sourceUrl || '').trim()
            if (sourceChanged) {
                this.runtimeBufferProfileOverride = ''
                this.runtimeBufferEscalationCount = 0
                this.lastRuntimeBufferEscalationAt = 0
            }

            this.sourceUrl = incomingSourceUrl
            const sourceUrl = this.sourceUrl
            const sourceLoadToken = ++this.sourceLoadToken

            this.resetRuntimeState()
            this.destroyHls()
            this.destroyDash()
            this.destroyMpegts()
            this.clearVideoElementSource()

            if (sourceUrl === '') {
                this.player.source = {
                    type: 'video',
                    sources: [],
                }
                this.setStatus('idle')
                return
            }

            if (this.isMixedContentBlocked(sourceUrl)) {
                this.setError(
                    'Браузер блокирует HTTP-поток внутри HTTPS-страницы. Нужен HTTPS-источник или совместимый режим FFmpeg.',
                    {
                        type: 'mixed-content-blocked',
                        details: 'http-stream-on-https-page',
                    }
                )
                return
            }

            this.setStatus('loading', 'Подключение к потоку...')
            const sourceType = this.detectSourceType(sourceUrl)
            const activeBufferProfile = this.resolveBufferingProfile()
            this.activeBufferProfile = activeBufferProfile
            this.emitDiagnostics()

            if (sourceType === 'hls') {
                if (Hls.isSupported()) {
                    this.activeEngine = 'hls.js'
                    this.emitDiagnostics()
                    this.initHls(video, sourceUrl, activeBufferProfile)
                    return
                }

                if (this.canPlayNativeHls(video)) {
                    this.activeEngine = 'native-hls'
                    this.emitDiagnostics()
                    video.src = sourceUrl
                    this.tryAutoplay(video)
                    return
                }

                this.setError('Поток HLS найден, но в этом браузере нет поддержки HLS. Откройте канал во внешнем плеере.')
                return
            }

            if (sourceType === 'dash') {
                let dashLibrary = null
                try {
                    dashLibrary = await this.loadDashLibrary()
                } catch (_error) {
                    if (sourceLoadToken !== this.sourceLoadToken) {
                        return
                    }

                    this.setError('Не удалось загрузить модуль dash.js для MPEG-DASH.')
                    return
                }

                if (sourceLoadToken !== this.sourceLoadToken) {
                    return
                }

                if (this.canUseDashJs(dashLibrary)) {
                    this.activeEngine = 'dash.js'
                    this.emitDiagnostics()
                    this.initDash(video, sourceUrl, dashLibrary, activeBufferProfile)
                    return
                }

                this.setError('Поток DASH (.mpd) не поддерживается в текущем браузере. Попробуйте другой источник.')
                return
            }

            if (sourceType === 'mpegts' || sourceType === 'flv') {
                let mpegtsLibrary = null
                try {
                    mpegtsLibrary = await this.loadMpegtsLibrary()
                } catch (_error) {
                    if (sourceLoadToken !== this.sourceLoadToken) {
                        return
                    }

                    this.setError('Не удалось загрузить модуль mpegts.js для MPEG-TS/FLV.')
                    return
                }

                if (sourceLoadToken !== this.sourceLoadToken) {
                    return
                }

                if (this.canUseMpegtsJs(mpegtsLibrary)) {
                    this.activeEngine = 'mpegts.js'
                    this.emitDiagnostics()
                    this.initMpegts(video, sourceUrl, sourceType, mpegtsLibrary, activeBufferProfile)
                    return
                }

                this.setError('Поток MPEG-TS/FLV требует Media Source Extensions. Браузер не поддерживает нужный режим.')
                return
            }

            const source = {
                src: sourceUrl,
            }

            const mimeType = this.guessMimeType(sourceUrl)
            if (mimeType !== '') {
                source.type = mimeType
            }

            this.player.source = {
                type: 'video',
                sources: [source],
            }

            this.activeEngine = 'native'
            this.emitDiagnostics()

            if (this.autoplay) {
                this.player.play().catch(() => {})
            }
        },

        initHls(video, sourceUrl, bufferingProfile) {
            this.hlsLastSourceUrl = String(sourceUrl || '')
            const streamConfig = this.getStreamProfileConfig(bufferingProfile)
            const hlsProfile = streamConfig.hls || {}

            const hls = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                backBufferLength: Number(hlsProfile.backBufferLength ?? 90),
                maxBufferLength: Number(hlsProfile.maxBufferLength ?? 30),
                maxBufferHole: Number(hlsProfile.maxBufferHole ?? 0.5),
                maxSeekHole: Number(hlsProfile.maxSeekHole ?? 2),
                capLevelToPlayerSize: false,
                manifestLoadingTimeOut: Number(hlsProfile.manifestLoadingTimeOut ?? 18000),
                levelLoadingTimeOut: Number(hlsProfile.levelLoadingTimeOut ?? 18000),
                fragLoadingTimeOut: Number(hlsProfile.fragLoadingTimeOut ?? 20000),
                manifestLoadingMaxRetry: Number(hlsProfile.manifestLoadingMaxRetry ?? 3),
                levelLoadingMaxRetry: Number(hlsProfile.levelLoadingMaxRetry ?? 3),
                fragLoadingMaxRetry: Number(hlsProfile.fragLoadingMaxRetry ?? 3),
                manifestLoadingRetryDelay: Number(hlsProfile.manifestLoadingRetryDelay ?? 500),
                levelLoadingRetryDelay: Number(hlsProfile.levelLoadingRetryDelay ?? 500),
                fragLoadingRetryDelay: Number(hlsProfile.fragLoadingRetryDelay ?? 500),
                fragLoadingMaxRetryTimeout: Number(hlsProfile.fragLoadingMaxRetryTimeout ?? 20000),
                liveSyncDurationCount: Number(hlsProfile.liveSyncDurationCount ?? 4),
                liveMaxLatencyDurationCount: Number(hlsProfile.liveMaxLatencyDurationCount ?? 10),
                maxLiveSyncPlaybackRate: Number(hlsProfile.maxLiveSyncPlaybackRate ?? 1.15),
            })

            hls.on(Hls.Events.MEDIA_ATTACHED, () => {
                hls.loadSource(sourceUrl)
            })

            hls.on(Hls.Events.MANIFEST_PARSED, (_event, data) => {
                const levels = Array.isArray(data?.levels) ? data.levels : hls.levels
                const firstVideoLevel = this.findFirstVideoLevel(levels)
                this.hlsMinVideoLevel = firstVideoLevel >= 0 ? firstVideoLevel : 0
                this.hlsAutoPreferredLevel = this.pickPreferredAutoLevel(levels, this.hlsMinVideoLevel)

                if (firstVideoLevel > 0) {
                    const startLevel = Number(hls.startLevel)
                    if (!Number.isFinite(startLevel) || startLevel < firstVideoLevel) {
                        hls.startLevel = firstVideoLevel
                    }

                    const currentLevel = Number(hls.currentLevel)
                    if (Number.isFinite(currentLevel) && currentLevel >= 0 && currentLevel < firstVideoLevel) {
                        hls.currentLevel = firstVideoLevel
                    }
                }

                this.syncQualityOptions(levels)
                this.setStreamCodecs(this.extractHlsCodecs(levels))
                this.applySelectedQuality()
                this.applyAutoPreferredLevel()
                this.tryAutoplay(video)
                this.setStatus('ready')
            })

            hls.on(Hls.Events.LEVEL_SWITCHED, () => {
                this.handleLoadedMetadata()
            })

            hls.on(Hls.Events.ERROR, (_event, data) => {
                this.handleHlsError(data)
            })

            hls.attachMedia(video)
            this.hls = hls
        },

        initDash(video, sourceUrl, dashLibrary, bufferingProfile) {
            const player = dashLibrary.MediaPlayer().create()
            const events = dashLibrary.MediaPlayer?.events || {}
            const streamConfig = this.getStreamProfileConfig(bufferingProfile)
            const dashProfile = streamConfig.dash || {}

            if (typeof player.updateSettings === 'function') {
                player.updateSettings({
                    streaming: {
                        lowLatencyEnabled: false,
                        buffer: {
                            stableBufferTime: Number(dashProfile.stableBufferTime ?? 12),
                            bufferTimeAtTopQuality: Number(dashProfile.bufferTimeAtTopQuality ?? 20),
                            bufferToKeep: Number(dashProfile.bufferToKeep ?? 12),
                        },
                        abr: {
                            autoSwitchBitrate: {
                                video: true,
                                audio: true,
                            },
                            bandwidthSafetyFactor: this.autoStability ? 0.75 : 0.9,
                            useBufferOccupancyABR: Boolean(this.autoStability),
                        },
                    },
                })
            }

            if (events.STREAM_INITIALIZED) {
                player.on(events.STREAM_INITIALIZED, () => {
                    this.setStreamCodecs(this.extractDashCodecs(player))
                    this.setStatus('ready')
                })
            }

            if (events.ERROR) {
                player.on(events.ERROR, (event) => {
                    this.setError(this.getReadableDashError(event), {
                        type: 'dash-error',
                        details: String(event?.error || event?.event?.message || event?.message || ''),
                    })
                })
            }

            if (events.PLAYBACK_ERROR) {
                player.on(events.PLAYBACK_ERROR, (event) => {
                    this.tryAdaptiveRecovery('dash-playback-error')

                    if (this.autoStability) {
                        this.setStatus('buffering', 'Сеть нестабильна, пытаемся восстановить DASH-поток...')
                        return
                    }

                    this.setError(this.getReadableDashError(event), {
                        type: 'dash-playback-error',
                        details: String(event?.error || event?.event?.message || event?.message || ''),
                    })
                })
            }

            player.initialize(video, sourceUrl, Boolean(this.autoplay))
            this.dashPlayer = player
        },

        initMpegts(video, sourceUrl, sourceType, mpegtsLibrary, bufferingProfile) {
            const playerType = sourceType === 'flv' ? 'flv' : 'mpegts'
            const streamConfig = this.getStreamProfileConfig(bufferingProfile)
            const mpegtsProfile = streamConfig.mpegts || {}
            const player = mpegtsLibrary.createPlayer({
                type: playerType,
                isLive: true,
                url: sourceUrl,
            }, {
                enableWorker: true,
                enableWorkerForMSE: true,
                liveBufferLatencyChasing: true,
                enableStashBuffer: Boolean(mpegtsProfile.enableStashBuffer ?? true),
                stashInitialSize: Number(mpegtsProfile.stashInitialSize ?? 384 * 1024),
                autoCleanupSourceBuffer: Boolean(mpegtsProfile.autoCleanupSourceBuffer ?? true),
                autoCleanupMaxBackwardDuration: Number(mpegtsProfile.autoCleanupMaxBackwardDuration ?? 60),
                autoCleanupMinBackwardDuration: Number(mpegtsProfile.autoCleanupMinBackwardDuration ?? 30),
                fixAudioTimestampGap: true,
            })

            if (mpegtsLibrary.Events?.MEDIA_INFO) {
                player.on(mpegtsLibrary.Events.MEDIA_INFO, (info) => {
                    this.setStreamCodecs(this.extractMpegtsCodecs(info))
                    this.setStatus('ready')
                })
            }

            if (mpegtsLibrary.Events?.ERROR) {
                player.on(mpegtsLibrary.Events.ERROR, (errorType, errorDetail) => {
                    const combinedDetails = `${String(errorType || '')} ${String(errorDetail || '')}`.toLowerCase()
                    if (this.autoStability && (combinedDetails.includes('network') || combinedDetails.includes('timeout') || combinedDetails.includes('http'))) {
                        this.tryAdaptiveRecovery('mpegts-network-error')
                        this.setStatus('loading', 'Сеть нестабильна, переподключаем MPEG-TS/FLV поток...')

                        try {
                            player.unload()
                            player.load()
                            if (this.autoplay) {
                                const playPromise = player.play()
                                if (playPromise && typeof playPromise.catch === 'function') {
                                    playPromise.catch(() => {})
                                }
                            }
                            return
                        } catch (_error) {
                            // fallback to regular error output
                        }
                    }

                    this.setError(this.getReadableMpegtsError(errorType, errorDetail), {
                        type: String(errorType || ''),
                        details: String(errorDetail || ''),
                    })
                })
            }

            player.attachMediaElement(video)
            player.load()
            this.mpegtsPlayer = player

            if (this.autoplay) {
                const promise = player.play()
                if (promise && typeof promise.catch === 'function') {
                    promise.catch(() => {
                        this.setStatus('ready', 'Нажмите кнопку Play для запуска канала.')
                    })
                }
            }
        },

        handleHlsError(data) {
            if (!data?.fatal) {
                if (data?.details === Hls.ErrorDetails.BUFFER_STALLED_ERROR) {
                    this.setStatus('buffering', 'Поток буферизуется...')
                }

                const responseCode = this.extractHlsResponseCode(data)
                if (
                    this.isHlsSegmentIssueDetails(data?.details)
                    && (responseCode === 403 || responseCode === 404)
                ) {
                    this.retryState.network = Number(this.retryState.network || 0) + 1

                    if (this.retryState.network >= 3) {
                        this.setError(
                            'Источник отклоняет сегменты для встроенного плеера. Откройте поток в новой вкладке или включите режим прокси.',
                            {
                                type: 'hls-origin-blocked',
                                details: this.buildHlsErrorDetails(data),
                            }
                        )
                        this.destroyHls()
                        return
                    }

                    this.setStatus(
                        'loading',
                        `Сегменты недоступны (${responseCode}), пробуем переподключить поток... (${this.retryState.network}/3)`
                    )
                    this.hls?.startLoad(-1)
                    return
                }

                if (this.tryFailoverHlsLevel(data)) {
                    return
                }

                if (data?.details === Hls.ErrorDetails.BUFFER_STALLED_ERROR || data?.type === Hls.ErrorTypes.NETWORK_ERROR) {
                    this.tryAdaptiveRecovery('hls-non-fatal')
                }
                return
            }

            if (this.trySwitchToNativeHls(data)) {
                return
            }

            if (this.tryFailoverHlsLevel(data)) {
                return
            }

            if (data.type === Hls.ErrorTypes.NETWORK_ERROR && this.retryState.network < 2) {
                this.retryState.network += 1
                this.setStatus('loading', `Переподключение к каналу... (${this.retryState.network}/2)`)
                this.hls?.startLoad()
                this.tryAdaptiveRecovery('hls-network-retry')
                return
            }

            if (data.type === Hls.ErrorTypes.MEDIA_ERROR && this.retryState.media < 2) {
                this.retryState.media += 1
                this.setStatus('loading', `Восстановление декодера... (${this.retryState.media}/2)`)
                this.hls?.recoverMediaError()
                this.tryAdaptiveRecovery('hls-media-retry')
                return
            }

            this.setError(this.getReadableHlsError(data), {
                type: data?.type || '',
                details: this.buildHlsErrorDetails(data),
            })
            this.destroyHls()
        },

        tryFailoverHlsLevel(data) {
            if (!this.hls || !data) {
                return false
            }

            // Respect manual quality lock set by user.
            if (Number(this.selectedQuality) >= 0) {
                return false
            }

            const details = String(data?.details || '').toLowerCase()
            const isSegmentIssue = this.isHlsSegmentIssueDetails(details)

            if (!isSegmentIssue) {
                return false
            }

            const levels = Array.isArray(this.hls.levels) ? this.hls.levels : []
            if (levels.length < 2) {
                return false
            }

            if (Number(this.retryState.levelSwitch || 0) >= 3) {
                this.setError(
                    'Сегменты канала недоступны на текущих профилях качества. Попробуйте другой канал или режим прокси/совместимости.',
                    {
                        type: 'hls-segment-exhausted',
                        details: this.buildHlsErrorDetails(data),
                    }
                )
                this.destroyHls()
                return true
            }

            let currentLevel = Number(this.hls.currentLevel)
            if (!Number.isFinite(currentLevel) || currentLevel < 0) {
                currentLevel = Number(this.hls.nextLoadLevel)
            }
            if (!Number.isFinite(currentLevel) || currentLevel < 0) {
                currentLevel = Number(this.hls.loadLevel)
            }
            if (!Number.isFinite(currentLevel) || currentLevel < 0) {
                currentLevel = 0
            }

            const candidates = levels
                .map((_item, index) => index)
                .filter((index) => index >= Number(this.hlsMinVideoLevel || 0) && index !== currentLevel)

            if (candidates.length === 0) {
                return false
            }

            const failoverStep = Number(this.retryState.levelSwitch || 0)
            const targetLevel = candidates[Math.min(failoverStep, candidates.length - 1)]
            this.retryState.levelSwitch = failoverStep + 1

            if (typeof this.hls.nextLoadLevel === 'number') {
                this.hls.nextLoadLevel = targetLevel
            }
            if (typeof this.hls.currentLevel === 'number') {
                this.hls.currentLevel = targetLevel
            }
            if (typeof this.hls.loadLevel === 'number') {
                this.hls.loadLevel = targetLevel
            }

            this.setStatus('loading', 'Битый сегмент канала, переключаем профиль качества...')
            this.emitDiagnostics({
                recoveryReason: details || 'hls-segment-failover',
                recoveryAction: `switch-hls-level:${currentLevel}->${targetLevel}`,
            })
            this.hls.startLoad(-1)
            return true
        },

        shouldSwitchToNativeHls(data) {
            if (!data) {
                return false
            }

            const details = String(data?.details || '').toLowerCase()
            const responseText = String(data?.response?.text || '').toLowerCase()
            const reason = `${details} ${responseText}`

            return data?.type === Hls.ErrorTypes.NETWORK_ERROR
                || details.includes('manifest')
                || details.includes('frag')
                || reason.includes('cors')
                || reason.includes('cross-origin')
                || reason.includes('access-control-allow-origin')
        },

        isMobileViewport() {
            if (typeof window === 'undefined' || !window.matchMedia) {
                return false
            }

            return window.matchMedia('(max-width: 980px)').matches
        },

        pickPreferredAutoLevel(levels, minLevel = 0) {
            if (!Array.isArray(levels) || levels.length === 0) {
                return -1
            }

            if (!this.isMobileViewport()) {
                return -1
            }

            const isAutoQuality = !Number.isFinite(Number(this.selectedQuality)) || Number(this.selectedQuality) < 0
            if (!isAutoQuality) {
                return -1
            }

            const candidates = levels
                .map((level, index) => {
                    const height = Number(level?.height || 0)
                    const bitrate = Number(level?.bitrate || 0)
                    const codec = String(level?.videoCodec || level?.codecs || level?.attrs?.CODECS || '').toLowerCase()
                    const hevcLike = codec.includes('hev') || codec.includes('hvc')
                    return {
                        index,
                        height,
                        bitrate,
                        hevcLike,
                    }
                })
                .filter((item) => item.index >= Number(minLevel || 0))

            if (candidates.length === 0) {
                return -1
            }

            const balanced = candidates.filter((item) => !item.hevcLike && item.height >= 360 && item.height <= 720)
            if (balanced.length > 0) {
                return balanced[Math.floor(balanced.length / 2)].index
            }

            const nonHevc = candidates.filter((item) => !item.hevcLike)
            if (nonHevc.length > 0) {
                return nonHevc[Math.max(0, nonHevc.length - 1)].index
            }

            return candidates[Math.max(0, candidates.length - 1)].index
        },

        applyAutoPreferredLevel() {
            if (!this.hls) {
                return
            }

            if (Number(this.selectedQuality) >= 0) {
                return
            }

            const target = Number(this.hlsAutoPreferredLevel)
            if (!Number.isFinite(target) || target < 0 || target >= this.hls.levels.length) {
                return
            }

            if (typeof this.hls.nextLoadLevel === 'number') {
                this.hls.nextLoadLevel = target
            }
            if (typeof this.hls.currentLevel === 'number') {
                this.hls.currentLevel = target
            }
            if (typeof this.hls.loadLevel === 'number') {
                this.hls.loadLevel = target
            }
        },

        trySwitchToNativeHls(data) {
            if (this.hlsNativeFallbackAttempted) {
                return false
            }

            if (!this.shouldSwitchToNativeHls(data)) {
                return false
            }

            const video = this.getVideoElement()
            if (!video || !this.canPlayNativeHls(video)) {
                return false
            }

            const sourceUrl = String(this.hlsLastSourceUrl || this.sourceUrl || '').trim()
            if (sourceUrl === '') {
                return false
            }

            this.hlsNativeFallbackAttempted = true
            this.destroyHls()
            this.activeEngine = 'native-hls'
            this.emitDiagnostics({
                recoveryReason: 'hls-fatal',
                recoveryAction: 'switch-native-hls',
            })

            this.setStatus('loading', 'HLS.js заблокирован источником, переключаемся на нативный режим...')
            video.src = sourceUrl
            this.tryAutoplay(video)
            return true
        },

        isHlsSegmentIssueDetails(details) {
            const normalized = String(details || '').toLowerCase()
            return normalized.includes('frag_load_error')
                || normalized.includes('fragloaderror')
                || normalized.includes('frag_load_timeout')
                || normalized.includes('fragloadtimeout')
                || normalized.includes('level_load_error')
                || normalized.includes('levelloaderror')
                || normalized.includes('level_load_timeout')
                || normalized.includes('levelloadtimeout')
                || normalized.includes('key_load_error')
                || normalized.includes('keyloaderror')
                || normalized.includes('key_load_timeout')
                || normalized.includes('keyloadtimeout')
        },

        extractHlsResponseCode(data) {
            const code = Number(data?.response?.code ?? data?.response?.status ?? 0)
            return Number.isFinite(code) ? code : 0
        },

        extractHlsErrorUrl(data) {
            return String(
                data?.response?.url
                || data?.frag?.url
                || data?.url
                || data?.context?.url
                || ''
            ).trim()
        },

        buildHlsErrorDetails(data) {
            const parts = []
            const type = String(data?.type || '').trim()
            const details = String(data?.details || '').trim()
            const code = this.extractHlsResponseCode(data)
            const failedUrl = this.extractHlsErrorUrl(data)

            if (type !== '') {
                parts.push(`type=${type}`)
            }
            if (details !== '') {
                parts.push(`detail=${details}`)
            }
            if (code > 0) {
                parts.push(`code=${code}`)
            }
            if (failedUrl !== '') {
                parts.push(`url=${failedUrl}`)
            }

            return parts.join('; ')
        },

        getReadableHlsError(data) {
            const details = String(data?.details || '')
            const responseCode = this.extractHlsResponseCode(data)
            const isSegmentIssue = this.isHlsSegmentIssueDetails(details)

            if (isSegmentIssue && (responseCode === 403 || responseCode === 404)) {
                return 'Источник отклоняет загрузку сегментов во встроенном плеере (часто это Origin/anti-hotlink). Откройте поток в новой вкладке или включите режим прокси.'
            }

            if (details === Hls.ErrorDetails.MANIFEST_LOAD_ERROR || details === Hls.ErrorDetails.MANIFEST_LOAD_TIMEOUT) {
                return 'Не удалось загрузить HLS-манифест канала. Частая причина: недоступный сервер или блокировка CORS.'
            }

            if (details === Hls.ErrorDetails.FRAG_LOAD_ERROR || details === Hls.ErrorDetails.FRAG_LOAD_TIMEOUT) {
                return 'Сегменты видео не загружаются. Поток нестабилен или источник ограничивает доступ.'
            }

            if (details === Hls.ErrorDetails.BUFFER_ADD_CODEC_ERROR || details === Hls.ErrorDetails.BUFFER_APPEND_ERROR) {
                return 'Кодек потока не поддерживается браузером. Из-за этого звук может быть, а видео черным.'
            }

            return 'Плеер не смог воспроизвести канал. Попробуйте другой поток или откройте ссылку во внешнем плеере.'
        },

        getReadableDashError(event) {
            const message = String(
                event?.error?.message
                || event?.event?.message
                || event?.message
                || ''
            ).toLowerCase()

            if (message.includes('codec') || message.includes('decode')) {
                return 'DASH-поток использует кодек, который браузер не может декодировать.'
            }

            if (message.includes('manifest') || message.includes('mpd')) {
                return 'Не удалось обработать DASH-манифест (.mpd). Проверьте ссылку и доступность сервера.'
            }

            return 'DASH-поток не удалось воспроизвести в браузере.'
        },

        getReadableMpegtsError(errorType, errorDetail) {
            const combined = `${String(errorType || '')} ${String(errorDetail || '')}`.toLowerCase()

            if (combined.includes('codec') || combined.includes('format')) {
                return 'MPEG-TS/FLV поток использует неподдерживаемый кодек для браузера.'
            }

            if (combined.includes('network') || combined.includes('timeout') || combined.includes('http')) {
                return 'Не удалось загрузить сегменты MPEG-TS/FLV. Проверьте сеть, CORS и доступность источника.'
            }

            return 'Плеер mpegts.js не смог воспроизвести поток.'
        },

        extractHlsCodecs(levels) {
            if (!Array.isArray(levels) || levels.length === 0) {
                return []
            }

            const codecs = new Set()
            levels.forEach((level) => {
                const candidates = [
                    level?.codecs,
                    level?.videoCodec,
                    level?.audioCodec,
                    level?.attrs?.CODECS,
                ]

                candidates.forEach((value) => {
                    String(value || '')
                        .split(',')
                        .map((item) => item.trim())
                        .filter((item) => item !== '')
                        .forEach((item) => codecs.add(item))
                })
            })

            return Array.from(codecs).slice(0, 12)
        },

        findFirstVideoLevel(levels) {
            if (!Array.isArray(levels) || levels.length === 0) {
                return -1
            }

            return levels.findIndex((level) => {
                const height = Number(level?.height || 0)
                if (height > 0) {
                    return true
                }

                const codecCandidates = [
                    level?.videoCodec,
                    level?.codecs,
                    level?.attrs?.CODECS,
                ]

                return codecCandidates.some((codec) => /avc|h26|hvc|hev|vp9|av01|mpeg2|mpeg-2|mp4v/i.test(String(codec || '')))
            })
        },

        extractDashCodecs(player) {
            if (!player || typeof player.getTracksFor !== 'function') {
                return []
            }

            const codecs = new Set()
            ;['video', 'audio'].forEach((kind) => {
                const tracks = player.getTracksFor(kind)
                if (!Array.isArray(tracks)) {
                    return
                }

                tracks.forEach((track) => {
                    const codec = String(track?.codec || '').trim()
                    if (codec !== '') {
                        codecs.add(codec)
                    }
                })
            })

            return Array.from(codecs).slice(0, 12)
        },

        extractMpegtsCodecs(mediaInfo) {
            const codecs = []

            const videoCodec = String(mediaInfo?.videoCodec || '').trim()
            if (videoCodec !== '') {
                codecs.push(videoCodec)
            }

            const audioCodec = String(mediaInfo?.audioCodec || '').trim()
            if (audioCodec !== '') {
                codecs.push(audioCodec)
            }

            return codecs
        },

        setStreamCodecs(codecs) {
            const normalized = Array.from(new Set(
                (Array.isArray(codecs) ? codecs : [])
                    .map((codec) => String(codec || '').trim())
                    .filter((codec) => codec !== '')
            )).slice(0, 12)

            this.streamCodecs = normalized
            this.emitDiagnostics()
        },

        resolveBufferingProfile() {
            const runtimeOverride = this.normalizeBufferProfile(this.runtimeBufferProfileOverride, '')
            if (runtimeOverride !== '') {
                return runtimeOverride
            }

            const requested = String(this.bufferingMode || 'auto').toLowerCase()
            if (requested !== 'auto') {
                return ['fast', 'balanced', 'stable'].includes(requested) ? requested : 'balanced'
            }

            if (typeof navigator === 'undefined') {
                return 'stable'
            }

            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection
            const effectiveType = String(connection?.effectiveType || '').toLowerCase()
            const downlink = Number(connection?.downlink || 0)

            if (effectiveType.includes('slow-2g') || effectiveType.includes('2g') || effectiveType.includes('3g')) {
                return 'stable'
            }

            if (Number.isFinite(downlink) && downlink > 0 && downlink < 4) {
                return 'stable'
            }

            if (Number.isFinite(downlink) && downlink >= 20) {
                return 'fast'
            }

            if (!Number.isFinite(downlink) || downlink <= 0) {
                return 'stable'
            }

            return 'balanced'
        },

        getStreamProfileConfig(profile) {
            const presets = {
                fast: {
                    hls: {
                        maxBufferLength: 16,
                        backBufferLength: 40,
                        maxBufferHole: 0.35,
                        maxSeekHole: 1,
                        manifestLoadingTimeOut: 12000,
                        levelLoadingTimeOut: 12000,
                        fragLoadingTimeOut: 15000,
                        manifestLoadingMaxRetry: 2,
                        levelLoadingMaxRetry: 2,
                        fragLoadingMaxRetry: 2,
                        manifestLoadingRetryDelay: 300,
                        levelLoadingRetryDelay: 300,
                        fragLoadingRetryDelay: 350,
                        fragLoadingMaxRetryTimeout: 9000,
                        liveSyncDurationCount: 3,
                        liveMaxLatencyDurationCount: 7,
                        maxLiveSyncPlaybackRate: 1.1,
                    },
                    dash: {
                        stableBufferTime: 8,
                        bufferTimeAtTopQuality: 12,
                        bufferToKeep: 8,
                    },
                    mpegts: {
                        enableStashBuffer: false,
                        stashInitialSize: 192 * 1024,
                        autoCleanupSourceBuffer: true,
                        autoCleanupMaxBackwardDuration: 30,
                        autoCleanupMinBackwardDuration: 15,
                    },
                },
                balanced: {
                    hls: {
                        maxBufferLength: 30,
                        backBufferLength: 90,
                        maxBufferHole: 0.5,
                        maxSeekHole: 2,
                        manifestLoadingTimeOut: 18000,
                        levelLoadingTimeOut: 18000,
                        fragLoadingTimeOut: 22000,
                        manifestLoadingMaxRetry: 3,
                        levelLoadingMaxRetry: 3,
                        fragLoadingMaxRetry: 3,
                        manifestLoadingRetryDelay: 500,
                        levelLoadingRetryDelay: 500,
                        fragLoadingRetryDelay: 500,
                        fragLoadingMaxRetryTimeout: 20000,
                        liveSyncDurationCount: 4,
                        liveMaxLatencyDurationCount: 10,
                        maxLiveSyncPlaybackRate: 1.15,
                    },
                    dash: {
                        stableBufferTime: 12,
                        bufferTimeAtTopQuality: 20,
                        bufferToKeep: 12,
                    },
                    mpegts: {
                        enableStashBuffer: true,
                        stashInitialSize: 512 * 1024,
                        autoCleanupSourceBuffer: true,
                        autoCleanupMaxBackwardDuration: 60,
                        autoCleanupMinBackwardDuration: 30,
                    },
                },
                stable: {
                    hls: {
                        maxBufferLength: 90,
                        backBufferLength: 240,
                        maxBufferHole: 0.8,
                        maxSeekHole: 3,
                        manifestLoadingTimeOut: 25000,
                        levelLoadingTimeOut: 25000,
                        fragLoadingTimeOut: 30000,
                        manifestLoadingMaxRetry: 5,
                        levelLoadingMaxRetry: 5,
                        fragLoadingMaxRetry: 5,
                        manifestLoadingRetryDelay: 900,
                        levelLoadingRetryDelay: 900,
                        fragLoadingRetryDelay: 1000,
                        fragLoadingMaxRetryTimeout: 30000,
                        liveSyncDurationCount: 6,
                        liveMaxLatencyDurationCount: 14,
                        maxLiveSyncPlaybackRate: 1.25,
                    },
                    dash: {
                        stableBufferTime: 20,
                        bufferTimeAtTopQuality: 35,
                        bufferToKeep: 22,
                    },
                    mpegts: {
                        enableStashBuffer: true,
                        stashInitialSize: 1536 * 1024,
                        autoCleanupSourceBuffer: true,
                        autoCleanupMaxBackwardDuration: 120,
                        autoCleanupMinBackwardDuration: 60,
                    },
                },
            }

            return presets[profile] || presets.balanced
        },

        tryAdaptiveRecovery(reason = 'unknown') {
            if (!this.autoStability) {
                return false
            }

            const now = Date.now()
            if (now < (this.stabilityState.lastRecoveryAt + 3500)) {
                return false
            }

            this.stabilityState = {
                totalEvents: Number(this.stabilityState.totalEvents || 0) + 1,
                lastRecoveryAt: now,
            }

            if (
                this.autoStability
                && this.activeBufferProfile !== 'stable'
                && this.stabilityState.totalEvents >= 3
                && this.sourceUrl !== ''
                && (now - Number(this.lastRuntimeBufferEscalationAt || 0)) > 120000
            ) {
                this.runtimeBufferProfileOverride = 'stable'
                this.runtimeBufferEscalationCount = Number(this.runtimeBufferEscalationCount || 0) + 1
                this.lastRuntimeBufferEscalationAt = now
                this.emitDiagnostics({
                    recoveryReason: reason,
                    recoveryAction: 'escalate-buffer-profile:stable',
                })
                this.setStatus('loading', 'Сильная нестабильность, усиливаем буферизацию и перезапускаем поток...')
                this.applySource()
                return true
            }

            const downgraded = this.tryDowngradeHlsLevel() || this.tryDowngradeDashQuality()
            if (downgraded) {
                this.setStatus('buffering', 'Сеть нестабильна, снижаем качество для непрерывного воспроизведения...')
                this.emitDiagnostics({
                    recoveryReason: reason,
                    recoveryAction: 'downgrade',
                })
                return true
            }

            if (this.hls) {
                this.hls.startLoad(-1)
                this.emitDiagnostics({
                    recoveryReason: reason,
                    recoveryAction: 'reload-hls',
                })
                return true
            }

            return false
        },

        schedulePlaybackWatchdog() {
            this.clearPlaybackWatchdog()

            if (typeof window === 'undefined') {
                return
            }

            const activeProfile = this.normalizeBufferProfile(this.activeBufferProfile || this.resolveBufferingProfile(), 'balanced')
            let timeoutMs = this.activeEngine === 'native-hls' ? 10000 : 18000

            if (activeProfile === 'fast') {
                timeoutMs = Math.max(8000, timeoutMs - 2000)
            } else if (activeProfile === 'stable') {
                timeoutMs += this.activeEngine === 'native-hls' ? 6000 : 11000
            }

            if (this.activeEngine === 'mpegts.js') {
                timeoutMs += activeProfile === 'stable' ? 5000 : 1500
            }

            this.playbackWatchdogTimer = window.setTimeout(() => {
                this.handlePlaybackWatchdog()
            }, timeoutMs)
        },

        clearPlaybackWatchdog() {
            if (typeof window === 'undefined') {
                return
            }

            if (this.playbackWatchdogTimer !== null) {
                window.clearTimeout(this.playbackWatchdogTimer)
                this.playbackWatchdogTimer = null
            }
        },

        resetVideoFrameMonitor() {
            this.clearVideoFrameMonitor()
            this.videoFrameMonitorState = {
                lastFrameCount: -1,
                stagnantTicks: 0,
                lastPlaybackTime: 0,
            }
        },

        clearVideoFrameMonitor() {
            if (typeof window === 'undefined') {
                return
            }

            if (this.videoFrameMonitorTimer !== null) {
                window.clearTimeout(this.videoFrameMonitorTimer)
                this.videoFrameMonitorTimer = null
            }
        },

        scheduleVideoFrameMonitor(delayMs = 3500) {
            if (typeof window === 'undefined') {
                return
            }

            this.clearVideoFrameMonitor()
            const timeout = Number.isFinite(Number(delayMs)) ? Math.max(1200, Number(delayMs)) : 3500
            this.videoFrameMonitorTimer = window.setTimeout(() => {
                this.runVideoFrameMonitor()
            }, timeout)
        },

        runVideoFrameMonitor() {
            this.videoFrameMonitorTimer = null

            const video = this.getVideoElement()
            if (!video || this.playerState === 'error' || this.sourceUrl === '') {
                return
            }

            const frameCount = this.readRenderedFrameCount(video)
            if (frameCount === null) {
                return
            }

            const currentTime = Number(video.currentTime || 0)
            const canEvaluate = !video.paused && currentTime >= 4
            const hasVisibleVideoSignal = Number(video.videoWidth || 0) > 0 && Number(video.videoHeight || 0) > 0

            if (!canEvaluate) {
                this.scheduleVideoFrameMonitor()
                return
            }

            if (frameCount <= 0 && hasVisibleVideoSignal) {
                // Some browsers report unstable frame counters for MSE streams.
                // When actual video dimensions are present, avoid false "no frames" alerts.
                this.videoFrameMonitorState = {
                    lastFrameCount: frameCount,
                    stagnantTicks: 0,
                    lastPlaybackTime: currentTime,
                }
                this.scheduleVideoFrameMonitor(4500)
                return
            }

            if (!this.hasLikelyVideoCodec() && frameCount <= 0) {
                this.scheduleVideoFrameMonitor()
                return
            }

            let stagnantTicks = Number(this.videoFrameMonitorState.stagnantTicks || 0)
            const previousFrameCount = Number(this.videoFrameMonitorState.lastFrameCount || 0)
            const previousPlaybackTime = Number(this.videoFrameMonitorState.lastPlaybackTime || 0)
            const playbackAdvanced = currentTime > (previousPlaybackTime + 1.4)

            if (
                hasVisibleVideoSignal
                && frameCount > 0
                && previousFrameCount > 0
                && frameCount === previousFrameCount
                && playbackAdvanced
            ) {
                // Browser counter may be stale while video is actually rendering.
                this.videoFrameMonitorState = {
                    lastFrameCount: frameCount,
                    stagnantTicks: 0,
                    lastPlaybackTime: currentTime,
                }
                this.emitDiagnostics({
                    frameCounterUnreliable: true,
                })
                this.scheduleVideoFrameMonitor(4500)
                return
            }

            if (frameCount <= 0 || (previousFrameCount >= 0 && frameCount <= previousFrameCount)) {
                stagnantTicks += 1
            } else {
                stagnantTicks = 0
            }

            this.videoFrameMonitorState = {
                lastFrameCount: frameCount,
                stagnantTicks,
                lastPlaybackTime: currentTime,
            }

            if (stagnantTicks >= 3) {
                this.setError(
                    'Идет звук, но видеокадры не отображаются. Попробуйте совместимый режим FFmpeg или другой канал.',
                    {
                        type: 'video-frame-timeout',
                        details: `frames=${frameCount}; time=${currentTime.toFixed(1)}`,
                    }
                )
                return
            }

            this.scheduleVideoFrameMonitor()
        },

        readRenderedFrameCount(video) {
            if (!video) {
                return null
            }

            const counters = []

            if (typeof video.getVideoPlaybackQuality === 'function') {
                const quality = video.getVideoPlaybackQuality()
                const totalFrames = Number(quality?.totalVideoFrames)
                if (Number.isFinite(totalFrames) && totalFrames >= 0) {
                    counters.push(totalFrames)
                }
            }

            const webkitDecodedFrames = Number(video.webkitDecodedFrameCount)
            if (Number.isFinite(webkitDecodedFrames) && webkitDecodedFrames >= 0) {
                counters.push(webkitDecodedFrames)
            }

            if (counters.length === 0) {
                return null
            }

            return Math.max(...counters)
        },

        hasLikelyVideoCodec() {
            if (!Array.isArray(this.streamCodecs) || this.streamCodecs.length === 0) {
                return true
            }

            return this.streamCodecs.some((codec) => /(^|[^a-z])(avc|h26|hvc|hev|vp9|av01|mpeg2|mpeg-2|mp4v|theora)/i.test(String(codec)))
        },

        async handlePlaybackWatchdog() {
            this.playbackWatchdogTimer = null

            if (this.playerState !== 'loading' && this.playerState !== 'buffering') {
                return
            }

            const bufferedAheadSeconds = this.getBufferedAheadSeconds(this.getVideoElement())
            if (bufferedAheadSeconds >= 2.4 && this.watchdogRecoveryAttempts < 2) {
                this.setStatus('buffering', 'Источник медленный, ожидаем заполнение буфера...')
                this.emitDiagnostics({
                    recoveryReason: 'watchdog-timeout',
                    recoveryAction: 'wait-for-buffer',
                    watchdogBufferedAhead: bufferedAheadSeconds,
                })
                return
            }

            const recovered = this.tryAdaptiveRecovery('watchdog-timeout')
            if (recovered && this.watchdogRecoveryAttempts < 2) {
                this.watchdogRecoveryAttempts += 1
                this.setStatus('buffering', 'Нестабильный канал, пробуем восстановить поток...')
                return
            }

            if (this.watchdogRecoveryAttempts < 3) {
                this.watchdogRecoveryAttempts += 1
                this.setStatus('loading', 'Долгая буферизация, перезапускаем поток...')
                await this.applySource()
                return
            }

            this.setError(
                'Поток завис в буферизации. Проверьте источник, VPN/прокси и CORS, либо включите совместимый режим FFmpeg.',
                { type: 'watchdog-timeout' }
            )
        },

        tryDowngradeHlsLevel() {
            if (!this.hls) {
                return false
            }

            let currentLevel = Number(this.hls.currentLevel)
            if (!Number.isFinite(currentLevel) || currentLevel < 0) {
                currentLevel = Number(this.hls.nextAutoLevel)
            }
            if (!Number.isFinite(currentLevel) || currentLevel < 0) {
                currentLevel = Number(this.hls.loadLevel)
            }

            if (!Number.isFinite(currentLevel) || currentLevel <= 0) {
                return false
            }

            const targetLevel = Math.max(Number(this.hlsMinVideoLevel || 0), currentLevel - 1)
            if (typeof this.hls.nextLoadLevel === 'number') {
                this.hls.nextLoadLevel = targetLevel
            }
            if (typeof this.hls.currentLevel === 'number' && this.hls.currentLevel > targetLevel) {
                this.hls.currentLevel = targetLevel
            }

            return true
        },

        tryDowngradeDashQuality() {
            if (!this.dashPlayer) {
                return false
            }

            if (typeof this.dashPlayer.getQualityFor !== 'function' || typeof this.dashPlayer.setQualityFor !== 'function') {
                return false
            }

            const currentVideoQuality = Number(this.dashPlayer.getQualityFor('video'))
            if (!Number.isFinite(currentVideoQuality) || currentVideoQuality <= 0) {
                return false
            }

            this.dashPlayer.setQualityFor('video', Math.max(0, currentVideoQuality - 1), true)
            return true
        },

        syncQualityOptions(levels) {
            if (!Array.isArray(levels) || levels.length === 0) {
                this.qualityOptions = []
                this.$emit('qualities-change', [])
                return
            }

            const options = levels.map((level, index) => {
                const height = Number(level?.height || 0)
                const bitrate = Number(level?.bitrate || 0)
                const labelParts = []

                if (height > 0) {
                    labelParts.push(`${height}p`)
                }
                if (bitrate > 0) {
                    labelParts.push(`${Math.round(bitrate / 1000)} kbps`)
                }

                return {
                    value: index,
                    label: labelParts.length > 0 ? labelParts.join(' · ') : `Поток ${index + 1}`,
                }
            })

            this.qualityOptions = options
            this.$emit('qualities-change', options)
        },

        applySelectedQuality() {
            if (!this.hls) {
                return
            }

            const quality = Number(this.selectedQuality)
            if (!Number.isFinite(quality) || quality < 0) {
                this.hls.currentLevel = -1
                return
            }

            if (quality < this.hls.levels.length) {
                this.hls.currentLevel = quality
            }
        },

        tryAutoplay(video) {
            if (!this.autoplay) {
                return
            }

            video.play().catch(() => {
                this.setStatus('ready', 'Нажмите кнопку Play для запуска канала.')
            })
        },

        isMixedContentBlocked(url) {
            if (typeof window === 'undefined') {
                return false
            }

            const pageProtocol = String(window.location?.protocol || '')
            return pageProtocol === 'https:' && String(url).toLowerCase().startsWith('http://')
        },

        isHlsSource(url) {
            const normalized = String(url || '').toLowerCase()
            return normalized.includes('.m3u8')
                || normalized.includes('format=m3u8')
                || normalized.includes('playlist.m3u8')
        },

        isDashSource(url) {
            const normalized = String(url || '').toLowerCase()
            return normalized.includes('.mpd')
                || normalized.includes('format=mpd')
                || normalized.includes('manifest.mpd')
        },

        isFlvSource(url) {
            const normalized = String(url || '').toLowerCase()
            return normalized.includes('.flv') || normalized.includes('format=flv')
        },

        isMpegTsSource(url) {
            const normalized = String(url || '').toLowerCase()
            return normalized.includes('.ts')
                || normalized.includes('.m2ts')
                || normalized.includes('.mts')
                || normalized.includes('format=ts')
                || normalized.includes('mpegts')
        },

        detectSourceType(url) {
            if (this.isHlsSource(url)) {
                return 'hls'
            }

            if (this.isDashSource(url)) {
                return 'dash'
            }

            if (this.isFlvSource(url)) {
                return 'flv'
            }

            if (this.isMpegTsSource(url)) {
                return 'mpegts'
            }

            return 'native'
        },

        canPlayNativeHls(video) {
            const element = video || this.getVideoElement()
            if (!element || typeof element.canPlayType !== 'function') {
                return false
            }

            return element.canPlayType('application/vnd.apple.mpegurl') !== ''
                || element.canPlayType('application/x-mpegurl') !== ''
        },

        async loadDashLibrary() {
            if (this.dashLibrary) {
                return this.dashLibrary
            }

            if (this.dashLibraryPromise) {
                return this.dashLibraryPromise
            }

            this.dashLibraryPromise = import('dashjs')
                .then((module) => {
                    const normalized = module?.default || module
                    this.dashLibrary = normalized
                    this.emitDiagnostics()
                    return normalized
                })
                .catch((error) => {
                    this.dashLibraryPromise = null
                    throw error
                })

            return this.dashLibraryPromise
        },

        async loadMpegtsLibrary() {
            if (this.mpegtsLibrary) {
                return this.mpegtsLibrary
            }

            if (this.mpegtsLibraryPromise) {
                return this.mpegtsLibraryPromise
            }

            this.mpegtsLibraryPromise = import('mpegts.js')
                .then((module) => {
                    const normalized = module?.default || module
                    this.mpegtsLibrary = normalized
                    this.emitDiagnostics()
                    return normalized
                })
                .catch((error) => {
                    this.mpegtsLibraryPromise = null
                    throw error
                })

            return this.mpegtsLibraryPromise
        },

        canUseDashJs(dashLibrary = this.dashLibrary) {
            try {
                return Boolean(
                    dashLibrary
                    && typeof dashLibrary.supportsMediaSource === 'function'
                    && dashLibrary.supportsMediaSource()
                )
            } catch (_error) {
                return false
            }
        },

        canUseMpegtsJs(mpegtsLibrary = this.mpegtsLibrary) {
            try {
                if (!mpegtsLibrary || typeof mpegtsLibrary.isSupported !== 'function' || !mpegtsLibrary.isSupported()) {
                    return false
                }

                const featureList = this.getMpegtsFeatureList(mpegtsLibrary)
                return Boolean(featureList?.msePlayback || featureList?.mseLivePlayback)
            } catch (_error) {
                return false
            }
        },

        getMpegtsFeatureList(mpegtsLibrary = this.mpegtsLibrary) {
            try {
                if (!mpegtsLibrary || typeof mpegtsLibrary.getFeatureList !== 'function') {
                    return null
                }

                return mpegtsLibrary.getFeatureList()
            } catch (_error) {
                return null
            }
        },

        mediaSourceSupports(mimeType) {
            if (typeof window === 'undefined' || !window.MediaSource || typeof window.MediaSource.isTypeSupported !== 'function') {
                return false
            }

            return window.MediaSource.isTypeSupported(mimeType)
        },

        canPlayCodec(video, mimeType) {
            const element = video || this.getVideoElement()
            if (!element || typeof element.canPlayType !== 'function') {
                return false
            }

            return element.canPlayType(mimeType) !== '' || this.mediaSourceSupports(mimeType)
        },

        emitDiagnostics(extra = {}) {
            const video = this.getVideoElement()
            const mpegtsFeatures = this.getMpegtsFeatureList()
            const bufferedAheadSeconds = this.getBufferedAheadSeconds(video)
            const diagnostics = {
                engine: this.activeEngine,
                sourceType: this.detectSourceType(this.sourceUrl),
                streamCodecs: [...this.streamCodecs],
                requestedBufferingMode: this.bufferingMode,
                activeBufferProfile: this.activeBufferProfile,
                runtimeBufferProfileOverride: this.runtimeBufferProfileOverride || null,
                runtimeBufferEscalationCount: Number(this.runtimeBufferEscalationCount || 0),
                bufferedAheadSeconds,
                autoStability: Boolean(this.autoStability),
                modules: {
                    hlsjs: Hls.isSupported(),
                    nativeHls: this.canPlayNativeHls(video),
                    dashjs: this.canUseDashJs(),
                    mpegtsjs: this.canUseMpegtsJs(),
                    dashjsLoaded: Boolean(this.dashLibrary),
                    mpegtsjsLoaded: Boolean(this.mpegtsLibrary),
                },
                codecs: {
                    h264: this.canPlayCodec(video, 'video/mp4; codecs="avc1.42E01E,mp4a.40.2"'),
                    h265: this.canPlayCodec(video, 'video/mp4; codecs="hvc1.1.6.L93.B0,mp4a.40.2"')
                        || this.canPlayCodec(video, 'video/mp4; codecs="hev1.1.6.L93.B0,mp4a.40.2"'),
                    av1: this.canPlayCodec(video, 'video/mp4; codecs="av01.0.08M.08,mp4a.40.2"'),
                    vp9: this.canPlayCodec(video, 'video/webm; codecs="vp9"'),
                    aac: this.canPlayCodec(video, 'audio/mp4; codecs="mp4a.40.2"'),
                    ac3: this.canPlayCodec(video, 'audio/mp4; codecs="ac-3"'),
                    mp3: this.canPlayCodec(video, 'audio/mpeg'),
                },
                mpegtsFeatures: {
                    msePlayback: Boolean(mpegtsFeatures?.msePlayback),
                    mseLivePlayback: Boolean(mpegtsFeatures?.mseLivePlayback),
                    mseH265Playback: Boolean(mpegtsFeatures?.mseH265Playback),
                },
                stability: {
                    totalEvents: Number(this.stabilityState.totalEvents || 0),
                    lastRecoveryAt: Number(this.stabilityState.lastRecoveryAt || 0),
                },
                ...extra,
            }

            this.$emit('diagnostics-change', diagnostics)
        },

        normalizeVolume(value) {
            const numeric = Number(value)
            if (!Number.isFinite(numeric)) {
                return 1
            }

            return Math.min(1, Math.max(0, numeric))
        },

        normalizeBufferProfile(profile, fallback = 'balanced') {
            const normalized = String(profile || '').toLowerCase()
            if (['fast', 'balanced', 'stable'].includes(normalized)) {
                return normalized
            }

            const fallbackRaw = String(fallback ?? '')
            if (fallbackRaw === '') {
                return ''
            }

            const fallbackNormalized = fallbackRaw.toLowerCase()
            if (['fast', 'balanced', 'stable'].includes(fallbackNormalized)) {
                return fallbackNormalized
            }

            return 'balanced'
        },

        getBufferedAheadSeconds(video) {
            const element = video || this.getVideoElement()
            if (!element || !element.buffered) {
                return 0
            }

            const currentTime = Number(element.currentTime || 0)
            const ranges = element.buffered

            for (let index = 0; index < ranges.length; index += 1) {
                const start = Number(ranges.start(index))
                const end = Number(ranges.end(index))

                if (currentTime >= (start - 0.05) && currentTime <= end) {
                    const ahead = Math.max(0, end - currentTime)
                    return Number(ahead.toFixed(2))
                }
            }

            return 0
        },

        getVideoElement() {
            return this.$refs.videoElement || null
        },

        clearVideoElementSource() {
            const video = this.getVideoElement()
            if (!video) {
                return
            }

            try {
                video.pause()
            } catch (_error) {
                // ignore pause errors
            }

            try {
                video.currentTime = 0
            } catch (_error) {
                // ignore seek errors
            }

            if ('srcObject' in video) {
                try {
                    video.srcObject = null
                } catch (_error) {
                    // ignore srcObject errors
                }
            }

            video.removeAttribute('src')
            video.querySelectorAll('source').forEach((sourceNode) => sourceNode.remove())

            try {
                video.load()
            } catch (_error) {
                // ignore load errors
            }
        },

        getFullscreenTarget() {
            return this.$refs.playerShell || this.getVideoElement()
        },

        togglePlayback() {
            const video = this.getVideoElement()
            if (!video) {
                return
            }

            if (video.paused) {
                video.play().catch(() => {})
                return
            }

            video.pause()
        },

        toggleMute() {
            const video = this.getVideoElement()
            if (!video) {
                return
            }

            video.muted = !video.muted
            this.handleVolumeChange()
        },

        toggleFullscreen() {
            const fullscreenTarget = this.getFullscreenTarget()
            if (!fullscreenTarget) {
                return
            }

            if (this.isFullscreenActive()) {
                this.exitFullscreen()
                if (typeof window !== 'undefined') {
                    window.setTimeout(() => this.normalizeFullscreenClasses(), 140)
                }
                return
            }

            this.requestFullscreen(fullscreenTarget)
        },

        exitPictureInPicture() {
            const video = this.getVideoElement()

            if (typeof document !== 'undefined' && document.pictureInPictureElement && typeof document.exitPictureInPicture === 'function') {
                const promise = document.exitPictureInPicture()
                if (promise && typeof promise.catch === 'function') {
                    promise.catch(() => {})
                }
            }

            if (video && typeof video.webkitSetPresentationMode === 'function') {
                try {
                    if (video.webkitPresentationMode === 'picture-in-picture') {
                        video.webkitSetPresentationMode('inline')
                    }
                } catch (_error) {
                    // ignore picture-in-picture exit errors
                }
            }
        },

        isFullscreenActive() {
            const video = this.getVideoElement()
            return Boolean(document.fullscreenElement || document.webkitFullscreenElement || video?.webkitDisplayingFullscreen)
        },

        requestFullscreen(target) {
            if (!target) {
                return false
            }

            const requestMethod = target.requestFullscreen || target.webkitRequestFullscreen || target.webkitRequestFullScreen
            if (typeof requestMethod === 'function') {
                const promise = requestMethod.call(target)
                if (promise && typeof promise.catch === 'function') {
                    promise.catch(() => {})
                }
                return true
            }

            const video = this.getVideoElement()
            if (video && typeof video.webkitEnterFullscreen === 'function') {
                try {
                    video.webkitEnterFullscreen()
                    return true
                } catch (_error) {
                    return false
                }
            }

            return false
        },

        exitFullscreen() {
            const exitMethod = document.exitFullscreen || document.webkitExitFullscreen
            if (typeof exitMethod === 'function') {
                const promise = exitMethod.call(document)
                if (promise && typeof promise.catch === 'function') {
                    promise.catch(() => {})
                }
                return true
            }

            const video = this.getVideoElement()
            if (video && typeof video.webkitExitFullscreen === 'function') {
                try {
                    video.webkitExitFullscreen()
                    return true
                } catch (_error) {
                    return false
                }
            }

            return false
        },

        bindFullscreenEscapeHandler() {
            if (typeof document === 'undefined') {
                return
            }

            this.unbindFullscreenEscapeHandler()
            this.fullscreenEscapeHandler = (event) => {
                const key = String(event?.key || '').toLowerCase()
                if (key !== 'escape' && key !== 'esc') {
                    return
                }

                if (!this.isFullscreenActive()) {
                    return
                }

                event.preventDefault()
                this.exitFullscreen()
            }

            document.addEventListener('keydown', this.fullscreenEscapeHandler, true)
        },

        unbindFullscreenEscapeHandler() {
            if (typeof document === 'undefined' || !this.fullscreenEscapeHandler) {
                return
            }

            document.removeEventListener('keydown', this.fullscreenEscapeHandler, true)
            this.fullscreenEscapeHandler = null
        },

        bindFullscreenStateListeners() {
            if (typeof document === 'undefined') {
                return
            }

            this.unbindFullscreenStateListeners()
            this.fullscreenStateHandler = () => {
                this.normalizeFullscreenClasses()
            }

            document.addEventListener('fullscreenchange', this.fullscreenStateHandler, true)
            document.addEventListener('webkitfullscreenchange', this.fullscreenStateHandler, true)
        },

        unbindFullscreenStateListeners() {
            if (typeof document === 'undefined' || !this.fullscreenStateHandler) {
                return
            }

            document.removeEventListener('fullscreenchange', this.fullscreenStateHandler, true)
            document.removeEventListener('webkitfullscreenchange', this.fullscreenStateHandler, true)
            this.fullscreenStateHandler = null
        },

        normalizeFullscreenClasses() {
            if (this.isFullscreenActive()) {
                return
            }

            const shell = this.$refs.playerShell
            if (!(shell instanceof HTMLElement)) {
                return
            }

            shell.classList.remove('plyr--fullscreen-fallback')

            const plyrNode = shell.querySelector('.plyr')
            if (plyrNode instanceof HTMLElement) {
                plyrNode.classList.remove('plyr--fullscreen-fallback')
            }
        },

        guessMimeType(url) {
            const normalized = String(url || '').toLowerCase()

            if (normalized.endsWith('.mp4')) {
                return 'video/mp4'
            }
            if (normalized.endsWith('.webm')) {
                return 'video/webm'
            }
            if (normalized.endsWith('.mov')) {
                return 'video/quicktime'
            }
            if (normalized.endsWith('.m4v')) {
                return 'video/x-m4v'
            }
            if (normalized.endsWith('.mpd')) {
                return 'application/dash+xml'
            }
            if (normalized.endsWith('.flv')) {
                return 'video/x-flv'
            }
            if (normalized.endsWith('.ts') || normalized.endsWith('.m2ts') || normalized.endsWith('.mts')) {
                return 'video/mp2t'
            }

            return ''
        },
    },
}
</script>
