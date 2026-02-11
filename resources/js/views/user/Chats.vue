<template>
    <div class="page-wrap chat-layout">
        <section class="chat-list fade-in">
            <div>
                <h2 class="section-title" style="font-size: 1.2rem; margin-bottom: 0.4rem;">Чаты</h2>
                <p class="section-subtitle" style="margin: 0;">Личные и общий чат с realtime-сообщениями.</p>
            </div>

            <div class="section-card" style="padding: 0.75rem; box-shadow: none;">
                <strong style="display: block; margin-bottom: 0.45rem;">Звук уведомлений</strong>
                <div class="form-grid">
                    <label class="muted" style="font-size: 0.82rem; display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" v-model="notificationSettings.enabled" @change="saveNotificationSettings">
                        Включить звук при входящих сообщениях
                    </label>
                    <select class="select-field" v-model="notificationSettings.sound" @change="saveNotificationSettings">
                        <option value="ping">Ping</option>
                        <option value="bell">Bell</option>
                        <option value="chime">Chime</option>
                        <option value="custom">Свой звук</option>
                    </select>
                    <label class="muted" style="font-size: 0.82rem;">
                        Громкость: {{ notificationSettings.volume }}%
                    </label>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        class="input-field"
                        style="padding: 0;"
                        v-model.number="notificationSettings.volume"
                        @input="saveNotificationSettings"
                    >
                    <button
                        class="btn btn-outline btn-sm"
                        type="button"
                        @click="previewNotificationSound"
                    >
                        Проверить звук
                    </button>
                    <input
                        class="input-field"
                        type="file"
                        accept="audio/*"
                        @change="onCustomSoundSelected"
                    >
                    <p class="muted" style="margin: 0; font-size: 0.75rem;">
                        Можно загрузить свой сигнал (до 1MB).
                    </p>
                </div>
            </div>

            <div class="form-grid">
                <input
                    class="input-field"
                    v-model.trim="userSearch"
                    type="text"
                    placeholder="Поиск пользователей"
                    @input="onUserSearchInput"
                >
            </div>

            <div class="simple-list">
                <div
                    v-for="user in users"
                    :key="`u-${user.id}`"
                    class="simple-item"
                    style="display: block;"
                >
                    <div style="display: flex; align-items: center; gap: 0.55rem;">
                        <img v-if="avatarUrl(user)" :src="avatarUrl(user)" alt="avatar" class="avatar avatar-sm">
                        <span v-else class="avatar avatar-sm avatar-placeholder">{{ initials(user) }}</span>
                        <strong>{{ displayName(user) }}</strong>
                    </div>
                    <p class="muted" style="margin: 0.2rem 0 0; font-size: 0.8rem;" v-if="user.nickname">@{{ user.nickname }}</p>
                    <p class="muted" style="margin: 0.2rem 0 0; font-size: 0.8rem;">
                        <span v-if="isBlockedByMe(user)">Вы заблокировали этого пользователя.</span>
                        <span v-else-if="isBlockedByUser(user)">Этот пользователь заблокировал вас.</span>
                        <span v-else>Доступен для личного чата.</span>
                    </p>
                    <p
                        v-if="isBlockedByMe(user) && getMyBlockStatusLabel(user)"
                        class="muted"
                        style="margin: 0.15rem 0 0; font-size: 0.76rem;"
                    >
                        {{ getMyBlockStatusLabel(user) }}
                    </p>

                    <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.5rem;">
                        <button
                            class="btn btn-outline btn-sm"
                            @click="startDirectChat(user)"
                            :disabled="isBlockedByMe(user) || isBlockedByUser(user)"
                        >
                            Личный чат
                        </button>

                        <button
                            v-if="!isBlockedByMe(user)"
                            class="btn btn-danger btn-sm"
                            @click="blockUser(user, 'permanent')"
                        >
                            Блок навсегда
                        </button>
                        <button
                            v-if="!isBlockedByMe(user)"
                            class="btn btn-danger btn-sm"
                            @click="blockUser(user, 'temporary')"
                        >
                            Блок 24ч
                        </button>
                        <button
                            v-if="isBlockedByMe(user)"
                            class="btn btn-success btn-sm"
                            @click="unblockUser(user)"
                        >
                            Разблокировать
                        </button>
                    </div>
                </div>
            </div>

            <div class="simple-list">
                <div
                    v-for="conversation in conversations"
                    :key="conversation.id"
                    class="chat-item"
                    :class="{'active': activeConversation && activeConversation.id === conversation.id}"
                    @click="openConversation(conversation)"
                >
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                        <strong>{{ conversation.title }}</strong>
                        <span
                            v-if="Number(conversation.unread_count ?? 0) > 0"
                            class="badge"
                            style="font-size: 0.72rem; min-width: 1.8rem; text-align: center;"
                        >
                            {{ formatUnreadBadge(conversation.unread_count) }}
                        </span>
                    </div>
                    <p class="muted" style="margin: 0; font-size: 0.82rem;">
                        {{ messagePreview(conversation) }}
                    </p>
                    <p v-if="conversation.is_blocked" class="error-text" style="margin: 0.2rem 0 0; font-size: 0.75rem;">
                        Диалог заблокирован.
                    </p>
                </div>
            </div>
        </section>

        <section class="chat-window fade-in">
            <header>
                <h3 class="section-title" style="font-size: 1.15rem; margin: 0 0 0.2rem;">
                    {{ activeConversation ? activeConversation.title : 'Выберите чат' }}
                </h3>
                <p class="muted" style="margin: 0; font-size: 0.85rem;">
                    {{ activeConversation ? 'SMS, emoji, gif, фото, видео и голосовые в realtime.' : 'Слева выберите общий или личный чат.' }}
                </p>
                <p v-if="activeConversation && activeConversation.is_blocked" class="error-text" style="margin: 0.4rem 0 0;">
                    В этом диалоге действует блокировка. Отправка сообщений недоступна.
                </p>
            </header>

            <div class="chat-messages" ref="messagesContainer">
                <template v-if="activeConversation">
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="chat-message"
                        :class="{'mine': isMine(message)}"
                    >
                        <div class="chat-meta">
                            {{ displayName(message.user) }} · {{ message.date }}
                        </div>
                        <div v-if="message.body">{{ message.body }}</div>

                        <div class="media-grid" v-if="message.attachments && message.attachments.length > 0" style="margin-top: 0.45rem;">
                            <template v-for="attachment in message.attachments" :key="`msg-att-${message.id}-${attachment.id}`">
                                <MediaPlayer
                                    v-if="attachment.type === 'video'"
                                    type="video"
                                    :src="attachment.url"
                                    :mime-type="attachment.mime_type"
                                    player-class="media-video"
                                ></MediaPlayer>
                                <MediaPlayer
                                    v-else-if="attachment.type === 'audio'"
                                    type="audio"
                                    :src="attachment.url"
                                    :mime-type="attachment.mime_type"
                                    player-class="media-audio"
                                ></MediaPlayer>
                                <button
                                    v-else
                                    type="button"
                                    class="media-open-btn"
                                    @click="openMedia(attachment.url, attachment.original_name || 'attachment')"
                                >
                                    <img
                                        class="media-preview"
                                        :src="attachment.url"
                                        :alt="attachment.original_name || 'attachment'"
                                        @error="handlePreviewError($event, attachment.original_name || 'attachment')"
                                        @load="handlePreviewLoad"
                                    >
                                </button>
                            </template>
                        </div>
                    </div>

                    <p v-if="messages.length === 0" class="muted" style="margin: 0;">Сообщений пока нет.</p>
                </template>

                <p v-else class="muted" style="margin: 0;">Откройте чат, чтобы начать переписку.</p>
            </div>

            <form class="form-grid" @submit.prevent="sendMessage">
                <textarea
                    class="textarea-field"
                    style="min-height: 90px;"
                    v-model="messageBody"
                    placeholder="Введите сообщение..."
                    :disabled="isComposerDisabled"
                    @keydown.ctrl.enter.prevent="sendMessage"
                    @keydown.meta.enter.prevent="sendMessage"
                ></textarea>
                <p class="muted" style="margin: 0; font-size: 0.76rem;">
                    Ctrl+Enter для быстрой отправки.
                </p>

                <div class="emoji-row">
                    <button v-for="emoji in emojis" :key="emoji" type="button" class="emoji-btn" @click="appendEmoji(emoji)">
                        {{ emoji }}
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <input
                        ref="messageFiles"
                        type="file"
                        accept="image/*,video/*,audio/*,.gif,.mp3,.wav,.ogg,.m4a,.aac,.opus,.weba,.webm"
                        multiple
                        class="hidden"
                        @change="onMessageFilesSelected"
                    >
                    <button class="btn btn-outline" type="button" @click="openFileDialog" :disabled="isComposerDisabled">
                        Добавить gif/фото/видео/аудио
                    </button>
                    <button
                        class="btn btn-outline"
                        type="button"
                        @click="startVoiceRecording"
                        :disabled="isComposerDisabled || isRecordingVoice || isProcessingVoice || voiceStopInProgress || !canRecordVoice"
                    >
                        Записать голосовое
                    </button>
                    <button
                        class="btn btn-danger"
                        type="button"
                        @click="stopVoiceRecording"
                        :disabled="voiceStopInProgress"
                        v-if="isRecordingVoice"
                    >
                        Остановить запись ({{ formattedVoiceRecordDuration }} / {{ formattedVoiceRecordDurationLimit }})
                    </button>
                    <button
                        class="btn btn-outline"
                        type="button"
                        @click="stopVoiceRecording(true)"
                        :disabled="voiceStopInProgress"
                        v-if="isRecordingVoice"
                    >
                        Отменить запись
                    </button>
                </div>

                <p class="muted" style="margin: 0;" v-if="!canRecordVoice">
                    Запись голосовых в этом браузере недоступна. Можно отправить готовый аудиофайл.
                </p>
                <p class="muted" style="margin: 0;" v-if="isRecordingVoice">
                    Идёт запись голосового сообщения...
                </p>
                <div
                    v-if="isRecordingVoice"
                    class="section-card"
                    style="padding: 0.5rem; box-shadow: none; display: grid; gap: 0.35rem;"
                >
                    <div class="muted" style="font-size: 0.78rem; display: flex; justify-content: space-between; gap: 0.45rem;">
                        <span>Уровень микрофона: {{ Math.round(voiceLevelPercent) }}%</span>
                        <span>Лимит: {{ formattedVoiceRecordDurationLimit }}</span>
                    </div>
                    <div style="height: 8px; border-radius: 999px; background: rgba(15, 82, 186, 0.15); overflow: hidden;">
                        <div
                            :style="{
                                width: `${voiceLevelPercent}%`,
                                height: '100%',
                                borderRadius: '999px',
                                background: 'linear-gradient(90deg, #16a34a 0%, #059669 50%, #0ea5e9 100%)',
                                transition: 'width 120ms ease-out',
                            }"
                        ></div>
                    </div>
                    <div style="height: 6px; border-radius: 999px; background: rgba(15, 82, 186, 0.12); overflow: hidden;">
                        <div
                            :style="{
                                width: `${voiceDurationProgressPercent}%`,
                                height: '100%',
                                borderRadius: '999px',
                                background: 'linear-gradient(90deg, #2563eb 0%, #14b8a6 100%)',
                                transition: 'width 200ms linear',
                            }"
                        ></div>
                    </div>
                </div>
                <p class="muted" style="margin: 0;" v-if="isProcessingVoice">
                    Обрабатываем запись...
                </p>

                <div class="media-grid" v-if="selectedFilePreviews.length > 0">
                    <div v-for="item in selectedFilePreviews" :key="item.key" class="section-card" style="padding: 0.45rem; box-shadow: none;">
                        <MediaPlayer
                            v-if="item.kind === 'video'"
                            type="video"
                            :src="item.url"
                            :mime-type="item.mimeType"
                            player-class="media-video"
                        ></MediaPlayer>
                        <MediaPlayer
                            v-else-if="item.kind === 'audio'"
                            type="audio"
                            :src="item.url"
                            :mime-type="item.mimeType"
                            player-class="media-audio"
                        ></MediaPlayer>
                        <button
                            v-else
                            type="button"
                            class="media-open-btn"
                            @click="openMedia(item.url, item.name)"
                        >
                            <img
                                class="media-preview"
                                :src="item.url"
                                :alt="item.name"
                                @error="handlePreviewError($event, item.name || 'attachment')"
                                @load="handlePreviewLoad"
                            >
                        </button>
                        <p class="muted" style="margin: 0.35rem 0 0; font-size: 0.78rem;" v-if="item.kind === 'audio'">
                            {{ item.name }}
                        </p>
                        <button class="btn btn-danger btn-sm" style="margin-top: 0.4rem;" type="button" @click="removeSelectedFile(item.key)">
                            Убрать
                        </button>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit" :disabled="isComposerDisabled || isSending || isRecordingVoice || isProcessingVoice || voiceStopInProgress || !canSendCurrentMessage">
                    {{ isSending ? 'Отправка...' : 'Отправить' }}
                </button>
            </form>
        </section>

        <MediaLightbox ref="mediaLightbox"></MediaLightbox>
    </div>
</template>

<script>
import MediaLightbox from '../../components/MediaLightbox.vue'
import MediaPlayer from '../../components/MediaPlayer.vue'
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../../utils/mediaPreview'

const CHAT_SOUND_STORAGE_KEY = 'chat_notification_settings_v1'

export default {
    name: 'Chats',

    components: {
        MediaLightbox,
        MediaPlayer,
    },

    data() {
        return {
            currentUser: null,
            conversations: [],
            users: [],
            myBlocks: [],
            activeConversation: null,
            messages: [],
            messageBody: '',
            userSearch: '',
            userSearchDebounceTimerId: null,
            loadUsersRequestId: 0,
            isSending: false,
            selectedFiles: [],
            selectedFilePreviews: [],
            subscribedChannels: {},
            emojis: ['😀', '🔥', '❤️', '😂', '👏', '😎', '👍', '🎉', '🤝', '🤩'],
            canRecordVoice: false,
            isRecordingVoice: false,
            isProcessingVoice: false,
            voiceRecordDurationSeconds: 0,
            voiceStopInProgress: false,
            maxVoiceRecordDurationSeconds: 5 * 60,
            voiceLevelPercent: 0,
            voiceAutoStopTriggered: false,
            voiceNormalizationTargetPeak: 0.92,
            voiceNormalizationMaxGain: 6,
            voiceNormalizationMinSignal: 0.003,
            mediaRecorder: null,
            voiceRecordStream: null,
            voiceRecordTimerId: null,
            voiceRecordStartedAt: null,
            voiceAudioContext: null,
            voicePcmSourceNode: null,
            voicePcmAnalyserNode: null,
            voicePcmGainNode: null,
            voicePcmSampleTimerId: null,
            voicePcmChunks: [],
            voicePcmSampleRate: 0,
            voiceRecordedChunks: [],
            voiceRecordedMimeType: '',
            notificationSettings: {
                enabled: true,
                sound: 'ping',
                volume: 60,
                customSoundDataUrl: null,
            },
        }
    },

    computed: {
        isComposerDisabled() {
            return !this.activeConversation || this.activeConversation.is_blocked
        },

        canSendCurrentMessage() {
            return this.messageBody.trim() !== '' || this.selectedFiles.length > 0
        },

        formattedVoiceRecordDuration() {
            const total = Math.max(0, Number(this.voiceRecordDurationSeconds) || 0)
            const minutes = Math.floor(total / 60)
            const seconds = total % 60

            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
        },

        formattedVoiceRecordDurationLimit() {
            const total = Math.max(0, Number(this.maxVoiceRecordDurationSeconds) || 0)
            const minutes = Math.floor(total / 60)
            const seconds = total % 60

            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
        },

        voiceDurationProgressPercent() {
            if (this.maxVoiceRecordDurationSeconds <= 0) {
                return 0
            }

            return Math.max(
                0,
                Math.min(100, (this.voiceRecordDurationSeconds / this.maxVoiceRecordDurationSeconds) * 100)
            )
        }
    },

    async mounted() {
        this.loadNotificationSettings()
        this.canRecordVoice = this.isVoiceRecordingSupported()
        await this.loadCurrentUser()
        await Promise.all([this.loadConversations(), this.loadUsers(), this.loadMyBlocks()])

        if (this.conversations.length > 0) {
            await this.openConversation(this.conversations[0])
        }
    },

    beforeUnmount() {
        this.stopVoiceRecording(true)
        if (this.userSearchDebounceTimerId) {
            window.clearTimeout(this.userSearchDebounceTimerId)
            this.userSearchDebounceTimerId = null
        }
        this.unsubscribeAllChannels()
        this.clearSelectedFiles()
    },

    methods: {
        handlePreviewError(event, label = 'Preview unavailable') {
            applyImagePreviewFallback(event, label)
        },

        handlePreviewLoad(event) {
            resetImagePreviewFallback(event)
        },

        openMedia(url, alt = 'Фото') {
            this.$refs.mediaLightbox?.open(url, alt)
        },

        displayName(user) {
            return user?.display_name || user?.name || 'Пользователь'
        },

        avatarUrl(user) {
            return user?.avatar_url || null
        },

        initials(user) {
            const source = this.displayName(user).trim()
            return source ? source.slice(0, 1).toUpperCase() : 'U'
        },

        messagePreview(conversation) {
            if (!conversation?.last_message) {
                return 'Пока нет сообщений'
            }

            const message = conversation.last_message
            const author = this.displayName(message.user)
            const text = message.body || this.attachmentSummary(message)

            return `${author}: ${text}`
        },

        normalizeConversation(conversation) {
            const unreadCount = Number(conversation?.unread_count ?? 0)

            return {
                ...conversation,
                unread_count: Number.isFinite(unreadCount) ? Math.max(0, unreadCount) : 0,
                has_unread: Number.isFinite(unreadCount) ? unreadCount > 0 : Boolean(conversation?.has_unread),
            }
        },

        formatUnreadBadge(count) {
            const value = Number(count || 0)
            if (value <= 0) {
                return ''
            }

            return value > 99 ? '99+' : String(value)
        },

        calculateTotalUnread() {
            return this.conversations.reduce((total, conversation) => {
                const unread = Number(conversation?.unread_count ?? 0)

                return total + (Number.isFinite(unread) ? Math.max(0, unread) : 0)
            }, 0)
        },

        emitUnreadTotal() {
            this.$emit('chat-unread-updated', this.calculateTotalUnread())
        },

        async loadCurrentUser() {
            const response = await axios.get('/api/user')
            this.currentUser = response.data
        },

        async loadConversations() {
            const response = await axios.get('/api/chats')
            const conversations = (response.data.data ?? []).map((conversation) => this.normalizeConversation(conversation))

            this.conversations = conversations
            this.sortConversationsByActivity()
            this.syncConversationSubscriptions()
            this.emitUnreadTotal()

            if (this.activeConversation) {
                const updated = this.conversations.find((conversation) => conversation.id === this.activeConversation.id)
                if (updated) {
                    this.activeConversation = updated
                }
            }
        },

        onUserSearchInput() {
            if (this.userSearchDebounceTimerId) {
                window.clearTimeout(this.userSearchDebounceTimerId)
                this.userSearchDebounceTimerId = null
            }

            this.userSearchDebounceTimerId = window.setTimeout(() => {
                this.loadUsers()
            }, 250)
        },

        async loadUsers() {
            const requestId = this.loadUsersRequestId + 1
            this.loadUsersRequestId = requestId

            const response = await axios.get('/api/chats/users', {
                params: {
                    search: this.userSearch,
                    per_page: 25,
                }
            })

            if (requestId !== this.loadUsersRequestId) {
                return
            }

            this.users = response.data.data ?? []
        },

        async loadMyBlocks() {
            const response = await axios.get('/api/users/blocks')
            this.myBlocks = response.data.data ?? []
        },

        async startDirectChat(user) {
            if (this.isBlockedByMe(user) || this.isBlockedByUser(user)) {
                return
            }

            try {
                const response = await axios.post(`/api/chats/direct/${user.id}`)
                const conversation = response.data.data

                await this.loadConversations()
                const target = this.conversations.find((item) => item.id === conversation.id) ?? conversation
                await this.openConversation(target)
            } catch (error) {
                if (error.response?.status === 423) {
                    await Promise.all([this.loadUsers(), this.loadMyBlocks(), this.loadConversations()])
                }
                alert(this.resolveApiMessage(error, 'Не удалось открыть личный чат.'))
            }
        },

        async blockUser(user, mode) {
            try {
                const payload = mode === 'temporary'
                    ? {mode: 'temporary', duration_minutes: 24 * 60}
                    : {mode: 'permanent'}

                await axios.post(`/api/users/${user.id}/block`, payload)

                await Promise.all([this.loadUsers(), this.loadMyBlocks(), this.loadConversations()])
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось заблокировать пользователя.'))
            }
        },

        async unblockUser(user) {
            try {
                await axios.delete(`/api/users/${user.id}/block`)
                await Promise.all([this.loadUsers(), this.loadMyBlocks(), this.loadConversations()])
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось снять блокировку.'))
            }
        },

        isBlockedByMe(user) {
            return Boolean(user.is_blocked_by_me)
        },

        isBlockedByUser(user) {
            return Boolean(user.has_blocked_me)
        },

        getMyBlockForUser(user) {
            if (!user) {
                return null
            }

            return this.myBlocks.find((block) => Number(block.blocked_user_id) === Number(user.id)) ?? null
        },

        getMyBlockStatusLabel(user) {
            const block = this.getMyBlockForUser(user)
            if (!block) {
                return ''
            }

            if (!block.expires_at) {
                return 'Тип блокировки: навсегда'
            }

            return `Тип блокировки: до ${this.formatDateTime(block.expires_at)}`
        },

        async openConversation(conversation) {
            this.activeConversation = conversation
            await this.loadMessages()
        },

        async loadMessages() {
            if (!this.activeConversation) {
                this.messages = []
                return
            }

            try {
                const response = await axios.get(`/api/chats/${this.activeConversation.id}/messages`, {
                    params: { per_page: 80 }
                })

                this.messages = response.data.data ?? []
                this.setConversationReadLocally(this.activeConversation.id)
                await this.markConversationRead(this.activeConversation.id)
                this.$nextTick(() => this.scrollMessagesDown())
            } catch (error) {
                this.messages = []
                if (error.response?.status === 403) {
                    alert('Нет доступа к этому чату.')
                }
            }
        },

        async markConversationRead(conversationId) {
            if (!conversationId) {
                return
            }

            try {
                await axios.post(`/api/chats/${conversationId}/read`)
            } catch (error) {
                // Ignore silent read-sync errors.
            }
        },

        setConversationReadLocally(conversationId) {
            if (!conversationId) {
                return
            }

            const target = this.conversations.find((conversation) => Number(conversation.id) === Number(conversationId))
            if (!target) {
                return
            }

            target.unread_count = 0
            target.has_unread = false
            this.emitUnreadTotal()
        },

        appendEmoji(emoji) {
            this.messageBody = `${this.messageBody}${emoji}`
        },

        openFileDialog() {
            this.$refs.messageFiles.click()
        },

        isVoiceRecordingSupported() {
            const hasAudioContext = typeof window !== 'undefined'
                && Boolean(window.AudioContext || window.webkitAudioContext)

            return hasAudioContext
                && typeof navigator !== 'undefined'
                && Boolean(navigator.mediaDevices?.getUserMedia)
        },

        getPreferredVoiceMimeType() {
            if (typeof window === 'undefined' || typeof window.MediaRecorder === 'undefined') {
                return ''
            }

            const candidates = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/ogg',
                'audio/mp4',
            ]

            const probe = typeof document !== 'undefined' ? document.createElement('audio') : null
            const canPlay = (candidate) => {
                if (!probe || typeof probe.canPlayType !== 'function') {
                    return true
                }

                const mime = candidate.split(';')[0]?.trim() || candidate

                return probe.canPlayType(mime) !== ''
            }

            const recorderAndPlayerSupported = candidates.find((candidate) => {
                return window.MediaRecorder.isTypeSupported(candidate) && canPlay(candidate)
            })

            if (recorderAndPlayerSupported) {
                return recorderAndPlayerSupported
            }

            // Let browser choose native default when we cannot find codec supported by both recorder and player.
            return ''
        },

        async startVoiceRecording() {
            if (!this.canRecordVoice || this.isComposerDisabled || this.isRecordingVoice || this.isProcessingVoice || this.voiceStopInProgress) {
                return
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                const recordStartedAt = Date.now()

                this.isProcessingVoice = false
                this.voiceRecordDurationSeconds = 0
                this.voiceRecordStartedAt = recordStartedAt
                this.voiceRecordStream = stream
                this.mediaRecorder = null
                this.voiceRecordedChunks = []
                this.voiceRecordedMimeType = ''
                this.voiceLevelPercent = 0
                this.voiceAutoStopTriggered = false
                this.isRecordingVoice = true
                await this.startVoicePcmCapture(stream)

                if (!this.voiceAudioContext) {
                    this.stopVoiceRecordStreamTracks(stream)
                    this.voiceRecordStream = null
                    this.isRecordingVoice = false
                    this.voiceRecordStartedAt = null
                    alert('Не удалось инициализировать запись голосового сообщения.')
                    return
                }

                if (typeof window !== 'undefined' && typeof window.MediaRecorder !== 'undefined') {
                    const options = {}
                    const preferredMimeType = this.getPreferredVoiceMimeType()
                    if (preferredMimeType) {
                        options.mimeType = preferredMimeType
                    }

                    try {
                        const recorder = new MediaRecorder(stream, options)
                        recorder.ondataavailable = (event) => {
                            if (event.data && event.data.size > 0) {
                                this.voiceRecordedChunks.push(event.data)
                                if (!this.voiceRecordedMimeType && typeof event.data.type === 'string' && event.data.type.trim() !== '') {
                                    this.voiceRecordedMimeType = event.data.type
                                }
                            }
                        }

                        recorder.onerror = () => {
                            // Keep PCM path active even if MediaRecorder fails.
                        }

                        recorder.start(500)
                        this.mediaRecorder = recorder
                    } catch (error) {
                        this.mediaRecorder = null
                    }
                }

                this.voiceRecordTimerId = window.setInterval(() => {
                    this.voiceRecordDurationSeconds += 1

                    if (!this.voiceAutoStopTriggered && this.voiceRecordDurationSeconds >= this.maxVoiceRecordDurationSeconds) {
                        this.voiceAutoStopTriggered = true
                        this.stopVoiceRecording(false)
                            .finally(() => {
                                alert(`Достигнут лимит ${this.formattedVoiceRecordDurationLimit}. Голосовое добавлено в сообщение.`)
                            })
                    }
                }, 1000)
            } catch (error) {
                this.resetVoicePcmCaptureState(true)
                this.stopVoiceRecording(true)
                alert('Не удалось получить доступ к микрофону.')
            }
        },

        async stopVoiceRecording(forceDiscard = false) {
            const shouldDiscard = forceDiscard === true
            if (this.voiceStopInProgress) {
                return
            }

            this.voiceStopInProgress = true

            try {
                this.stopVoiceRecordTimer()
                const stream = this.voiceRecordStream
                const recorder = this.mediaRecorder
                const recordedDurationMs = this.voiceRecordStartedAt
                    ? Math.max(0, Date.now() - this.voiceRecordStartedAt)
                    : 0

                this.isRecordingVoice = false

                if (shouldDiscard) {
                    if (stream) {
                        this.stopVoiceRecordStreamTracks(stream)
                    }
                    this.voiceRecordStream = null
                    this.mediaRecorder = null
                    this.voiceRecordedChunks = []
                    this.voiceRecordedMimeType = ''
                    this.voiceLevelPercent = 0
                    this.voiceAutoStopTriggered = false
                    this.isProcessingVoice = false
                    this.resetVoicePcmCaptureState(true)
                    this.voiceRecordStartedAt = null
                    return
                }

                this.isProcessingVoice = true

                try {
                    if (recorder && recorder.state !== 'inactive') {
                        if (typeof recorder.requestData === 'function') {
                            try {
                                recorder.requestData()
                            } catch (error) {
                                // Ignore flush errors.
                            }
                        }

                        try {
                            recorder.stop()
                        } catch (error) {
                            // Ignore stop race errors.
                        }
                    }

                    this.captureVoicePcmFrame()
                    let appended = this.appendRecordedVoiceFromPcm(recordedDurationMs)
                    if (!appended && this.voiceRecordedChunks.length > 0) {
                        const mimeType = this.voiceRecordedMimeType || recorder?.mimeType || ''
                        appended = this.appendRecordedVoice(this.voiceRecordedChunks, mimeType, recordedDurationMs)
                    }

                    if (!appended && recorder) {
                        await this.waitForRecorderInactive(recorder, 2200)
                        await this.waitForRecordedChunks(this.voiceRecordedChunks, 2200)

                        if (this.voiceRecordedChunks.length > 0) {
                            const mimeType = this.voiceRecordedMimeType || recorder.mimeType || ''
                            appended = this.appendRecordedVoice(this.voiceRecordedChunks, mimeType, recordedDurationMs)
                        }
                    }

                    if (!appended) {
                        alert('Голосовое не записалось. Попробуйте ещё раз.')
                    }
                } catch (error) {
                    let appended = false
                    if (this.voiceRecordedChunks.length > 0) {
                        const mimeType = this.voiceRecordedMimeType || recorder?.mimeType || ''
                        appended = this.appendRecordedVoice(this.voiceRecordedChunks, mimeType, recordedDurationMs)
                    }

                    if (!appended) {
                        alert('Голосовое не записалось. Попробуйте ещё раз.')
                    }
                } finally {
                    if (recorder && recorder.state !== 'inactive') {
                        try {
                            recorder.stop()
                        } catch (error) {
                            // Ignore repeated stop calls.
                        }
                    }

                    if (stream) {
                        this.stopVoiceRecordStreamTracks(stream)
                    }

                    this.voiceRecordStream = null
                    this.mediaRecorder = null
                    this.voiceRecordedChunks = []
                    this.voiceRecordedMimeType = ''
                    this.voiceLevelPercent = 0
                    this.voiceAutoStopTriggered = false
                    this.resetVoicePcmCaptureState(true)
                    this.voiceRecordStartedAt = null
                    this.isProcessingVoice = false
                }
            } finally {
                this.voiceStopInProgress = false
            }
        },

        captureVoicePcmFrame() {
            if (!this.voicePcmAnalyserNode) {
                return
            }

            const frame = new Float32Array(this.voicePcmAnalyserNode.fftSize)
            this.voicePcmAnalyserNode.getFloatTimeDomainData(frame)
            this.voicePcmChunks.push(frame)
            this.updateVoiceLevelPercent(frame)
        },

        async startVoicePcmCapture(stream) {
            this.resetVoicePcmCaptureState(true)

            const AudioContextClass = window.AudioContext || window.webkitAudioContext
            if (!AudioContextClass || !stream) {
                return
            }

            try {
                const audioContext = new AudioContextClass()
                if (audioContext.state === 'suspended') {
                    await audioContext.resume()
                }

                const source = audioContext.createMediaStreamSource(stream)
                const analyser = audioContext.createAnalyser()
                analyser.fftSize = 2048
                const gain = audioContext.createGain()
                gain.gain.value = 0

                this.voicePcmChunks = []
                this.voicePcmSampleRate = Number(audioContext.sampleRate || 0) || 44100

                const captureBuffer = new Float32Array(analyser.fftSize)
                this.voicePcmSampleTimerId = window.setInterval(() => {
                    analyser.getFloatTimeDomainData(captureBuffer)
                    this.voicePcmChunks.push(new Float32Array(captureBuffer))
                    this.updateVoiceLevelPercent(captureBuffer)
                }, 50)

                source.connect(analyser)
                analyser.connect(gain)
                gain.connect(audioContext.destination)

                this.voiceAudioContext = audioContext
                this.voicePcmSourceNode = source
                this.voicePcmAnalyserNode = analyser
                this.voicePcmGainNode = gain
            } catch (error) {
                this.resetVoicePcmCaptureState(true)
            }
        },

        resetVoicePcmCaptureState(clearChunks = true) {
            try {
                if (this.voicePcmSourceNode) {
                    this.voicePcmSourceNode.disconnect()
                }
            } catch (error) {
                // Ignore disconnection errors.
            }

            try {
                if (this.voicePcmAnalyserNode) {
                    this.voicePcmAnalyserNode.disconnect()
                }
            } catch (error) {
                // Ignore disconnection errors.
            }

            try {
                if (this.voicePcmGainNode) {
                    this.voicePcmGainNode.disconnect()
                }
            } catch (error) {
                // Ignore disconnection errors.
            }

            if (this.voiceAudioContext) {
                this.voiceAudioContext.close().catch(() => {})
            }

            if (this.voicePcmSampleTimerId) {
                window.clearInterval(this.voicePcmSampleTimerId)
                this.voicePcmSampleTimerId = null
            }

            this.voiceAudioContext = null
            this.voicePcmSourceNode = null
            this.voicePcmAnalyserNode = null
            this.voicePcmGainNode = null
            this.voiceLevelPercent = 0

            if (clearChunks) {
                this.voicePcmChunks = []
                this.voicePcmSampleRate = 0
            }
        },

        updateVoiceLevelPercent(samples) {
            if (!(samples instanceof Float32Array) || samples.length === 0) {
                this.voiceLevelPercent = Math.max(0, this.voiceLevelPercent * 0.82)
                return
            }

            let sum = 0
            for (let i = 0; i < samples.length; i += 1) {
                sum += samples[i] * samples[i]
            }

            const rms = Math.sqrt(sum / samples.length)
            const rawPercent = Math.max(0, Math.min(100, rms * 850))
            const smoothed = (this.voiceLevelPercent * 0.65) + (rawPercent * 0.35)
            this.voiceLevelPercent = Math.max(0, Math.min(100, smoothed))
        },

        waitForRecordedChunks(chunks, timeoutMs = 700) {
            if (Array.isArray(chunks) && chunks.length > 0) {
                return Promise.resolve(true)
            }

            return new Promise((resolve) => {
                const startedAt = Date.now()
                const timerId = window.setInterval(() => {
                    if (Array.isArray(chunks) && chunks.length > 0) {
                        window.clearInterval(timerId)
                        resolve(true)
                        return
                    }

                    if (Date.now() - startedAt >= timeoutMs) {
                        window.clearInterval(timerId)
                        resolve(false)
                    }
                }, 50)
            })
        },

        waitForRecorderInactive(recorder, timeoutMs = 1200) {
            if (!recorder || recorder.state === 'inactive') {
                return Promise.resolve(true)
            }

            return new Promise((resolve) => {
                const startedAt = Date.now()
                const timerId = window.setInterval(() => {
                    if (recorder.state === 'inactive') {
                        window.clearInterval(timerId)
                        resolve(true)
                        return
                    }

                    if (Date.now() - startedAt >= timeoutMs) {
                        window.clearInterval(timerId)
                        resolve(recorder.state === 'inactive')
                    }
                }, 50)
            })
        },

        stopVoiceRecordTimer() {
            if (this.voiceRecordTimerId) {
                window.clearInterval(this.voiceRecordTimerId)
                this.voiceRecordTimerId = null
            }

            this.voiceRecordDurationSeconds = 0
        },

        stopVoiceRecordStreamTracks(stream) {
            if (!stream) {
                return
            }

            for (const track of stream.getTracks()) {
                track.stop()
            }
        },

        normalizeRecordedAudioMimeType(mimeType) {
            const normalized = String(mimeType || '').toLowerCase().trim()
            if (!normalized) {
                return ''
            }

            const [baseType] = normalized.split(';')

            return baseType?.trim() || ''
        },

        appendRecordedVoice(chunks, mimeType, recordedDurationMs = 0) {
            const blobType = this.normalizeRecordedAudioMimeType(mimeType)
            const blob = blobType !== ''
                ? new Blob(chunks, { type: blobType })
                : new Blob(chunks)

            if (blob.size === 0) {
                alert('Голосовое получилось пустым. Повторите запись.')
                return false
            }

            const extension = this.fileExtensionFromMime(blobType)
            const timestamp = Date.now()
            const file = new File([blob], `voice-${timestamp}.${extension}`, { type: blobType })
            const key = `voice-${timestamp}-${Math.random().toString(36).slice(2)}`
            this.appendVoiceFileToComposer(file, key, file.type || blobType || '')

            return true
        },

        appendRecordedVoiceFromPcm(recordedDurationMs = 0) {
            const wavBlob = this.buildWavBlobFromPcm(recordedDurationMs)
            if (!wavBlob || wavBlob.size === 0) {
                return false
            }

            const timestamp = Date.now()
            const file = new File([wavBlob], `voice-${timestamp}.wav`, { type: 'audio/wav' })
            const key = `voice-pcm-${timestamp}-${Math.random().toString(36).slice(2)}`
            this.appendVoiceFileToComposer(file, key, 'audio/wav')

            return true
        },

        buildWavBlobFromPcm(expectedDurationMs = 0) {
            if (!Array.isArray(this.voicePcmChunks) || this.voicePcmChunks.length === 0) {
                return null
            }

            const sampleRate = Number(this.voicePcmSampleRate || 0) || 44100
            const totalSamples = this.voicePcmChunks.reduce((sum, chunk) => sum + chunk.length, 0)
            const expectedSamples = expectedDurationMs > 0
                ? Math.round((sampleRate * expectedDurationMs) / 1000)
                : 0
            const targetSamples = Math.max(totalSamples, expectedSamples)

            if (targetSamples <= 0) {
                return null
            }

            let peak = 0
            for (const chunk of this.voicePcmChunks) {
                for (let i = 0; i < chunk.length; i += 1) {
                    const value = Math.abs(chunk[i])
                    if (value > peak) {
                        peak = value
                    }
                }
            }

            let normalizationGain = 1
            if (peak >= this.voiceNormalizationMinSignal) {
                normalizationGain = this.voiceNormalizationTargetPeak / peak
            }

            if (!Number.isFinite(normalizationGain) || normalizationGain <= 0) {
                normalizationGain = 1
            }

            normalizationGain = Math.min(this.voiceNormalizationMaxGain, normalizationGain)

            const buffer = new ArrayBuffer(44 + targetSamples * 2)
            const view = new DataView(buffer)

            const writeString = (offset, value) => {
                for (let i = 0; i < value.length; i += 1) {
                    view.setUint8(offset + i, value.charCodeAt(i))
                }
            }

            writeString(0, 'RIFF')
            view.setUint32(4, 36 + targetSamples * 2, true)
            writeString(8, 'WAVE')
            writeString(12, 'fmt ')
            view.setUint32(16, 16, true)
            view.setUint16(20, 1, true)
            view.setUint16(22, 1, true)
            view.setUint32(24, sampleRate, true)
            view.setUint32(28, sampleRate * 2, true)
            view.setUint16(32, 2, true)
            view.setUint16(34, 16, true)
            writeString(36, 'data')
            view.setUint32(40, targetSamples * 2, true)

            let offset = 44
            for (const chunk of this.voicePcmChunks) {
                for (let i = 0; i < chunk.length; i += 1) {
                    const sample = Math.max(-1, Math.min(1, chunk[i] * normalizationGain))
                    const int16 = sample < 0 ? sample * 0x8000 : sample * 0x7fff
                    view.setInt16(offset, int16, true)
                    offset += 2

                    if (offset >= 44 + targetSamples * 2) {
                        break
                    }
                }

                if (offset >= 44 + targetSamples * 2) {
                    break
                }
            }

            return new Blob([buffer], { type: 'audio/wav' })
        },

        appendVoiceFileToComposer(file, key, mimeType = '') {
            const url = URL.createObjectURL(file)

            this.selectedFiles.push({ key, file })
            this.selectedFilePreviews.push({
                key,
                url,
                kind: 'audio',
                name: file.name,
                mimeType: mimeType || file.type || '',
            })
        },

        fileExtensionFromMime(mimeType) {
            const normalized = String(mimeType || '').toLowerCase()
            if (normalized.includes('ogg')) {
                return 'ogg'
            }
            if (normalized.includes('mp4') || normalized.includes('m4a')) {
                return 'm4a'
            }
            if (normalized.includes('mpeg') || normalized.includes('mp3')) {
                return 'mp3'
            }
            if (normalized.includes('wav')) {
                return 'wav'
            }
            if (normalized.includes('aac')) {
                return 'aac'
            }
            if (normalized.includes('webm')) {
                return 'webm'
            }

            return 'webm'
        },

        onMessageFilesSelected(event) {
            const files = Array.from(event.target.files ?? [])
            if (files.length === 0) {
                return
            }

            for (const file of files) {
                const key = `${Date.now()}-${Math.random().toString(36).slice(2)}`
                const kind = this.resolvePreviewKind(file)
                const url = URL.createObjectURL(file)

                this.selectedFiles.push({key, file})
                this.selectedFilePreviews.push({
                    key,
                    url,
                    kind,
                    name: file.name,
                    mimeType: file.type || '',
                })
            }

            this.$refs.messageFiles.value = null
        },

        resolvePreviewKind(file) {
            const mime = String(file?.type || '').toLowerCase()
            const name = String(file?.name || '').toLowerCase()

            if (mime.startsWith('video/')) {
                return 'video'
            }
            if (mime.startsWith('audio/') || /\.(mp3|wav|ogg|m4a|aac|opus|weba|webm)$/i.test(name)) {
                return 'audio'
            }

            return 'image'
        },

        removeSelectedFile(key) {
            const preview = this.selectedFilePreviews.find((item) => item.key === key)
            if (preview) {
                URL.revokeObjectURL(preview.url)
            }

            this.selectedFiles = this.selectedFiles.filter((item) => item.key !== key)
            this.selectedFilePreviews = this.selectedFilePreviews.filter((item) => item.key !== key)
        },

        clearSelectedFiles() {
            for (const preview of this.selectedFilePreviews) {
                URL.revokeObjectURL(preview.url)
            }

            this.selectedFiles = []
            this.selectedFilePreviews = []
        },

        async sendMessage() {
            if (!this.activeConversation || !this.canSendCurrentMessage || this.activeConversation.is_blocked) {
                return
            }

            this.isSending = true
            try {
                let response

                if (this.selectedFiles.length > 0) {
                    const formData = new FormData()
                    formData.append('body', this.messageBody)

                    for (const item of this.selectedFiles) {
                        formData.append('files[]', item.file)
                    }

                    response = await axios.post(`/api/chats/${this.activeConversation.id}/messages`, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        }
                    })
                } else {
                    response = await axios.post(`/api/chats/${this.activeConversation.id}/messages`, {
                        body: this.messageBody,
                    })
                }

                this.upsertMessage(response.data.data)
                this.messageBody = ''
                this.clearSelectedFiles()
                this.updateConversationFromIncoming(response.data.data)
                this.$nextTick(() => this.scrollMessagesDown())
            } catch (error) {
                if (error.response?.status === 423) {
                    await this.loadConversations()
                }
                alert(this.resolveApiMessage(error, 'Не удалось отправить сообщение.'))
            } finally {
                this.isSending = false
            }
        },

        resolveApiMessage(error, fallback) {
            const status = Number(error?.response?.status ?? 0)
            if (status >= 500 || status === 0) {
                return fallback
            }

            const payloadMessage = String(error?.response?.data?.message || '').trim()
            if (payloadMessage !== '' && payloadMessage.toLowerCase() !== 'validation failed.') {
                return payloadMessage
            }

            const errors = error?.response?.data?.errors
            if (errors && typeof errors === 'object') {
                for (const messages of Object.values(errors)) {
                    if (Array.isArray(messages) && messages.length > 0) {
                        const firstMessage = String(messages[0] || '').trim()
                        if (firstMessage !== '') {
                            return firstMessage
                        }
                    }
                }
            }

            return payloadMessage || fallback
        },

        syncConversationSubscriptions() {
            if (!window.Echo) {
                return
            }

            const activeIds = new Set(this.conversations.map((conversation) => conversation.id))

            for (const [id, channelName] of Object.entries(this.subscribedChannels)) {
                if (!activeIds.has(Number(id))) {
                    window.Echo.leave(channelName)
                    delete this.subscribedChannels[id]
                }
            }

            for (const conversation of this.conversations) {
                if (this.subscribedChannels[conversation.id]) {
                    continue
                }

                const channelName = `chat.conversation.${conversation.id}`

                window.Echo.private(channelName)
                    .listen('.chat.message.sent', (payload) => {
                        this.handleIncomingMessage(payload)
                    })

                this.subscribedChannels[conversation.id] = channelName
            }
        },

        unsubscribeAllChannels() {
            if (!window.Echo) {
                this.subscribedChannels = {}
                return
            }

            for (const channelName of Object.values(this.subscribedChannels)) {
                window.Echo.leave(channelName)
            }

            this.subscribedChannels = {}
        },

        handleIncomingMessage(payload) {
            const mine = this.isMine(payload)
            const isActiveConversation = Boolean(this.activeConversation && payload.conversation_id === this.activeConversation.id)

            this.updateConversationFromIncoming(payload, {
                incrementUnread: !mine && !isActiveConversation,
            })

            if (!mine) {
                this.playNotificationSound()
            }

            if (isActiveConversation) {
                this.upsertMessage(payload)
                this.setConversationReadLocally(payload.conversation_id)
                this.markConversationRead(payload.conversation_id)
                this.$nextTick(() => this.scrollMessagesDown())
            }
        },

        upsertMessage(message) {
            if (this.messages.some((item) => item.id === message.id)) {
                return
            }

            this.messages.push(message)
            this.messages.sort((first, second) => new Date(first.created_at) - new Date(second.created_at))
        },

        updateConversationFromIncoming(message, options = {}) {
            const target = this.conversations.find((conversation) => conversation.id === message.conversation_id)

            if (!target) {
                return
            }

            target.last_message = message
            target.updated_at = message.created_at

            if (options.incrementUnread === true) {
                const current = Number(target.unread_count ?? 0)
                target.unread_count = Number.isFinite(current) ? current + 1 : 1
                target.has_unread = true
                this.emitUnreadTotal()
            }

            this.sortConversationsByActivity()

            if (this.activeConversation && this.activeConversation.id === target.id) {
                this.activeConversation = target
            }
        },

        attachmentSummary(message) {
            const attachments = Array.isArray(message.attachments) ? message.attachments : []
            const count = attachments.length
            if (count === 0) {
                return 'Сообщение'
            }

            const audioCount = attachments.filter((item) => item.type === 'audio').length
            const videoCount = attachments.filter((item) => item.type === 'video').length
            const mediaCount = count - audioCount - videoCount

            const parts = []
            if (audioCount > 0) {
                parts.push(audioCount === 1 ? 'Голосовое' : `Голосовых: ${audioCount}`)
            }
            if (videoCount > 0) {
                parts.push(videoCount === 1 ? 'Видео' : `Видео: ${videoCount}`)
            }
            if (mediaCount > 0) {
                parts.push(mediaCount === 1 ? 'Медиа' : `Медиа: ${mediaCount}`)
            }

            return parts.join(' · ') || `Вложений: ${count}`
        },

        sortConversationsByActivity() {
            this.conversations.sort((first, second) => {
                const firstStamp = first.updated_at ? new Date(first.updated_at).getTime() : 0
                const secondStamp = second.updated_at ? new Date(second.updated_at).getTime() : 0

                return secondStamp - firstStamp
            })
        },

        isMine(message) {
            return this.currentUser && message.user && message.user.id === this.currentUser.id
        },

        scrollMessagesDown() {
            if (!this.$refs.messagesContainer) {
                return
            }

            this.$refs.messagesContainer.scrollTop = this.$refs.messagesContainer.scrollHeight
        },

        loadNotificationSettings() {
            try {
                const raw = localStorage.getItem(CHAT_SOUND_STORAGE_KEY)
                if (!raw) {
                    return
                }

                const parsed = JSON.parse(raw)
                this.notificationSettings = {
                    enabled: typeof parsed.enabled === 'boolean' ? parsed.enabled : true,
                    sound: parsed.sound || 'ping',
                    volume: Number.isFinite(parsed.volume) ? Math.max(0, Math.min(100, parsed.volume)) : 60,
                    customSoundDataUrl: parsed.customSoundDataUrl || null,
                }
            } catch (error) {
                // Ignore malformed storage values.
            }
        },

        saveNotificationSettings() {
            localStorage.setItem(CHAT_SOUND_STORAGE_KEY, JSON.stringify(this.notificationSettings))
        },

        onCustomSoundSelected(event) {
            const file = event.target.files?.[0]
            if (!file) {
                return
            }

            if (!file.type.startsWith('audio/')) {
                alert('Можно загрузить только аудиофайл.')
                return
            }

            if (file.size > 1024 * 1024) {
                alert('Файл звука слишком большой. Максимум 1MB.')
                return
            }

            const reader = new FileReader()
            reader.onload = () => {
                this.notificationSettings.customSoundDataUrl = typeof reader.result === 'string' ? reader.result : null
                this.notificationSettings.sound = 'custom'
                this.saveNotificationSettings()
            }
            reader.readAsDataURL(file)

            event.target.value = null
        },

        previewNotificationSound() {
            this.playNotificationSound()
        },

        playNotificationSound() {
            if (!this.notificationSettings.enabled) {
                return
            }

            if (this.notificationSettings.sound === 'custom' && this.notificationSettings.customSoundDataUrl) {
                const audio = new Audio(this.notificationSettings.customSoundDataUrl)
                audio.volume = this.notificationSettings.volume / 100
                audio.play().catch(() => {})
                return
            }

            const AudioContextClass = window.AudioContext || window.webkitAudioContext
            if (!AudioContextClass) {
                return
            }

            const context = new AudioContextClass()
            const gainNode = context.createGain()
            gainNode.connect(context.destination)

            const preset = this.notificationSettings.sound
            const frequency = preset === 'bell' ? 660 : preset === 'chime' ? 520 : 880
            const waveType = preset === 'bell' ? 'triangle' : preset === 'chime' ? 'square' : 'sine'

            const oscillator = context.createOscillator()
            oscillator.type = waveType
            oscillator.frequency.value = frequency
            oscillator.connect(gainNode)

            const now = context.currentTime
            const volume = (this.notificationSettings.volume / 100) * 0.15

            gainNode.gain.setValueAtTime(0.0001, now)
            gainNode.gain.linearRampToValueAtTime(volume, now + 0.01)
            gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.2)

            oscillator.start(now)
            oscillator.stop(now + 0.2)

            oscillator.onended = () => {
                context.close().catch(() => {})
            }
        },

        formatDateTime(value) {
            if (!value) {
                return '—'
            }

            const date = new Date(value)
            if (Number.isNaN(date.getTime())) {
                return value
            }

            return date.toLocaleString('ru-RU')
        },
    }
}
</script>
