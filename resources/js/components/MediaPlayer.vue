<template>
    <div
        ref="playerShell"
        :class="shellClasses"
        data-media-player-shell
    >
        <component
            :is="tagName"
            ref="mediaElement"
            :class="playerClass"
            :preload="preload"
            :autoplay="autoplay"
            :loop="loop"
            :muted="muted"
            :playsinline="isVideo ? true : null"
        ></component>
    </div>
</template>

<script>
import { nextTick } from 'vue'
import Plyr from 'plyr'
import 'plyr/dist/plyr.css'

export default {
    name: 'MediaPlayer',

    emits: ['enterfullscreen', 'exitfullscreen', 'playbackstate', 'playererror', 'loadedmetadata'],

    props: {
        src: {
            type: String,
            default: '',
        },
        type: {
            type: String,
            default: 'video',
            validator(value) {
                return value === 'video' || value === 'audio'
            },
        },
        mimeType: {
            type: String,
            default: '',
        },
        preload: {
            type: String,
            default: 'metadata',
        },
        autoplay: {
            type: Boolean,
            default: false,
        },
        loop: {
            type: Boolean,
            default: false,
        },
        muted: {
            type: Boolean,
            default: false,
        },
        playerClass: {
            type: String,
            default: '',
        },
        shellClass: {
            type: String,
            default: '',
        },
    },

    data() {
        return {
            player: null,
            playerContainerElement: null,
            videoWrapperElement: null,
            overlayPlayButtonElement: null,
            pendingSurfaceClickTimer: null,
            boundMediaElement: null,
        }
    },

    computed: {
        isVideo() {
            return this.type === 'video'
        },

        tagName() {
            return this.isVideo ? 'video' : 'audio'
        },

        shellClasses() {
            return [
                'media-player-shell',
                this.isVideo ? 'media-player-shell--video' : 'media-player-shell--audio',
                this.shellClass,
            ]
        },
    },

    mounted() {
        this.initPlayer()
    },

    beforeUnmount() {
        this.destroyPlayer()
    },

    watch: {
        src() {
            this.updateSource()
        },

        mimeType() {
            this.updateSource()
        },

        async type() {
            this.destroyPlayer()
            await nextTick()
            this.initPlayer()
        },
    },

    methods: {
        initPlayer() {
            const element = this.$refs.mediaElement
            if (!element) {
                return
            }

            this.player = new Plyr(element, {
                controls: this.isVideo
                    ? ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'settings', 'fullscreen']
                    : ['play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'settings'],
                settings: ['speed'],
                clickToPlay: !this.isVideo,
                fullscreen: {
                    enabled: this.isVideo,
                    fallback: true,
                    iosNative: false,
                },
                keyboard: {
                    focused: true,
                    global: false,
                },
            })

            this.updateSource()
            this.bindInteractiveElements()
            this.bindMediaElementEvents()
        },

        destroyPlayer() {
            this.clearPendingSurfaceClick()
            this.unbindInteractiveElements()
            this.unbindMediaElementEvents()

            if (!this.player) {
                return
            }

            this.player.destroy()
            this.player = null
        },

        updateSource() {
            if (!this.player) {
                return
            }

            const trimmedSrc = String(this.src || '').trim()
            if (trimmedSrc === '') {
                this.player.source = {
                    type: this.type,
                    sources: [],
                }
                return
            }

            const explicitMime = String(this.mimeType || '').trim()
            const guessedMime = explicitMime !== '' ? explicitMime : this.guessMimeType(trimmedSrc)
            const source = {
                src: trimmedSrc,
            }

            if (guessedMime !== '') {
                source.type = guessedMime
            }

            this.player.source = {
                type: this.type,
                sources: [source],
            }

            nextTick(() => {
                this.bindInteractiveElements()
                this.bindMediaElementEvents()
            })
        },

        async play() {
            if (!this.player || typeof this.player.play !== 'function') {
                return false
            }

            for (let attempt = 0; attempt < 3; attempt += 1) {
                try {
                    const playResult = this.player.play()
                    if (playResult && typeof playResult.then === 'function') {
                        await playResult
                    }

                    return true
                } catch (error) {
                    if (attempt === 2) {
                        return false
                    }

                    await new Promise((resolve) => setTimeout(resolve, 120))
                }
            }

            return false
        },

        pause() {
            if (!this.player || typeof this.player.pause !== 'function') {
                return
            }

            this.player.pause()
        },

        async togglePlayback() {
            const mediaElement = this.$refs.mediaElement
            if (!mediaElement) {
                return false
            }

            if (!mediaElement.paused && !mediaElement.ended) {
                this.pause()
                return true
            }

            if (mediaElement.ended) {
                mediaElement.currentTime = 0
            }

            return this.play()
        },

        toggleFullscreen() {
            if (!this.isVideo || !this.player?.fullscreen || typeof this.player.fullscreen.toggle !== 'function') {
                return
            }

            try {
                this.player.fullscreen.toggle()
            } catch (_error) {
                // Ignore fullscreen toggle errors.
            }
        },

        async enterFullscreen() {
            if (!this.isVideo || !this.player?.fullscreen || typeof this.player.fullscreen.enter !== 'function') {
                return false
            }

            try {
                const result = this.player.fullscreen.enter()
                if (result && typeof result.then === 'function') {
                    await result
                }

                return true
            } catch (_error) {
                return false
            }
        },

        async exitFullscreen() {
            if (!this.isVideo || !this.player?.fullscreen || typeof this.player.fullscreen.exit !== 'function') {
                return false
            }

            try {
                const result = this.player.fullscreen.exit()
                if (result && typeof result.then === 'function') {
                    await result
                }

                return true
            } catch (_error) {
                return false
            }
        },

        isFullscreen() {
            return Boolean(this.player?.fullscreen?.active)
        },

        clearPendingSurfaceClick() {
            if (this.pendingSurfaceClickTimer && typeof window !== 'undefined') {
                window.clearTimeout(this.pendingSurfaceClickTimer)
            }

            this.pendingSurfaceClickTimer = null
        },

        shouldIgnoreSurfaceInteraction(event) {
            const target = event?.target
            if (!(target instanceof HTMLElement)) {
                return true
            }

            return Boolean(
                target.closest('.plyr__controls')
                || target.closest('.plyr__menu')
                || target.closest('.plyr__captions')
                || target.closest('.plyr__progress')
                || target.closest('.plyr__volume')
                || target.closest('.plyr__control--overlaid')
                || target.closest('a')
                || target.closest('button')
                || target.closest('input')
            )
        },

        handleVideoSurfaceClick(event) {
            if (this.shouldIgnoreSurfaceInteraction(event)) {
                return
            }

            this.clearPendingSurfaceClick()

            if (typeof window === 'undefined') {
                void this.togglePlayback()
                return
            }

            this.pendingSurfaceClickTimer = window.setTimeout(() => {
                this.pendingSurfaceClickTimer = null
                void this.togglePlayback()
            }, 160)
        },

        handleVideoSurfaceDoubleClick(event) {
            if (this.shouldIgnoreSurfaceInteraction(event)) {
                return
            }

            event.preventDefault()
            this.clearPendingSurfaceClick()
            this.toggleFullscreen()
        },

        handleOverlayPlayClick(event) {
            event.preventDefault()
            event.stopPropagation()
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation()
            }

            this.clearPendingSurfaceClick()

            if (typeof window === 'undefined') {
                void this.togglePlayback()
                return
            }

            this.pendingSurfaceClickTimer = window.setTimeout(() => {
                this.pendingSurfaceClickTimer = null
                void this.togglePlayback()
            }, 160)
        },

        handleOverlayPlayDoubleClick(event) {
            event.preventDefault()
            event.stopPropagation()
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation()
            }

            this.clearPendingSurfaceClick()
            this.toggleFullscreen()
        },

        handlePlayerEnterFullscreen() {
            this.$emit('enterfullscreen')
        },

        handlePlayerExitFullscreen() {
            this.$emit('exitfullscreen')
        },

        emitPlaybackState(state, extra = {}) {
            const mediaElement = this.$refs.mediaElement
            if (!mediaElement) {
                return
            }

            this.$emit('playbackstate', {
                state,
                currentTime: Number(mediaElement.currentTime || 0),
                duration: Number(mediaElement.duration || 0),
                paused: Boolean(mediaElement.paused),
                ended: Boolean(mediaElement.ended),
                muted: Boolean(mediaElement.muted),
                volume: Number(mediaElement.volume || 0),
                ...extra,
            })
        },

        handleMediaPlay() {
            this.emitPlaybackState('play')
        },

        handleMediaPause() {
            this.emitPlaybackState('pause')
        },

        handleMediaEnded() {
            this.emitPlaybackState('ended')
        },

        handleMediaTimeUpdate() {
            this.emitPlaybackState('timeupdate')
        },

        handleMediaLoadedMetadata() {
            const mediaElement = this.$refs.mediaElement
            this.$emit('loadedmetadata', {
                duration: Number(mediaElement?.duration || 0),
                width: Number(mediaElement?.videoWidth || 0),
                height: Number(mediaElement?.videoHeight || 0),
            })
            this.emitPlaybackState('loadedmetadata')
        },

        handleMediaError() {
            const mediaElement = this.$refs.mediaElement
            const mediaError = mediaElement?.error

            this.$emit('playererror', {
                code: Number(mediaError?.code || 0),
                message: String(mediaError?.message || '').trim(),
            })
        },

        bindMediaElementEvents() {
            this.unbindMediaElementEvents()

            const mediaElement = this.$refs.mediaElement
            if (!(mediaElement instanceof HTMLMediaElement)) {
                return
            }

            mediaElement.addEventListener('play', this.handleMediaPlay)
            mediaElement.addEventListener('pause', this.handleMediaPause)
            mediaElement.addEventListener('ended', this.handleMediaEnded)
            mediaElement.addEventListener('timeupdate', this.handleMediaTimeUpdate)
            mediaElement.addEventListener('loadedmetadata', this.handleMediaLoadedMetadata)
            mediaElement.addEventListener('error', this.handleMediaError)
            this.boundMediaElement = mediaElement
        },

        unbindMediaElementEvents() {
            if (!(this.boundMediaElement instanceof HTMLMediaElement)) {
                this.boundMediaElement = null
                return
            }

            this.boundMediaElement.removeEventListener('play', this.handleMediaPlay)
            this.boundMediaElement.removeEventListener('pause', this.handleMediaPause)
            this.boundMediaElement.removeEventListener('ended', this.handleMediaEnded)
            this.boundMediaElement.removeEventListener('timeupdate', this.handleMediaTimeUpdate)
            this.boundMediaElement.removeEventListener('loadedmetadata', this.handleMediaLoadedMetadata)
            this.boundMediaElement.removeEventListener('error', this.handleMediaError)
            this.boundMediaElement = null
        },

        bindInteractiveElements() {
            this.unbindInteractiveElements()

            if (!this.isVideo) {
                return
            }

            const shell = this.$refs.playerShell
            if (!(shell instanceof HTMLElement)) {
                return
            }

            const playerContainer = shell.querySelector('.plyr')
            if (playerContainer instanceof HTMLElement) {
                playerContainer.addEventListener('enterfullscreen', this.handlePlayerEnterFullscreen)
                playerContainer.addEventListener('exitfullscreen', this.handlePlayerExitFullscreen)
                this.playerContainerElement = playerContainer
            }

            const videoWrapper = shell.querySelector('.plyr__video-wrapper')
            if (videoWrapper instanceof HTMLElement) {
                videoWrapper.addEventListener('click', this.handleVideoSurfaceClick)
                videoWrapper.addEventListener('dblclick', this.handleVideoSurfaceDoubleClick)
                this.videoWrapperElement = videoWrapper
            }

            const overlayPlayButton = shell.querySelector('.plyr__control--overlaid')
            if (overlayPlayButton instanceof HTMLElement) {
                overlayPlayButton.addEventListener('click', this.handleOverlayPlayClick, true)
                overlayPlayButton.addEventListener('dblclick', this.handleOverlayPlayDoubleClick, true)
                this.overlayPlayButtonElement = overlayPlayButton
            }
        },

        unbindInteractiveElements() {
            if (this.playerContainerElement) {
                this.playerContainerElement.removeEventListener('enterfullscreen', this.handlePlayerEnterFullscreen)
                this.playerContainerElement.removeEventListener('exitfullscreen', this.handlePlayerExitFullscreen)
            }

            if (this.videoWrapperElement) {
                this.videoWrapperElement.removeEventListener('click', this.handleVideoSurfaceClick)
                this.videoWrapperElement.removeEventListener('dblclick', this.handleVideoSurfaceDoubleClick)
            }

            if (this.overlayPlayButtonElement) {
                this.overlayPlayButtonElement.removeEventListener('click', this.handleOverlayPlayClick, true)
                this.overlayPlayButtonElement.removeEventListener('dblclick', this.handleOverlayPlayDoubleClick, true)
            }

            this.playerContainerElement = null
            this.videoWrapperElement = null
            this.overlayPlayButtonElement = null
        },

        guessMimeType(src) {
            const normalized = String(src).toLowerCase()

            if (normalized.endsWith('.mp4')) {
                return 'video/mp4'
            }
            if (normalized.endsWith('.webm')) {
                return this.isVideo ? 'video/webm' : 'audio/webm'
            }
            if (normalized.endsWith('.mov')) {
                return 'video/quicktime'
            }
            if (normalized.endsWith('.m4v')) {
                return 'video/x-m4v'
            }
            if (normalized.endsWith('.mkv')) {
                return 'video/x-matroska'
            }
            if (normalized.endsWith('.mp3')) {
                return 'audio/mpeg'
            }
            if (normalized.endsWith('.ogg') || normalized.endsWith('.opus')) {
                return 'audio/ogg'
            }
            if (normalized.endsWith('.m4a')) {
                return 'audio/mp4'
            }
            if (normalized.endsWith('.wav')) {
                return 'audio/wav'
            }
            if (normalized.endsWith('.aac')) {
                return 'audio/aac'
            }

            return ''
        },
    },
}
</script>
