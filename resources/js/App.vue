<template>
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
                <img v-if="user?.avatar_url" :src="user.avatar_url" alt="avatar" class="avatar avatar-sm">
                <span v-else class="avatar avatar-sm avatar-placeholder">{{ initials(user) }}</span>
                <span>{{ displayName(user) }}</span>
                <span v-if="!isEmailVerified" class="badge">{{ $t('auth.emailNotVerified') }}</span>
                <button class="btn btn-danger btn-sm" @click.prevent="logout">{{ $t('auth.logout') }}</button>
            </div>
        </header>

        <main>
            <router-view @auth-changed="syncAuthState" @chat-unread-updated="onChatUnreadUpdated"></router-view>
        </main>
    </div>
</template>

<script>
import BrandLogo from './components/BrandLogo.vue'

export default {
    name: 'App',
    components: {
        BrandLogo
    },

    data() {
        return {
            isAuthenticated: false,
            user: null,
            chatUnreadTotal: 0,
            unreadPollingTimerId: null,
            authSyncPromise: null,
        }
    },

    mounted() {
        this.syncAuthState()
    },

    beforeUnmount() {
        this.stopUnreadPolling()
    },

    watch: {
        $route() {
            this.syncAuthState()
        }
    },

    computed: {
        isEmailVerified() {
            return Boolean(this.user?.email_verified_at)
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

        initials(user) {
            const source = this.displayName(user).trim()
            if (!source) {
                return 'U'
            }

            return source.slice(0, 1).toUpperCase()
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
                    } else {
                        this.chatUnreadTotal = 0
                        this.stopUnreadPolling()
                    }
                } catch (error) {
                    this.user = null
                    this.isAuthenticated = false
                    this.chatUnreadTotal = 0
                    this.stopUnreadPolling()
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
                await axios.post('/logout')
            } finally {
                this.user = null
                this.isAuthenticated = false
                this.chatUnreadTotal = 0
                this.authSyncPromise = null
                this.stopUnreadPolling()
                await this.$router.push(this.localizedRoute('home'))
            }
        }
    }
}
</script>
