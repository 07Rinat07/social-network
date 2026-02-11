<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">Пользователи</h1>
            <p class="section-subtitle">Подписывайтесь и формируйте свою ленту.</p>

            <div class="user-list">
                <div class="user-item" v-for="user in users" :key="user.id">
                    <router-link :to="{name: 'user.show', params: {id: user.id}}">
                        <div style="display: flex; align-items: center; gap: 0.55rem;">
                            <img v-if="user.avatar_url" :src="user.avatar_url" alt="avatar" class="avatar avatar-sm">
                            <span v-else class="avatar avatar-sm avatar-placeholder">{{ initials(user) }}</span>
                            <strong>{{ user.display_name || user.name }}</strong>
                        </div>
                        <p class="muted" style="margin: 0.2rem 0 0;" v-if="user.nickname">@{{ user.nickname }}</p>
                        <p class="muted" style="margin: 0.2rem 0 0;">ID: {{ user.id }}</p>
                    </router-link>
                    <button
                        class="btn"
                        :class="user.is_followed ? 'btn-outline' : 'btn-primary'"
                        @click.prevent="toggleFollowing(user)"
                    >
                        {{ user.is_followed ? 'Отписаться' : 'Подписаться' }}
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
        }
    },

    mounted() {
        this.getUsers()
    },

    methods: {
        initials(user) {
            const source = (user?.display_name || user?.name || '').trim()
            return source ? source.slice(0, 1).toUpperCase() : 'U'
        },

        async getUsers() {
            const response = await axios.get('/api/users/', {params: {per_page: 100}})
            this.users = response.data.data ?? []
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
