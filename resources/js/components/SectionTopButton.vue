<template>
    <transition name="section-top-btn-fade">
        <div
            v-if="isVisible"
            class="section-top-btn-wrap"
            :class="wrapClasses"
        >
            <button
                type="button"
                class="section-top-btn"
                :title="$t('common.backToTopHint')"
                :aria-label="$t('common.backToTopHint')"
                @click="scrollToTop"
            >
                <span class="section-top-btn__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path
                            d="M12 5l-6.5 6.5 1.4 1.4 4.1-4.1V19h2V8.8l4.1 4.1 1.4-1.4L12 5z"
                            fill="currentColor"
                        />
                    </svg>
                </span>
                <span class="section-top-btn__label">{{ $t('common.backToTop') }}</span>
            </button>

            <span class="section-top-btn__hint" aria-hidden="true">
                {{ $t('common.backToTopHint') }}
            </span>
        </div>
    </transition>
</template>

<script>
import { shouldShowSectionTopButton } from '../utils/sectionTopButtonState.mjs'

export default {
    name: 'SectionTopButton',

    props: {
        hasPersistentWidgets: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            isVisible: false,
            scrollThreshold: 320,
        }
    },

    computed: {
        wrapClasses() {
            return {
                'section-top-btn-wrap--with-widgets': this.hasPersistentWidgets,
            }
        },
    },

    mounted() {
        this.syncVisibility()

        if (typeof window !== 'undefined') {
            window.addEventListener('scroll', this.syncVisibility, {passive: true})
            window.addEventListener('resize', this.syncVisibility, {passive: true})
        }
    },

    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('scroll', this.syncVisibility)
            window.removeEventListener('resize', this.syncVisibility)
        }
    },

    watch: {
        '$route.fullPath'() {
            this.$nextTick(() => {
                this.syncVisibility()
            })
        },
    },

    methods: {
        syncVisibility() {
            if (typeof window === 'undefined' || typeof document === 'undefined') {
                this.isVisible = false
                return
            }

            this.isVisible = shouldShowSectionTopButton({
                scrollTop: window.scrollY || document.documentElement.scrollTop || 0,
                scrollHeight: Math.max(
                    document.body?.scrollHeight || 0,
                    document.documentElement?.scrollHeight || 0,
                ),
                viewportHeight: window.innerHeight || document.documentElement?.clientHeight || 0,
                threshold: this.scrollThreshold,
            })
        },

        scrollToTop() {
            if (typeof window === 'undefined') {
                return
            }

            window.scrollTo({
                top: 0,
                behavior: 'smooth',
            })
        },
    },
}
</script>
