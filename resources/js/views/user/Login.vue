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

                <button class="btn btn-primary" type="submit" :disabled="isSubmitDisabled">
                    {{ submitButtonText }}
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
            lockoutSeconds: 0,
            lockoutTimerId: null,
        }
    },

    computed: {
        flatErrors() {
            return Object.values(this.errors || {}).flat()
        },

        isSubmitDisabled() {
            return this.isLoading || this.lockoutSeconds > 0
        },

        submitButtonText() {
            if (this.isLoading) {
                return this.$t('login.submitting')
            }

            if (this.lockoutSeconds > 0) {
                return this.$t('login.lockedSubmit', {seconds: this.lockoutSeconds})
            }

            return this.$t('login.submit')
        },
    },

    beforeUnmount() {
        this.stopLockoutTimer()
    },

    methods: {
        stopLockoutTimer() {
            if (this.lockoutTimerId !== null) {
                window.clearInterval(this.lockoutTimerId)
                this.lockoutTimerId = null
            }
        },

        startLockoutTimer(seconds) {
            const normalized = Number.isFinite(Number(seconds))
                ? Math.max(0, Math.trunc(Number(seconds)))
                : 0

            this.stopLockoutTimer()
            this.lockoutSeconds = normalized

            if (this.lockoutSeconds <= 0) {
                return
            }

            this.lockoutTimerId = window.setInterval(() => {
                this.lockoutSeconds = Math.max(0, this.lockoutSeconds - 1)
                if (this.lockoutSeconds === 0) {
                    this.stopLockoutTimer()
                }
            }, 1000)
        },

        resolveRetryAfter(error) {
            const response = error?.response
            const rawFromBody = Number(response?.data?.retry_after)

            if (Number.isFinite(rawFromBody) && rawFromBody > 0) {
                return Math.trunc(rawFromBody)
            }

            const retryAfterHeader = Number(response?.headers?.['retry-after'])
            if (Number.isFinite(retryAfterHeader) && retryAfterHeader > 0) {
                return Math.trunc(retryAfterHeader)
            }

            const candidateMessage = String(response?.data?.message || '')
            const match = candidateMessage.match(/(\d+)\s*(seconds?|сек|секунд|секунды)/i)
            if (match && Number.isFinite(Number(match[1]))) {
                return Math.trunc(Number(match[1]))
            }

            return 0
        },

        async login() {
            if (this.lockoutSeconds > 0) {
                return
            }

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
                const statusCode = Number(error?.response?.status || 0)
                const retryAfter = this.resolveRetryAfter(error)
                const responseErrors = error.response?.data?.errors
                const responseMessage = error.response?.data?.message

                if (statusCode === 429) {
                    const seconds = retryAfter > 0 ? retryAfter : 15
                    this.startLockoutTimer(seconds)
                    this.errors = {
                        auth: [this.$t('login.lockedForSeconds', {seconds: this.lockoutSeconds || seconds})],
                    }
                    return
                }

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
