<template>
    <component :is="as" class="sticker-rich-text">
        <template v-for="(segment, index) in segments" :key="`sticker-segment-${index}`">
            <span v-if="segment.type === 'text'">{{ segment.value }}</span>
            <img
                v-else
                :src="segment.sticker.src"
                :alt="segmentLabel(segment.sticker)"
                :title="segmentLabel(segment.sticker)"
                class="sticker-rich-text__img"
                loading="lazy"
                decoding="async"
            >
        </template>
    </component>
</template>

<script>
import {
    localizedStickerLabel,
    parseStickerTextSegments,
} from '../../data/stickerCatalog'

export default {
    name: 'StickerRichText',

    props: {
        as: {
            type: String,
            default: 'span',
        },
        text: {
            type: String,
            default: '',
        },
    },

    computed: {
        segments() {
            return parseStickerTextSegments(this.text)
        },

        currentLocale() {
            return this.$i18n?.locale || 'ru'
        },
    },

    methods: {
        segmentLabel(sticker) {
            return localizedStickerLabel(sticker, this.currentLocale)
        },
    },
}
</script>
