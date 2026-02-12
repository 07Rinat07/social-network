<template>
    <div class="app-shell">
        <header class="topbar glass-panel">
            <router-link class="brand" :to="{ name: 'home' }" aria-label="Solid Social">
                <BrandLogo />
            </router-link>

            <nav class="nav-links">
                <router-link class="nav-link" :to="{ name: 'home' }">Главная</router-link>

                <template v-if="isAuthenticated">
                    <template v-if="isEmailVerified">
                        <router-link class="nav-link" :to="{ name: 'user.index' }">Пользователи</router-link>
                        <router-link class="nav-link" :to="{ name: 'user.feed' }">Лента</router-link>
                        <router-link class="nav-link" :to="{ name: 'user.personal' }">Кабинет</router-link>
                        <router-link class="nav-link" :to="{ name: 'radio.index' }">Радио</router-link>
                        <router-link class="nav-link" :to="{ name: 'iptv.index' }">IPTV</router-link>
                        <router-link class="nav-link" :to="{ name: 'user.feedback' }">Мои обращения</router-link>
                        <router-link class="nav-link" :to="{ name: 'chat.index' }">
                            Чаты
                            <span
                                v-if="chatUnreadTotal > 0"
                                class="badge"
                                style="margin-left: 0.35rem; font-size: 0.72rem; min-width: 1.8rem; text-align: center;"
                            >
                                {{ chatUnreadBadge }}
                            </span>
                        </router-link>
                        <router-link v-if="user?.is_admin" class="nav-link" :to="{ name: 'admin.index' }">Админка</router-link>
                    </template>
                    <template v-else>
                        <router-link class="nav-link" :to="{ name: 'auth.verify' }">Подтвердить email</router-link>
                    </template>
                </template>

                <template v-else>
                    <router-link class="nav-link" :to="{ name: 'user.login' }">Вход</router-link>
                    <router-link class="nav-link" :to="{ name: 'user.registration' }">Регистрация</router-link>
                </template>
            </nav>

            <div class="auth-chip" v-if="isAuthenticated">
                <img v-if="user?.avatar_url" :src="user.avatar_url" alt="avatar" class="avatar avatar-sm">
                <span v-else class="avatar avatar-sm avatar-placeholder">{{ initials(user) }}</span>
                <span>{{ displayName(user) }}</span>
                <span v-if="!isEmailVerified" class="badge">Email не подтвержден</span>
                <button class="btn btn-danger btn-sm" @click.prevent="logout">Выход</button>
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

        chatUnreadBadge() {
            if (this.chatUnreadTotal <= 0) {
                return ''
            }

            return this.chatUnreadTotal > 99 ? '99+' : String(this.chatUnreadTotal)
        }
    },

    methods: {
        displayName(user) {
            return user?.display_name || user?.name || 'Пользователь'
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

        async logout() {
            try {
                await axios.post('/logout')
            } finally {
                this.user = null
                this.isAuthenticated = false
                this.chatUnreadTotal = 0
                this.authSyncPromise = null
                this.stopUnreadPolling()
                await this.$router.push({ name: 'home' })
            }
        }
    }
}
</script>
