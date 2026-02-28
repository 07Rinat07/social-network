<template>
    <teleport to="body">
        <div
            v-if="isOpen && source"
            ref="theaterRoot"
            class="post-media-theater"
            @click.self="close"
        >
            <div class="post-media-theater__dialog" @click.stop>
                <div class="post-media-theater__toolbar">
                    <div class="post-media-theater__meta">
                        <strong class="post-media-theater__title">{{ titleText }}</strong>
                        <p class="post-media-theater__hint">{{ $t('common.doubleClickFullscreen') }}</p>
                    </div>

                    <div class="post-media-theater__actions">
                        <a
                            v-if="downloadHref"
                            :href="downloadHref"
                            :download="downloadName || null"
                            class="btn btn-outline btn-sm"
                            target="_blank"
                        >
                            {{ downloadLabelText }}
                        </a>

                        <button class="btn btn-danger btn-sm" type="button" @click="close">
                            {{ $t('common.close') }}
                        </button>
                    </div>
                </div>

                <div class="post-media-theater__viewer">
                    <MediaPlayer
                        ref="player"
                        type="video"
                        :src="source"
                        :mime-type="mimeType"
                        :autoplay="true"
                        player-class="media-video"
                        shell-class="media-player-shell--theater"
                        @enterfullscreen="handlePlayerEnterFullscreen"
                        @exitfullscreen="handlePlayerExitFullscreen"
                        @playbackstate="handlePlaybackState"
                    ></MediaPlayer>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script>
import { nextTick } from 'vue'
import MediaPlayer from './MediaPlayer.vue'
import { ANALYTICS_EVENTS, ANALYTICS_FEATURES, createAnalyticsSessionId, reportAnalyticsEvent } from '../utils/analyticsTracker.mjs'

export default {
    name: 'PostMediaTheater',

    components: {
        MediaPlayer,
    },

    data() {
        return {
            isOpen: false,
            source: '',
            mimeType: '',
            titleText: '',
            downloadHref: '',
            downloadName: '',
            downloadLabelText: '',
            hadBodyNoScrollBeforeOpen: false,
            suppressCloseOnFullscreenExit: false,
            analyticsSessionId: '',
            analyticsMediaId: null,
            analyticsPostId: null,
            analyticsWatchSeconds: 0,
            analyticsLastTime: 0,
            analyticsMaxTime: 0,
            analyticsDuration: 0,
            analyticsCompleted: false,
        }
    },

    watch: {
        isOpen(isOpen) {
            if (typeof document === 'undefined') {
                return
            }

            if (isOpen) {
                document.body.classList.add('no-scroll')
                return
            }

            if (!this.hadBodyNoScrollBeforeOpen) {
                document.body.classList.remove('no-scroll')
            }
        },
    },

    mounted() {
        if (typeof window !== 'undefined') {
            window.addEventListener('keydown', this.onWindowKeyDown)
        }
    },

    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('keydown', this.onWindowKeyDown)
        }

        if (typeof document !== 'undefined' && this.isOpen && !this.hadBodyNoScrollBeforeOpen) {
            document.body.classList.remove('no-scroll')
        }

        void this.finalizeAnalyticsSession()
    },

    methods: {
        resetAnalyticsSession() {
            this.analyticsSessionId = ''
            this.analyticsMediaId = null
            this.analyticsPostId = null
            this.analyticsWatchSeconds = 0
            this.analyticsLastTime = 0
            this.analyticsMaxTime = 0
            this.analyticsDuration = 0
            this.analyticsCompleted = false
        },

        async open(payload = {}) {
            const source = String(payload?.source || '').trim()
            if (source === '') {
                return
            }

            const preferFullscreen = payload?.preferFullscreen !== false

            if (typeof document !== 'undefined') {
                this.hadBodyNoScrollBeforeOpen = document.body.classList.contains('no-scroll')
            } else {
                this.hadBodyNoScrollBeforeOpen = false
            }

            this.source = source
            this.mimeType = String(payload?.mimeType || '').trim()
            this.titleText = String(payload?.title || this.$t('common.video')).trim() || this.$t('common.video')
            this.downloadHref = String(payload?.downloadHref || '').trim()
            this.downloadName = String(payload?.downloadName || '').trim()
            this.downloadLabelText = String(payload?.downloadLabel || '').trim() || this.$t('common.download')
            this.isOpen = true
            this.analyticsSessionId = createAnalyticsSessionId('video-theater')
            this.analyticsMediaId = Number.isInteger(Number(payload?.mediaId)) ? Number(payload.mediaId) : null
            this.analyticsPostId = Number.isInteger(Number(payload?.postId)) ? Number(payload.postId) : null
            this.analyticsWatchSeconds = 0
            this.analyticsLastTime = 0
            this.analyticsMaxTime = 0
            this.analyticsDuration = 0
            this.analyticsCompleted = false

            await reportAnalyticsEvent({
                feature: ANALYTICS_FEATURES.MEDIA,
                event_name: ANALYTICS_EVENTS.VIDEO_THEATER_OPEN,
                entity_type: 'post_media',
                entity_id: this.analyticsMediaId,
                entity_key: this.source,
                session_id: this.analyticsSessionId,
                context: {
                    post_id: this.analyticsPostId ?? 0,
                    title: this.titleText,
                },
            })

            await nextTick()
            if (preferFullscreen) {
                await this.$refs.player?.enterFullscreen?.()
            }
            await this.$refs.player?.play?.()
        },

        async close() {
            await this.finalizeAnalyticsSession()
            this.$refs.player?.pause?.()

            if (this.$refs.player?.isFullscreen?.()) {
                this.suppressCloseOnFullscreenExit = true
                await this.$refs.player?.exitFullscreen?.()
            }

            this.isOpen = false
            this.source = ''
            this.mimeType = ''
            this.titleText = ''
            this.downloadHref = ''
            this.downloadName = ''
            this.downloadLabelText = ''
            this.suppressCloseOnFullscreenExit = false
            this.resetAnalyticsSession()
        },

        onWindowKeyDown(event) {
            if (event.key === 'Escape' && this.isOpen) {
                this.close()
            }
        },

        handlePlaybackState(payload) {
            const state = String(payload?.state || '')
            const currentTime = Math.max(0, Number(payload?.currentTime || 0))
            const duration = Math.max(0, Number(payload?.duration || 0))

            if (duration > 0) {
                this.analyticsDuration = Math.max(this.analyticsDuration, duration)
            }

            if (currentTime >= this.analyticsLastTime && (currentTime - this.analyticsLastTime) <= 15) {
                this.analyticsWatchSeconds += Math.max(0, currentTime - this.analyticsLastTime)
            }

            this.analyticsLastTime = currentTime
            this.analyticsMaxTime = Math.max(this.analyticsMaxTime, currentTime)

            if (state === 'ended') {
                this.analyticsCompleted = true
                void this.finalizeAnalyticsSession()
            }
        },

        async handlePlayerEnterFullscreen() {
            await reportAnalyticsEvent({
                feature: ANALYTICS_FEATURES.MEDIA,
                event_name: ANALYTICS_EVENTS.VIDEO_FULLSCREEN_ENTER,
                entity_type: 'post_media',
                entity_id: this.analyticsMediaId,
                entity_key: this.source,
                session_id: this.analyticsSessionId || null,
                context: {
                    post_id: this.analyticsPostId ?? 0,
                    title: this.titleText,
                    player_scope: 'theater',
                },
            })
        },

        handlePlayerExitFullscreen() {
            if (!this.isOpen) {
                return
            }

            if (this.suppressCloseOnFullscreenExit) {
                this.suppressCloseOnFullscreenExit = false
                return
            }

            this.close()
        },

        async finalizeAnalyticsSession() {
            if (!this.analyticsSessionId) {
                return
            }

            const duration = Math.max(0, Number(this.analyticsDuration || 0))
            const maxTime = Math.max(0, Number(this.analyticsMaxTime || 0))
            const watchSeconds = Math.max(0, Math.round(this.analyticsWatchSeconds || 0))
            const completionPercent = duration > 0
                ? Math.min(100, Number(((maxTime / duration) * 100).toFixed(1)))
                : 0
            const completed = this.analyticsCompleted || (duration > 0 && maxTime >= duration * 0.95)
            const sessionId = this.analyticsSessionId

            this.analyticsSessionId = ''

            await reportAnalyticsEvent({
                feature: ANALYTICS_FEATURES.MEDIA,
                event_name: ANALYTICS_EVENTS.VIDEO_SESSION,
                entity_type: 'post_media',
                entity_id: this.analyticsMediaId,
                entity_key: this.source,
                session_id: sessionId,
                duration_seconds: watchSeconds,
                metric_value: completionPercent,
                context: {
                    completed,
                    theater_used: true,
                    post_id: this.analyticsPostId ?? 0,
                    title: this.titleText,
                    duration_seconds: duration,
                    watched_max_seconds: maxTime,
                },
            })
        },
    },
}
</script>
