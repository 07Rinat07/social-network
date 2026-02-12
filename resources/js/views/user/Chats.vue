<template>
    <div class="page-wrap chat-layout chat-screen">
        <section class="chat-list fade-in chat-sidebar">
            <div class="chat-sidebar-head">
                <div>
                    <h2 class="section-title chat-sidebar-title">My chats</h2>
                    <p class="section-subtitle chat-sidebar-subtitle">All dialogs {{ conversationCounters.all }}</p>
                </div>
                <div class="chat-pane-switch">
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="leftPaneMode === 'conversations' ? 'btn-primary' : 'btn-outline'"
                        @click="setLeftPaneMode('conversations')"
                    >
                        Диалоги
                    </button>
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="leftPaneMode === 'users' ? 'btn-primary' : 'btn-outline'"
                        @click="setLeftPaneMode('users')"
                    >
                        Люди
                    </button>
                </div>
            </div>

            <div class="chat-sidebar-controls">
                <input
                    v-if="leftPaneMode === 'conversations'"
                    class="input-field chat-search-field"
                    v-model.trim="conversationSearch"
                    type="text"
                    placeholder="Search..."
                >
                <input
                    v-else
                    class="input-field chat-search-field"
                    v-model.trim="userSearch"
                    type="text"
                    placeholder="Поиск пользователей"
                    @input="onUserSearchInput"
                >

                <div class="chat-filter-row" v-if="leftPaneMode === 'conversations'">
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="conversationFilter === 'all' ? 'btn-primary' : 'btn-outline'"
                        @click="setConversationFilter('all')"
                    >
                        Все {{ conversationCounters.all }}
                    </button>
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="conversationFilter === 'unread' ? 'btn-primary' : 'btn-outline'"
                        @click="setConversationFilter('unread')"
                    >
                        Непрочит. {{ conversationCounters.unread }}
                    </button>
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="conversationFilter === 'blocked' ? 'btn-primary' : 'btn-outline'"
                        @click="setConversationFilter('blocked')"
                    >
                        Блок {{ conversationCounters.blocked }}
                    </button>
                </div>
            </div>

            <div class="chat-conversation-list" v-if="leftPaneMode === 'conversations'">
                <div
                    v-for="conversation in filteredConversations"
                    :key="conversation.id"
                    class="chat-item chat-item--rich"
                    :class="{
                        active: activeConversation && activeConversation.id === conversation.id,
                        'is-pinned': isConversationPinned(conversation.id),
                    }"
                    @click="openConversation(conversation)"
                >
                    <div class="chat-item-avatar-wrap">
                        <img
                            v-if="conversationAvatar(conversation)"
                            :src="conversationAvatar(conversation)"
                            alt="avatar"
                            class="avatar avatar-sm chat-item-avatar"
                        >
                        <span
                            v-else
                            class="avatar avatar-sm avatar-placeholder chat-item-avatar"
                        >
                            {{ conversationInitial(conversation) }}
                        </span>
                    </div>

                    <div class="chat-item-body">
                        <div class="chat-item-row">
                            <strong class="chat-item-title">{{ conversation.title }}</strong>
                            <span class="chat-item-time">{{ conversationTime(conversation) }}</span>
                        </div>
                        <p class="muted chat-item-preview">{{ messagePreview(conversation) }}</p>
                        <p v-if="conversation.is_blocked" class="error-text chat-item-warning">Диалог заблокирован.</p>
                    </div>

                    <div class="chat-item-side">
                        <span
                            v-if="Number(conversation.unread_count ?? 0) > 0"
                            class="badge chat-unread-badge"
                        >
                            {{ formatUnreadBadge(conversation.unread_count) }}
                        </span>
                        <button
                            class="chat-pin-btn"
                            type="button"
                            @click.stop="toggleConversationPin(conversation.id)"
                            :title="isConversationPinned(conversation.id) ? 'Убрать из закреплённых' : 'Закрепить диалог'"
                        >
                            {{ isConversationPinned(conversation.id) ? '★' : '☆' }}
                        </button>
                    </div>
                </div>

                <p v-if="filteredConversations.length === 0" class="muted chat-empty">
                    Диалоги по текущему фильтру не найдены.
                </p>
            </div>

            <div class="chat-users-list" v-else>
                <div
                    v-for="user in users"
                    :key="`u-${user.id}`"
                    class="simple-item chat-user-card"
                >
                    <div class="chat-user-head">
                        <img v-if="avatarUrl(user)" :src="avatarUrl(user)" alt="avatar" class="avatar avatar-sm">
                        <span v-else class="avatar avatar-sm avatar-placeholder">{{ initials(user) }}</span>
                        <div>
                            <strong>{{ displayName(user) }}</strong>
                            <p class="muted chat-user-nickname" v-if="user.nickname">@{{ user.nickname }}</p>
                        </div>
                    </div>

                    <p class="muted chat-user-status">
                        <span v-if="isBlockedByMe(user)">Вы заблокировали этого пользователя.</span>
                        <span v-else-if="isBlockedByUser(user)">Этот пользователь заблокировал вас.</span>
                        <span v-else>Доступен для личного чата.</span>
                    </p>

                    <p
                        v-if="isBlockedByMe(user) && getMyBlockStatusLabel(user)"
                        class="muted chat-user-status"
                    >
                        {{ getMyBlockStatusLabel(user) }}
                    </p>

                    <div class="chat-user-actions">
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
                            @click="blockUser(user, 'temporary')"
                        >
                            Блок 24ч
                        </button>
                        <button
                            v-if="!isBlockedByMe(user)"
                            class="btn btn-danger btn-sm"
                            @click="blockUser(user, 'permanent')"
                        >
                            Блок навсегда
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

                <p v-if="users.length === 0" class="muted chat-empty">
                    Пользователи не найдены.
                </p>
            </div>

            <details class="chat-sound-panel">
                <summary>Звук уведомлений</summary>
                <div class="form-grid chat-sound-grid">
                    <label class="muted chat-sound-toggle">
                        <input type="checkbox" v-model="notificationSettings.enabled" @change="saveNotificationSettings">
                        Включить звук при входящих сообщениях
                    </label>
                    <select class="select-field" v-model="notificationSettings.sound" @change="saveNotificationSettings">
                        <option
                            v-for="preset in notificationSoundPresets"
                            :key="preset.id"
                            :value="preset.id"
                        >
                            {{ preset.label }}
                        </option>
                        <option value="custom">Свой звук (из файла)</option>
                    </select>
                    <label class="muted">Громкость: {{ notificationSettings.volume }}%</label>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        class="input-field chat-volume-slider"
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
                    <p class="muted chat-sound-note">Можно загрузить свой сигнал (до 15MB). Файлы больше 2MB работают до перезагрузки страницы.</p>
                </div>
            </details>
        </section>

        <section class="chat-window fade-in chat-main">
            <header class="chat-window-head">
                <div class="chat-window-title-wrap">
                    <img
                        v-if="activeConversationPeer && avatarUrl(activeConversationPeer)"
                        :src="avatarUrl(activeConversationPeer)"
                        alt="avatar"
                        class="avatar avatar-sm chat-header-avatar"
                    >
                    <span v-else class="avatar avatar-sm avatar-placeholder chat-header-avatar">
                        {{ activeConversation ? conversationInitial(activeConversation) : '#' }}
                    </span>
                    <div>
                        <h3 class="section-title chat-window-title">
                            {{ activeConversation ? activeConversation.title : 'Выберите чат' }}
                        </h3>
                        <p class="muted chat-window-subtitle">{{ activeConversationSubtitle }}</p>
                        <p v-if="activeConversation && activeConversation.is_blocked" class="error-text chat-window-warning">
                            В этом диалоге действует блокировка. Отправка сообщений недоступна.
                        </p>
                    </div>
                </div>

                <div class="chat-window-tools">
                    <input
                        class="input-field chat-inline-search"
                        v-model.trim="messageSearch"
                        type="text"
                        placeholder="Поиск по сообщениям"
                        :disabled="!activeConversation"
                    >
                    <div class="chat-message-filter-row">
                        <button
                            class="btn btn-sm"
                            type="button"
                            :class="messageFilter === 'all' ? 'btn-primary' : 'btn-outline'"
                            :disabled="!activeConversation"
                            @click="setMessageFilter('all')"
                        >
                            Все
                        </button>
                        <button
                            class="btn btn-sm"
                            type="button"
                            :class="messageFilter === 'files_only' ? 'btn-primary' : 'btn-outline'"
                            :disabled="!activeConversation"
                            @click="setMessageFilter('files_only')"
                        >
                            Только файлы
                        </button>
                    </div>
                    <button class="btn btn-outline btn-sm" type="button" @click="scrollMessagesDown" :disabled="!activeConversation">
                        Вниз
                    </button>
                </div>
            </header>

            <div class="chat-messages" ref="messagesContainer">
                <template v-if="activeConversation">
                    <div
                        v-for="message in displayedMessages"
                        :key="message.id"
                        class="chat-bubble-row"
                        :class="{mine: isMine(message)}"
                    >
                        <img
                            v-if="!isMine(message) && avatarUrl(message.user)"
                            :src="avatarUrl(message.user)"
                            alt="avatar"
                            class="avatar avatar-sm chat-message-avatar"
                        >
                        <span
                            v-else-if="!isMine(message)"
                            class="avatar avatar-sm avatar-placeholder chat-message-avatar"
                        >
                            {{ initials(message.user) }}
                        </span>

                        <div class="chat-message" :class="{mine: isMine(message)}">
                            <div class="chat-message-head">
                                <div class="chat-meta">
                                    {{ displayName(message.user) }} · {{ message.date }}
                                </div>
                                <button
                                    v-if="canDeleteMessage(message)"
                                    class="btn btn-danger btn-sm chat-message-remove-btn"
                                    type="button"
                                    :disabled="isMessageDeleting(message.id)"
                                    @click.stop="deleteMessage(message)"
                                >
                                    {{ isMessageDeleting(message.id) ? 'Удаление...' : 'Удалить' }}
                                </button>
                            </div>
                            <div v-if="message.body" class="chat-message-body">{{ message.body }}</div>

                            <div class="media-grid chat-message-media" v-if="message.attachments && message.attachments.length > 0">
                                <div
                                    v-for="attachment in message.attachments"
                                    :key="`msg-att-${message.id}-${attachment.id}`"
                                    class="chat-message-attachment"
                                >
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
                                    <div v-else-if="attachment.type === 'file'" class="chat-file-card">
                                        <strong class="chat-file-name">{{ attachment.original_name || 'Файл' }}</strong>
                                        <span class="muted chat-file-meta">
                                            {{ attachment.mime_type || 'application/octet-stream' }}
                                            <template v-if="Number(attachment.size || 0) > 0">
                                                · {{ formatBytes(attachment.size) }}
                                            </template>
                                        </span>
                                    </div>
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
                                    <button
                                        class="btn btn-outline btn-sm chat-attachment-download-btn"
                                        type="button"
                                        @click.stop="downloadAttachment(attachment)"
                                    >
                                        Скачать
                                    </button>
                                    <button
                                        v-if="canDeleteMessage(message)"
                                        class="btn btn-danger btn-sm chat-attachment-remove-btn"
                                        type="button"
                                        :disabled="isAttachmentDeleting(message.id, attachment.id)"
                                        @click.stop="deleteAttachment(message, attachment)"
                                    >
                                        {{ isAttachmentDeleting(message.id, attachment.id) ? 'Удаление...' : 'Удалить файл' }}
                                    </button>
                                </div>
                            </div>

                            <div class="chat-message-reactions" v-if="Array.isArray(message.reactions) && message.reactions.length > 0">
                                <button
                                    v-for="reaction in message.reactions"
                                    :key="`msg-reaction-${message.id}-${reaction.emoji}`"
                                    class="chat-reaction-chip"
                                    :class="{'is-active': reaction.reacted_by_me}"
                                    type="button"
                                    :disabled="isMessageReactionToggling(message.id, reaction.emoji)"
                                    @click.stop="toggleMessageReaction(message, reaction.emoji)"
                                >
                                    <span>{{ reaction.emoji }}</span>
                                    <span>{{ reaction.count }}</span>
                                </button>
                            </div>

                            <div class="chat-message-reaction-picker">
                                <button
                                    v-for="emoji in messageReactionEmojis"
                                    :key="`msg-reaction-picker-${message.id}-${emoji}`"
                                    class="chat-reaction-picker-btn"
                                    :class="{'is-active': hasMessageReactionFromMe(message, emoji)}"
                                    type="button"
                                    :title="`Реакция ${emoji}`"
                                    :disabled="isMessageReactionToggling(message.id, emoji)"
                                    @click.stop="toggleMessageReaction(message, emoji)"
                                >
                                    {{ emoji }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="messages.length === 0" class="muted chat-empty">Сообщений пока нет.</p>
                    <p v-else-if="displayedMessages.length === 0" class="muted chat-empty">
                        {{ messageFilter === 'files_only' ? 'Сообщений с файлами не найдено.' : 'По запросу ничего не найдено.' }}
                    </p>
                </template>

                <p v-else class="muted chat-empty">Откройте чат, чтобы начать переписку.</p>
            </div>

            <form class="form-grid chat-composer" @submit.prevent="sendMessage">
                <div class="chat-composer-meta">
                    <p class="muted">Ctrl+Enter для быстрой отправки.</p>
                    <span class="muted chat-char-counter">{{ messageBody.length }}/4000</span>
                </div>

                <textarea
                    class="textarea-field chat-composer-input"
                    v-model="messageBody"
                    placeholder="Введите сообщение..."
                    maxlength="4000"
                    :disabled="isComposerDisabled"
                    @keydown.ctrl.enter.prevent="sendMessage"
                    @keydown.meta.enter.prevent="sendMessage"
                ></textarea>

                <div class="emoji-row chat-emoji-row">
                    <button v-for="emoji in emojis" :key="emoji" type="button" class="emoji-btn" @click="appendEmoji(emoji)">
                        {{ emoji }}
                    </button>
                </div>

                <div class="chat-composer-actions">
                    <input
                        ref="messageFiles"
                        type="file"
                        accept="image/*,video/*,audio/*,.gif,.mp3,.wav,.ogg,.m4a,.aac,.opus,.weba,.webm,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.rtf,.zip,.rar,.7z,.tar,.gz,.json,.xml"
                        multiple
                        class="hidden"
                        @change="onMessageFilesSelected"
                    >
                    <button class="btn btn-outline" type="button" @click="openFileDialog" :disabled="isComposerDisabled">
                        Добавить файл/фото/видео/аудио
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

                <p class="muted" v-if="!canRecordVoice">
                    Запись голосовых в этом браузере недоступна. Можно отправить готовый аудиофайл.
                </p>
                <p class="muted" v-if="isRecordingVoice">
                    Идёт запись голосового сообщения...
                </p>
                <div
                    v-if="isRecordingVoice"
                    class="section-card chat-voice-card"
                >
                    <div class="muted chat-voice-meta">
                        <span>Уровень микрофона: {{ Math.round(voiceLevelPercent) }}%</span>
                        <span>Лимит: {{ formattedVoiceRecordDurationLimit }}</span>
                    </div>
                    <div class="chat-voice-progress chat-voice-progress-level">
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
                    <div class="chat-voice-progress chat-voice-progress-duration">
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
                <p class="muted" v-if="isProcessingVoice">
                    Обрабатываем запись...
                </p>

                <div class="media-grid" v-if="selectedFilePreviews.length > 0">
                    <div v-for="item in selectedFilePreviews" :key="item.key" class="section-card chat-preview-item">
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
                        <div v-else-if="item.kind === 'file'" class="chat-file-card">
                            <strong class="chat-file-name">{{ item.name || 'Файл' }}</strong>
                            <span class="muted chat-file-meta">
                                {{ item.mimeType || 'application/octet-stream' }}
                                <template v-if="Number(item.size || 0) > 0">
                                    · {{ formatBytes(item.size) }}
                                </template>
                            </span>
                        </div>
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
                        <p class="muted chat-preview-name" v-if="item.kind === 'audio' || item.kind === 'file'">
                            {{ item.name }}
                        </p>
                        <button class="btn btn-danger btn-sm chat-preview-remove" type="button" @click="removeSelectedFile(item.key)">
                            Убрать
                        </button>
                    </div>
                </div>

                <button class="btn btn-primary chat-send-btn" type="submit" :disabled="isComposerDisabled || isSending || isRecordingVoice || isProcessingVoice || voiceStopInProgress || !canSendCurrentMessage">
                    {{ isSending ? 'Отправка...' : 'Отправить' }}
                </button>
            </form>
        </section>

        <aside class="chat-inspector fade-in">
            <template v-if="activeConversation">
                <div class="chat-inspector-card">
                    <div class="chat-inspector-head">
                        <img
                            v-if="activeConversationPeer && avatarUrl(activeConversationPeer)"
                            :src="avatarUrl(activeConversationPeer)"
                            alt="avatar"
                            class="avatar avatar-sm"
                        >
                        <span v-else class="avatar avatar-sm avatar-placeholder">{{ conversationInitial(activeConversation) }}</span>
                        <div>
                            <strong>{{ activeConversation.title }}</strong>
                            <p class="muted" v-if="activeConversationPeer && activeConversationPeer.nickname">@{{ activeConversationPeer.nickname }}</p>
                        </div>
                    </div>

                    <div class="chat-inspector-grid">
                        <div class="chat-inspector-metric chat-inspector-metric--wide">
                            <span class="muted">Тип</span>
                            <strong>{{ activeConversation.type === 'global' ? 'Общий чат' : 'Личный чат' }}</strong>
                        </div>
                        <div class="chat-inspector-metric">
                            <span class="muted">Участники</span>
                            <strong>{{ Array.isArray(activeConversation.participants) ? activeConversation.participants.length : 0 }}</strong>
                        </div>
                        <div class="chat-inspector-metric">
                            <span class="muted">Непрочитано</span>
                            <strong>{{ Number(activeConversation.unread_count ?? 0) }}</strong>
                        </div>
                    </div>

                    <div class="chat-inspector-actions">
                        <button class="btn btn-outline btn-sm" type="button" @click="markConversationRead(activeConversation.id)">
                            Отметить прочитанным
                        </button>
                        <button class="btn btn-outline btn-sm" type="button" @click="setLeftPaneMode('users')">
                            Новый диалог
                        </button>
                    </div>
                </div>

                <div class="chat-inspector-card" v-if="activeConversationPeer">
                    <h4 class="chat-inspector-card-title">Быстрые действия</h4>
                    <div class="chat-inspector-actions">
                        <button
                            v-if="!isBlockedByMe(activeConversationPeer)"
                            class="btn btn-danger btn-sm"
                            type="button"
                            @click="blockUser(activeConversationPeer, 'temporary')"
                        >
                            Блок 24ч
                        </button>
                        <button
                            v-if="!isBlockedByMe(activeConversationPeer)"
                            class="btn btn-danger btn-sm"
                            type="button"
                            @click="blockUser(activeConversationPeer, 'permanent')"
                        >
                            Блок навсегда
                        </button>
                        <button
                            v-if="isBlockedByMe(activeConversationPeer)"
                            class="btn btn-success btn-sm"
                            type="button"
                            @click="unblockUser(activeConversationPeer)"
                        >
                            Разблокировать
                        </button>
                    </div>
                    <p v-if="isBlockedByMe(activeConversationPeer) && getMyBlockStatusLabel(activeConversationPeer)" class="muted">
                        {{ getMyBlockStatusLabel(activeConversationPeer) }}
                    </p>
                </div>

                <div class="chat-inspector-card">
                    <h4 class="chat-inspector-card-title">Статистика диалога</h4>
                    <ul class="chat-stats-list">
                        <li>
                            <span class="muted">Сообщений в окне</span>
                            <strong>{{ messages.length }}</strong>
                        </li>
                        <li>
                            <span class="muted">Медиа во вложениях</span>
                            <strong>{{ activeConversationMediaCount }}</strong>
                        </li>
                        <li>
                            <span class="muted">Закреплён</span>
                            <strong>{{ isConversationPinned(activeConversation.id) ? 'Да' : 'Нет' }}</strong>
                        </li>
                    </ul>
                </div>
            </template>

            <div class="chat-inspector-card" v-else>
                <h4 class="chat-inspector-card-title">Информация</h4>
                <p class="muted">Выберите чат слева, чтобы увидеть детали, фильтровать сообщения и управлять диалогом.</p>
            </div>

            <div class="chat-inspector-card">
                <h4 class="chat-inspector-card-title">Хранение переписки</h4>
                <p class="muted chat-storage-note">
                    Сообщения в чатах сохраняются, пока пользователь их не удалит. Эти настройки управляют содержимым архива и сроком хранения.
                </p>

                <label class="chat-setting-check">
                    <input type="checkbox" v-model="chatStorageForm.save_text_messages">
                    <span>Сохранять текст сообщений</span>
                </label>
                <label class="chat-setting-check">
                    <input type="checkbox" v-model="chatStorageForm.save_media_attachments">
                    <span>Сохранять медиа (фото/видео/аудио/gif)</span>
                </label>
                <label class="chat-setting-check">
                    <input type="checkbox" v-model="chatStorageForm.save_file_attachments">
                    <span>Сохранять файлы (документы/архивы)</span>
                </label>
                <label class="chat-setting-check">
                    <input type="checkbox" v-model="chatStorageForm.auto_archive_enabled">
                    <span>Разрешить автоматическое архивирование</span>
                </label>

                <div class="chat-setting-inline">
                    <label for="chatRetentionDays" class="muted">Срок хранения (дни)</label>
                    <input
                        id="chatRetentionDays"
                        class="input-field"
                        type="number"
                        min="1"
                        max="3650"
                        step="1"
                        v-model.trim="chatStorageForm.retention_days"
                        placeholder="Пусто = бессрочно"
                    >
                </div>

                <div class="chat-inspector-actions">
                    <button
                        class="btn btn-primary btn-sm"
                        type="button"
                        :disabled="isSavingChatStorageSettings || isLoadingChatStorageSettings"
                        @click="saveChatStorageSettings"
                    >
                        {{ isSavingChatStorageSettings ? 'Сохранение...' : 'Сохранить настройки' }}
                    </button>
                    <button
                        class="btn btn-outline btn-sm"
                        type="button"
                        :disabled="isSavingChatStorageSettings || isLoadingChatStorageSettings"
                        @click="resetChatStorageForm"
                    >
                        Сбросить
                    </button>
                </div>
            </div>

            <div class="chat-inspector-card">
                <h4 class="chat-inspector-card-title">Архивы чатов</h4>

                <div class="chat-inspector-actions">
                    <button
                        class="btn btn-outline btn-sm"
                        type="button"
                        :disabled="archiveCreateScopeInProgress === 'all'"
                        @click="createChatArchive('all')"
                    >
                        {{ archiveCreateScopeInProgress === 'all' ? 'Создание...' : 'Архив всех чатов' }}
                    </button>
                    <button
                        class="btn btn-outline btn-sm"
                        type="button"
                        :disabled="!activeConversation || archiveCreateScopeInProgress === 'conversation'"
                        @click="createChatArchive('conversation')"
                    >
                        {{ archiveCreateScopeInProgress === 'conversation' ? 'Создание...' : 'Архив текущего чата' }}
                    </button>
                </div>

                <p v-if="isLoadingChatArchives" class="muted">Загружаем архивы...</p>
                <p v-else-if="chatArchives.length === 0" class="muted">Архивов пока нет.</p>

                <ul v-else class="chat-archive-list">
                    <li v-for="archive in chatArchives" :key="archive.id" class="chat-archive-item">
                        <div class="chat-archive-head">
                            <strong class="chat-archive-title">{{ archive.title }}</strong>
                            <span class="muted">{{ formatDateTime(archive.created_at) }}</span>
                        </div>
                        <p class="muted chat-archive-meta">
                            Сообщений: {{ archive.messages_count }} · Чатов: {{ archive.conversations_count }}
                        </p>
                        <p v-if="archive.restored_at" class="muted chat-archive-meta">
                            Восстановлен: {{ formatDateTime(archive.restored_at) }}
                        </p>

                        <div class="chat-inspector-actions">
                            <button
                                class="btn btn-outline btn-sm"
                                type="button"
                                :disabled="isArchiveDownloading(archive.id)"
                                @click="downloadChatArchive(archive)"
                            >
                                {{ isArchiveDownloading(archive.id) ? 'Скачивание...' : 'Скачать' }}
                            </button>
                            <button
                                class="btn btn-outline btn-sm"
                                type="button"
                                :disabled="isArchiveRestoring(archive.id)"
                                @click="restoreChatArchive(archive)"
                            >
                                {{ isArchiveRestoring(archive.id) ? 'Восстановление...' : 'Восстановить' }}
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </aside>

        <MediaLightbox ref="mediaLightbox"></MediaLightbox>
    </div>
</template>

<script>
import MediaLightbox from '../../components/MediaLightbox.vue'
import MediaPlayer from '../../components/MediaPlayer.vue'
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../../utils/mediaPreview'

const CHAT_SOUND_STORAGE_KEY = 'chat_notification_settings_v1'
const CHAT_UI_STORAGE_KEY = 'chat_ui_settings_v1'
const CHAT_MESSAGE_REACTION_EMOJIS = ['👍', '❤️', '🔥', '😂', '👏', '😮']
const DEFAULT_NOTIFICATION_SOUND_ID = 'beep_short'
const MAX_CUSTOM_NOTIFICATION_SOUND_BYTES = 15 * 1024 * 1024
const MAX_PERSISTED_CUSTOM_NOTIFICATION_SOUND_BYTES = 2 * 1024 * 1024
const LEGACY_NOTIFICATION_SOUND_MAP = {
    ping: 'beep_short',
    bell: 'alarm_clock',
    chime: 'pop',
}

const NOTIFICATION_SOUND_PRESETS = [
    { id: 'beep_short', label: 'Короткий сигнал', url: '/sounds/notifications/beep_short.ogg' },
    { id: 'pop', label: 'Pop', url: '/sounds/notifications/pop.ogg' },
    { id: 'swoosh', label: 'Swoosh', url: '/sounds/notifications/swoosh.ogg' },
    { id: 'cartoon_boing', label: 'Boing', url: '/sounds/notifications/cartoon_boing.ogg' },
    { id: 'wood_plank_flicks', label: 'Деревянный клик', url: '/sounds/notifications/wood_plank_flicks.ogg' },
    { id: 'slide_whistle', label: 'Свисток', url: '/sounds/notifications/slide_whistle.ogg' },
    { id: 'clang_and_wobble', label: 'Clang Wobble', url: '/sounds/notifications/clang_and_wobble.ogg' },
    { id: 'concussive_hit_guitar_boing', label: 'Guitar Boing', url: '/sounds/notifications/concussive_hit_guitar_boing.ogg' },
    { id: 'alarm_clock', label: 'Будильник', url: '/sounds/notifications/alarm_clock.ogg' },
    { id: 'cartoon_cowbell', label: 'Cowbell', url: '/sounds/notifications/cartoon_cowbell.ogg' },
    { id: 'wood_pecker', label: 'Wood Pecker', url: '/sounds/notifications/wood_pecker.ogg' },
    { id: 'medium_bell_ringing_near', label: 'Колокольчик', url: '/sounds/notifications/medium_bell_ringing_near.ogg' },
    { id: 'digital_watch_alarm_long', label: 'Digital Alarm', url: '/sounds/notifications/digital_watch_alarm_long.ogg' },
]

const NOTIFICATION_SOUND_PRESET_IDS = new Set(NOTIFICATION_SOUND_PRESETS.map((preset) => preset.id))

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
            leftPaneMode: 'conversations',
            conversationSearch: '',
            conversationFilter: 'all',
            messageSearch: '',
            messageFilter: 'all',
            pinnedConversationIds: [],
            userSearch: '',
            userSearchDebounceTimerId: null,
            loadUsersRequestId: 0,
            isSending: false,
            selectedFiles: [],
            selectedFilePreviews: [],
            deletingMessageIds: [],
            deletingAttachmentKeys: [],
            togglingMessageReactionKeys: [],
            subscribedChannels: {},
            emojis: ['😀', '🔥', '❤️', '😂', '👏', '😎', '👍', '🎉', '🤝', '🤩'],
            messageReactionEmojis: CHAT_MESSAGE_REACTION_EMOJIS,
            canRecordVoice: false,
            isRecordingVoice: false,
            isProcessingVoice: false,
            voiceRecordDurationSeconds: 0,
            voiceStopInProgress: false,
            maxVoiceRecordDurationSeconds: 5 * 60,
            voiceLevelPercent: 0,
            voiceAutoStopTriggered: false,
            voiceNormalizationTargetPeak: 0.74,
            voiceNormalizationMaxGain: 1.8,
            voiceNormalizationMinSignal: 0.01,
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
                sound: DEFAULT_NOTIFICATION_SOUND_ID,
                volume: 60,
                customSoundDataUrl: null,
            },
            chatStorageSettings: null,
            chatStorageForm: {
                save_text_messages: true,
                save_media_attachments: true,
                save_file_attachments: true,
                retention_days: '',
                auto_archive_enabled: true,
            },
            isLoadingChatStorageSettings: false,
            isSavingChatStorageSettings: false,
            chatArchives: [],
            isLoadingChatArchives: false,
            archiveCreateScopeInProgress: '',
            downloadingArchiveIds: [],
            restoringArchiveIds: [],
        }
    },

    computed: {
        isComposerDisabled() {
            return !this.activeConversation || this.activeConversation.is_blocked
        },

        canSendCurrentMessage() {
            return this.messageBody.trim() !== '' || this.selectedFiles.length > 0
        },

        notificationSoundPresets() {
            return NOTIFICATION_SOUND_PRESETS
        },

        conversationCounters() {
            const all = this.conversations.length
            const unread = this.conversations.filter((conversation) => Number(conversation?.unread_count ?? 0) > 0).length
            const blocked = this.conversations.filter((conversation) => Boolean(conversation?.is_blocked)).length

            return { all, unread, blocked }
        },

        filteredConversations() {
            const matched = this.conversations.filter((conversation) => {
                return this.conversationMatchesFilter(conversation) && this.conversationMatchesSearch(conversation)
            })

            return [...matched].sort((first, second) => {
                const firstPinned = this.isConversationPinned(first.id) ? 1 : 0
                const secondPinned = this.isConversationPinned(second.id) ? 1 : 0

                if (firstPinned !== secondPinned) {
                    return secondPinned - firstPinned
                }

                const firstStamp = first.updated_at ? new Date(first.updated_at).getTime() : 0
                const secondStamp = second.updated_at ? new Date(second.updated_at).getTime() : 0

                return secondStamp - firstStamp
            })
        },

        displayedMessages() {
            const query = this.messageSearch.trim().toLowerCase()
            return this.messages.filter((message) => {
                const attachmentsList = Array.isArray(message?.attachments) ? message.attachments : []

                if (this.messageFilter === 'files_only') {
                    const filesCount = attachmentsList.filter((attachment) => attachment?.type === 'file').length
                    if (filesCount === 0 || filesCount !== attachmentsList.length) {
                        return false
                    }
                }

                if (query === '') {
                    return true
                }

                const body = String(message?.body || '').toLowerCase()
                const author = this.displayName(message?.user).toLowerCase()
                const attachments = attachmentsList
                    .map((attachment) => `${attachment?.original_name || ''} ${attachment?.type || ''}`)
                    .join(' ')
                    .toLowerCase()

                return body.includes(query) || author.includes(query) || attachments.includes(query)
            })
        },

        activeConversationPeer() {
            return this.conversationPeer(this.activeConversation)
        },

        activeConversationSubtitle() {
            if (!this.activeConversation) {
                return 'Слева выберите общий или личный чат.'
            }

            const participantsCount = Array.isArray(this.activeConversation.participants)
                ? this.activeConversation.participants.length
                : 0

            if (this.activeConversation.is_blocked) {
                return 'Диалог открыт только для чтения до снятия блокировки.'
            }

            if (this.activeConversation.type === 'global') {
                return `Общий канал · участников: ${participantsCount > 0 ? participantsCount : 'много'}`
            }

            if (this.activeConversationPeer?.nickname) {
                return `@${this.activeConversationPeer.nickname} · realtime-диалог`
            }

            return `Личный диалог · участников: ${participantsCount}`
        },

        activeConversationMediaCount() {
            return this.messages.reduce((total, message) => {
                const attachmentsCount = Array.isArray(message?.attachments) ? message.attachments.length : 0

                return total + attachmentsCount
            }, 0)
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
        this.loadChatUiSettings()
        this.canRecordVoice = this.isVoiceRecordingSupported()
        await this.loadCurrentUser()
        await Promise.all([
            this.loadConversations(),
            this.loadUsers(),
            this.loadMyBlocks(),
            this.loadChatStorageSettings(),
            this.loadChatArchives(),
        ])

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
        this.revokeRuntimeCustomNotificationSoundUrl()
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

        downloadAttachment(attachment) {
            const sourceUrl = String(attachment?.download_url || attachment?.url || '').trim()
            if (sourceUrl === '') {
                return
            }

            const fileName = String(attachment?.original_name || 'chat-file').trim() || 'chat-file'
            const link = document.createElement('a')
            link.href = sourceUrl
            link.download = fileName
            link.rel = 'noopener'
            link.style.display = 'none'

            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
        },

        formatBytes(bytes) {
            const size = Number(bytes || 0)
            if (!Number.isFinite(size) || size <= 0) {
                return '0 B'
            }

            const units = ['B', 'KB', 'MB', 'GB']
            let value = size
            let unitIndex = 0

            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024
                unitIndex += 1
            }

            const precision = value >= 100 ? 0 : value >= 10 ? 1 : 2
            return `${value.toFixed(precision)} ${units[unitIndex]}`
        },

        defaultChatStorageForm() {
            return {
                save_text_messages: true,
                save_media_attachments: true,
                save_file_attachments: true,
                retention_days: '',
                auto_archive_enabled: true,
            }
        },

        normalizeChatStorageSettings(settings) {
            const retentionDays = Number(settings?.retention_days)
            const maxRetentionDays = Number(settings?.max_retention_days)

            return {
                save_text_messages: Boolean(settings?.save_text_messages ?? true),
                save_media_attachments: Boolean(settings?.save_media_attachments ?? true),
                save_file_attachments: Boolean(settings?.save_file_attachments ?? true),
                retention_days: Number.isInteger(retentionDays) && retentionDays > 0 ? retentionDays : null,
                auto_archive_enabled: Boolean(settings?.auto_archive_enabled ?? true),
                max_retention_days: Number.isInteger(maxRetentionDays) && maxRetentionDays > 0 ? maxRetentionDays : 3650,
                updated_at: settings?.updated_at || null,
            }
        },

        applyChatStorageSettingsToForm(settings) {
            const normalized = this.normalizeChatStorageSettings(settings)
            this.chatStorageForm = {
                save_text_messages: normalized.save_text_messages,
                save_media_attachments: normalized.save_media_attachments,
                save_file_attachments: normalized.save_file_attachments,
                retention_days: normalized.retention_days === null ? '' : String(normalized.retention_days),
                auto_archive_enabled: normalized.auto_archive_enabled,
            }
        },

        resetChatStorageForm() {
            if (!this.chatStorageSettings) {
                this.chatStorageForm = this.defaultChatStorageForm()
                return
            }

            this.applyChatStorageSettingsToForm(this.chatStorageSettings)
        },

        async loadChatStorageSettings() {
            this.isLoadingChatStorageSettings = true

            try {
                const response = await axios.get('/api/chats/settings')
                const normalized = this.normalizeChatStorageSettings(response?.data?.data || {})

                this.chatStorageSettings = normalized
                this.applyChatStorageSettingsToForm(normalized)
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось загрузить настройки хранения чатов.'))
            } finally {
                this.isLoadingChatStorageSettings = false
            }
        },

        async saveChatStorageSettings() {
            if (this.isSavingChatStorageSettings || this.isLoadingChatStorageSettings) {
                return
            }

            const retentionRaw = String(this.chatStorageForm.retention_days ?? '').trim()
            let retentionDays = null
            if (retentionRaw !== '') {
                const parsed = Number(retentionRaw)
                const maxRetentionDays = Number(this.chatStorageSettings?.max_retention_days ?? 3650)

                if (!Number.isInteger(parsed) || parsed < 1 || parsed > maxRetentionDays) {
                    alert(`Срок хранения должен быть целым числом от 1 до ${maxRetentionDays}.`)
                    return
                }

                retentionDays = parsed
            }

            const payload = {
                save_text_messages: Boolean(this.chatStorageForm.save_text_messages),
                save_media_attachments: Boolean(this.chatStorageForm.save_media_attachments),
                save_file_attachments: Boolean(this.chatStorageForm.save_file_attachments),
                retention_days: retentionDays,
                auto_archive_enabled: Boolean(this.chatStorageForm.auto_archive_enabled),
            }

            this.isSavingChatStorageSettings = true

            try {
                const response = await axios.patch('/api/chats/settings', payload)
                const normalized = this.normalizeChatStorageSettings(response?.data?.data || payload)

                this.chatStorageSettings = normalized
                this.applyChatStorageSettingsToForm(normalized)
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось сохранить настройки хранения чатов.'))
            } finally {
                this.isSavingChatStorageSettings = false
            }
        },

        normalizeChatArchive(archive) {
            const archiveId = Number(archive?.id)
            if (!Number.isInteger(archiveId) || archiveId <= 0) {
                return null
            }

            const messagesCount = Number(archive?.messages_count)
            const conversationsCount = Number(archive?.conversations_count)

            return {
                id: archiveId,
                scope: String(archive?.scope || 'all'),
                title: String(archive?.title || `Архив #${archiveId}`),
                messages_count: Number.isInteger(messagesCount) && messagesCount >= 0 ? messagesCount : 0,
                conversations_count: Number.isInteger(conversationsCount) && conversationsCount >= 0 ? conversationsCount : 0,
                created_at: archive?.created_at || null,
                restored_at: archive?.restored_at || null,
            }
        },

        upsertChatArchive(archive) {
            const normalized = this.normalizeChatArchive(archive)
            if (!normalized) {
                return
            }

            const existingIndex = this.chatArchives.findIndex((item) => Number(item.id) === Number(normalized.id))
            if (existingIndex !== -1) {
                this.chatArchives.splice(existingIndex, 1, {
                    ...this.chatArchives[existingIndex],
                    ...normalized,
                })
            } else {
                this.chatArchives = [normalized, ...this.chatArchives]
            }

            this.chatArchives.sort((first, second) => Number(second.id) - Number(first.id))
        },

        async loadChatArchives() {
            this.isLoadingChatArchives = true

            try {
                const response = await axios.get('/api/chats/archives')
                const archives = Array.isArray(response?.data?.data) ? response.data.data : []

                this.chatArchives = archives
                    .map((archive) => this.normalizeChatArchive(archive))
                    .filter((archive) => archive !== null)
                    .sort((first, second) => Number(second.id) - Number(first.id))
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось загрузить список архивов чатов.'))
            } finally {
                this.isLoadingChatArchives = false
            }
        },

        isArchiveDownloading(archiveId) {
            return this.downloadingArchiveIds.includes(Number(archiveId))
        },

        isArchiveRestoring(archiveId) {
            return this.restoringArchiveIds.includes(Number(archiveId))
        },

        async createChatArchive(scope = 'all') {
            if (!['all', 'conversation'].includes(scope)) {
                return
            }

            if (scope === 'conversation' && !this.activeConversation?.id) {
                return
            }

            if (this.archiveCreateScopeInProgress !== '') {
                return
            }

            const payload = { scope }
            if (scope === 'conversation') {
                payload.conversation_id = Number(this.activeConversation.id)
            }

            this.archiveCreateScopeInProgress = scope

            try {
                const response = await axios.post('/api/chats/archives', payload)
                this.upsertChatArchive(response?.data?.data)
                await this.loadChatArchives()
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось создать архив чата.'))
            } finally {
                this.archiveCreateScopeInProgress = ''
            }
        },

        extractDownloadFileName(contentDisposition, fallbackName) {
            const disposition = String(contentDisposition || '')

            const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i)
            if (utf8Match?.[1]) {
                try {
                    return decodeURIComponent(utf8Match[1]).replace(/[/\\?%*:|"<>]/g, '_')
                } catch (error) {
                    // Ignore malformed URI sequence.
                }
            }

            const asciiMatch = disposition.match(/filename=\"?([^\";]+)\"?/i)
            if (asciiMatch?.[1]) {
                return String(asciiMatch[1]).replace(/[/\\?%*:|"<>]/g, '_')
            }

            return fallbackName
        },

        async downloadChatArchive(archive) {
            const archiveId = Number(archive?.id)
            if (!Number.isInteger(archiveId) || archiveId <= 0 || this.isArchiveDownloading(archiveId)) {
                return
            }

            this.downloadingArchiveIds = [...this.downloadingArchiveIds, archiveId]

            try {
                const response = await axios.get(`/api/chats/archives/${archiveId}/download`, {
                    responseType: 'blob',
                })

                const fallbackFileName = `chat-archive-${archiveId}.json`
                const headerDisposition = response?.headers?.['content-disposition'] || ''
                const fileName = this.extractDownloadFileName(headerDisposition, fallbackFileName)
                const blob = response.data instanceof Blob
                    ? response.data
                    : new Blob([response.data], { type: 'application/json' })

                const blobUrl = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = blobUrl
                link.download = fileName
                link.rel = 'noopener'
                link.style.display = 'none'

                document.body.appendChild(link)
                link.click()
                document.body.removeChild(link)
                URL.revokeObjectURL(blobUrl)
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось скачать архив.'))
            } finally {
                this.downloadingArchiveIds = this.downloadingArchiveIds.filter((id) => id !== archiveId)
            }
        },

        async restoreChatArchive(archive) {
            const archiveId = Number(archive?.id)
            if (!Number.isInteger(archiveId) || archiveId <= 0 || this.isArchiveRestoring(archiveId)) {
                return
            }

            const shouldRestore = window.confirm('Восстановить архив в отдельный чат?')
            if (!shouldRestore) {
                return
            }

            this.restoringArchiveIds = [...this.restoringArchiveIds, archiveId]

            try {
                const response = await axios.post(`/api/chats/archives/${archiveId}/restore`)
                this.upsertChatArchive(response?.data?.data?.archive)
                await Promise.all([this.loadConversations(), this.loadChatArchives()])

                const restoredConversationId = Number(response?.data?.data?.conversation?.id)
                if (Number.isInteger(restoredConversationId) && restoredConversationId > 0) {
                    const target = this.conversations.find((conversation) => Number(conversation.id) === restoredConversationId)
                    if (target) {
                        await this.openConversation(target)
                    }
                }
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось восстановить архив.'))
            } finally {
                this.restoringArchiveIds = this.restoringArchiveIds.filter((id) => id !== archiveId)
            }
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

        setLeftPaneMode(mode) {
            if (mode !== 'conversations' && mode !== 'users') {
                return
            }

            this.leftPaneMode = mode
            this.saveChatUiSettings()
        },

        setConversationFilter(filter) {
            if (!['all', 'unread', 'blocked'].includes(filter)) {
                return
            }

            this.conversationFilter = filter
            this.saveChatUiSettings()
        },

        setMessageFilter(filter) {
            if (!['all', 'files_only'].includes(filter)) {
                return
            }

            this.messageFilter = filter
            this.saveChatUiSettings()
        },

        loadChatUiSettings() {
            if (typeof localStorage === 'undefined') {
                return
            }

            try {
                const raw = localStorage.getItem(CHAT_UI_STORAGE_KEY)
                if (!raw) {
                    return
                }

                const parsed = JSON.parse(raw)

                this.leftPaneMode = parsed.leftPaneMode === 'users' ? 'users' : 'conversations'
                this.conversationFilter = ['all', 'unread', 'blocked'].includes(parsed.conversationFilter)
                    ? parsed.conversationFilter
                    : 'all'
                this.messageFilter = ['all', 'files_only'].includes(parsed.messageFilter)
                    ? parsed.messageFilter
                    : 'all'
                this.pinnedConversationIds = Array.isArray(parsed.pinnedConversationIds)
                    ? [...new Set(parsed.pinnedConversationIds
                        .map((id) => Number(id))
                        .filter((id) => Number.isFinite(id) && id > 0))]
                    : []
            } catch (error) {
                // Ignore malformed storage values.
            }
        },

        saveChatUiSettings() {
            if (typeof localStorage === 'undefined') {
                return
            }

            try {
                localStorage.setItem(CHAT_UI_STORAGE_KEY, JSON.stringify({
                    leftPaneMode: this.leftPaneMode,
                    conversationFilter: this.conversationFilter,
                    messageFilter: this.messageFilter,
                    pinnedConversationIds: this.pinnedConversationIds,
                }))
            } catch (error) {
                // Ignore storage write issues.
            }
        },

        isConversationPinned(conversationId) {
            const normalizedId = Number(conversationId)
            return this.pinnedConversationIds.includes(normalizedId)
        },

        toggleConversationPin(conversationId) {
            const normalizedId = Number(conversationId)
            if (!Number.isFinite(normalizedId) || normalizedId <= 0) {
                return
            }

            if (this.isConversationPinned(normalizedId)) {
                this.pinnedConversationIds = this.pinnedConversationIds.filter((id) => id !== normalizedId)
            } else {
                this.pinnedConversationIds = [...this.pinnedConversationIds, normalizedId]
            }

            this.saveChatUiSettings()
        },

        conversationPeer(conversation) {
            if (!conversation || !Array.isArray(conversation.participants) || conversation.participants.length === 0) {
                return null
            }

            if (conversation.type === 'direct') {
                const viewerId = Number(this.currentUser?.id ?? 0)

                return conversation.participants.find((participant) => Number(participant.id) !== viewerId)
                    ?? conversation.participants[0]
                    ?? null
            }

            return conversation.participants[0] ?? null
        },

        conversationAvatar(conversation) {
            return this.avatarUrl(this.conversationPeer(conversation))
        },

        conversationInitial(conversation) {
            const participant = this.conversationPeer(conversation)
            const source = participant
                ? this.displayName(participant)
                : String(conversation?.title ?? '').trim()

            return source ? source.slice(0, 1).toUpperCase() : '#'
        },

        conversationTime(conversation) {
            if (conversation?.last_message?.date) {
                return conversation.last_message.date
            }

            if (!conversation?.updated_at) {
                return '—'
            }

            const updatedAt = new Date(conversation.updated_at)
            if (Number.isNaN(updatedAt.getTime())) {
                return '—'
            }

            return updatedAt.toLocaleTimeString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit',
            })
        },

        conversationMatchesFilter(conversation) {
            if (this.conversationFilter === 'unread') {
                return Number(conversation?.unread_count ?? 0) > 0
            }

            if (this.conversationFilter === 'blocked') {
                return Boolean(conversation?.is_blocked)
            }

            return true
        },

        conversationMatchesSearch(conversation) {
            const query = this.conversationSearch.trim().toLowerCase()
            if (query === '') {
                return true
            }

            const participants = Array.isArray(conversation?.participants)
                ? conversation.participants
                    .map((participant) => `${this.displayName(participant)} ${participant?.nickname ? `@${participant.nickname}` : ''}`)
                    .join(' ')
                : ''

            const haystack = `${conversation?.title ?? ''} ${this.messagePreview(conversation)} ${participants}`.toLowerCase()

            return haystack.includes(query)
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
            const normalizedLastMessage = conversation?.last_message
                ? this.normalizeMessage(conversation.last_message)
                : null
            const unreadCount = Number(conversation?.unread_count ?? 0)

            return {
                ...conversation,
                last_message: normalizedLastMessage,
                unread_count: Number.isFinite(unreadCount) ? Math.max(0, unreadCount) : 0,
                has_unread: Number.isFinite(unreadCount) ? unreadCount > 0 : Boolean(conversation?.has_unread),
            }
        },

        normalizeMessageReactions(reactions) {
            if (!Array.isArray(reactions)) {
                return []
            }

            const normalized = reactions
                .map((reaction) => {
                    const emoji = String(reaction?.emoji ?? '').trim()
                    const count = Number(reaction?.count ?? 0)

                    if (emoji === '' || !Number.isFinite(count) || count <= 0) {
                        return null
                    }

                    return {
                        emoji,
                        count,
                        reacted_by_me: Boolean(reaction?.reacted_by_me),
                    }
                })
                .filter((item) => item !== null)

            normalized.sort((first, second) => {
                const countDiff = Number(second.count) - Number(first.count)
                if (countDiff !== 0) {
                    return countDiff
                }

                return String(first.emoji).localeCompare(String(second.emoji))
            })

            return normalized
        },

        normalizeMessage(message) {
            if (!message || typeof message !== 'object') {
                return message
            }

            const attachments = Array.isArray(message.attachments) ? message.attachments : []

            return {
                ...message,
                attachments: attachments.map((attachment) => ({
                    ...attachment,
                    download_url: attachment?.download_url || attachment?.url || null,
                })),
                reactions: this.normalizeMessageReactions(message.reactions),
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
            const availableConversationIds = new Set(conversations.map((conversation) => Number(conversation.id)))
            const normalizedPinnedIds = this.pinnedConversationIds.filter((id) => availableConversationIds.has(Number(id)))
            if (normalizedPinnedIds.length !== this.pinnedConversationIds.length) {
                this.pinnedConversationIds = normalizedPinnedIds
                this.saveChatUiSettings()
            }

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
                this.setLeftPaneMode('conversations')
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
            return Boolean(user?.is_blocked_by_me) || Boolean(this.getMyBlockForUser(user))
        },

        isBlockedByUser(user) {
            return Boolean(user?.has_blocked_me)
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
            this.leftPaneMode = 'conversations'
            this.saveChatUiSettings()
            this.messageSearch = ''
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

                this.messages = (response.data.data ?? []).map((message) => this.normalizeMessage(message))
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
                this.setConversationReadLocally(conversationId)
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

        buildVoiceCaptureConstraints() {
            return {
                audio: {
                    channelCount: { ideal: 1 },
                    echoCancellation: { ideal: true },
                    noiseSuppression: { ideal: true },
                    autoGainControl: { ideal: true },
                    sampleRate: { ideal: 48000 },
                    sampleSize: { ideal: 16 },
                },
            }
        },

        async startVoiceRecording() {
            if (!this.canRecordVoice || this.isComposerDisabled || this.isRecordingVoice || this.isProcessingVoice || this.voiceStopInProgress) {
                return
            }

            try {
                let stream = null
                try {
                    stream = await navigator.mediaDevices.getUserMedia(this.buildVoiceCaptureConstraints())
                } catch (error) {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                }
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

                        recorder.start(250)
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

                    let appended = false
                    if (recorder) {
                        await this.waitForRecorderInactive(recorder, 2200)
                        await this.waitForRecordedChunks(this.voiceRecordedChunks, 2200)

                        if (this.voiceRecordedChunks.length > 0) {
                            const mimeType = this.voiceRecordedMimeType || recorder.mimeType || ''
                            appended = this.appendRecordedVoice(this.voiceRecordedChunks, mimeType, recordedDurationMs)
                        }
                    }

                    if (!appended && this.voiceRecordedChunks.length > 0) {
                        const mimeType = this.voiceRecordedMimeType || recorder?.mimeType || ''
                        appended = this.appendRecordedVoice(this.voiceRecordedChunks, mimeType, recordedDurationMs)
                    }

                    // PCM fallback only when browser recorder failed to produce data.
                    if (!appended) {
                        this.captureVoicePcmFrame()
                        appended = this.appendRecordedVoiceFromPcm(recordedDurationMs)
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
                        this.captureVoicePcmFrame()
                        appended = this.appendRecordedVoiceFromPcm(recordedDurationMs)
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
                analyser.smoothingTimeConstant = 0.85
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
            const targetSamples = totalSamples

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
                size: Number(file.size || 0),
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
                    size: Number(file.size || 0),
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
            if (mime.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif|bmp|svg)$/i.test(name)) {
                return 'image'
            }

            return 'file'
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
            const normalizedPayload = this.normalizeMessage(payload)
            const mine = this.isMine(payload)
            const isActiveConversation = Boolean(this.activeConversation && normalizedPayload.conversation_id === this.activeConversation.id)

            this.updateConversationFromIncoming(normalizedPayload, {
                incrementUnread: !mine && !isActiveConversation,
            })

            if (!mine) {
                this.playNotificationSound()
            }

            if (isActiveConversation) {
                this.upsertMessage(normalizedPayload)
                this.setConversationReadLocally(normalizedPayload.conversation_id)
                this.markConversationRead(normalizedPayload.conversation_id)
                this.$nextTick(() => this.scrollMessagesDown())
            }
        },

        upsertMessage(message) {
            const normalizedMessage = this.normalizeMessage(message)
            if (!normalizedMessage?.id) {
                return
            }

            const existingIndex = this.messages.findIndex((item) => Number(item.id) === Number(normalizedMessage.id))
            if (existingIndex !== -1) {
                this.messages.splice(existingIndex, 1, {
                    ...this.messages[existingIndex],
                    ...normalizedMessage,
                })
                return
            }

            this.messages.push(normalizedMessage)
            this.messages.sort((first, second) => new Date(first.created_at) - new Date(second.created_at))
        },

        updateConversationFromIncoming(message, options = {}) {
            const normalizedMessage = this.normalizeMessage(message)
            const target = this.conversations.find((conversation) => conversation.id === normalizedMessage.conversation_id)

            if (!target) {
                return
            }

            target.last_message = normalizedMessage
            target.updated_at = normalizedMessage.created_at

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
            const fileCount = attachments.filter((item) => item.type === 'file').length
            const mediaCount = count - audioCount - videoCount - fileCount

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
            if (fileCount > 0) {
                parts.push(fileCount === 1 ? 'Файл' : `Файлов: ${fileCount}`)
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

        messageReactionKey(messageId, emoji) {
            return `${Number(messageId)}:${String(emoji)}`
        },

        isMessageReactionToggling(messageId, emoji) {
            return this.togglingMessageReactionKeys.includes(this.messageReactionKey(messageId, emoji))
        },

        hasMessageReactionFromMe(message, emoji) {
            if (!Array.isArray(message?.reactions)) {
                return false
            }

            return message.reactions.some((reaction) => reaction.emoji === emoji && Boolean(reaction.reacted_by_me))
        },

        syncMessageAcrossConversationCards(message) {
            if (!message?.id) {
                return
            }

            const normalizedMessage = this.normalizeMessage(message)
            const messageId = Number(normalizedMessage.id)

            for (const conversation of this.conversations) {
                if (Number(conversation?.last_message?.id) !== messageId) {
                    continue
                }

                conversation.last_message = normalizedMessage
            }

            if (this.activeConversation && Number(this.activeConversation?.last_message?.id) === messageId) {
                this.activeConversation = {
                    ...this.activeConversation,
                    last_message: normalizedMessage,
                }
            }
        },

        async toggleMessageReaction(message, emoji) {
            if (!this.activeConversation || !message?.id || !this.currentUser) {
                return
            }

            const key = this.messageReactionKey(message.id, emoji)
            if (this.togglingMessageReactionKeys.includes(key)) {
                return
            }

            this.togglingMessageReactionKeys = [...this.togglingMessageReactionKeys, key]

            try {
                const response = await axios.post(
                    `/api/chats/${this.activeConversation.id}/messages/${message.id}/reactions`,
                    { emoji }
                )

                const updatedMessage = response?.data?.data?.message
                if (updatedMessage) {
                    this.upsertMessage(updatedMessage)
                    this.syncMessageAcrossConversationCards(updatedMessage)
                }
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось обновить реакцию.'))
            } finally {
                this.togglingMessageReactionKeys = this.togglingMessageReactionKeys.filter((item) => item !== key)
            }
        },

        isMine(message) {
            return this.currentUser && message.user && message.user.id === this.currentUser.id
        },

        canDeleteMessage(message) {
            if (!this.currentUser || !message?.user) {
                return false
            }

            const viewerId = Number(this.currentUser.id)
            const authorId = Number(message.user.id)

            return Number.isFinite(viewerId) && Number.isFinite(authorId) && viewerId === authorId
        },

        isMessageDeleting(messageId) {
            return this.deletingMessageIds.includes(Number(messageId))
        },

        attachmentDeleteKey(messageId, attachmentId) {
            return `${Number(messageId)}:${Number(attachmentId)}`
        },

        isAttachmentDeleting(messageId, attachmentId) {
            return this.deletingAttachmentKeys.includes(this.attachmentDeleteKey(messageId, attachmentId))
        },

        async deleteMessage(message) {
            if (!this.activeConversation || !message?.id || !this.canDeleteMessage(message)) {
                return
            }

            const messageId = Number(message.id)
            if (!Number.isFinite(messageId) || this.isMessageDeleting(messageId)) {
                return
            }

            const shouldDelete = window.confirm('Удалить это сообщение?')
            if (!shouldDelete) {
                return
            }

            this.deletingMessageIds = [...this.deletingMessageIds, messageId]

            try {
                await axios.delete(`/api/chats/${this.activeConversation.id}/messages/${messageId}`)
                this.removeMessageLocally(messageId)
                await this.loadConversations()
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось удалить сообщение.'))
            } finally {
                this.deletingMessageIds = this.deletingMessageIds.filter((id) => id !== messageId)
            }
        },

        async deleteAttachment(message, attachment) {
            if (!this.activeConversation || !message?.id || !attachment?.id || !this.canDeleteMessage(message)) {
                return
            }

            const messageId = Number(message.id)
            const attachmentId = Number(attachment.id)
            if (!Number.isFinite(messageId) || !Number.isFinite(attachmentId)) {
                return
            }

            const key = this.attachmentDeleteKey(messageId, attachmentId)
            if (this.deletingAttachmentKeys.includes(key)) {
                return
            }

            const shouldDelete = window.confirm('Удалить это вложение?')
            if (!shouldDelete) {
                return
            }

            this.deletingAttachmentKeys = [...this.deletingAttachmentKeys, key]

            try {
                const response = await axios.delete(
                    `/api/chats/${this.activeConversation.id}/messages/${messageId}/attachments/${attachmentId}`
                )

                if (Boolean(response?.data?.data?.message_deleted)) {
                    this.removeMessageLocally(messageId)
                } else {
                    this.removeAttachmentLocally(messageId, attachmentId)
                }

                await this.loadConversations()
            } catch (error) {
                alert(this.resolveApiMessage(error, 'Не удалось удалить вложение.'))
            } finally {
                this.deletingAttachmentKeys = this.deletingAttachmentKeys.filter((item) => item !== key)
            }
        },

        removeMessageLocally(messageId) {
            const normalizedId = Number(messageId)
            this.messages = this.messages.filter((item) => Number(item.id) !== normalizedId)
        },

        removeAttachmentLocally(messageId, attachmentId) {
            const messageItem = this.messages.find((item) => Number(item.id) === Number(messageId))
            if (!messageItem || !Array.isArray(messageItem.attachments)) {
                return
            }

            messageItem.attachments = messageItem.attachments.filter((item) => Number(item.id) !== Number(attachmentId))
        },

        scrollMessagesDown() {
            if (!this.$refs.messagesContainer) {
                return
            }

            this.$refs.messagesContainer.scrollTop = this.$refs.messagesContainer.scrollHeight
        },

        normalizeNotificationSoundId(soundId) {
            const normalizedRaw = typeof soundId === 'string' ? soundId.trim() : ''
            const mappedId = LEGACY_NOTIFICATION_SOUND_MAP[normalizedRaw] || normalizedRaw

            if (mappedId === 'custom') {
                return 'custom'
            }

            return NOTIFICATION_SOUND_PRESET_IDS.has(mappedId)
                ? mappedId
                : DEFAULT_NOTIFICATION_SOUND_ID
        },

        resolveNotificationSoundUrl(soundId) {
            const normalizedSoundId = this.normalizeNotificationSoundId(soundId)
            if (normalizedSoundId === 'custom') {
                return null
            }

            const preset = NOTIFICATION_SOUND_PRESETS.find((item) => item.id === normalizedSoundId)
            return preset ? preset.url : null
        },

        revokeRuntimeCustomNotificationSoundUrl() {
            const source = this.notificationSettings?.customSoundDataUrl
            if (typeof source === 'string' && source.startsWith('blob:')) {
                URL.revokeObjectURL(source)
                this.notificationSettings.customSoundDataUrl = null
            }
        },

        loadNotificationSettings() {
            try {
                const raw = localStorage.getItem(CHAT_SOUND_STORAGE_KEY)
                if (!raw) {
                    return
                }

                const parsed = JSON.parse(raw)
                const parsedVolume = Number(parsed?.volume)
                const parsedCustomSound = typeof parsed?.customSoundDataUrl === 'string' && parsed.customSoundDataUrl.startsWith('data:')
                    ? parsed.customSoundDataUrl
                    : null

                let normalizedSound = this.normalizeNotificationSoundId(parsed?.sound)
                if (normalizedSound === 'custom' && !parsedCustomSound) {
                    normalizedSound = DEFAULT_NOTIFICATION_SOUND_ID
                }

                this.notificationSettings = {
                    enabled: typeof parsed?.enabled === 'boolean' ? parsed.enabled : true,
                    sound: normalizedSound,
                    volume: Number.isFinite(parsedVolume) ? Math.max(0, Math.min(100, parsedVolume)) : 60,
                    customSoundDataUrl: parsedCustomSound,
                }
            } catch (error) {
                // Ignore malformed storage values.
            }
        },

        saveNotificationSettings() {
            const normalizedSound = this.normalizeNotificationSoundId(this.notificationSettings.sound)
            const persistentCustomSound = typeof this.notificationSettings.customSoundDataUrl === 'string'
                && this.notificationSettings.customSoundDataUrl.startsWith('data:')
                ? this.notificationSettings.customSoundDataUrl
                : null

            const payload = {
                enabled: Boolean(this.notificationSettings.enabled),
                sound: normalizedSound === 'custom' && !persistentCustomSound
                    ? DEFAULT_NOTIFICATION_SOUND_ID
                    : normalizedSound,
                volume: Number.isFinite(this.notificationSettings.volume)
                    ? Math.max(0, Math.min(100, Number(this.notificationSettings.volume)))
                    : 60,
                customSoundDataUrl: persistentCustomSound,
            }

            try {
                localStorage.setItem(CHAT_SOUND_STORAGE_KEY, JSON.stringify(payload))
            } catch (error) {
                // Ignore storage quota errors.
            }
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

            if (file.size > MAX_CUSTOM_NOTIFICATION_SOUND_BYTES) {
                alert('Файл звука слишком большой. Максимум 15MB.')
                return
            }

            this.revokeRuntimeCustomNotificationSoundUrl()

            if (file.size <= MAX_PERSISTED_CUSTOM_NOTIFICATION_SOUND_BYTES) {
                const reader = new FileReader()
                reader.onload = () => {
                    this.notificationSettings.customSoundDataUrl = typeof reader.result === 'string' ? reader.result : null
                    this.notificationSettings.sound = 'custom'
                    this.saveNotificationSettings()
                }
                reader.readAsDataURL(file)
            } else {
                this.notificationSettings.customSoundDataUrl = URL.createObjectURL(file)
                this.notificationSettings.sound = 'custom'
                this.saveNotificationSettings()
                alert('Файл загружен. Звук будет работать до перезагрузки страницы (файл больше 2MB).')
            }

            event.target.value = null
        },

        previewNotificationSound() {
            this.playNotificationSound()
        },

        playHtmlNotificationSound(sourceUrl) {
            const audio = new Audio(sourceUrl)
            audio.volume = this.notificationSettings.volume / 100
            audio.play().catch(() => {})
        },

        playNotificationSound() {
            if (!this.notificationSettings.enabled) {
                return
            }

            if (this.notificationSettings.sound === 'custom' && this.notificationSettings.customSoundDataUrl) {
                this.playHtmlNotificationSound(this.notificationSettings.customSoundDataUrl)
                return
            }

            const presetUrl = this.resolveNotificationSoundUrl(this.notificationSettings.sound)
            if (presetUrl) {
                this.playHtmlNotificationSound(presetUrl)
                return
            }

            this.playLegacySynthNotificationSound(this.notificationSettings.sound)
        },

        playLegacySynthNotificationSound(legacySoundId = 'ping') {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext
            if (!AudioContextClass) {
                return
            }

            const context = new AudioContextClass()
            const gainNode = context.createGain()
            gainNode.connect(context.destination)

            const preset = String(legacySoundId || 'ping')
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
