<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">Лента подписок</h1>
            <p class="section-subtitle">Здесь отображаются публикации пользователей, на которых вы подписаны.</p>

            <div v-if="posts.length === 0" class="muted">Пока пусто. Подпишитесь на пользователей во вкладке «Пользователи».</div>
            <div class="post-list">
                <Post v-for="post in posts" :key="post.id" :post="post"></Post>
            </div>
        </section>
    </div>
</template>

<script>
import Post from '../../components/Post.vue'

export default {
    name: 'Feed',

    components: {
        Post
    },

    data() {
        return {
            posts: [],
        }
    },

    mounted() {
        this.getPosts()
    },

    methods: {
        async getPosts() {
            const response = await axios.get('/api/users/following_posts', {params: {per_page: 50}})
            this.posts = response.data.data ?? []
        },
    }
}
</script>
