<template>
    <teleport to="body">
        <div v-if="isOpen && source" class="media-lightbox" @click.self="close">
            <button class="btn btn-danger media-lightbox-close" type="button" @click="close">Закрыть</button>
            <img class="media-lightbox-image" :src="source" :alt="altText">
        </div>
    </teleport>
</template>

<script>
export default {
    name: 'MediaLightbox',

    data() {
        return {
            isOpen: false,
            source: '',
            altText: 'media',
        }
    },

    mounted() {
        window.addEventListener('keydown', this.onWindowKeyDown)
    },

    beforeUnmount() {
        window.removeEventListener('keydown', this.onWindowKeyDown)
    },

    methods: {
        open(source, altText = 'media') {
            if (!source) {
                return
            }

            this.source = source
            this.altText = altText || 'media'
            this.isOpen = true
        },

        close() {
            this.isOpen = false
            this.source = ''
        },

        onWindowKeyDown(event) {
            if (event.key === 'Escape' && this.isOpen) {
                this.close()
            }
        },
    },
}
</script>
