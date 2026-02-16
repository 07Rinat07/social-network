<template>
    <section class="sticker-picker">
        <div class="sticker-picker__toolbar">
            <label v-if="showCategoryLabel" class="sticker-picker__label" :for="pickerId">
                {{ categoryLabel }}
            </label>
            <select
                :id="pickerId"
                v-model="selectedCategory"
                class="select-field sticker-picker__select"
                :disabled="disabled"
            >
                <option
                    v-for="item in categoryOptions"
                    :key="`sticker-category-${item.id}`"
                    :value="item.id"
                >
                    {{ item.label }} ({{ item.count }})
                </option>
            </select>
        </div>

        <div class="sticker-picker__grid">
            <button
                v-for="sticker in filteredStickers"
                :key="`sticker-picker-${sticker.id}`"
                type="button"
                class="sticker-picker__item"
                :disabled="disabled"
                :title="stickerLabel(sticker)"
                @click="$emit('select', sticker)"
            >
                <img
                    :src="sticker.src"
                    :alt="stickerLabel(sticker)"
                    loading="lazy"
                    decoding="async"
                >
            </button>
        </div>
    </section>
</template>

<script>
import {
    STICKER_CATALOG,
    STICKER_CATEGORIES,
    localizedCategoryLabel,
    localizedStickerLabel,
} from '../../data/stickerCatalog'

export default {
    name: 'StickerPicker',

    props: {
        disabled: {
            type: Boolean,
            default: false,
        },
        showCategoryLabel: {
            type: Boolean,
            default: true,
        },
        categoryLabel: {
            type: String,
            default: 'Категория',
        },
    },

    emits: ['select'],

    data() {
        return {
            selectedCategory: 'all',
            pickerId: `sticker-picker-category-${Math.random().toString(36).slice(2, 10)}`,
        }
    },

    computed: {
        currentLocale() {
            return this.$i18n?.locale || 'ru'
        },

        categoryOptions() {
            return STICKER_CATEGORIES.map((category) => {
                if (category.id === 'all') {
                    return {
                        id: category.id,
                        label: localizedCategoryLabel(category, this.currentLocale),
                        count: STICKER_CATALOG.length,
                    }
                }

                const count = STICKER_CATALOG.filter((sticker) => sticker.category === category.id).length
                return {
                    id: category.id,
                    label: localizedCategoryLabel(category, this.currentLocale),
                    count,
                }
            })
        },

        filteredStickers() {
            if (this.selectedCategory === 'all') {
                return STICKER_CATALOG
            }

            return STICKER_CATALOG.filter((sticker) => sticker.category === this.selectedCategory)
        },
    },

    methods: {
        stickerLabel(sticker) {
            return localizedStickerLabel(sticker, this.currentLocale)
        },
    },
}
</script>
