<template>
    <div class="page-wrap grid-layout home-page-bg">
        <section class="section-card hero-grid home-hero-card">
            <div class="home-hero-main">
                <div class="home-hero-copy">
                    <span class="badge home-hero-badge">{{ homeContent.badge }}</span>
                    <h1 class="hero-heading">{{ homeContent.hero_title }}</h1>
                    <p class="hero-note">{{ homeContent.hero_note }}</p>
                </div>

                <div class="home-hero-actions" v-if="!isAuthenticated">
                    <router-link class="btn btn-primary" :to="{name: 'user.registration'}">{{ $t('home.createAccount') }}</router-link>
                    <router-link class="btn btn-outline" :to="{name: 'user.login'}">{{ $t('home.signIn') }}</router-link>
                </div>

                <div class="home-feature-list">
                    <article class="home-feature-item" v-for="(item, index) in homeContent.feature_items" :key="`home-feature-${index}`">
                        <span class="home-feature-index">{{ String(index + 1).padStart(2, '0') }}</span>
                        <span>{{ item }}</span>
                    </article>
                </div>
            </div>

            <div class="section-card hero-quick">
                <h2 class="section-title hero-quick-title">{{ quickTitle }}</h2>
                <p class="section-subtitle" v-if="isVerifiedUser">
                    {{ $t('home.welcomeLine', {name: (user?.display_name || user?.name)}) }}
                </p>
                <p class="section-subtitle" v-else-if="isAuthenticated">
                    {{ $t('home.verifyHint') }}
                </p>
                <p class="section-subtitle" v-else>
                    {{ $t('home.guestHint') }}
                </p>
                <div class="form-grid">
                    <template v-if="!isAuthenticated">
                        <router-link class="btn btn-primary" :to="{name: 'user.registration'}">{{ $t('home.registration') }}</router-link>
                        <router-link class="btn btn-outline" :to="{name: 'user.login'}">{{ $t('home.accountLogin') }}</router-link>
                    </template>
                    <template v-else-if="isVerifiedUser">
                        <router-link class="btn btn-primary" :to="{name: 'user.personal'}">{{ $t('home.createPost') }}</router-link>
                        <router-link class="btn btn-outline" :to="{name: 'chat.index'}">{{ $t('home.openChats') }}</router-link>
                        <router-link class="btn btn-sun" :to="{name: 'user.feed'}">{{ $t('home.followingFeed') }}</router-link>
                    </template>
                    <template v-else>
                        <router-link class="btn btn-primary" :to="{name: 'auth.verify'}">{{ $t('home.verifyEmail') }}</router-link>
                        <router-link class="btn btn-outline" :to="{name: 'home'}">{{ $t('home.toHome') }}</router-link>
                    </template>
                </div>

                <div class="home-quick-stats">
                    <article class="home-quick-stat" v-for="item in quickStats" :key="item.label">
                        <span>{{ item.label }}</span>
                        <strong>{{ item.value }}</strong>
                    </article>
                </div>

                <div class="home-quick-parade" aria-hidden="true">
                    <div class="home-quick-parade-head">
                        <span>{{ $t('home.feedInMotion') }}</span>
                        <small>{{ $t('home.motionTrail') }}</small>
                    </div>
                    <div class="home-quick-parade-stage">
                        <div class="home-quick-parade-lane"></div>
                        <span class="home-quick-checkpoint is-feed">{{ $t('home.motionFeed') }}</span>
                        <span class="home-quick-checkpoint is-chat">{{ $t('home.motionChat') }}</span>
                        <span class="home-quick-checkpoint is-like">{{ $t('home.motionLike') }}</span>

                        <div class="home-quick-mascot-runner">
                            <div class="home-quick-mascot">
                                <div class="home-quick-mascot-figure">
                                    <div class="home-quick-mascot-head">
                                        <svg class="home-quick-logo-head" viewBox="0 0 120 120" role="presentation" focusable="false">
                                            <g opacity="0.95">
                                                <rect x="54" y="6" width="12" height="36" rx="6" fill="#37b9ff"></rect>
                                                <rect x="54" y="6" width="12" height="36" rx="6" transform="rotate(45 60 60)" fill="#9d74ff"></rect>
                                                <rect x="54" y="6" width="12" height="36" rx="6" transform="rotate(90 60 60)" fill="#1fcf8f"></rect>
                                                <rect x="54" y="6" width="12" height="36" rx="6" transform="rotate(135 60 60)" fill="#ff80ba"></rect>
                                                <rect x="54" y="6" width="12" height="36" rx="6" transform="rotate(180 60 60)" fill="#ff9b52"></rect>
                                                <rect x="54" y="6" width="12" height="36" rx="6" transform="rotate(225 60 60)" fill="#f5c14f"></rect>
                                                <rect x="54" y="6" width="12" height="36" rx="6" transform="rotate(270 60 60)" fill="#39c3be"></rect>
                                                <rect x="54" y="6" width="12" height="36" rx="6" transform="rotate(315 60 60)" fill="#56a7ff"></rect>
                                            </g>
                                            <circle cx="60" cy="60" r="21" fill="#0d63d7"></circle>
                                            <circle cx="60" cy="60" r="14" fill="#f3f9ff" fill-opacity="0.26"></circle>
                                            <g fill="#ffffff" opacity="0.94">
                                                <circle cx="60" cy="48" r="2.8"></circle>
                                                <circle cx="70.6" cy="63.8" r="2.8"></circle>
                                                <circle cx="49.4" cy="63.8" r="2.8"></circle>
                                                <circle cx="60" cy="60" r="3.2"></circle>
                                            </g>
                                        </svg>
                                    </div>
                                    <span class="home-quick-mascot-body"></span>
                                    <span class="home-quick-mascot-arm is-left"></span>
                                    <span class="home-quick-mascot-arm is-right"></span>
                                    <span class="home-quick-mascot-leg is-left"></span>
                                    <span class="home-quick-mascot-leg is-right"></span>
                                    <span class="home-quick-mascot-trail"></span>
                                </div>
                                <span class="home-quick-bubble">{{ $t('home.motionBubble') }}</span>
                            </div>
                        </div>

                        <span class="home-quick-float is-chat">💬</span>
                        <span class="home-quick-float is-heart">❤️</span>
                        <span class="home-quick-float is-star">✨</span>
                        <span class="home-quick-float is-bolt">⚡</span>
                    </div>
                </div>

                <div class="home-world-widget">
                    <div class="home-world-widget-head">
                        <span>{{ worldOverviewTitle }}</span>
                        <small v-if="worldOverviewUpdatedLabel">{{ worldOverviewUpdatedLabel }}</small>
                    </div>

                    <p class="muted home-world-widget-hint" v-if="isWorldOverviewLoading && worldOverviewCities.length === 0">
                        {{ $t('home.worldLoading') }}
                    </p>
                    <p class="error-text" v-if="worldOverviewError">{{ worldOverviewError }}</p>

                    <div class="home-world-grid" v-if="worldOverviewCities.length > 0">
                        <article class="home-world-card" v-for="city in worldOverviewCities" :key="`world-city-${city.id}`">
                            <div class="home-world-card-head">
                                <strong>{{ city.name }}</strong>
                                <span>{{ city.country }}</span>
                            </div>

                            <div class="home-world-card-time">{{ formatWorldCityTime(city) }}</div>

                            <div class="home-world-card-weather">
                                <span class="home-world-card-icon">{{ city.weather?.icon || '🌡️' }}</span>
                                <span>{{ formatWorldCityTemperature(city) }}</span>
                                <small>{{ formatWorldCityWind(city) }}</small>
                            </div>

                            <p class="home-world-card-note">
                                {{ city.weather?.description || $t('home.noWeatherData') }}
                            </p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-card home-showcase-card">
            <div class="home-showcase-head">
                <span class="badge home-showcase-badge">{{ $t('home.showcaseBadge') }}</span>
                <h2 class="section-title">{{ $t('home.showcaseTitle') }}</h2>
                <p class="section-subtitle">{{ $t('home.showcaseSubtitle') }}</p>
            </div>

            <div class="home-showcase-grid">
                <article class="home-showcase-item" v-for="item in homeShowcaseCards" :key="item.kicker">
                    <span class="home-showcase-kicker">{{ item.kicker }}</span>
                    <h3>{{ item.title }}</h3>
                    <p>{{ item.note }}</p>
                </article>
            </div>
        </section>

        <section class="section-card home-avatar-ribbon-card" v-if="isVerifiedUser">
            <div class="home-avatar-ribbon-head">
                <div>
                    <h2 class="section-title">{{ $t('home.avatarCarouselTitle') }}</h2>
                    <p class="section-subtitle">{{ $t('home.avatarCarouselSubtitle') }}</p>
                </div>
                <span class="badge home-avatar-ribbon-badge">
                    {{ $t('home.avatarCarouselCount', { count: filteredAvatarCarouselUsers.length }) }}
                </span>
            </div>

            <div class="home-avatar-ribbon-search-row">
                <input
                    v-model.trim="avatarCarouselSearchQuery"
                    class="input-field home-avatar-ribbon-search-input"
                    type="search"
                    :placeholder="$t('home.avatarCarouselSearchPlaceholder')"
                    @keydown.enter.prevent="applyAvatarCarouselSearch"
                >
                <button
                    type="button"
                    class="btn btn-primary btn-sm home-avatar-ribbon-search-submit"
                    @click="applyAvatarCarouselSearch"
                >
                    {{ $t('home.avatarCarouselSearchFind') }}
                </button>
                <button
                    v-if="avatarCarouselSearchQuery !== '' || avatarCarouselSearchAppliedQuery !== ''"
                    type="button"
                    class="btn btn-outline btn-sm home-avatar-ribbon-search-reset"
                    @click="clearAvatarCarouselSearch"
                >
                    {{ $t('home.avatarCarouselSearchReset') }}
                </button>
            </div>
            <p v-if="avatarCarouselSearchUsesSimilarResults" class="muted home-avatar-carousel-tip">
                {{ $t('home.avatarCarouselSearchSimilar') }}
            </p>
            <p v-else-if="avatarCarouselSearchHasNoResults" class="muted home-avatar-carousel-tip">
                {{ $t('home.avatarCarouselSearchNoResults') }}
            </p>

            <div
                v-if="avatarCarouselUsers.length > 0"
                class="home-avatar-carousel"
                @mouseenter="pauseAvatarCarousel"
                @mouseleave="resumeAvatarCarousel"
                @focusin="pauseAvatarCarousel"
                @focusout="resumeAvatarCarousel"
            >
                <template v-if="filteredAvatarCarouselUsers.length > 0">
                    <div class="home-avatar-carousel-marquee" :class="{ 'is-static': !shouldLoopAvatarCarousel }">
                        <div
                            class="home-avatar-carousel-track"
                            :class="{
                                'is-paused': isAvatarCarouselPaused || !shouldLoopAvatarCarousel,
                                'is-static': !shouldLoopAvatarCarousel,
                            }"
                            :style="avatarCarouselTrackStyle"
                        >
                            <router-link
                                v-for="entry in avatarCarouselConveyorItems"
                                :key="entry._avatarTrackKey"
                                class="home-avatar-carousel-card"
                                :to="localizedUserRoute(entry)"
                            >
                                <div class="home-avatar-carousel-avatar-ring">
                                    <img
                                        v-if="avatarUrl(entry)"
                                        :src="avatarUrl(entry)"
                                        :alt="displayName(entry)"
                                        class="avatar home-avatar-carousel-image"
                                        @error="onAvatarImageError"
                                    >
                                    <span v-else class="avatar home-avatar-carousel-placeholder">{{ initials(entry) }}</span>
                                </div>
                                <strong class="home-avatar-carousel-name">{{ displayName(entry) }}</strong>
                                <small v-if="entry.nickname" class="home-avatar-carousel-nickname">@{{ entry.nickname }}</small>
                            </router-link>
                        </div>
                    </div>
                    <p class="muted home-avatar-carousel-tip">{{ $t('home.avatarCarouselTip') }}</p>
                </template>
                <p v-else class="muted home-avatar-carousel-tip">{{ $t('home.avatarCarouselSearchNoResults') }}</p>
            </div>

            <p v-else class="muted">{{ $t('home.avatarCarouselEmpty') }}</p>
        </section>

        <section class="section-card" v-if="isVerifiedUser">
            <h2 class="section-title">{{ $t('home.carouselTitle') }}</h2>
            <p class="section-subtitle">{{ $t('home.carouselSubtitle') }}</p>

            <div
                v-if="carouselItems.length > 0"
                class="home-carousel"
                @mouseenter="pauseCarouselAutoplay"
                @mouseleave="resumeCarouselAutoplay"
                @focusin="pauseCarouselAutoplay"
                @focusout="resumeCarouselAutoplay"
            >
                <div class="home-carousel-marquee">
                    <div
                        class="home-carousel-track"
                        :class="{ 'is-paused': isCarouselAutoplayPaused || carouselItems.length <= 1 }"
                        :style="carouselTrackStyle"
                    >
                        <button
                            v-for="item in carouselConveyorItems"
                            :key="item._carouselTrackKey"
                            type="button"
                            class="home-carousel-card"
                            :class="{ 'is-active': item._sourceIndex === activeCarouselIndex }"
                            @click="handleCarouselCardClick(item._sourceIndex)"
                        >
                            <div class="home-carousel-card-media">
                                <div v-if="item.type === 'video'" class="home-carousel-card-video-placeholder">
                                    {{ $t('home.videoLabel') }}
                                </div>
                                <img
                                    v-else
                                    :src="item.url"
                                    :alt="item.post?.title || 'carousel media'"
                                    class="media-preview home-carousel-card-image"
                                    @error="handlePreviewError($event, item.post?.title || 'media')"
                                    @load="handlePreviewLoad"
                                >
                            </div>
                            <span class="home-carousel-card-type">{{ item.type === 'video' ? 'VIDEO' : 'PHOTO' }}</span>
                            <strong class="home-carousel-card-title">{{ item.post?.title || $t('home.untitledPost') }}</strong>
                            <small class="muted home-carousel-card-author">
                                {{ item.post?.user?.display_name || item.post?.user?.name || $t('common.user') }}
                            </small>
                        </button>
                    </div>
                </div>

                <div class="home-carousel-meta">
                    <strong>{{ currentCarouselItem.post?.title || $t('home.untitledPost') }}</strong>
                    <p class="muted home-carousel-author">
                        {{ $t('home.author') }} {{ currentCarouselItem.post?.user?.display_name || currentCarouselItem.post?.user?.name || $t('common.user') }} ·
                        👁 {{ currentCarouselItem.post?.views_count ?? 0 }}
                    </p>
                    <p class="home-carousel-content">{{ currentCarouselItem.post?.content || '—' }}</p>
                </div>

                <div class="home-carousel-controls">
                    <button class="btn btn-outline btn-sm" @click="selectPreviousCarouselItem">{{ $t('home.previous') }}</button>
                    <button class="btn btn-outline btn-sm" @click="selectNextCarouselItem">{{ $t('home.next') }}</button>
                    <button
                        class="btn btn-primary btn-sm"
                        :disabled="!canOpenCurrentCarouselPost"
                        @click="openCarouselPostModal"
                    >
                        {{ $t('home.openPost') }}
                    </button>
                    <button
                        class="btn btn-outline btn-sm"
                        :disabled="!currentCarouselItem.url"
                        @click="openMedia(currentCarouselItem.url, currentCarouselItem.post?.title || 'carousel media')"
                    >
                        {{ $t('home.openMedia') }}
                    </button>
                    <span class="muted home-carousel-counter">{{ activeCarouselIndex + 1 }} / {{ carouselItems.length }}</span>
                </div>
                <p class="muted home-carousel-tip">{{ $t('home.carouselTip') }}</p>
            </div>

            <p v-else class="muted">{{ $t('home.carouselEmpty') }}</p>
        </section>

        <section class="section-card" v-if="isVerifiedUser">
            <h2 class="section-title">{{ $t('home.communityFeeds') }}</h2>
            <p class="section-subtitle">{{ $t('home.communityFeedsSubtitle') }}</p>

            <div class="discover-tabs">
                <button class="btn" :class="discoverSort === 'popular' ? 'btn-primary' : 'btn-outline'" @click="loadDiscover('popular')">{{ $t('home.popular') }}</button>
                <button class="btn" :class="discoverSort === 'most_viewed' ? 'btn-primary' : 'btn-outline'" @click="loadDiscover('most_viewed')">{{ $t('home.mostViewed') }}</button>
                <button class="btn" :class="discoverSort === 'newest' ? 'btn-primary' : 'btn-outline'" @click="loadDiscover('newest')">{{ $t('home.newest') }}</button>
            </div>

            <p class="muted" v-if="isLoadingDiscover">{{ $t('common.loading') }}</p>
            <p class="muted" v-else-if="discoverPosts.length === 0">{{ $t('home.discoverEmpty') }}</p>

            <div class="post-list" v-else>
                <Post v-for="post in discoverPosts" :key="`discover-post-${post.id}`" :post="post"></Post>
            </div>
        </section>

        <section id="feedback-form" class="section-card home-feedback-card">
            <h2 class="section-title">{{ homeContent.feedback_title }}</h2>
            <p class="section-subtitle">{{ homeContent.feedback_subtitle }}</p>
            <form class="form-grid" @submit.prevent="submitFeedback">
                <input class="input-field" v-model.trim="form.name" type="text" :placeholder="$t('home.feedbackNamePlaceholder')">
                <input class="input-field" v-model.trim="form.email" type="email" :placeholder="$t('home.feedbackEmailPlaceholder')">
                <textarea class="textarea-field" v-model.trim="form.message" :placeholder="$t('home.feedbackMessagePlaceholder')"></textarea>

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
                    <p class="muted">{{ $t('home.feedbackAccepted') }}</p>
                </div>
                <div v-if="feedbackDeliveryState !== 'idle'" class="feature-item">
                    <p class="muted">
                        {{ $t('home.feedbackDeliveryStatus') }} <strong>{{ feedbackDeliveryLabel }}</strong>
                    </p>
                    <p v-if="lastFeedbackMeta?.id" class="muted">
                        {{ $t('home.feedbackRequestNumber') }} <strong>#{{ lastFeedbackMeta.id }}</strong>
                    </p>
                    <p v-if="lastFeedbackMeta?.status" class="muted">
                        {{ $t('home.feedbackRequestStatus') }} <strong>{{ feedbackStatusLabel(lastFeedbackMeta.status) }}</strong>
                    </p>
                </div>

                <button class="btn btn-primary" :disabled="isSending" type="submit">
                    {{ isSending ? $t('common.sending') : $t('home.sendToAdmin') }}
                </button>
            </form>
        </section>

        <div
            v-if="isCarouselPostModalOpen && carouselModalPost"
            :class="carouselPostModalClasses"
            role="dialog"
            aria-modal="true"
            :aria-label="$t('home.carouselPostModalAria')"
            @click.self="closeCarouselPostModal"
        >
            <div :class="carouselPostModalDialogClasses">
                <div class="home-post-modal-head">
                    <strong>{{ $t('home.carouselPostModalTitle') }}</strong>
                    <button class="btn btn-outline btn-sm" type="button" @click="closeCarouselPostModal">{{ $t('common.close') }}</button>
                </div>
                <Post :post="carouselModalPost" display-mode="carousel-modal"></Post>
            </div>
        </div>

        <MediaLightbox ref="mediaLightbox"></MediaLightbox>
    </div>
</template>

<script>
import MediaLightbox from '../components/MediaLightbox.vue'
import Post from '../components/Post.vue'
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../utils/mediaPreview'
import { resolveAvatarCarouselSearch } from '../utils/avatarCarouselSearch.mjs'
import enMessages from '../i18n/messages/en'
import ruMessages from '../i18n/messages/ru'

function resolveMessage(messages, key, fallback = '') {
    if (!messages || typeof messages !== 'object' || typeof key !== 'string' || key.trim() === '') {
        return fallback
    }

    const value = key.split('.').reduce((cursor, part) => {
        if (!cursor || typeof cursor !== 'object') {
            return null
        }

        return Object.prototype.hasOwnProperty.call(cursor, part) ? cursor[part] : null
    }, messages)

    return value ?? fallback
}

const defaultHomeContent = (locale = 'ru') => {
    const dictionary = locale === 'en' ? enMessages : ruMessages
    const featureItems = resolveMessage(dictionary, 'admin.defaultHome.featureItems', [])
    return {
        badge: String(resolveMessage(dictionary, 'admin.defaultHome.badge', '')),
        hero_title: String(resolveMessage(dictionary, 'admin.defaultHome.heroTitle', '')),
        hero_note: String(resolveMessage(dictionary, 'admin.defaultHome.heroNote', '')),
        feature_items: Array.isArray(featureItems)
            ? featureItems.map((item) => String(item ?? '').trim()).filter((item) => item !== '').slice(0, 8)
            : [],
        feedback_title: String(resolveMessage(dictionary, 'admin.defaultHome.feedbackTitle', '')),
        feedback_subtitle: String(resolveMessage(dictionary, 'admin.defaultHome.feedbackSubtitle', '')),
    }
}

export default {
    name: 'Home',

    components: {
        MediaLightbox,
        Post,
    },

    data() {
        return {
            user: null,
            isAuthenticated: false,
            homeContent: defaultHomeContent('ru'),
            avatarCarouselUsers: [],
            avatarCarouselSearchQuery: '',
            avatarCarouselSearchAppliedQuery: '',
            carouselItems: [],
            activeCarouselIndex: 0,
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
            isAvatarCarouselPaused: false,
            isCarouselAutoplayPaused: false,
            isCarouselPostModalOpen: false,
            carouselModalPost: null,
            worldOverviewCities: [],
            isWorldOverviewLoading: false,
            worldOverviewError: '',
            worldOverviewUpdatedAt: '',
            worldOverviewSource: '',
            worldClockTick: Date.now(),
            worldClockTimerId: null,
            worldOverviewRefreshTimerId: null,
            failedAvatarUrls: {},
        }
    },

    computed: {
        isVerifiedUser() {
            return this.isAuthenticated && Boolean(this.user?.email_verified_at)
        },

        isEnglishLocale() {
            return this.resolveHomeContentLocale() === 'en'
        },

        avatarCarouselSearchLocale() {
            return this.isEnglishLocale ? 'en-US' : 'ru-RU'
        },

        avatarCarouselSearchResult() {
            return resolveAvatarCarouselSearch(
                this.avatarCarouselUsers,
                this.avatarCarouselSearchAppliedQuery,
                this.avatarCarouselSearchLocale,
            )
        },

        filteredAvatarCarouselUsers() {
            return Array.isArray(this.avatarCarouselSearchResult?.items)
                ? this.avatarCarouselSearchResult.items
                : []
        },

        avatarCarouselSearchHasNoResults() {
            return this.avatarCarouselSearchResult?.mode === 'none'
        },

        avatarCarouselSearchUsesSimilarResults() {
            return this.avatarCarouselSearchResult?.mode === 'similar'
        },

        shouldLoopAvatarCarousel() {
            return this.avatarCarouselSearchResult?.mode === 'all' && this.filteredAvatarCarouselUsers.length > 1
        },

        avatarCarouselConveyorItems() {
            if (!Array.isArray(this.filteredAvatarCarouselUsers) || this.filteredAvatarCarouselUsers.length === 0) {
                return []
            }

            const loops = this.shouldLoopAvatarCarousel ? [0, 1] : [0]
            return loops.flatMap((loop) => this.filteredAvatarCarouselUsers.map((entry, index) => ({
                ...entry,
                _sourceIndex: index,
                _avatarTrackKey: `avatar-carousel-track-${loop}-${entry.id ?? 'user'}-${index}`,
            })))
        },

        avatarCarouselTrackStyle() {
            const baseDuration = Math.max(14, Math.round(this.filteredAvatarCarouselUsers.length * 2.4))
            return {
                '--home-avatar-carousel-duration': `${baseDuration}s`,
            }
        },

        carouselConveyorItems() {
            if (!Array.isArray(this.carouselItems) || this.carouselItems.length === 0) {
                return []
            }

            const loops = this.carouselItems.length > 1 ? [0, 1] : [0]
            return loops.flatMap((loop) => this.carouselItems.map((item, index) => ({
                ...item,
                _sourceIndex: index,
                _carouselTrackKey: `carousel-track-${loop}-${item.id ?? 'item'}-${index}`,
            })))
        },

        carouselTrackStyle() {
            const baseDuration = Math.max(18, Math.round(this.carouselItems.length * 3.6))
            return {
                '--home-carousel-duration': `${baseDuration}s`,
            }
        },

        currentCarouselItem() {
            if (this.carouselItems.length === 0) {
                return {}
            }

            return this.carouselItems[this.activeCarouselIndex] ?? this.carouselItems[0]
        },

        canOpenCurrentCarouselPost() {
            return Boolean(this.currentCarouselItem?.post?.id && this.currentCarouselItem?.post?.user?.id)
        },

        isCarouselModalVideo() {
            if (!this.carouselModalPost || !Array.isArray(this.carouselModalPost.media)) {
                return false
            }

            return this.carouselModalPost.media.some((item) => item?.type === 'video')
        },

        carouselPostModalDialogClasses() {
            return {
                'home-post-modal-dialog': true,
                'home-post-modal-dialog--video': this.isCarouselModalVideo,
            }
        },

        carouselPostModalClasses() {
            return {
                'home-post-modal': true,
                'home-post-modal--video': this.isCarouselModalVideo,
            }
        },

        quickTitle() {
            if (this.isVerifiedUser) {
                return this.$t('home.quickWelcome')
            }

            return this.isAuthenticated
                ? this.$t('home.quickVerify')
                : this.$t('home.quickStart')
        },

        feedbackDeliveryLabel() {
            const map = {
                sending: this.$t('home.feedbackSending'),
                sent: this.$t('home.feedbackSent'),
                failed: this.$t('home.feedbackFailed'),
            }

            return map[this.feedbackDeliveryState] ?? '—'
        },

        quickStats() {
            const access = this.isVerifiedUser
                ? this.$t('home.quickAccessFull')
                : (this.isAuthenticated ? this.$t('home.quickAccessPending') : this.$t('home.quickAccessGuest'))

            const media = this.isVerifiedUser
                ? this.$t('home.quickMediaCount', {count: this.carouselItems.length})
                : this.$t('home.quickAfterAuth')

            const chats = this.isVerifiedUser
                ? this.$t('home.quickChatsActive')
                : this.$t('home.quickAfterLogin')

            return [
                {
                    label: this.$t('home.quickAccessLabel'),
                    value: access,
                },
                {
                    label: this.$t('home.quickCarouselLabel'),
                    value: media,
                },
                {
                    label: this.$t('home.quickChatsLabel'),
                    value: chats,
                },
            ]
        },

        homeShowcaseCards() {
            return [
                {
                    kicker: '01',
                    title: this.$t('home.showcaseCard1Title'),
                    note: this.$t('home.showcaseCard1Note'),
                },
                {
                    kicker: '02',
                    title: this.$t('home.showcaseCard2Title'),
                    note: this.$t('home.showcaseCard2Note'),
                },
                {
                    kicker: '03',
                    title: this.$t('home.showcaseCard3Title'),
                    note: this.$t('home.showcaseCard3Note'),
                },
            ]
        },

        worldOverviewTitle() {
            return this.$t('home.worldTitle')
        },

        worldOverviewUpdatedLabel() {
            if (!this.worldOverviewUpdatedAt) {
                return ''
            }

            const date = new Date(this.worldOverviewUpdatedAt)
            if (Number.isNaN(date.getTime())) {
                return ''
            }

            const prefix = this.$t('home.updatedPrefix')
            const locale = this.isEnglishLocale ? 'en-GB' : 'ru-RU'

            return `${prefix}: ${date.toLocaleTimeString(locale, {
                hour: '2-digit',
                minute: '2-digit',
            })}`
        },
    },

    async mounted() {
        if (typeof window !== 'undefined') {
            window.addEventListener('keydown', this.handleHomeKeydown)
        }

        this.startWorldClock()
        this.startWorldOverviewAutoRefresh()

        await this.bootstrapPage()
    },

    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('keydown', this.handleHomeKeydown)
        }

        if (typeof document !== 'undefined') {
            document.body.classList.remove('no-scroll')
        }

        this.stopWorldClock()
        this.stopWorldOverviewAutoRefresh()
    },

    watch: {
        isCarouselPostModalOpen(isOpen) {
            if (typeof document === 'undefined') {
                return
            }

            document.body.classList.toggle('no-scroll', Boolean(isOpen))
        },
        '$route.params.locale': {
            handler() {
                this.loadHomeContent()
                this.loadWorldOverview()
            },
        },
    },

    methods: {
        resolveHomeContentLocale() {
            return this.$route?.params?.locale === 'en' ? 'en' : 'ru'
        },

        localizedUserRoute(user) {
            return {
                name: 'user.show',
                params: {
                    id: user.id,
                    locale: this.resolveHomeContentLocale(),
                },
            }
        },

        displayName(user) {
            return user?.display_name || user?.name || this.$t('common.user')
        },

        initials(user) {
            const source = this.displayName(user).trim()
            return source ? source.slice(0, 1).toUpperCase() : 'U'
        },

        normalizeAvatarUrl(value) {
            const raw = String(value || '').trim()
            if (raw === '') {
                return ''
            }

            if (typeof window === 'undefined') {
                return raw
            }

            try {
                return new URL(raw, window.location.origin).href
            } catch (_error) {
                return raw
            }
        },

        onAvatarImageError(event) {
            const target = event?.target
            const sources = [
                target?.getAttribute?.('src') || '',
                target?.currentSrc || '',
                target?.src || '',
            ]

            const next = { ...this.failedAvatarUrls }
            for (const source of sources) {
                const normalized = this.normalizeAvatarUrl(source)
                if (normalized !== '') {
                    next[normalized] = true
                }
            }

            this.failedAvatarUrls = next
        },

        avatarUrl(user) {
            const raw = String(user?.avatar_url || '').trim()
            if (raw === '') {
                return null
            }

            const normalized = this.normalizeAvatarUrl(raw)
            return this.failedAvatarUrls[normalized] ? null : raw
        },

        handlePreviewError(event, label = 'Preview unavailable') {
            applyImagePreviewFallback(event, label)
        },

        handlePreviewLoad(event) {
            resetImagePreviewFallback(event)
        },

        async bootstrapPage() {
            await Promise.all([
                this.loadHomeContent(),
                this.loadWorldOverview(),
                this.loadCurrentUser(),
            ])

            if (this.isVerifiedUser) {
                await Promise.all([
                    this.loadAvatarCarouselUsers(),
                    this.loadCarousel(),
                    this.loadDiscover(this.discoverSort),
                ])
            } else {
                this.avatarCarouselUsers = []
                this.isAvatarCarouselPaused = false
                this.isCarouselAutoplayPaused = false
            }

            this.prefillAuthorizedUser()
        },

        async loadWorldOverview() {
            this.isWorldOverviewLoading = true
            this.worldOverviewError = ''

            try {
                const response = await axios.get('/api/site/world-overview', {
                    params: {
                        locale: this.resolveHomeContentLocale(),
                    },
                    timeout: 12000,
                })

                const payload = response.data?.data ?? {}
                this.worldOverviewCities = Array.isArray(payload.cities) ? payload.cities : []
                this.worldOverviewUpdatedAt = String(payload.updated_at ?? '')
                this.worldOverviewSource = String(payload.source ?? '')
            } catch (error) {
                this.worldOverviewError = this.$t('home.worldLoadError')
            } finally {
                this.isWorldOverviewLoading = false
            }
        },

        startWorldClock() {
            this.stopWorldClock()
            this.worldClockTick = Date.now()
            this.worldClockTimerId = window.setInterval(() => {
                this.worldClockTick = Date.now()
            }, 1000)
        },

        stopWorldClock() {
            if (!this.worldClockTimerId) {
                return
            }

            window.clearInterval(this.worldClockTimerId)
            this.worldClockTimerId = null
        },

        startWorldOverviewAutoRefresh() {
            this.stopWorldOverviewAutoRefresh()
            this.worldOverviewRefreshTimerId = window.setInterval(() => {
                this.loadWorldOverview()
            }, 5 * 60 * 1000)
        },

        stopWorldOverviewAutoRefresh() {
            if (!this.worldOverviewRefreshTimerId) {
                return
            }

            window.clearInterval(this.worldOverviewRefreshTimerId)
            this.worldOverviewRefreshTimerId = null
        },

        formatWorldCityTime(city) {
            const timezone = String(city?.timezone ?? '').trim()
            if (timezone === '') {
                return '--:--:--'
            }

            try {
                const locale = this.isEnglishLocale ? 'en-GB' : 'ru-RU'
                return new Intl.DateTimeFormat(locale, {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                    timeZone: timezone,
                }).format(new Date(this.worldClockTick))
            } catch (error) {
                return '--:--:--'
            }
        },

        formatWorldCityTemperature(city) {
            const temperature = Number(city?.weather?.temperature_c)
            if (!Number.isFinite(temperature)) {
                return this.$t('home.naShort')
            }

            const prefix = temperature > 0 ? '+' : ''
            return `${prefix}${temperature.toFixed(1)}°C`
        },

        formatWorldCityWind(city) {
            const windSpeed = Number(city?.weather?.wind_speed_kmh)
            if (!Number.isFinite(windSpeed)) {
                return this.$t('home.windNa')
            }

            return this.$t('home.windValue', {value: windSpeed.toFixed(0)})
        },

        openMedia(url, alt = null) {
            this.$refs.mediaLightbox?.open(url, alt || this.$t('home.mediaAlt'))
        },

        normalizeHomeContent(payload) {
            const fallback = defaultHomeContent(this.resolveHomeContentLocale())
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
            const locale = this.resolveHomeContentLocale()

            try {
                const response = await axios.get('/api/site/home-content', {
                    params: {locale},
                })
                this.homeContent = this.normalizeHomeContent(response.data.data ?? {})
            } catch (error) {
                this.homeContent = defaultHomeContent(locale)
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

        normalizeAvatarCarouselUsers(users) {
            const entries = []

            if (this.user?.id && !this.user?.is_admin) {
                entries.push({
                    ...this.user,
                    is_followed: false,
                })
            }

            if (Array.isArray(users)) {
                entries.push(...users)
            }

            const seenIds = new Set()
            const locale = this.isEnglishLocale ? 'en' : 'ru'

            return entries
                .filter((entry) => Number.isFinite(Number(entry?.id)))
                .filter((entry) => !Boolean(entry?.is_admin))
                .filter((entry) => {
                    const normalizedId = Number(entry.id)
                    if (seenIds.has(normalizedId)) {
                        return false
                    }

                    seenIds.add(normalizedId)
                    return true
                })
                .sort((first, second) => this.displayName(first).localeCompare(this.displayName(second), locale))
        },

        async loadAvatarCarouselUsers() {
            const collectedUsers = []
            let page = 1
            let lastPage = 1

            try {
                do {
                    const response = await axios.get('/api/users', {
                        params: {
                            per_page: 50,
                            page,
                        },
                    })

                    const chunk = Array.isArray(response.data?.data) ? response.data.data : []
                    collectedUsers.push(...chunk)

                    const reportedLastPage = Number(response.data?.meta?.last_page)
                    lastPage = Number.isFinite(reportedLastPage) && reportedLastPage > 0
                        ? reportedLastPage
                        : page
                    page += 1
                } while (page <= lastPage)
            } catch (_error) {
                this.avatarCarouselUsers = this.normalizeAvatarCarouselUsers([])
                return
            }

            this.avatarCarouselUsers = this.normalizeAvatarCarouselUsers(collectedUsers)
        },

        async loadCarousel() {
            const response = await axios.get('/api/posts/carousel', { params: { limit: 40 } })
            this.carouselItems = response.data.data ?? []
            this.activeCarouselIndex = 0
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

        normalizeCarouselIndex(index) {
            const length = this.carouselItems.length
            if (length <= 0) {
                return 0
            }

            const normalized = Number(index)
            if (!Number.isFinite(normalized)) {
                return 0
            }

            const floored = Math.floor(normalized)
            return ((floored % length) + length) % length
        },

        selectCarouselItem(index) {
            if (this.carouselItems.length <= 1) {
                this.activeCarouselIndex = 0
                return
            }

            this.activeCarouselIndex = this.normalizeCarouselIndex(index)
        },

        handleCarouselCardClick(index) {
            const normalizedIndex = this.normalizeCarouselIndex(index)
            const wasActive = normalizedIndex === this.activeCarouselIndex

            this.selectCarouselItem(normalizedIndex)

            if (wasActive) {
                this.openCarouselPostModal()
            }
        },

        selectPreviousCarouselItem() {
            this.selectCarouselItem(this.activeCarouselIndex - 1)
        },

        selectNextCarouselItem() {
            this.selectCarouselItem(this.activeCarouselIndex + 1)
        },

        pauseCarouselAutoplay() {
            this.isCarouselAutoplayPaused = true
        },

        resumeCarouselAutoplay() {
            this.isCarouselAutoplayPaused = false
        },

        pauseAvatarCarousel() {
            this.isAvatarCarouselPaused = true
        },

        resumeAvatarCarousel() {
            this.isAvatarCarouselPaused = false
        },

        clearAvatarCarouselSearch() {
            this.avatarCarouselSearchQuery = ''
            this.avatarCarouselSearchAppliedQuery = ''
        },

        applyAvatarCarouselSearch() {
            this.avatarCarouselSearchAppliedQuery = this.avatarCarouselSearchQuery
        },

        buildCarouselModalPost(item) {
            if (!item?.post || typeof item.post !== 'object') {
                return null
            }

            if (!item.post.id || !item.post.user?.id) {
                return null
            }

            const fallbackMedia = item.url
                ? [{
                    id: `carousel-modal-media-${item.post.id}-${item.type || 'image'}`,
                    type: item.type === 'video' ? 'video' : 'image',
                    url: item.url,
                }]
                : []

            const media = Array.isArray(item.post.media) && item.post.media.length > 0
                ? item.post.media
                : fallbackMedia

            return {
                ...item.post,
                user: {
                    ...item.post.user,
                },
                media,
                likes_count: Number(item.post.likes_count ?? 0),
                comments_count: Number(item.post.comments_count ?? 0),
                reposted_by_posts_count: Number(item.post.reposted_by_posts_count ?? 0),
                is_liked: Boolean(item.post.is_liked),
                views_count: Number(item.post.views_count ?? 0),
            }
        },

        openCarouselPostModal() {
            const nextPost = this.buildCarouselModalPost(this.currentCarouselItem)
            if (!nextPost) {
                return
            }

            this.carouselModalPost = nextPost
            this.isCarouselPostModalOpen = true
            this.pauseCarouselAutoplay()
        },

        closeCarouselPostModal() {
            this.isCarouselPostModalOpen = false
            this.carouselModalPost = null
            this.resumeCarouselAutoplay()
        },

        handleHomeKeydown(event) {
            const key = String(event?.key || '').toLowerCase()
            if (key !== 'escape' && key !== 'esc') {
                return
            }

            if (!this.isCarouselPostModalOpen) {
                return
            }

            event.preventDefault()
            this.closeCarouselPostModal()
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

                let message = error.response?.data?.message ?? this.$t('home.feedbackSendError')

                if (error.code === 'ECONNABORTED') {
                    message = this.$t('home.feedbackTimeoutError')
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
                new: this.$t('home.statusNew'),
                in_progress: this.$t('home.statusInProgress'),
                resolved: this.$t('home.statusResolved'),
            }

            return labels[status] ?? status
        },
    }
}
</script>
