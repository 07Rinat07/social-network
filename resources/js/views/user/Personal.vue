<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">Личный кабинет</h1>
            <p class="section-subtitle">Создавайте публикации с большими фото и видео, добавляйте эмодзи и публикуйте в ленту.</p>

            <Stat :stats="stats"></Stat>
        </section>

        <section class="section-card">
            <h2 class="section-title" style="font-size: 1.2rem;">Профиль для постов и чатов</h2>
            <p class="section-subtitle">Настройте никнейм и аватар. Ник будет отображаться как имя автора в постах и чатах.</p>

            <div class="form-grid">
                <div style="display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                    <button
                        v-if="profileAvatarUrl"
                        type="button"
                        class="media-open-btn"
                        style="width: auto;"
                        @click="openMedia(profileAvatarUrl, profileDisplayName)"
                    >
                        <img :src="profileAvatarUrl" alt="avatar" class="avatar avatar-xl avatar-profile">
                    </button>
                    <span v-else class="avatar avatar-xl avatar-placeholder">{{ profileInitials }}</span>

                    <div class="form-grid" style="min-width: 260px; flex: 1;">
                        <input
                            v-model.trim="profileForm.name"
                            class="input-field"
                            type="text"
                            placeholder="Ваше имя"
                        >
                        <input
                            v-model.trim="profileForm.nickname"
                            class="input-field"
                            type="text"
                            placeholder="Никнейм (например, cool_user)"
                        >
                        <p class="muted" style="margin: 0; font-size: 0.8rem;">
                            Текущее отображаемое имя: <strong>{{ profileDisplayName }}</strong>
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
                        Выбрать аватар
                    </button>
                    <button
                        class="btn btn-danger"
                        @click.prevent="removeProfileAvatar"
                        :disabled="isSavingProfile || !hasAnyAvatar"
                    >
                        Удалить аватар
                    </button>
                    <button class="btn btn-primary" @click.prevent="saveProfile()" :disabled="isSavingProfile">
                        {{ isSavingProfile ? 'Сохранение...' : 'Сохранить профиль' }}
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
            <h2 class="section-title" style="font-size: 1.2rem;">Новый пост</h2>

            <div class="form-grid">
                <input
                    v-model.trim="title"
                    class="input-field"
                    type="text"
                    placeholder="Заголовок"
                >
                <textarea
                    v-model.trim="content"
                    class="textarea-field"
                    placeholder="Текст поста"
                ></textarea>

                <div class="form-grid" style="background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 0.65rem;">
                    <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" v-model="postOptions.is_public">
                        Пост публичный
                    </label>

                    <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" v-model="postOptions.show_in_feed" :disabled="!postOptions.is_public">
                        Показывать в общей ленте на главной
                    </label>

                    <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" v-model="postOptions.show_in_carousel" :disabled="!postOptions.is_public">
                        Показывать в карусели фото/видео на главной
                    </label>
                </div>

                <div class="form-grid" v-if="siteConfig.allow_user_storage_choice" style="background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 0.65rem;">
                    <label class="muted">Где сохранять ваши фото/видео</label>
                    <select class="select-field" v-model="storagePreference" @change="saveStoragePreference">
                        <option value="server_local">Сервер сайта</option>
                        <option value="cloud">Облако</option>
                    </select>
                </div>

                <p class="muted" style="margin: 0; font-size: 0.82rem;">
                    Текущий режим хранения: {{ readableStorageMode }}
                </p>

                <div class="emoji-row">
                    <button v-for="emoji in emojis" :key="emoji" type="button" class="emoji-btn" @click="appendEmoji(emoji)">
                        {{ emoji }}
                    </button>
                </div>

                <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
                    <input @change="uploadMedia" ref="file" type="file" class="hidden" multiple accept="image/*,video/*">
                    <button class="btn btn-outline" @click.prevent="selectFile">Загрузить фото/видео</button>
                    <span class="muted" style="font-size: 0.84rem;">Поддерживаются большие файлы, включая видео.</span>
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
                        <button class="btn btn-danger btn-sm" style="margin-top: 0.5rem;" @click.prevent="removeMedia(media.id)">Убрать</button>
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
                    {{ isPublishing ? 'Публикация...' : 'Опубликовать' }}
                </button>
            </div>
        </section>

        <section class="section-card">
            <h2 class="section-title" style="font-size: 1.2rem;">Мои посты</h2>
            <p class="section-subtitle" v-if="posts.length === 0">Пока нет постов. Опубликуйте первый пост выше.</p>
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
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../../utils/mediaPreview'

export default {
    name: 'Personal',
    emits: ['auth-changed'],

    components: {
        MediaLightbox,
        MediaPlayer,
        Post,
        Stat
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
                return 'облако'
            }
            if (mode === 'user_choice') {
                return this.storagePreference === 'cloud'
                    ? 'вы выбрали облако'
                    : 'вы выбрали сервер сайта'
            }

            return 'сервер сайта'
        },

        profileAvatarUrl() {
            return this.profileAvatarPreview || this.currentUser?.avatar_url || null
        },

        profileDisplayName() {
            const nickname = (this.profileForm.nickname || '').trim()
            if (nickname !== '') {
                return nickname
            }

            return (this.profileForm.name || this.currentUser?.name || 'Пользователь').trim()
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
        openMedia(url, alt = 'Фото') {
            this.$refs.mediaLightbox?.open(url, alt)
        },

        async loadCurrentUser() {
            try {
                const response = await axios.get('/api/user')
                const user = response.data

                this.currentUser = user
                this.profileForm.name = user?.name ?? ''
                this.profileForm.nickname = user?.nickname ?? ''
            } catch (error) {
                this.currentUser = null
            }
        },

        appendEmoji(emoji) {
            this.content = `${this.content}${emoji}`
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
                alert(error.response?.data?.message ?? 'Не удалось обновить выбор хранилища.')
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
            this.clearProfileAvatarPreview()
            this.profileAvatarPreview = URL.createObjectURL(file)
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
                    general: ['Не удалось обновить профиль.']
                }
            } finally {
                this.isSavingProfile = false
            }
        },

        async removeProfileAvatar() {
            this.profileAvatarFile = null
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
                    general: ['Не удалось опубликовать пост.']
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
                        media: ['Ошибка загрузки файла.']
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
