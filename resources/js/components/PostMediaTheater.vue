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
                        @exitfullscreen="handlePlayerExitFullscreen"
                    ></MediaPlayer>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script>
import { nextTick } from 'vue'
import MediaPlayer from './MediaPlayer.vue'

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
    },

    methods: {
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

            await nextTick()
            if (preferFullscreen) {
                await this.$refs.player?.enterFullscreen?.()
            }
            await this.$refs.player?.play?.()
        },

        async close() {
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
        },

        onWindowKeyDown(event) {
            if (event.key === 'Escape' && this.isOpen) {
                this.close()
            }
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
    },
}
</script>
