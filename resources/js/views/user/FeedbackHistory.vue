<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.7rem; flex-wrap: wrap;">
                <div>
                    <h1 class="section-title">{{ $t('feedbackHistory.title') }}</h1>
                    <p class="section-subtitle">{{ $t('feedbackHistory.subtitle') }}</p>
                </div>
                <button class="btn btn-outline" type="button" @click="loadFeedback" :disabled="isLoading">
                    {{ isLoading ? $t('feedbackHistory.refreshing') : $t('feedbackHistory.refresh') }}
                </button>
            </div>

            <div class="feature-list" style="margin-top: 0;">
                <div class="feature-item">
                    <strong>{{ $t('feedbackHistory.total') }}</strong> {{ total }}
                </div>
                <div class="feature-item">
                    <strong>{{ $t('feedbackHistory.newCount') }}</strong> {{ statusCounts.new }}
                </div>
                <div class="feature-item">
                    <strong>{{ $t('feedbackHistory.inProgressCount') }}</strong> {{ statusCounts.in_progress }}
                </div>
                <div class="feature-item">
                    <strong>{{ $t('feedbackHistory.resolvedCount') }}</strong> {{ statusCounts.resolved }}
                </div>
            </div>
        </section>

        <section class="section-card">
            <p v-if="isLoading" class="muted">{{ $t('feedbackHistory.loading') }}</p>
            <p v-else-if="errorMessage" class="error-text">{{ errorMessage }}</p>
            <p v-else-if="items.length === 0" class="muted">{{ $t('feedbackHistory.empty') }}</p>

            <div v-else class="feature-list" style="margin-top: 0;">
                <article v-for="item in items" :key="`my-feedback-${item.id}`" class="feature-item">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.7rem; flex-wrap: wrap;">
                        <strong>{{ $t('feedbackHistory.requestNumber', {id: item.id}) }}</strong>
                        <span class="badge">{{ statusLabel(item.status) }}</span>
                    </div>

                    <p class="muted" style="margin: 0.45rem 0 0;">
                        {{ $t('feedbackHistory.sentAt') }} {{ formatDate(item.created_at) }}
                    </p>

                    <p style="margin: 0.55rem 0 0; white-space: pre-wrap;">{{ item.message }}</p>

                    <div v-if="item.admin_note" style="margin-top: 0.6rem; padding: 0.55rem 0.7rem; border-radius: 10px; border: 1px solid var(--line); background: #fff;">
                        <p class="muted" style="margin: 0 0 0.35rem;">{{ $t('feedbackHistory.adminReply') }}</p>
                        <p style="margin: 0; white-space: pre-wrap;">{{ item.admin_note }}</p>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: 'FeedbackHistory',

    data() {
        return {
            items: [],
            total: 0,
            isLoading: false,
            errorMessage: '',
            currentUserId: null,
            subscribedChannelName: null,
        }
    },

    computed: {
        statusCounts() {
            return this.items.reduce((accumulator, item) => {
                const status = item.status ?? 'new'
                if (Object.prototype.hasOwnProperty.call(accumulator, status)) {
                    accumulator[status] += 1
                }

                return accumulator
            }, {
                new: 0,
                in_progress: 0,
                resolved: 0,
            })
        }
    },

    async mounted() {
        await this.bootstrapPage()
    },

    beforeUnmount() {
        this.unsubscribeRealtime()
    },

    methods: {
        async bootstrapPage() {
            await this.loadCurrentUser()
            await this.loadFeedback()
            this.subscribeRealtime()
        },

        async loadCurrentUser() {
            const response = await axios.get('/api/user')
            this.currentUserId = response.data?.id ?? null
        },

        async loadFeedback() {
            this.isLoading = true
            this.errorMessage = ''

            try {
                const response = await axios.get('/api/feedback/my', {
                    params: {
                        per_page: 100,
                    }
                })

                this.items = response.data.data ?? []
                this.total = response.data.total ?? response.data.meta?.total ?? this.items.length
            } catch (error) {
                this.errorMessage = error.response?.data?.message ?? this.$t('feedbackHistory.loadError')
            } finally {
                this.isLoading = false
            }
        },

        subscribeRealtime() {
            if (!window.Echo || !this.currentUserId || this.subscribedChannelName) {
                return
            }

            const channelName = `feedback.user.${this.currentUserId}`

            window.Echo.private(channelName)
                .listen('.feedback.status.updated', (payload) => {
                    this.handleRealtimeStatusUpdate(payload)
                })

            this.subscribedChannelName = channelName
        },

        unsubscribeRealtime() {
            if (!window.Echo || !this.subscribedChannelName) {
                this.subscribedChannelName = null
                return
            }

            window.Echo.leave(this.subscribedChannelName)
            this.subscribedChannelName = null
        },

        handleRealtimeStatusUpdate(payload) {
            if (!payload || !payload.id) {
                return
            }

            const index = this.items.findIndex((item) => item.id === payload.id)

            if (index === -1) {
                this.items.unshift(payload)
                this.total += 1
                return
            }

            const current = this.items[index]
            this.items[index] = {
                ...current,
                ...payload,
            }
        },

        statusLabel(status) {
            const labels = {
                new: this.$t('feedbackHistory.statusNew'),
                in_progress: this.$t('feedbackHistory.statusInProgress'),
                resolved: this.$t('feedbackHistory.statusResolved'),
            }

            return labels[status] ?? status
        },

        formatDate(dateValue) {
            if (!dateValue) {
                return '—'
            }

            const locale = this.$route?.params?.locale === 'en' ? 'en-GB' : 'ru-RU'
            return new Date(dateValue).toLocaleString(locale)
        },
    }
}
</script>
