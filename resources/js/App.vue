<template>
    <div class="app-root">
        <div class="app-shell">
            <header class="topbar glass-panel">
                <router-link class="brand" :to="localizedRoute('home')" aria-label="Solid Social">
                    <BrandLogo />
                </router-link>

                <nav class="nav-links">
                    <router-link class="nav-link" :to="localizedRoute('home')">{{ $t('nav.home') }}</router-link>
                    <router-link class="nav-link" :to="localizedRoute('home', {hash: '#feedback-form'})">{{ $t('nav.feedback') }}</router-link>

                    <template v-if="isAuthenticated">
                        <template v-if="isEmailVerified">
                            <router-link class="nav-link" :to="localizedRoute('user.index')">{{ $t('nav.users') }}</router-link>
                            <router-link class="nav-link" :to="localizedRoute('user.feed')">{{ $t('nav.feed') }}</router-link>
                            <router-link class="nav-link" :to="localizedRoute('user.personal')">{{ $t('nav.cabinet') }}</router-link>
                            <router-link class="nav-link" :to="localizedRoute('radio.index')">{{ $t('nav.radio') }}</router-link>
                            <router-link class="nav-link" :to="localizedRoute('iptv.index')">{{ $t('nav.iptv') }}</router-link>
                            <router-link class="nav-link" :to="localizedRoute('user.feedback')">{{ $t('nav.myRequests') }}</router-link>
                            <router-link class="nav-link" :to="localizedRoute('chat.index')">
                                {{ $t('nav.chats') }}
                                <span
                                    v-if="chatUnreadTotal > 0"
                                    class="badge"
                                    style="margin-left: 0.35rem; font-size: 0.72rem; min-width: 1.8rem; text-align: center;"
                                >
                                    {{ chatUnreadBadge }}
                                </span>
                            </router-link>
                            <router-link v-if="user?.is_admin" class="nav-link" :to="localizedRoute('admin.index')">{{ $t('nav.admin') }}</router-link>
                        </template>
                        <template v-else>
                            <router-link class="nav-link" :to="localizedRoute('auth.verify')">{{ $t('nav.verifyEmail') }}</router-link>
                        </template>
                    </template>

                    <template v-else>
                        <router-link class="nav-link" :to="localizedRoute('user.login')">{{ $t('nav.login') }}</router-link>
                        <router-link class="nav-link" :to="localizedRoute('user.registration')">{{ $t('nav.registration') }}</router-link>
                    </template>

                    <div class="lang-switch" :aria-label="$t('common.languageSwitcher')">
                        <button
                            type="button"
                            class="lang-switch-btn"
                            :class="{'is-active': currentLocale === 'ru'}"
                            @click="switchLocale('ru')"
                        >
                            {{ $t('lang.ru') }}
                        </button>
                        <button
                            type="button"
                            class="lang-switch-btn"
                            :class="{'is-active': currentLocale === 'en'}"
                            @click="switchLocale('en')"
                        >
                            {{ $t('lang.en') }}
                        </button>
                    </div>
                </nav>

                <div class="auth-chip" v-if="isAuthenticated">
                    <img
                        v-if="avatarUrl(user)"
                        :src="avatarUrl(user)"
                        alt="avatar"
                        class="avatar avatar-sm"
                        @error="onAvatarImageError"
                    >
                    <span v-else class="avatar avatar-sm avatar-placeholder">{{ initials(user) }}</span>
                    <span>{{ displayName(user) }}</span>
                    <span v-if="!isEmailVerified" class="badge">{{ $t('auth.emailNotVerified') }}</span>
                    <button class="btn btn-danger btn-sm" @click.prevent="logout">{{ $t('auth.logout') }}</button>
                </div>
            </header>

            <div class="app-workspace">
                <main class="app-main">
                    <router-view @auth-changed="syncAuthState" @chat-unread-updated="onChatUnreadUpdated"></router-view>
                </main>
            </div>
        </div>

        <div v-if="canUsePersistentWidgets" class="widget-docks">
            <PersistentRadioWidget
                class="app-widget app-widget--left"
                :active="canUsePersistentWidgets"
                :user="user"
            ></PersistentRadioWidget>

            <PersistentChatWidget
                class="app-widget app-widget--right"
                :active="canUsePersistentWidgets"
                :user="user"
                @unread-updated="onChatUnreadUpdated"
            ></PersistentChatWidget>
        </div>
    </div>
</template>

<script>
import BrandLogo from './components/BrandLogo.vue'
import PersistentRadioWidget from './components/widgets/PersistentRadioWidget.vue'
import PersistentChatWidget from './components/widgets/PersistentChatWidget.vue'
import globalTropicalBeachBackground from '../images/home-tropical-beach.jpg'

const ACTIVITY_HEARTBEAT_SECONDS = 30
const ACTIVITY_INIT_SECONDS = 5
const ACTIVITY_FEATURE_BY_ROUTE = {
    home: 'social',
    'user.index': 'social',
    'user.show': 'social',
    'user.feed': 'social',
    'user.personal': 'social',
    'chat.index': 'chats',
    'radio.index': 'radio',
    'iptv.index': 'iptv',
}

export default {
    name: 'App',
    components: {
        BrandLogo,
        PersistentRadioWidget,
        PersistentChatWidget,
    },

    data() {
        return {
            isAuthenticated: false,
            user: null,
            chatUnreadTotal: 0,
            unreadPollingTimerId: null,
            authSyncPromise: null,
            failedAvatarUrls: {},
            activityHeartbeatTimerId: null,
            activityCurrentFeature: null,
            activityCurrentSessionId: '',
            activitySyncPromise: null,
            visibilityChangeHandler: null,
        }
    },

    mounted() {
        this.applyGlobalBackground()
        this.syncAuthState()

        if (typeof document !== 'undefined') {
            this.visibilityChangeHandler = () => {
                this.syncActivityTracking()
            }

            document.addEventListener('visibilitychange', this.visibilityChangeHandler, {passive: true})
        }

        this.startActivityHeartbeat()
        this.syncActivityTracking()
    },

    beforeUnmount() {
        this.clearGlobalBackground()
        this.stopUnreadPolling()
        this.stopActivityHeartbeat()
        this.syncActivityTracking({forceStop: true})

        if (typeof document !== 'undefined' && this.visibilityChangeHandler) {
            document.removeEventListener('visibilitychange', this.visibilityChangeHandler)
        }
    },

    watch: {
        $route() {
            this.syncAuthState()
            this.syncActivityTracking()
        },

        canUsePersistentWidgets(next) {
            if (next) {
                this.startActivityHeartbeat()
                this.syncActivityTracking()
                return
            }

            this.stopActivityHeartbeat()
            this.syncActivityTracking({forceStop: true})
        }
    },

    computed: {
        isEmailVerified() {
            return Boolean(this.user?.email_verified_at)
        },

        canUsePersistentWidgets() {
            return this.isAuthenticated && this.isEmailVerified
        },

        currentLocale() {
            return this.$route?.params?.locale === 'en' ? 'en' : 'ru'
        },

        chatUnreadBadge() {
            if (this.chatUnreadTotal <= 0) {
                return ''
            }

            return this.chatUnreadTotal > 99 ? '99+' : String(this.chatUnreadTotal)
        }
    },

    methods: {
        applyGlobalBackground() {
            if (typeof document === 'undefined') {
                return
            }

            document.body.classList.add('home-route-bg')
            document.body.style.setProperty('--home-route-bg-image', `url(${globalTropicalBeachBackground})`)
        },

        clearGlobalBackground() {
            if (typeof document === 'undefined') {
                return
            }

            document.body.classList.remove('home-route-bg')
            document.body.style.removeProperty('--home-route-bg-image')
        },

        localizedRoute(name, options = {}) {
            const sourceParams = (options && typeof options.params === 'object' && options.params !== null)
                ? options.params
                : {}

            return {
                ...options,
                name,
                params: {
                    ...sourceParams,
                    locale: this.currentLocale,
                },
            }
        },

        displayName(user) {
            return user?.display_name || user?.name || this.$t('common.user')
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

        initials(user) {
            const source = this.displayName(user).trim()
            if (!source) {
                return 'U'
            }

            return source.slice(0, 1).toUpperCase()
        },

        resolveActivityFeature(routeName) {
            const key = String(routeName || '')
            return ACTIVITY_FEATURE_BY_ROUTE[key] ?? null
        },

        generateActivitySessionId(feature) {
            const safeFeature = String(feature || '').replace(/[^a-z0-9_-]/gi, '').slice(0, 20) || 'feature'
            const timestampPart = Date.now().toString(36)
            let randomPart = ''

            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                randomPart = crypto.randomUUID().replace(/-/g, '').slice(0, 24)
            } else {
                randomPart = `${Math.random().toString(36).slice(2, 14)}${Math.random().toString(36).slice(2, 10)}`
            }

            return `${safeFeature}:${timestampPart}:${randomPart}`.slice(0, 120)
        },

        startActivityHeartbeat() {
            if (typeof window === 'undefined' || this.activityHeartbeatTimerId) {
                return
            }

            this.activityHeartbeatTimerId = window.setInterval(() => {
                this.sendPeriodicActivityHeartbeat()
            }, ACTIVITY_HEARTBEAT_SECONDS * 1000)
        },

        stopActivityHeartbeat() {
            if (!this.activityHeartbeatTimerId || typeof window === 'undefined') {
                return
            }

            window.clearInterval(this.activityHeartbeatTimerId)
            this.activityHeartbeatTimerId = null
        },

        async sendActivityHeartbeat({feature, sessionId, elapsedSeconds, ended = false}) {
            if (!this.canUsePersistentWidgets) {
                return
            }

            const safeFeature = String(feature || '').trim()
            const safeSessionId = String(sessionId || '').trim()
            if (!safeFeature || !safeSessionId) {
                return
            }

            const rawSeconds = Number(elapsedSeconds || ACTIVITY_HEARTBEAT_SECONDS)
            const safeSeconds = Number.isFinite(rawSeconds)
                ? Math.max(1, Math.min(Math.round(rawSeconds), 300))
                : ACTIVITY_HEARTBEAT_SECONDS

            try {
                await axios.post('/api/activity/heartbeat', {
                    feature: safeFeature,
                    session_id: safeSessionId,
                    elapsed_seconds: safeSeconds,
                    ended: Boolean(ended),
                })
            } catch (_error) {
                // Heartbeats are best-effort and must not block UI.
            }
        },

        async sendPeriodicActivityHeartbeat() {
            if (!this.activityCurrentFeature || !this.activityCurrentSessionId) {
                return
            }

            if (typeof document !== 'undefined' && document.hidden) {
                return
            }

            await this.sendActivityHeartbeat({
                feature: this.activityCurrentFeature,
                sessionId: this.activityCurrentSessionId,
                elapsedSeconds: ACTIVITY_HEARTBEAT_SECONDS,
                ended: false,
            })
        },

        async syncActivityTracking(options = {}) {
            if (this.activitySyncPromise) {
                return this.activitySyncPromise
            }

            this.activitySyncPromise = (async () => {
                const forceStop = Boolean(options?.forceStop)
                const canTrack = this.canUsePersistentWidgets
                    && !forceStop
                    && (typeof document === 'undefined' || !document.hidden)

                const nextFeature = canTrack
                    ? this.resolveActivityFeature(this.$route?.name)
                    : null

                if (nextFeature === this.activityCurrentFeature) {
                    return
                }

                const previousFeature = this.activityCurrentFeature
                const previousSessionId = this.activityCurrentSessionId

                this.activityCurrentFeature = nextFeature
                this.activityCurrentSessionId = nextFeature
                    ? this.generateActivitySessionId(nextFeature)
                    : ''

                if (previousFeature && previousSessionId) {
                    await this.sendActivityHeartbeat({
                        feature: previousFeature,
                        sessionId: previousSessionId,
                        elapsedSeconds: ACTIVITY_INIT_SECONDS,
                        ended: true,
                    })
                }

                if (nextFeature && this.activityCurrentSessionId) {
                    await this.sendActivityHeartbeat({
                        feature: nextFeature,
                        sessionId: this.activityCurrentSessionId,
                        elapsedSeconds: ACTIVITY_INIT_SECONDS,
                        ended: false,
                    })
                }
            })()

            try {
                await this.activitySyncPromise
            } finally {
                this.activitySyncPromise = null
            }
        },

        async syncAuthState() {
            if (this.authSyncPromise) {
                return this.authSyncPromise
            }

            this.authSyncPromise = (async () => {
                try {
                    const response = await axios.get('/api/user')
                    this.user = response.data
                    this.isAuthenticated = true

                    if (this.isEmailVerified) {
                        this.startUnreadPolling()
                        await this.loadChatUnreadSummary()
                        this.startActivityHeartbeat()
                        await this.syncActivityTracking()
                    } else {
                        this.chatUnreadTotal = 0
                        this.stopUnreadPolling()
                        this.stopActivityHeartbeat()
                        await this.syncActivityTracking({forceStop: true})
                    }
                } catch (error) {
                    this.user = null
                    this.isAuthenticated = false
                    this.chatUnreadTotal = 0
                    this.stopUnreadPolling()
                    this.stopActivityHeartbeat()
                    await this.syncActivityTracking({forceStop: true})
                }
            })()

            try {
                await this.authSyncPromise
            } finally {
                this.authSyncPromise = null
            }
        },

        async loadChatUnreadSummary() {
            if (!this.isAuthenticated) {
                this.chatUnreadTotal = 0
                return
            }

            try {
                const response = await axios.get('/api/chats/unread-summary')
                this.chatUnreadTotal = Number(response.data?.data?.total_unread ?? 0)
            } catch (error) {
                this.chatUnreadTotal = 0
            }
        },

        startUnreadPolling() {
            this.stopUnreadPolling()
            this.unreadPollingTimerId = window.setInterval(() => {
                this.loadChatUnreadSummary()
            }, 20000)
        },

        stopUnreadPolling() {
            if (this.unreadPollingTimerId) {
                window.clearInterval(this.unreadPollingTimerId)
                this.unreadPollingTimerId = null
            }
        },

        onChatUnreadUpdated(total) {
            const next = Number(total)
            this.chatUnreadTotal = Number.isFinite(next) ? Math.max(0, next) : 0
        },

        async switchLocale(nextLocale) {
            const normalized = nextLocale === 'en' ? 'en' : 'ru'
            if (normalized === this.currentLocale) {
                return
            }

            this.$setLocale?.(normalized)

            if (!this.$route?.name) {
                await this.$router.push({path: `/${normalized}`})
                return
            }

            const routeParams = {
                ...(this.$route.params || {}),
                locale: normalized,
            }

            await this.$router.push({
                name: this.$route.name,
                params: routeParams,
                query: this.$route.query,
                hash: this.$route.hash,
            })
        },

        async logout() {
            try {
                await this.syncActivityTracking({forceStop: true})
                await axios.post('/logout')
            } finally {
                this.user = null
                this.isAuthenticated = false
                this.chatUnreadTotal = 0
                this.authSyncPromise = null
                this.stopUnreadPolling()
                this.stopActivityHeartbeat()
                await this.$router.push(this.localizedRoute('home'))
            }
        }
    }
}
</script>
