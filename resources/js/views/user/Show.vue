<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">{{ $t('show.title') }}</h1>
            <Stat :stats="stats"></Stat>
        </section>

        <section class="section-card">
            <h2 class="section-title" style="font-size: 1.2rem;">{{ $t('show.postsTitle') }}</h2>
            <div v-if="posts.length === 0" class="muted">{{ $t('show.empty') }}</div>
            <div class="post-list">
                <Post v-for="post in posts" :key="post.id" :post="post"></Post>
            </div>
        </section>
    </div>
</template>

<script>
import Post from '../../components/Post.vue'
import Stat from '../../components/Stat.vue'

export default {
    name: 'Show',

    components: {
        Stat,
        Post
    },

    data() {
        return {
            posts: [],
            userId: this.$route.params.id,
            stats: {}
        }
    },

    mounted() {
        this.getPosts()
        this.getStats()
    },

    methods: {
        async getStats() {
            const response = await axios.post('/api/users/stats', { user_id: this.userId })
            this.stats = response.data.data
        },

        async getPosts() {
            const response = await axios.get(`/api/users/${this.userId}/posts`, {params: {per_page: 50}})
            this.posts = response.data.data ?? []
        },
    }
}
</script>
