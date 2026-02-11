<template>
    <div class="page-wrap grid-layout" style="max-width: 680px; margin: 0 auto;">
        <section class="section-card">
            <h1 class="section-title">Подтверждение email</h1>
            <p class="section-subtitle">
                Для доступа к кабинету, чатам и публикациям подтвердите email.
            </p>
            <p class="muted" v-if="currentUser?.email">
                Адрес: <strong>{{ currentUser.email }}</strong>
            </p>

            <div class="form-grid">
                <p class="success-text" v-if="successMessage">{{ successMessage }}</p>
                <p class="error-text" v-if="errorMessage">{{ errorMessage }}</p>

                <button class="btn btn-primary" :disabled="isSending" @click.prevent="resendVerificationEmail">
                    {{ isSending ? 'Отправка...' : 'Отправить письмо повторно' }}
                </button>

                <button class="btn btn-outline" :disabled="isChecking" @click.prevent="checkVerificationStatus">
                    {{ isChecking ? 'Проверка...' : 'Я уже подтвердил email' }}
                </button>

                <button class="btn btn-danger" @click.prevent="logout">
                    Выйти
                </button>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: 'EmailVerification',
    emits: ['auth-changed'],

    data() {
        return {
            currentUser: null,
            isSending: false,
            isChecking: false,
            successMessage: '',
            errorMessage: '',
        }
    },

    async mounted() {
        await this.loadCurrentUser()

        if (this.$route.query.registered === '1') {
            this.successMessage = 'Аккаунт создан. Мы отправили письмо для подтверждения email.'
        }
    },

    methods: {
        async loadCurrentUser() {
            try {
                const response = await axios.get('/api/user')
                this.currentUser = response.data

                if (this.currentUser?.email_verified_at) {
                    this.$emit('auth-changed')
                    await this.$router.replace({name: 'user.personal'})
                }
            } catch (error) {
                this.currentUser = null
                await this.$router.replace({name: 'user.login'})
            }
        },

        async resendVerificationEmail() {
            this.errorMessage = ''
            this.successMessage = ''
            this.isSending = true

            try {
                const response = await axios.post('/api/auth/email/verification-notification')
                this.successMessage = response.data?.message || 'Письмо отправлено.'
            } catch (error) {
                this.errorMessage = error.response?.data?.message || 'Не удалось отправить письмо подтверждения.'
            } finally {
                this.isSending = false
            }
        },

        async checkVerificationStatus() {
            this.errorMessage = ''
            this.successMessage = ''
            this.isChecking = true

            try {
                await this.loadCurrentUser()
                if (this.currentUser?.email_verified_at) {
                    this.successMessage = 'Email подтвержден. Переходим в кабинет.'
                } else {
                    this.errorMessage = 'Email пока не подтвержден. Проверьте письмо и перейдите по ссылке.'
                }
            } finally {
                this.isChecking = false
            }
        },

        async logout() {
            try {
                await axios.post('/logout')
            } finally {
                this.$emit('auth-changed')
                await this.$router.replace({name: 'home'})
            }
        },
    },
}
</script>
