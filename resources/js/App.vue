<template>
    <div>
        <div class="flex justify-between p-8 w-96 mx-auto">

            <router-link v-if="!token" :to="{ name: 'user.login'}">Login</router-link>
            <router-link v-if="token" :to="{ name: 'user.index'}">Users</router-link>
            <router-link v-if="token" :to="{ name: 'user.feed'}">Feed</router-link>
            <router-link v-if="token" :to="{ name: 'user.personal'}">Personal</router-link>
            <router-link v-if="!token" :to="{ name: 'user.registration'}">Registration</router-link>
            <a v-if="token" @click.prevent="logout" href="#">Logout</a>
        </div>

        <router-view></router-view>
    </div>
</template>

<script>
export default {
    name: "App",
    data() {
        return {
            token: null
        }
    },

    mounted() {
        this.getToken()
    },

    watch: {
        $route(to, from) {
            this.getToken()
        }
    },

    methods: {

        getToken() {
            this.token = localStorage.getItem('x_xsrf_token')
        },

        logout() {
            axios.post('/logout')
                .then( res => {
                    localStorage.removeItem('x_xsrf_token')
                    this.$router.push({name: 'user.login'})
                })
        }
    }
}
</script>

<style scoped>

</style>
