<template>
    <div class="w-96 mx-auto">
        <Stat :stats="stats"></Stat>

        <div v-if="posts">
            <h1 class="mb-8 pb-8 border-b border-gray-400">Posts</h1>
            <Post v-for="post in posts" :post="post"></Post>
        </div>


    </div>
</template>

<script>
import Post from "../../components/Post.vue";
import Stat from "../../components/Stat.vue";
export default {
    name: "Personal",

    data() {
        return {
            posts: [],
            userId: this.$route.params.id,
            stats: []
        }
    },

    components: {
        Stat,
        Post
    },
    mounted() {
        this.getPosts()
        this.getStats()
    },

    methods: {

        getStats() {
            axios.post('/api/users/stats', { user_id: this.userId})
                .then( res => {
                    this.stats = res.data.data
                })
        },

        getPosts() {
            axios.get(`/api/users/${this.userId}/posts`)
                .then(res => {

                    this.posts = res.data.data
                })
        },


    }
}
</script>

<style scoped>

</style>
