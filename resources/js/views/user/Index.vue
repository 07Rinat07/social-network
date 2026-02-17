<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">{{ $t('users.title') }}</h1>
            <p class="section-subtitle">{{ $t('users.subtitle') }}</p>

            <div class="user-search-row">
                <input
                    class="input-field"
                    v-model.trim="searchQuery"
                    type="search"
                    :placeholder="$t('users.searchPlaceholder')"
                    @input="handleSearchInput"
                >
                <button
                    class="btn btn-outline btn-sm"
                    type="button"
                    :disabled="searchQuery === ''"
                    @click="clearSearch"
                >
                    {{ $t('users.searchReset') }}
                </button>
            </div>
            <p class="muted user-search-note">{{ $t('users.searchHint') }}</p>

            <p v-if="isLoadingUsers" class="muted user-search-state">{{ $t('users.loading') }}</p>
            <p v-else-if="users.length === 0" class="muted user-search-state">
                {{ $t('users.empty') }}
            </p>

            <div class="user-list">
                <div class="user-item" v-for="user in users" :key="user.id">
                    <router-link :to="localizedUserRoute(user)">
                        <div style="display: flex; align-items: center; gap: 0.55rem;">
                            <img
                                v-if="avatarUrl(user)"
                                :src="avatarUrl(user)"
                                alt="avatar"
                                class="avatar avatar-sm"
                                @error="onAvatarImageError"
                            >
                            <span v-else class="avatar avatar-sm avatar-placeholder">{{ initials(user) }}</span>
                            <strong>{{ user.display_name || user.name }}</strong>
                        </div>
                        <p class="muted" style="margin: 0.2rem 0 0;" v-if="user.nickname">@{{ user.nickname }}</p>
                        <p class="muted" style="margin: 0.2rem 0 0;">{{ $t('users.id', {id: user.id}) }}</p>
                    </router-link>
                    <button
                        class="btn"
                        :class="user.is_followed ? 'btn-outline' : 'btn-primary'"
                        @click.prevent="toggleFollowing(user)"
                    >
                        {{ user.is_followed ? $t('users.unfollow') : $t('users.follow') }}
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: 'Index',

    data() {
        return {
            users: [],
            searchQuery: '',
            isLoadingUsers: false,
            searchDebounceTimerId: null,
            usersRequestId: 0,
            failedAvatarUrls: {},
        }
    },

    mounted() {
        this.getUsers()
    },

    beforeUnmount() {
        if (this.searchDebounceTimerId) {
            window.clearTimeout(this.searchDebounceTimerId)
            this.searchDebounceTimerId = null
        }
    },

    methods: {
        localizedUserRoute(user) {
            return {
                name: 'user.show',
                params: {
                    id: user.id,
                    locale: this.$route?.params?.locale === 'en' ? 'en' : 'ru',
                },
            }
        },

        initials(user) {
            const source = (user?.display_name || user?.name || '').trim()
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

        handleSearchInput() {
            if (this.searchDebounceTimerId) {
                window.clearTimeout(this.searchDebounceTimerId)
                this.searchDebounceTimerId = null
            }

            this.searchDebounceTimerId = window.setTimeout(() => {
                this.getUsers()
            }, 260)
        },

        clearSearch() {
            this.searchQuery = ''
            this.getUsers()
        },

        async getUsers() {
            const requestId = this.usersRequestId + 1
            this.usersRequestId = requestId
            this.isLoadingUsers = true

            try {
                const response = await axios.get('/api/users/', {
                    params: {
                        per_page: 100,
                        search: this.searchQuery,
                    },
                })

                if (requestId !== this.usersRequestId) {
                    return
                }

                this.users = response.data.data ?? []
            } catch (error) {
                if (requestId !== this.usersRequestId) {
                    return
                }

                this.users = []
            } finally {
                if (requestId === this.usersRequestId) {
                    this.isLoadingUsers = false
                }
            }
        },

        toggleFollowing(user) {
            axios.post(`/api/users/${user.id}/toggle_following`)
                .then((response) => {
                    user.is_followed = response.data.is_followed
                })
        }
    }
}
</script>
