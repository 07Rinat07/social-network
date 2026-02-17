<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">{{ $t('personal.title') }}</h1>
            <p class="section-subtitle">{{ $t('personal.subtitle') }}</p>

            <Stat :stats="stats"></Stat>
        </section>

        <section class="section-card">
            <h2 class="section-title" style="font-size: 1.2rem;">{{ $t('personal.profileTitle') }}</h2>
            <p class="section-subtitle">{{ $t('personal.profileSubtitle') }}</p>

            <div class="form-grid">
                <div style="display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                    <button
                        v-if="profileAvatarUrl"
                        type="button"
                        class="media-open-btn"
                        style="width: auto;"
                        @click="openMedia(profileAvatarUrl, profileDisplayName)"
                    >
                        <img
                            :src="profileAvatarUrl"
                            alt="avatar"
                            class="avatar avatar-xl avatar-profile"
                            @error="onProfileAvatarImageError"
                        >
                    </button>
                    <span v-else class="avatar avatar-xl avatar-placeholder">{{ profileInitials }}</span>

                    <div class="form-grid" style="min-width: min(260px, 100%); flex: 1;">
                        <input
                            v-model.trim="profileForm.name"
                            class="input-field"
                            type="text"
                            :placeholder="$t('personal.namePlaceholder')"
                        >
                        <input
                            v-model.trim="profileForm.nickname"
                            class="input-field"
                            type="text"
                            :placeholder="$t('personal.nicknamePlaceholder')"
                        >
                        <p class="muted" style="margin: 0; font-size: 0.8rem;">
                            {{ $t('personal.currentDisplayName') }} <strong>{{ profileDisplayName }}</strong>
                        </p>
                    </div>
                </div>

                <input
                    ref="profileAvatarInput"
                    type="file"
                    class="hidden"
                    accept="image/*"
                    @change="onProfileAvatarSelected"
                >

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="btn btn-outline" @click.prevent="openProfileAvatarPicker" :disabled="isSavingProfile">
                        {{ $t('personal.chooseAvatar') }}
                    </button>
                    <button
                        class="btn btn-danger"
                        @click.prevent="removeProfileAvatar"
                        :disabled="isSavingProfile || !hasAnyAvatar"
                    >
                        {{ $t('personal.deleteAvatar') }}
                    </button>
                    <button class="btn btn-primary" @click.prevent="saveProfile()" :disabled="isSavingProfile">
                        {{ isSavingProfile ? $t('personal.saving') : $t('personal.saveProfile') }}
                    </button>
                </div>

                <div v-if="profileErrors.name">
                    <p v-for="error in profileErrors.name" :key="error" class="error-text">{{ error }}</p>
                </div>
                <div v-if="profileErrors.nickname">
                    <p v-for="error in profileErrors.nickname" :key="error" class="error-text">{{ error }}</p>
                </div>
                <div v-if="profileErrors.avatar">
                    <p v-for="error in profileErrors.avatar" :key="error" class="error-text">{{ error }}</p>
                </div>
                <div v-if="profileErrors.general">
                    <p v-for="error in profileErrors.general" :key="error" class="error-text">{{ error }}</p>
                </div>
            </div>
        </section>

        <section class="section-card">
            <h2 class="section-title" style="font-size: 1.2rem;">{{ $t('personal.newPostTitle') }}</h2>

            <div class="form-grid">
                <input
                    v-model.trim="title"
                    class="input-field"
                    type="text"
                    :placeholder="$t('personal.postTitlePlaceholder')"
                >
                <textarea
                    v-model.trim="content"
                    class="textarea-field"
                    :placeholder="$t('personal.postBodyPlaceholder')"
                ></textarea>

                <div class="form-grid" style="background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 0.65rem;">
                    <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" v-model="postOptions.is_public">
                        {{ $t('personal.postPublic') }}
                    </label>

                    <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" v-model="postOptions.show_in_feed" :disabled="!postOptions.is_public">
                        {{ $t('personal.postShowInFeed') }}
                    </label>

                    <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" v-model="postOptions.show_in_carousel" :disabled="!postOptions.is_public">
                        {{ $t('personal.postShowInCarousel') }}
                    </label>
                </div>

                <div class="form-grid" v-if="siteConfig.allow_user_storage_choice" style="background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 0.65rem;">
                    <label class="muted">{{ $t('personal.storageWhere') }}</label>
                    <select class="select-field" v-model="storagePreference" @change="saveStoragePreference">
                        <option value="server_local">{{ $t('personal.storageServer') }}</option>
                        <option value="cloud">{{ $t('personal.storageCloud') }}</option>
                    </select>
                </div>

                <p class="muted" style="margin: 0; font-size: 0.82rem;">
                    {{ $t('personal.storageCurrent') }} {{ readableStorageMode }}
                </p>

                <div class="emoji-row">
                    <button v-for="emoji in emojis" :key="emoji" type="button" class="emoji-btn" @click="appendEmoji(emoji)">
                        {{ emoji }}
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="btn btn-outline btn-sm" type="button" @click="toggleStickerTray">
                        {{ showStickerTray ? $t('chats.hideStickers') : $t('chats.stickers') }}
                    </button>
                </div>

                <div v-if="showStickerTray" class="chat-sticker-tray">
                    <StickerPicker
                        :category-label="$t('radio.genreFilterLabel')"
                        @select="insertSticker"
                    ></StickerPicker>
                </div>

                <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
                    <input @change="uploadMedia" ref="file" type="file" class="hidden" multiple accept="image/*,video/*">
                    <button class="btn btn-outline" @click.prevent="selectFile">{{ $t('personal.uploadMedia') }}</button>
                    <span class="muted" style="font-size: 0.84rem;">{{ $t('personal.uploadHint') }}</span>
                </div>

                <div class="media-grid" v-if="uploadedMedia.length > 0">
                    <div v-for="media in uploadedMedia" :key="`new-media-${media.id}`" class="section-card" style="padding: 0.5rem; box-shadow: none;">
                        <button
                            v-if="media.type === 'image'"
                            type="button"
                            class="media-open-btn"
                            @click="openMedia(resolveUploadedMediaPreviewUrl(media), 'preview')"
                        >
                            <img
                                class="media-preview"
                                :src="resolveUploadedMediaPreviewUrl(media)"
                                alt="preview"
                                @error="onUploadedMediaPreviewError(media, $event)"
                                @load="onUploadedMediaPreviewLoad(media, $event)"
                            >
                        </button>
                        <MediaPlayer v-else type="video" :src="media.url" player-class="media-video"></MediaPlayer>
                        <button class="btn btn-danger btn-sm" style="margin-top: 0.5rem;" @click.prevent="removeMedia(media.id)">{{ $t('personal.removeMedia') }}</button>
                    </div>
                </div>

                <div v-if="errors.title">
                    <p v-for="error in errors.title" :key="error" class="error-text">{{ error }}</p>
                </div>
                <div v-if="errors.content">
                    <p v-for="error in errors.content" :key="error" class="error-text">{{ error }}</p>
                </div>
                <div v-if="errors.media_ids">
                    <p v-for="error in errors.media_ids" :key="error" class="error-text">{{ error }}</p>
                </div>

                <button class="btn btn-primary" @click.prevent="store" :disabled="isPublishing || isUploading">
                    {{ isPublishing ? $t('personal.publishing') : $t('personal.publish') }}
                </button>
            </div>
        </section>

        <section class="section-card">
            <h2 class="section-title" style="font-size: 1.2rem;">{{ $t('personal.myPostsTitle') }}</h2>
            <p class="section-subtitle" v-if="posts.length === 0">{{ $t('personal.myPostsEmpty') }}</p>
            <div class="post-list">
                <Post v-for="post in posts" :key="post.id" :post="post"></Post>
            </div>
        </section>

        <MediaLightbox ref="mediaLightbox"></MediaLightbox>
    </div>
</template>

<script>
import MediaLightbox from '../../components/MediaLightbox.vue'
import MediaPlayer from '../../components/MediaPlayer.vue'
import Post from '../../components/Post.vue'
import Stat from '../../components/Stat.vue'
import StickerPicker from '../../components/stickers/StickerPicker.vue'
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../../utils/mediaPreview'
import { stickerTokenFromId } from '../../data/stickerCatalog'

export default {
    name: 'Personal',
    emits: ['auth-changed'],

    components: {
        MediaLightbox,
        MediaPlayer,
        Post,
        Stat,
        StickerPicker,
    },

    data() {
        return {
            title: '',
            content: '',
            uploadedMedia: [],
            posts: [],
            errors: {},
            stats: {},
            isPublishing: false,
            isUploading: false,
            isSavingProfile: false,
            emojis: ['🔥', '🚀', '😎', '❤️', '👏', '🎉'],
            showStickerTray: false,
            siteConfig: {},
            storagePreference: 'server_local',
            currentUser: null,
            profileForm: {
                name: '',
                nickname: '',
            },
            profileErrors: {},
            profileAvatarFile: null,
            profileAvatarPreview: null,
            profileAvatarLoadFailed: false,
            postOptions: {
                is_public: true,
                show_in_feed: true,
                show_in_carousel: false,
            },
        }
    },

    computed: {
        readableStorageMode() {
            const mode = this.siteConfig.media_storage_mode
            if (mode === 'cloud') {
                return this.$t('personal.storageCloudValue')
            }
            if (mode === 'user_choice') {
                return this.storagePreference === 'cloud'
                    ? this.$t('personal.storageCloudChosen')
                    : this.$t('personal.storageServerChosen')
            }

            return this.$t('personal.storageServerValue')
        },

        profileAvatarUrl() {
            if (this.profileAvatarPreview) {
                return this.profileAvatarPreview
            }

            if (this.profileAvatarLoadFailed) {
                return null
            }

            return this.currentUser?.avatar_url || null
        },

        profileDisplayName() {
            const nickname = (this.profileForm.nickname || '').trim()
            if (nickname !== '') {
                return nickname
            }

            return (this.profileForm.name || this.currentUser?.name || this.$t('common.user')).trim()
        },

        profileInitials() {
            const source = this.profileDisplayName
            return source ? source.slice(0, 1).toUpperCase() : 'U'
        },

        hasAnyAvatar() {
            return Boolean(this.profileAvatarPreview || this.profileAvatarFile || this.currentUser?.avatar_url)
        },
    },

    mounted() {
        this.loadCurrentUser()
        this.getPosts()
        this.getStats()
        this.loadSiteConfig()
    },

    beforeUnmount() {
        this.clearProfileAvatarPreview()
        this.clearUploadedMediaPreviews()
    },

    methods: {
        openMedia(url, alt = null) {
            const safeAlt = alt || this.$t('personal.mediaAlt')
            this.$refs.mediaLightbox?.open(url, safeAlt)
        },

        async loadCurrentUser() {
            try {
                const response = await axios.get('/api/user')
                const user = response.data

                this.currentUser = user
                this.profileAvatarLoadFailed = false
                this.profileForm.name = user?.name ?? ''
                this.profileForm.nickname = user?.nickname ?? ''
            } catch (error) {
                this.currentUser = null
                this.profileAvatarLoadFailed = false
            }
        },

        appendEmoji(emoji) {
            this.content = `${this.content}${emoji}`
        },

        toggleStickerTray() {
            this.showStickerTray = !this.showStickerTray
        },

        insertSticker(sticker) {
            const token = stickerTokenFromId(sticker?.id)
            if (token === '') {
                return
            }

            const suffix = this.content.trim() === '' ? '' : ' '
            this.content = `${this.content}${suffix}${token}`
            this.showStickerTray = false
        },

        async getStats() {
            const response = await axios.post('/api/users/stats', { user_id: null })
            this.stats = response.data.data
        },

        async getPosts() {
            const response = await axios.get('/api/posts', {params: {per_page: 50}})
            this.posts = response.data.data ?? []
        },

        async loadSiteConfig() {
            try {
                const response = await axios.get('/api/site/config')
                this.siteConfig = response.data.data ?? {}
                this.storagePreference = this.siteConfig.user_media_storage_preference ?? 'server_local'
            } catch (error) {
                this.siteConfig = {}
            }
        },

        async saveStoragePreference() {
            try {
                await axios.patch('/api/site/storage-preference', {
                    media_storage_preference: this.storagePreference,
                })
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('personal.storageUpdateError'))
                await this.loadSiteConfig()
            }
        },

        openProfileAvatarPicker() {
            this.$refs.profileAvatarInput?.click()
        },

        onProfileAvatarSelected(event) {
            const file = event.target.files?.[0]
            if (!file) {
                return
            }

            this.profileAvatarFile = file
            this.profileAvatarLoadFailed = false
            this.clearProfileAvatarPreview()
            this.profileAvatarPreview = URL.createObjectURL(file)
        },

        onProfileAvatarImageError() {
            this.profileAvatarLoadFailed = true
        },

        clearProfileAvatarPreview() {
            if (this.profileAvatarPreview) {
                URL.revokeObjectURL(this.profileAvatarPreview)
            }

            this.profileAvatarPreview = null
        },

        async saveProfile(removeAvatar = false) {
            this.profileErrors = {}
            this.isSavingProfile = true

            const formData = new FormData()
            formData.append('name', this.profileForm.name)
            formData.append('nickname', this.profileForm.nickname || '')

            if (removeAvatar) {
                formData.append('remove_avatar', '1')
            }

            if (this.profileAvatarFile) {
                formData.append('avatar', this.profileAvatarFile)
            }

            try {
                const response = await axios.post('/api/users/profile', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                })

                const user = response.data.data ?? null
                if (user) {
                    this.currentUser = user
                    this.profileAvatarLoadFailed = false
                    this.profileForm.name = user.name ?? this.profileForm.name
                    this.profileForm.nickname = user.nickname ?? ''
                }

                this.profileAvatarFile = null
                this.clearProfileAvatarPreview()
                if (this.$refs.profileAvatarInput) {
                    this.$refs.profileAvatarInput.value = null
                }

                this.$emit('auth-changed')
            } catch (error) {
                this.profileErrors = error.response?.data?.errors ?? {
                    general: [this.$t('personal.profileUpdateError')]
                }
            } finally {
                this.isSavingProfile = false
            }
        },

        async removeProfileAvatar() {
            this.profileAvatarFile = null
            this.profileAvatarLoadFailed = false
            this.clearProfileAvatarPreview()

            await this.saveProfile(true)
        },

        async store() {
            this.errors = {}
            this.isPublishing = true

            try {
                const response = await axios.post('/api/posts', {
                    title: this.title,
                    content: this.content,
                    media_ids: this.uploadedMedia.map((item) => item.id),
                    is_public: this.postOptions.is_public,
                    show_in_feed: this.postOptions.is_public ? this.postOptions.show_in_feed : false,
                    show_in_carousel: this.postOptions.is_public ? this.postOptions.show_in_carousel : false,
                })

                this.title = ''
                this.content = ''
                this.clearUploadedMediaPreviews()
                this.uploadedMedia = []
                this.postOptions = {
                    is_public: true,
                    show_in_feed: true,
                    show_in_carousel: false,
                }
                this.posts.unshift(response.data.data)
                await this.getStats()
            } catch (error) {
                this.errors = error.response?.data?.errors ?? {
                    general: [this.$t('personal.publishError')]
                }
            } finally {
                this.isPublishing = false
            }
        },

        selectFile() {
            this.$refs.file.click()
        },

        async uploadMedia(event) {
            const files = Array.from(event.target.files || [])
            if (files.length === 0) {
                return
            }

            this.isUploading = true

            for (const file of files) {
                const formData = new FormData()
                formData.append('file', file)
                const localPreviewUrl = URL.createObjectURL(file)

                try {
                    const response = await axios.post('/api/post_media', formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        }
                    })
                    this.uploadedMedia.push({
                        ...response.data.data,
                        local_preview_url: localPreviewUrl,
                        preview_fallback_used: false,
                    })
                } catch (error) {
                    URL.revokeObjectURL(localPreviewUrl)
                    this.errors = error.response?.data?.errors ?? {
                        media: [this.$t('personal.uploadError')]
                    }
                    break
                }
            }

            this.isUploading = false
            this.$refs.file.value = null
        },

        removeMedia(id) {
            const target = this.uploadedMedia.find((item) => item.id === id)
            this.revokeUploadedMediaPreviewUrl(target)
            this.uploadedMedia = this.uploadedMedia.filter((item) => item.id !== id)
        },

        resolveUploadedMediaPreviewUrl(media) {
            if (!media) {
                return ''
            }

            if (media.preview_fallback_used && media.local_preview_url) {
                return media.local_preview_url
            }

            return media.url || media.local_preview_url || ''
        },

        onUploadedMediaPreviewError(media, event) {
            if (!media || !event?.target) {
                return
            }

            if (media.local_preview_url && event.target.src !== media.local_preview_url) {
                media.preview_fallback_used = true
                event.target.src = media.local_preview_url
                return
            }

            applyImagePreviewFallback(event, 'Preview unavailable')
        },

        onUploadedMediaPreviewLoad(media, event) {
            resetImagePreviewFallback(event)
        },

        revokeUploadedMediaPreviewUrl(media) {
            const localPreviewUrl = media?.local_preview_url
            if (!localPreviewUrl) {
                return
            }

            URL.revokeObjectURL(localPreviewUrl)
            media.local_preview_url = null
        },

        clearUploadedMediaPreviews() {
            for (const media of this.uploadedMedia) {
                this.revokeUploadedMediaPreviewUrl(media)
            }
        }
    }
}
</script>
