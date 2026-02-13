<template>
    <div class="page-wrap grid-layout" style="max-width: 680px; margin: 0 auto;">
        <section class="section-card">
            <h1 class="section-title">{{ $t('verifyEmail.title') }}</h1>
            <p class="section-subtitle">
                {{ $t('verifyEmail.subtitle') }}
            </p>
            <p class="muted" v-if="currentUser?.email">
                {{ $t('verifyEmail.emailLabel') }} <strong>{{ currentUser.email }}</strong>
            </p>

            <div class="form-grid">
                <p class="success-text" v-if="successMessage">{{ successMessage }}</p>
                <p class="error-text" v-if="errorMessage">{{ errorMessage }}</p>

                <button class="btn btn-primary" :disabled="isSending" @click.prevent="resendVerificationEmail">
                    {{ isSending ? $t('verifyEmail.resending') : $t('verifyEmail.resend') }}
                </button>

                <button class="btn btn-outline" :disabled="isChecking" @click.prevent="checkVerificationStatus">
                    {{ isChecking ? $t('verifyEmail.checking') : $t('verifyEmail.check') }}
                </button>

                <button class="btn btn-danger" @click.prevent="logout">
                    {{ $t('verifyEmail.logout') }}
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
            this.successMessage = this.$t('verifyEmail.createdInfo')
        }
    },

    methods: {
        async loadCurrentUser() {
            try {
                const response = await axios.get('/api/user')
                this.currentUser = response.data

                if (this.currentUser?.email_verified_at) {
                    this.$emit('auth-changed')
                    const locale = this.$route?.params?.locale === 'en' ? 'en' : 'ru'
                    await this.$router.replace({name: 'user.personal', params: {locale}})
                }
            } catch (error) {
                this.currentUser = null
                const locale = this.$route?.params?.locale === 'en' ? 'en' : 'ru'
                await this.$router.replace({name: 'user.login', params: {locale}})
            }
        },

        async resendVerificationEmail() {
            this.errorMessage = ''
            this.successMessage = ''
            this.isSending = true

            try {
                const response = await axios.post('/api/auth/email/verification-notification')
                this.successMessage = response.data?.message || this.$t('verifyEmail.sentInfo')
            } catch (error) {
                this.errorMessage = error.response?.data?.message || this.$t('verifyEmail.sendError')
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
                    this.successMessage = this.$t('verifyEmail.verifiedInfo')
                } else {
                    this.errorMessage = this.$t('verifyEmail.notVerifiedYet')
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
                const locale = this.$route?.params?.locale === 'en' ? 'en' : 'ru'
                await this.$router.replace({name: 'home', params: {locale}})
            }
        },
    },
}
</script>
