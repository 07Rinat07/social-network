<template>
    <div class="page-wrap grid-layout" style="max-width: 620px; margin: 0 auto;">
        <section class="section-card">
            <h1 class="section-title">Регистрация</h1>
            <p class="section-subtitle">Создайте аккаунт для публикаций, лайков, чатов и доступа к сообществу.</p>

            <form class="form-grid" @submit.prevent="register">
                <input v-model.trim="name" type="text" placeholder="Имя" class="input-field">
                <input v-model.trim="email" type="email" placeholder="Email" class="input-field">
                <input v-model="password" type="password" placeholder="Пароль" class="input-field">
                <input v-model="password_confirmation" type="password" placeholder="Подтверждение пароля" class="input-field">

                <div v-if="flatErrors.length > 0">
                    <p v-for="error in flatErrors" :key="error" class="error-text">{{ error }}</p>
                </div>

                <button class="btn btn-primary" type="submit" :disabled="isLoading">
                    {{ isLoading ? 'Создание...' : 'Создать аккаунт' }}
                </button>
            </form>
        </section>
    </div>
</template>

<script>
export default {
    name: 'Registration',
    emits: ['auth-changed'],

    data() {
        return {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            errors: {},
            isLoading: false,
        }
    },

    computed: {
        flatErrors() {
            return Object.values(this.errors || {}).flat()
        }
    },

    methods: {
        async register() {
            this.errors = {}
            this.isLoading = true

            try {
                await axios.get('/sanctum/csrf-cookie')
                await axios.post('/register', {
                    name: this.name,
                    email: this.email,
                    password: this.password,
                    password_confirmation: this.password_confirmation,
                })

                this.$emit('auth-changed')
                await this.$router.push({name: 'auth.verify', query: {registered: '1'}})
            } catch (error) {
                this.errors = error.response?.data?.errors ?? {registration: ['Регистрация не выполнена.']}
            } finally {
                this.isLoading = false
            }
        }
    }
}
</script>
