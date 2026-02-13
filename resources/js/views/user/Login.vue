<template>
    <div class="page-wrap grid-layout" style="max-width: 520px; margin: 0 auto;">
        <section class="section-card">
            <h1 class="section-title">{{ $t('login.title') }}</h1>
            <p class="section-subtitle">{{ $t('login.subtitle') }}</p>

            <form class="form-grid" @submit.prevent="login">
                <input v-model.trim="email" type="email" :placeholder="$t('login.emailPlaceholder')" class="input-field">
                <input v-model="password" type="password" :placeholder="$t('login.passwordPlaceholder')" class="input-field">

                <div v-if="flatErrors.length > 0">
                    <p v-for="error in flatErrors" :key="error" class="error-text">{{ error }}</p>
                </div>

                <button class="btn btn-primary" type="submit" :disabled="isLoading">
                    {{ isLoading ? $t('login.submitting') : $t('login.submit') }}
                </button>
            </form>
        </section>
    </div>
</template>

<script>
export default {
    name: 'Login',
    emits: ['auth-changed'],

    data() {
        return {
            email: '',
            password: '',
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
        async login() {
            this.errors = {}
            this.isLoading = true

            try {
                await axios.get('/sanctum/csrf-cookie')
                await axios.post('/login', {email: this.email, password: this.password})
                this.$emit('auth-changed')

                const userResponse = await axios.get('/api/user')
                const isEmailVerified = Boolean(userResponse.data?.email_verified_at)
                const locale = this.$route?.params?.locale === 'en' ? 'en' : 'ru'

                if (!isEmailVerified) {
                    await this.$router.push({name: 'auth.verify', params: {locale}})
                    return
                }

                await this.$router.push({name: 'user.personal', params: {locale}})
            } catch (error) {
                const responseErrors = error.response?.data?.errors
                const responseMessage = error.response?.data?.message

                if (responseErrors && Object.keys(responseErrors).length > 0) {
                    this.errors = responseErrors
                    return
                }

                this.errors = {auth: [responseMessage || this.$t('login.defaultError')]}
            } finally {
                this.isLoading = false
            }
        }
    }
}
</script>
