<template>
    <div class="page-wrap grid-layout home-page-bg" :style="homePageStyle">
        <section class="section-card hero-grid home-hero-card">
            <div>
                <span class="badge">{{ homeContent.badge }}</span>
                <h1 class="hero-heading">{{ homeContent.hero_title }}</h1>
                <p class="hero-note">{{ homeContent.hero_note }}</p>
                <div class="feature-list">
                    <div class="feature-item" v-for="(item, index) in homeContent.feature_items" :key="`home-feature-${index}`">{{ item }}</div>
                </div>
            </div>

            <div class="section-card hero-quick">
                <h2 class="section-title hero-quick-title">{{ isVerifiedUser ? 'Добро пожаловать' : (isAuthenticated ? 'Подтвердите email' : 'Быстрый старт') }}</h2>
                <p class="section-subtitle" v-if="isVerifiedUser">
                    {{ user?.display_name || user?.name }}, вам доступны карусель публичных медиа и ленты лучших публикаций.
                </p>
                <p class="section-subtitle" v-else-if="isAuthenticated">
                    Подтвердите email, чтобы открыть публикации, чаты, ленты и личный кабинет.
                </p>
                <p class="section-subtitle" v-else>
                    Создайте аккаунт и получите доступ ко всем функциям соцсети.
                </p>
                <div class="form-grid">
                    <template v-if="!isAuthenticated">
                        <router-link class="btn btn-primary" :to="{name: 'user.registration'}">Регистрация</router-link>
                        <router-link class="btn btn-outline" :to="{name: 'user.login'}">Вход в аккаунт</router-link>
                    </template>
                    <template v-else-if="isVerifiedUser">
                        <router-link class="btn btn-primary" :to="{name: 'user.personal'}">Создать пост</router-link>
                        <router-link class="btn btn-outline" :to="{name: 'chat.index'}">Открыть чаты</router-link>
                        <router-link class="btn btn-sun" :to="{name: 'user.feed'}">Лента подписок</router-link>
                    </template>
                    <template v-else>
                        <router-link class="btn btn-primary" :to="{name: 'auth.verify'}">Подтвердить email</router-link>
                        <router-link class="btn btn-outline" :to="{name: 'home'}">На главную</router-link>
                    </template>
                </div>
            </div>
        </section>

        <section class="section-card" v-if="isVerifiedUser">
            <h2 class="section-title">Галерея-карусель публичного медиа</h2>
            <p class="section-subtitle">Здесь показываются фото и видео, которые авторы отметили для карусели.</p>

            <div
                v-if="carouselItems.length > 0"
                class="home-carousel"
                @mouseenter="pauseCarouselAutoplay"
                @mouseleave="resumeCarouselAutoplay"
                @focusin="pauseCarouselAutoplay"
                @focusout="resumeCarouselAutoplay"
            >
                <div class="home-carousel-media">
                    <MediaPlayer
                        v-if="currentCarouselItem.type === 'video'"
                        type="video"
                        :src="currentCarouselItem.url"
                        player-class="media-video"
                    ></MediaPlayer>
                    <button
                        v-else
                        type="button"
                        class="media-open-btn"
                        @click="openMedia(currentCarouselItem.url, currentCarouselItem.post?.title || 'carousel media')"
                    >
                        <img
                            :src="currentCarouselItem.url"
                            :alt="currentCarouselItem.post?.title || 'carousel media'"
                            class="media-preview home-carousel-image"
                            @error="handlePreviewError($event, currentCarouselItem.post?.title || 'media')"
                            @load="handlePreviewLoad"
                        >
                    </button>
                </div>

                <div class="home-carousel-meta">
                    <strong>{{ currentCarouselItem.post?.title || 'Пост без заголовка' }}</strong>
                    <p class="muted home-carousel-author">
                        Автор: {{ currentCarouselItem.post?.user?.display_name || currentCarouselItem.post?.user?.name || 'Пользователь' }} ·
                        👁 {{ currentCarouselItem.post?.views_count ?? 0 }}
                    </p>
                    <p class="home-carousel-content">{{ currentCarouselItem.post?.content || '—' }}</p>
                </div>

                <div class="home-carousel-controls">
                    <button class="btn btn-outline btn-sm" @click="prevSlide">Предыдущее</button>
                    <button class="btn btn-outline btn-sm" @click="nextSlide">Следующее</button>
                    <span class="muted home-carousel-counter">{{ currentSlide + 1 }} / {{ carouselItems.length }}</span>
                </div>
            </div>

            <p v-else class="muted">Пока нет публичных материалов для карусели.</p>
        </section>

        <section class="section-card" v-if="isVerifiedUser">
            <h2 class="section-title">Ленты сообщества</h2>
            <p class="section-subtitle">Популярные, самые просматриваемые и новые посты платформы.</p>

            <div class="discover-tabs">
                <button class="btn" :class="discoverSort === 'popular' ? 'btn-primary' : 'btn-outline'" @click="loadDiscover('popular')">Популярные</button>
                <button class="btn" :class="discoverSort === 'most_viewed' ? 'btn-primary' : 'btn-outline'" @click="loadDiscover('most_viewed')">Просматриваемые</button>
                <button class="btn" :class="discoverSort === 'newest' ? 'btn-primary' : 'btn-outline'" @click="loadDiscover('newest')">Новые</button>
            </div>

            <p class="muted" v-if="isLoadingDiscover">Загрузка...</p>
            <p class="muted" v-else-if="discoverPosts.length === 0">Пока нет постов для этого раздела.</p>

            <div class="post-list" v-else>
                <Post v-for="post in discoverPosts" :key="`discover-post-${post.id}`" :post="post"></Post>
            </div>
        </section>

        <section class="section-card home-feedback-card">
            <h2 class="section-title">{{ homeContent.feedback_title }}</h2>
            <p class="section-subtitle">{{ homeContent.feedback_subtitle }}</p>
            <form class="form-grid" @submit.prevent="submitFeedback">
                <input class="input-field" v-model.trim="form.name" type="text" placeholder="Ваше имя">
                <input class="input-field" v-model.trim="form.email" type="email" placeholder="Ваш email">
                <textarea class="textarea-field" v-model.trim="form.message" placeholder="Ваше сообщение"></textarea>

                <div v-if="errors.name">
                    <p v-for="error in errors.name" :key="error" class="error-text">{{ error }}</p>
                </div>
                <div v-if="errors.email">
                    <p v-for="error in errors.email" :key="error" class="error-text">{{ error }}</p>
                </div>
                <div v-if="errors.message">
                    <p v-for="error in errors.message" :key="error" class="error-text">{{ error }}</p>
                </div>
                <div v-if="errors.general">
                    <p v-for="error in errors.general" :key="error" class="error-text">{{ error }}</p>
                </div>
                <p v-if="successMessage" class="success-text">{{ successMessage }}</p>
                <div v-if="feedbackDeliveryState === 'sent'" class="feature-item">
                    <p class="muted">Обращение принято.</p>
                </div>
                <div v-if="feedbackDeliveryState !== 'idle'" class="feature-item">
                    <p class="muted">
                        Статус отправки: <strong>{{ feedbackDeliveryLabel }}</strong>
                    </p>
                    <p v-if="lastFeedbackMeta?.id" class="muted">
                        Номер обращения: <strong>#{{ lastFeedbackMeta.id }}</strong>
                    </p>
                    <p v-if="lastFeedbackMeta?.status" class="muted">
                        Статус обращения: <strong>{{ feedbackStatusLabel(lastFeedbackMeta.status) }}</strong>
                    </p>
                </div>

                <button class="btn btn-primary" :disabled="isSending" type="submit">
                    {{ isSending ? 'Отправка...' : 'Отправить в администрацию' }}
                </button>
            </form>
        </section>

        <MediaLightbox ref="mediaLightbox"></MediaLightbox>
    </div>
</template>

<script>
import MediaLightbox from '../components/MediaLightbox.vue'
import MediaPlayer from '../components/MediaPlayer.vue'
import Post from '../components/Post.vue'
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../utils/mediaPreview'
import homeSocialMapBackground from '../../images/home-social-map.jpg'

const defaultHomeContent = () => ({
    badge: 'Социальная сеть SPA',
    hero_title: 'Современная платформа с постами, чатами, каруселью медиа и гибкими настройками хранения.',
    hero_note: 'Публикуйте контент, общайтесь, продвигайте лучшие посты и управляйте видимостью своих материалов. Администратор контролирует настройки сайта и политику хранения фото/видео.',
    feature_items: [
        'Публичные и приватные посты с гибким показом в ленте/карусели.',
        'Личные и общие чаты с realtime-доставкой.',
        'Админ-панель с полным управлением настройками платформы.',
    ],
    feedback_title: 'Обратная связь для администрации',
    feedback_subtitle: 'Напишите нам предложение, жалобу или вопрос. Сообщение сразу попадёт в админ-панель.',
})

const CAROUSEL_AUTOPLAY_INTERVAL_MS = 5000

export default {
    name: 'Home',

    components: {
        MediaLightbox,
        MediaPlayer,
        Post,
    },

    data() {
        return {
            user: null,
            isAuthenticated: false,
            homeContent: defaultHomeContent(),
            carouselItems: [],
            currentSlide: 0,
            discoverSort: 'popular',
            discoverPosts: [],
            isLoadingDiscover: false,
            form: {
                name: '',
                email: '',
                message: '',
            },
            errors: {},
            successMessage: '',
            isSending: false,
            feedbackDeliveryState: 'idle',
            lastFeedbackMeta: null,
            carouselAutoplayTimerId: null,
            isCarouselAutoplayPaused: false,
        }
    },

    computed: {
        isVerifiedUser() {
            return this.isAuthenticated && Boolean(this.user?.email_verified_at)
        },

        homePageStyle() {
            return {
                '--home-bg-image': `url(${homeSocialMapBackground})`
            }
        },

        currentCarouselItem() {
            if (this.carouselItems.length === 0) {
                return {}
            }

            return this.carouselItems[this.currentSlide] ?? this.carouselItems[0]
        },

        feedbackDeliveryLabel() {
            const map = {
                sending: 'Отправляется...',
                sent: 'Отправлено и получено администрацией',
                failed: 'Ошибка отправки',
            }

            return map[this.feedbackDeliveryState] ?? '—'
        }
    },

    async mounted() {
        await this.bootstrapPage()
    },

    beforeUnmount() {
        this.stopCarouselAutoplay()
    },

    methods: {
        handlePreviewError(event, label = 'Preview unavailable') {
            applyImagePreviewFallback(event, label)
        },

        handlePreviewLoad(event) {
            resetImagePreviewFallback(event)
        },

        async bootstrapPage() {
            await this.loadHomeContent()
            await this.loadCurrentUser()

            if (this.isVerifiedUser) {
                await Promise.all([
                    this.loadCarousel(),
                    this.loadDiscover(this.discoverSort),
                ])
            } else {
                this.stopCarouselAutoplay()
            }

            this.prefillAuthorizedUser()
        },

        openMedia(url, alt = 'Фото') {
            this.$refs.mediaLightbox?.open(url, alt)
        },

        normalizeHomeContent(payload) {
            const fallback = defaultHomeContent()
            const featureItems = Array.isArray(payload?.feature_items)
                ? payload.feature_items.map((item) => String(item ?? '').trim()).filter((item) => item !== '').slice(0, 8)
                : []

            return {
                badge: String(payload?.badge ?? '').trim() || fallback.badge,
                hero_title: String(payload?.hero_title ?? '').trim() || fallback.hero_title,
                hero_note: String(payload?.hero_note ?? '').trim() || fallback.hero_note,
                feature_items: featureItems.length > 0 ? featureItems : fallback.feature_items,
                feedback_title: String(payload?.feedback_title ?? '').trim() || fallback.feedback_title,
                feedback_subtitle: String(payload?.feedback_subtitle ?? '').trim() || fallback.feedback_subtitle,
            }
        },

        async loadHomeContent() {
            try {
                const response = await axios.get('/api/site/home-content')
                this.homeContent = this.normalizeHomeContent(response.data.data ?? {})
            } catch (error) {
                this.homeContent = defaultHomeContent()
            }
        },

        async loadCurrentUser() {
            try {
                const response = await axios.get('/api/user')
                this.user = response.data
                this.isAuthenticated = true
            } catch (error) {
                this.user = null
                this.isAuthenticated = false
            }
        },

        async loadCarousel() {
            const response = await axios.get('/api/posts/carousel', { params: { limit: 40 } })
            this.carouselItems = response.data.data ?? []
            this.currentSlide = 0
            this.startCarouselAutoplay()
        },

        async loadDiscover(sort) {
            this.discoverSort = sort
            this.isLoadingDiscover = true

            try {
                const response = await axios.get('/api/posts/discover', {
                    params: {
                        sort,
                        per_page: 20,
                    }
                })

                this.discoverPosts = response.data.data ?? []
            } finally {
                this.isLoadingDiscover = false
            }
        },

        prevSlide(isManual = true) {
            if (this.carouselItems.length <= 1) {
                return
            }

            this.currentSlide = this.currentSlide > 0
                ? this.currentSlide - 1
                : this.carouselItems.length - 1

            if (isManual) {
                this.restartCarouselAutoplay()
            }
        },

        nextSlide(isManual = true) {
            if (this.carouselItems.length <= 1) {
                return
            }

            this.currentSlide = this.currentSlide < this.carouselItems.length - 1
                ? this.currentSlide + 1
                : 0

            if (isManual) {
                this.restartCarouselAutoplay()
            }
        },

        startCarouselAutoplay() {
            this.stopCarouselAutoplay()

            if (!this.isAuthenticated || this.carouselItems.length <= 1 || this.isCarouselAutoplayPaused) {
                return
            }

            this.carouselAutoplayTimerId = window.setInterval(() => {
                this.nextSlide(false)
            }, CAROUSEL_AUTOPLAY_INTERVAL_MS)
        },

        stopCarouselAutoplay() {
            if (this.carouselAutoplayTimerId) {
                window.clearInterval(this.carouselAutoplayTimerId)
                this.carouselAutoplayTimerId = null
            }
        },

        restartCarouselAutoplay() {
            if (this.isCarouselAutoplayPaused) {
                return
            }

            this.startCarouselAutoplay()
        },

        pauseCarouselAutoplay() {
            this.isCarouselAutoplayPaused = true
            this.stopCarouselAutoplay()
        },

        resumeCarouselAutoplay() {
            this.isCarouselAutoplayPaused = false
            this.startCarouselAutoplay()
        },

        prefillAuthorizedUser() {
            if (!this.user) {
                return
            }

            if (!this.form.name) {
                this.form.name = this.user.name ?? ''
            }
            if (!this.form.email) {
                this.form.email = this.user.email ?? ''
            }
        },

        async submitFeedback() {
            this.errors = {}
            this.successMessage = ''
            this.isSending = true
            this.feedbackDeliveryState = 'sending'
            this.lastFeedbackMeta = null

            try {
                const response = await axios.post('/api/feedback', this.form, {
                    timeout: 12000,
                })
                this.successMessage = response.data.message
                this.lastFeedbackMeta = response.data.data ?? null
                this.feedbackDeliveryState = 'sent'
                this.form.message = ''
            } catch (error) {
                const validationErrors = error.response?.data?.errors

                if (validationErrors && Object.keys(validationErrors).length > 0) {
                    this.errors = validationErrors
                    this.feedbackDeliveryState = 'failed'
                    return
                }

                let message = error.response?.data?.message ?? 'Не удалось отправить сообщение. Попробуйте позже.'

                if (error.code === 'ECONNABORTED') {
                    message = 'Сервер долго не отвечает. Проверьте подключение и попробуйте ещё раз.'
                }

                this.errors = {
                    general: [message],
                }
                this.feedbackDeliveryState = 'failed'
            } finally {
                this.isSending = false
            }
        },

        feedbackStatusLabel(status) {
            const labels = {
                new: 'Получено администрацией',
                in_progress: 'В обработке',
                resolved: 'Решено',
            }

            return labels[status] ?? status
        },
    }
}
</script>
