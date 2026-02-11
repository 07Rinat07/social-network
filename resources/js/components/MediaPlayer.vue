<template>
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
</template>

<script>
import { nextTick } from 'vue'
import Plyr from 'plyr'
import 'plyr/dist/plyr.css'

export default {
    name: 'MediaPlayer',

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
    },

    data() {
        return {
            player: null,
        }
    },

    computed: {
        isVideo() {
            return this.type === 'video'
        },

        tagName() {
            return this.isVideo ? 'video' : 'audio'
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
            })

            this.updateSource()
        },

        destroyPlayer() {
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
