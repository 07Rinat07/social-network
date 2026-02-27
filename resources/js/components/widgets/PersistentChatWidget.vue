<template>
    <aside
        ref="widgetRoot"
        class="side-widget side-widget--chat"
        :class="{'is-collapsed': !expanded, 'is-dragging': isDragging}"
        :style="floatingStyle"
    >
        <button
            v-if="!expanded"
            type="button"
            class="side-widget-mini-btn"
            :aria-label="collapsedButtonHint"
            :title="collapsedButtonHint"
            @click="expand"
        >
            <span class="side-widget-mini-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                    <path
                        fill="currentColor"
                        d="M4 5.5A3.5 3.5 0 0 1 7.5 2h9A3.5 3.5 0 0 1 20 5.5v7a3.5 3.5 0 0 1-3.5 3.5H11l-4.9 3.27A1 1 0 0 1 4.5 18.5V16A3.5 3.5 0 0 1 4 12.5v-7zm4 3a1 1 0 0 0 0 2h8a1 1 0 1 0 0-2H8zm0 4a1 1 0 0 0 0 2h5a1 1 0 1 0 0-2H8z"
                    />
                </svg>
            </span>
            <span v-if="unreadTotal > 0" class="side-widget-mini-btn__badge">{{ unreadBadge }}</span>
            <span class="side-widget-mini-btn__hint">{{ collapsedButtonHint }}</span>
        </button>

        <section v-else class="side-widget-panel glass-panel">
            <header
                class="side-widget-panel__header side-widget-panel__header--draggable"
                :class="{'is-dragging': isDragging}"
                @pointerdown="startDrag"
            >
                <div class="side-widget-panel__title-wrap">
                    <strong class="side-widget-panel__title">{{ $t('nav.chats') }}</strong>
                    <span v-if="unreadTotal > 0" class="badge side-widget-panel__status">{{ unreadBadge }}</span>
                </div>

                <div class="side-widget-panel__actions">
                    <button
                        v-if="isMovableMode"
                        type="button"
                        class="side-widget-panel__pin-btn"
                        :class="{'is-active': isPinned}"
                        :aria-label="pinButtonHint"
                        :title="pinButtonHint"
                        @click.stop="togglePin"
                    >
                        <span aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                <path
                                    fill="currentColor"
                                    d="M14.41 3.59a2 2 0 0 1 2.83 0l3.17 3.17a2 2 0 0 1 0 2.83l-1.18 1.18a1 1 0 0 1-.71.29h-2.1l-3.45 3.46 1.67 5.58a1 1 0 0 1-1.69.96l-2.98-2.98-3.97 3.96a1 1 0 1 1-1.41-1.41l3.97-3.97-2.98-2.97a1 1 0 0 1 .96-1.69l5.58 1.67 3.46-3.46v-2.1a1 1 0 0 1 .29-.71l1.18-1.18z"
                                />
                            </svg>
                        </span>
                    </button>

                    <button
                        type="button"
                        class="side-widget-panel__collapse-btn"
                        :aria-label="$t('common.close')"
                        :title="collapseButtonHint"
                        @click.stop="collapse"
                    >
                        <span aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                <path
                                    fill="currentColor"
                                    d="M9.29 5.3a1 1 0 0 1 1.42 0l6 6a1 1 0 0 1 0 1.4l-6 6a1 1 0 1 1-1.42-1.4l5.3-5.3-5.3-5.3a1 1 0 0 1 0-1.4z"
                                />
                            </svg>
                        </span>
                    </button>
                </div>
            </header>

            <div class="side-widget-panel__body widget-chat-body">
                <div v-if="user" class="widget-chat-self-profile">
                    <img
                        v-if="avatarUrl(user)"
                        :src="avatarUrl(user)"
                        alt="avatar"
                        class="avatar avatar-sm widget-chat-self-profile__avatar"
                        @error="onAvatarImageError"
                        @click.stop="openUserAvatar(user)"
                    >
                    <span v-else class="avatar avatar-sm avatar-placeholder widget-chat-self-profile__avatar">
                        {{ userInitial(user) }}
                    </span>
                    <div class="widget-chat-self-profile__meta">
                        <strong>{{ displayName(user) }}</strong>
                        <p v-if="user.nickname" class="muted">@{{ user.nickname }}</p>
                    </div>
                    <p
                        v-if="myMoodStatus || showMoodStatusSettings"
                        class="widget-chat-self-profile__status"
                        :title="selfProfileMoodStatusTitle"
                    >
                        {{ selfProfileMoodStatusText }}
                    </p>
                    <button
                        class="btn btn-outline btn-sm widget-chat-self-profile__settings-toggle"
                        type="button"
                        :title="moodStatusSettingsToggleTitle"
                        :aria-label="moodStatusSettingsToggleTitle"
                        :aria-expanded="showMoodStatusSettings ? 'true' : 'false'"
                        aria-controls="widget-chat-mood-settings-panel"
                        :disabled="!activeConversation || isSavingMoodStatus"
                        @click="showMoodStatusSettings = !showMoodStatusSettings"
                    >
                        <span class="widget-chat-window__mood-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                <path
                                    fill="currentColor"
                                    d="M12 8.6A3.4 3.4 0 1 0 12 15.4 3.4 3.4 0 0 0 12 8.6zm9 3.4-.02.56a1 1 0 0 1-.84.93l-1.64.25a6.86 6.86 0 0 1-.58 1.4l.99 1.35a1 1 0 0 1-.08 1.26l-.4.4a1 1 0 0 1-1.26.08l-1.35-.99c-.45.24-.92.44-1.4.58l-.25 1.64a1 1 0 0 1-.93.84L12 21l-.56-.02a1 1 0 0 1-.93-.84l-.25-1.64a6.86 6.86 0 0 1-1.4-.58l-1.35.99a1 1 0 0 1-1.26-.08l-.4-.4a1 1 0 0 1-.08-1.26l.99-1.35a6.86 6.86 0 0 1-.58-1.4l-1.64-.25a1 1 0 0 1-.84-.93L3 12l.02-.56a1 1 0 0 1 .84-.93l1.64-.25c.14-.48.34-.95.58-1.4l-.99-1.35a1 1 0 0 1 .08-1.26l.4-.4a1 1 0 0 1 1.26-.08l1.35.99c.45-.24.92-.44 1.4-.58l.25-1.64a1 1 0 0 1 .93-.84L12 3l.56.02a1 1 0 0 1 .93.84l.25 1.64c.48.14.95.34 1.4.58l1.35-.99a1 1 0 0 1 1.26.08l.4.4a1 1 0 0 1 .08 1.26l-.99 1.35c.24.45.44.92.58 1.4l1.64.25a1 1 0 0 1 .84.93L21 12z"
                                />
                            </svg>
                        </span>
                        <span>{{ $t('chats.moodStatusSettings') }}</span>
                        <span class="widget-chat-window__mood-toggle-chevron" aria-hidden="true">{{ showMoodStatusSettings ? '▾' : '▸' }}</span>
                    </button>
                </div>

                <div
                    v-if="activeConversation && showMoodStatusSettings"
                    id="widget-chat-mood-settings-panel"
                    class="widget-chat-mood-settings widget-chat-self-profile__mood-settings"
                >
                    <label class="muted widget-chat-note">{{ $t('chats.moodStatusYourComment') }}</label>
                    <textarea
                        v-model="moodStatusForm.text"
                        class="textarea-field widget-chat-mood-settings__input"
                        :placeholder="$t('chats.moodStatusPlaceholder')"
                        maxlength="500"
                        :disabled="isSavingMoodStatus"
                    ></textarea>

                    <template v-if="showMoodStatusSettings">
                        <label class="widget-chat-mood-settings__check">
                            <input type="checkbox" v-model="moodStatusForm.is_visible_to_all">
                            <span>{{ $t('chats.moodStatusVisibleToAll') }}</span>
                        </label>

                        <div v-if="!moodStatusForm.is_visible_to_all" class="widget-chat-mood-settings__hidden-list">
                            <label class="muted widget-chat-note">{{ $t('chats.moodStatusHiddenUsers') }}</label>
                            <label
                                v-for="participant in moodStatusVisibilityCandidates"
                                :key="`widget-chat-mood-hidden-${participant.id}`"
                                class="widget-chat-mood-settings__check"
                            >
                                <input
                                    :value="participant.id"
                                    type="checkbox"
                                    v-model="moodStatusForm.hidden_user_ids"
                                >
                                <span>{{ displayName(participant) }}</span>
                            </label>
                        </div>
                    </template>

                    <div class="widget-chat-mood-settings__actions">
                        <button
                            class="btn btn-primary btn-sm"
                            type="button"
                            :disabled="isSavingMoodStatus"
                            @click="saveMoodStatus"
                        >
                            {{ isSavingMoodStatus ? $t('admin.saving') : $t('chats.moodStatusSave') }}
                        </button>
                        <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            :disabled="isSavingMoodStatus"
                            @click="resetMoodStatusForm"
                        >
                            {{ $t('chats.reset') }}
                        </button>
                    </div>
                </div>

                <div class="widget-chat-tabs">
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="leftPaneMode === 'conversations' ? 'btn-primary' : 'btn-outline'"
                        @click="setLeftPaneMode('conversations')"
                    >
                        {{ $t('chats.dialogs') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="leftPaneMode === 'users' ? 'btn-primary' : 'btn-outline'"
                        @click="setLeftPaneMode('users')"
                    >
                        {{ $t('chats.people') }}
                    </button>
                </div>

                <div v-if="leftPaneMode === 'conversations'" class="widget-chat-conversations-pane">
                    <div class="widget-chat-conversations-toolbar">
                        <button
                            type="button"
                            class="btn btn-outline btn-sm widget-chat-global-btn"
                            :disabled="isLoadingConversations || !globalConversation"
                            @click="openGlobalConversation"
                        >
                            {{ $t('chats.globalChat') }}
                        </button>
                    </div>

                    <input
                        v-model.trim="conversationSearch"
                        class="input-field widget-chat-search"
                        type="text"
                        :placeholder="$t('chats.searchPlaceholder')"
                    >

                    <p v-if="loadError" class="error-text widget-chat-note">{{ loadError }}</p>

                    <div class="widget-chat-conversations">
                        <button
                            v-for="conversation in filteredConversations"
                            :key="`widget-chat-conversation-${conversation.id}`"
                            type="button"
                            class="widget-chat-conversation"
                            :class="{'is-active': activeConversation && activeConversation.id === conversation.id}"
                            @click="openConversation(conversation)"
                        >
                            <div class="widget-chat-conversation__row">
                                <strong>{{ conversation.title }}</strong>
                                <span class="widget-chat-conversation__time">{{ conversationTime(conversation) }}</span>
                            </div>
                            <p
                                v-if="conversation.type === 'direct' && conversationPeerMoodStatusLabel(conversation)"
                                class="muted widget-chat-conversation__status"
                            >
                                {{ $t('chats.dialogPeerMoodStatus', { status: conversationPeerMoodStatusLabel(conversation) }) }}
                            </p>
                            <p class="widget-chat-conversation__preview">{{ messagePreview(conversation) }}</p>
                            <span v-if="Number(conversation.unread_count || 0) > 0" class="badge widget-chat-conversation__unread">
                                {{ formatUnreadBadge(conversation.unread_count) }}
                            </span>
                        </button>

                        <p v-if="!isLoadingConversations && filteredConversations.length === 0" class="muted widget-chat-note">
                            {{ $t('chats.dialogsNotFound') }}
                        </p>
                    </div>
                </div>

                <div v-else class="widget-chat-users-pane">
                    <input
                        v-model.trim="userSearch"
                        class="input-field widget-chat-search"
                        type="text"
                        :placeholder="$t('chats.userSearch')"
                        @input="onUserSearchInput"
                    >

                    <p v-if="usersError" class="error-text widget-chat-note">{{ usersError }}</p>
                    <p v-if="isLoadingUsers" class="muted widget-chat-note">{{ $t('common.loading') }}</p>

                    <div class="widget-chat-users-list">
                        <article
                            v-for="chatUser in users"
                            :key="`widget-chat-user-${chatUser.id}`"
                            class="widget-chat-user"
                        >
                            <div class="widget-chat-user__row">
                                <strong>{{ displayName(chatUser) }}</strong>
                                <span v-if="chatUser.nickname" class="widget-chat-user__nickname">
                                    @{{ chatUser.nickname }}
                                </span>
                            </div>
                            <p class="muted widget-chat-user__status">{{ userStatusText(chatUser) }}</p>
                            <p
                                v-if="userMoodStatusLabel(chatUser)"
                                class="muted widget-chat-user__status widget-chat-user__status--mood"
                            >
                                {{ $t('chats.dialogPeerMoodStatus', { status: userMoodStatusLabel(chatUser) }) }}
                            </p>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                :disabled="isUserDirectChatBlocked(chatUser)"
                                @click="startDirectChat(chatUser)"
                            >
                                {{ $t('chats.directChat') }}
                            </button>
                        </article>

                        <p v-if="!isLoadingUsers && users.length === 0" class="muted widget-chat-note">
                            {{ $t('chats.usersNotFound') }}
                        </p>
                    </div>
                </div>

                <section class="widget-chat-window">
                    <header class="widget-chat-window__head">
                        <strong>{{ activeConversationTitle }}</strong>
                        <p v-if="activeTypingStatusLine" class="widget-chat-window__typing">
                            <span class="widget-chat-typing-indicator__icon" aria-hidden="true">✍️</span>
                            <span>{{ activeTypingStatusLine }}</span>
                        </p>
                    </header>

                    <button
                        v-if="incomingNotice"
                        type="button"
                        class="widget-chat-incoming"
                        :title="$t('chats.widgetOpenIncoming')"
                        @click="openIncomingNoticeConversation"
                    >
                        <span class="widget-chat-incoming__line">
                            {{ $t('chats.widgetIncomingMessage', { name: incomingNotice.sender, text: incomingNotice.text }) }}
                        </span>
                        <span class="widget-chat-incoming__meta">
                            {{ $t('chats.widgetIncomingFrom', { title: incomingNotice.conversationTitle }) }}
                        </span>
                    </button>

                    <div class="widget-chat-window__messages" ref="messagesContainer">
                        <p v-if="isLoadingMessages" class="muted widget-chat-note">{{ $t('common.loading') }}</p>
                        <p v-else-if="!activeConversation" class="muted widget-chat-note">{{ $t('chats.selectChat') }}</p>
                        <p v-else-if="messages.length === 0" class="muted widget-chat-note">{{ $t('chats.noMessagesYet') }}</p>

                        <div
                            v-for="message in messages"
                            :key="`widget-chat-message-${message.id}`"
                            class="widget-chat-message-row"
                            :class="{'is-mine': isMine(message)}"
                        >
                            <img
                                v-if="messageAvatarUrl(message)"
                                :src="messageAvatarUrl(message)"
                                alt="avatar"
                                class="avatar avatar-sm widget-chat-message__avatar"
                                @error="onAvatarImageError"
                                @click.stop="openUserAvatar(message?.user || user)"
                            >
                            <span v-else class="avatar avatar-sm avatar-placeholder widget-chat-message__avatar">
                                {{ messageAuthorInitial(message) }}
                            </span>

                            <article
                                class="widget-chat-message"
                                :class="{'is-mine': isMine(message)}"
                            >
                                <div class="widget-chat-message__head">
                                    <label
                                        v-if="canDeleteMessage(message)"
                                        class="widget-chat-message__select"
                                        :title="$t('chats.bulkSelectAll')"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="isMessageSelected(message.id)"
                                            :disabled="isBulkDeletingMessages"
                                            @change="toggleMessageSelection(message)"
                                        >
                                    </label>
                                    <p class="widget-chat-message__meta">
                                        <span class="widget-chat-message__author">{{ messageAuthorLabel(message) }}</span>
                                        <span class="widget-chat-message__time">{{ message?.date || '' }}</span>
                                    </p>
                                    <button
                                        v-if="canDeleteMessage(message)"
                                        type="button"
                                        class="btn btn-danger btn-sm widget-chat-message__remove"
                                        :disabled="isMessageDeleting(message.id) || isBulkDeletingMessages"
                                        @click.stop="deleteMessage(message)"
                                    >
                                        {{ isMessageDeleting(message.id) ? $t('chats.deleting') : $t('common.delete') }}
                                    </button>
                                </div>
                                <StickerRichText
                                    v-if="hasMessageBody(message)"
                                    as="div"
                                    class="widget-chat-message__body"
                                    :text="messageText(message)"
                                ></StickerRichText>

                                <div v-if="Array.isArray(message.attachments) && message.attachments.length > 0" class="widget-chat-message__attachments">
                                    <div
                                        v-for="(attachment, index) in message.attachments"
                                        :key="attachmentKey(message, attachment, index)"
                                        class="widget-chat-attachment"
                                    >
                                        <video
                                            v-if="isVideoAttachment(attachment)"
                                            :src="attachment.url"
                                            class="media-video widget-chat-media-video"
                                            controls
                                            preload="metadata"
                                            playsinline
                                            @click.stop
                                            @pointerdown.stop
                                        ></video>
                                        <MediaPlayer
                                            v-else-if="isAudioAttachment(attachment)"
                                            type="audio"
                                            :src="attachment.url"
                                            :mime-type="attachment.mime_type"
                                            player-class="media-audio widget-chat-media-audio"
                                        ></MediaPlayer>
                                        <div v-else class="widget-chat-file-card">
                                            <strong class="widget-chat-file-card__name">{{ attachment.original_name || $t('chats.file') }}</strong>
                                            <span class="muted widget-chat-file-card__meta">
                                                {{ attachment.mime_type || 'application/octet-stream' }}
                                                <template v-if="Number(attachment.size || 0) > 0">
                                                    · {{ formatBytes(attachment.size) }}
                                                </template>
                                            </span>
                                        </div>

                                        <button
                                            type="button"
                                            class="btn btn-outline btn-sm widget-chat-attachment__download"
                                            @click.stop="downloadAttachment(attachment)"
                                        >
                                            {{ $t('chats.download') }}
                                        </button>
                                        <button
                                            v-if="canDeleteMessage(message)"
                                            type="button"
                                            class="btn btn-danger btn-sm widget-chat-attachment__remove"
                                            :disabled="isAttachmentDeleting(message.id, attachment.id)"
                                            @click.stop="deleteAttachment(message, attachment)"
                                        >
                                            {{ isAttachmentDeleting(message.id, attachment.id) ? $t('chats.deleting') : $t('chats.deleteFile') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="widget-chat-message__reaction-row">
                                    <div v-if="Array.isArray(message.reactions) && message.reactions.length > 0" class="widget-chat-message__reactions">
                                        <button
                                            v-for="reaction in message.reactions"
                                            :key="`widget-chat-msg-reaction-${message.id}-${reaction.emoji}`"
                                            class="widget-chat-reaction-chip"
                                            :class="{ 'is-active': reaction.reacted_by_me }"
                                            type="button"
                                            :disabled="isMessageReactionToggling(message.id, reaction.emoji)"
                                            @click.stop="toggleMessageReaction(message, reaction.emoji)"
                                        >
                                            <span>{{ reaction.emoji }}</span>
                                            <span>{{ reaction.count }}</span>
                                        </button>
                                    </div>

                                    <button
                                        class="widget-chat-reaction-picker-toggle"
                                        :class="{ 'is-open': isMessageReactionPickerOpen(message.id) }"
                                        type="button"
                                        :title="isMessageReactionPickerOpen(message.id) ? $t('chats.reactionPickerCloseHint') : $t('chats.reactionPickerOpenHint')"
                                        :aria-label="isMessageReactionPickerOpen(message.id) ? $t('chats.reactionPickerCloseHint') : $t('chats.reactionPickerOpenHint')"
                                        :aria-expanded="isMessageReactionPickerOpen(message.id) ? 'true' : 'false'"
                                        :aria-controls="`widget-chat-reaction-picker-${message.id}`"
                                        @click.stop="toggleMessageReactionPicker(message.id)"
                                    >
                                        <span class="widget-chat-reaction-picker-toggle__icon" aria-hidden="true">😊</span>
                                        <span class="widget-chat-reaction-picker-toggle__label">{{ $t('chats.reactionPickerLabel') }}</span>
                                        <span class="widget-chat-reaction-picker-toggle__dots" aria-hidden="true">⋯</span>
                                    </button>
                                </div>

                                <div
                                    v-if="isMessageReactionPickerOpen(message.id)"
                                    :id="`widget-chat-reaction-picker-${message.id}`"
                                    class="widget-chat-message__reaction-picker"
                                >
                                    <button
                                        v-for="emoji in messageReactionEmojis"
                                        :key="`widget-chat-msg-reaction-picker-${message.id}-${emoji}`"
                                        class="widget-chat-reaction-picker-btn"
                                        :class="{ 'is-active': hasMessageReactionFromMe(message, emoji) }"
                                        type="button"
                                        :title="$t('chats.reactionWithEmoji', { emoji })"
                                        :disabled="isMessageReactionToggling(message.id, emoji)"
                                        @click.stop="toggleMessageReaction(message, emoji)"
                                    >
                                        {{ emoji }}
                                    </button>
                                </div>
                            </article>
                        </div>
                    </div>

                    <form class="widget-chat-composer" @submit.prevent="sendMessage">
                        <div class="emoji-row widget-chat-emoji-row">
                            <button
                                v-for="emoji in emojis"
                                :key="`widget-chat-emoji-${emoji}`"
                                type="button"
                                class="emoji-btn widget-chat-emoji-btn"
                                :disabled="composerDisabled"
                                @click="appendEmoji(emoji)"
                            >
                                {{ emoji }}
                            </button>
                        </div>

                        <textarea
                            v-model="messageBody"
                            class="textarea-field widget-chat-composer__input"
                            :placeholder="$t('chats.enterMessage')"
                            :disabled="composerDisabled"
                            @input="handleComposerInput"
                            @focus="notifyTypingActivity"
                            @blur="notifyTypingStopped"
                            @keydown.enter.exact.prevent="sendMessage"
                            @keydown.enter.shift.exact="onShiftEnter"
                            @keydown.ctrl.enter.prevent="sendMessage"
                            @keydown.meta.enter.prevent="sendMessage"
                        ></textarea>

                        <input
                            ref="messageFiles"
                            type="file"
                            accept="image/*,video/*,audio/*,.gif,.mp3,.wav,.ogg,.m4a,.aac,.opus,.weba,.webm,.mp4,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.rtf,.zip,.rar,.7z,.tar,.gz,.json,.xml"
                            multiple
                            class="hidden"
                            @change="onMessageFilesSelected"
                        >

                        <div class="widget-chat-send-row">
                            <button
                                class="btn btn-primary btn-sm widget-chat-send-row__btn"
                                type="submit"
                                :disabled="!canSend"
                            >
                                {{ isSending ? $t('common.sending') : $t('chats.send') }}
                            </button>
                        </div>

                        <div v-if="selectedFilePreviews.length > 0" class="widget-chat-composer-files">
                            <div
                                v-for="filePreview in selectedFilePreviews"
                                :key="`widget-chat-preview-${filePreview.key}`"
                                class="widget-chat-composer-file"
                            >
                                <video
                                    v-if="filePreview.kind === 'video'"
                                    :src="filePreview.url"
                                    class="media-video widget-chat-media-video widget-chat-preview-video"
                                    controls
                                    preload="metadata"
                                    playsinline
                                ></video>
                                <MediaPlayer
                                    v-else-if="filePreview.kind === 'audio'"
                                    type="audio"
                                    :src="filePreview.url"
                                    :mime-type="filePreview.mimeType"
                                    player-class="media-audio widget-chat-media-audio"
                                ></MediaPlayer>
                                <div v-else class="widget-chat-file-card">
                                    <strong class="widget-chat-file-card__name">{{ filePreview.name || $t('chats.file') }}</strong>
                                    <span class="muted widget-chat-file-card__meta">
                                        {{ filePreview.mimeType || 'application/octet-stream' }}
                                        <template v-if="Number(filePreview.size || 0) > 0">
                                            · {{ formatBytes(filePreview.size) }}
                                        </template>
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-danger btn-sm widget-chat-composer-file__remove"
                                    :disabled="isSending"
                                    @click="removeSelectedFile(filePreview.key)"
                                >
                                    {{ $t('common.delete') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="showStickerTray" class="chat-sticker-tray widget-chat-sticker-tray">
                            <StickerPicker
                                :disabled="composerDisabled"
                                :category-label="$t('radio.genreFilterLabel')"
                                @select="insertSticker"
                            ></StickerPicker>
                            <p class="muted chat-sticker-note">{{ $t('chats.stickerHint') }}</p>
                        </div>

                        <p v-if="!canRecordVoice" class="muted widget-chat-note">{{ $t('chats.voiceUnavailable') }}</p>
                        <p v-if="!canRecordVideo" class="muted widget-chat-note">{{ $t('chats.cameraUnavailable') }}</p>
                        <div v-if="canRecordVoice || canRecordVideo" class="widget-chat-device-panel">
                            <button
                                class="btn btn-outline btn-sm widget-chat-device-toggle-btn"
                                type="button"
                                :title="deviceSettingsToggleTitle"
                                :aria-expanded="showDeviceSettings ? 'true' : 'false'"
                                aria-controls="widget-chat-device-settings-panel"
                                @click="showDeviceSettings = !showDeviceSettings"
                            >
                                <span class="widget-chat-device-toggle-btn__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                        <path
                                            fill="currentColor"
                                            d="M12 8.6A3.4 3.4 0 1 0 12 15.4 3.4 3.4 0 0 0 12 8.6zm9 3.4-.02.56a1 1 0 0 1-.84.93l-1.64.25a6.86 6.86 0 0 1-.58 1.4l.99 1.35a1 1 0 0 1-.08 1.26l-.4.4a1 1 0 0 1-1.26.08l-1.35-.99c-.45.24-.92.44-1.4.58l-.25 1.64a1 1 0 0 1-.93.84L12 21l-.56-.02a1 1 0 0 1-.93-.84l-.25-1.64a6.86 6.86 0 0 1-1.4-.58l-1.35.99a1 1 0 0 1-1.26-.08l-.4-.4a1 1 0 0 1-.08-1.26l.99-1.35a6.86 6.86 0 0 1-.58-1.4l-1.64-.25a1 1 0 0 1-.84-.93L3 12l.02-.56a1 1 0 0 1 .84-.93l1.64-.25c.14-.48.34-.95.58-1.4l-.99-1.35a1 1 0 0 1 .08-1.26l.4-.4a1 1 0 0 1 1.26-.08l1.35.99c.45-.24.92-.44 1.4-.58l.25-1.64a1 1 0 0 1 .93-.84L12 3l.56.02a1 1 0 0 1 .93.84l.25 1.64c.48.14.95.34 1.4.58l1.35-.99a1 1 0 0 1 1.26.08l.4.4a1 1 0 0 1 .08 1.26l-.99 1.35c.24.45.44.92.58 1.4l1.64.25a1 1 0 0 1 .84.93L21 12z"
                                        />
                                    </svg>
                                </span>
                                <span>{{ $t('chats.recordingDevices') }}</span>
                                <span class="widget-chat-device-toggle-btn__chevron" aria-hidden="true">{{ showDeviceSettings ? '▾' : '▸' }}</span>
                            </button>

                            <div
                                v-if="showDeviceSettings"
                                id="widget-chat-device-settings-panel"
                                class="section-card widget-chat-device-card"
                            >
                                <div class="widget-chat-device-card__head">
                                    <strong>{{ $t('chats.recordingDevices') }}</strong>
                                    <button
                                        class="btn btn-outline btn-sm"
                                        type="button"
                                        :title="$t('common.refresh')"
                                        :disabled="isLoadingMediaDevices"
                                        @click="refreshMediaDeviceOptions(true)"
                                    >
                                        {{ isLoadingMediaDevices ? $t('common.refreshing') : $t('common.refresh') }}
                                    </button>
                                </div>
                                <div class="widget-chat-device-card__grid">
                                    <label v-if="canRecordVoice" class="widget-chat-device-card__field">
                                        <span>{{ $t('chats.microphoneInput') }}</span>
                                        <select
                                            v-model="selectedAudioInputId"
                                            class="select-field widget-chat-device-card__select"
                                            :title="$t('chats.microphoneInput')"
                                            :disabled="isLoadingMediaDevices || isRecordingVoice || isProcessingVoice || voiceStopInProgress"
                                            @change="onSelectedAudioInputChanged"
                                        >
                                            <option value="">{{ $t('chats.defaultDevice') }}</option>
                                            <option
                                                v-for="device in audioInputDevices"
                                                :key="`widget-chat-audio-input-${device.deviceId}`"
                                                :value="device.deviceId"
                                            >
                                                {{ device.label }}
                                            </option>
                                        </select>
                                    </label>
                                    <label v-if="canRecordVideo" class="widget-chat-device-card__field">
                                        <span>{{ $t('chats.cameraInput') }}</span>
                                        <select
                                            v-model="selectedVideoInputId"
                                            class="select-field widget-chat-device-card__select"
                                            :title="$t('chats.cameraInput')"
                                            :disabled="isLoadingMediaDevices || isRecordingVideo || isProcessingVideo || videoStopInProgress"
                                            @change="onSelectedVideoInputChanged"
                                        >
                                            <option value="">{{ $t('chats.defaultDevice') }}</option>
                                            <option
                                                v-for="device in videoInputDevices"
                                                :key="`widget-chat-video-input-${device.deviceId}`"
                                                :value="device.deviceId"
                                            >
                                                {{ device.label }}
                                            </option>
                                        </select>
                                    </label>
                                </div>
                                <p v-if="mediaDeviceError" class="error-text widget-chat-note">{{ mediaDeviceError }}</p>
                            </div>
                        </div>
                        <div v-if="isVideoPreviewActive || isVideoPreviewLoading" class="section-card widget-chat-preview-card">
                            <p class="muted widget-chat-note">
                                {{ isVideoPreviewLoading ? $t('chats.previewLoading') : $t('chats.cameraPreviewReady') }}
                            </p>
                            <video
                                ref="videoPreviewElement"
                                class="widget-chat-preview-card__video"
                                autoplay
                                muted
                                playsinline
                            ></video>
                        </div>

                        <p v-if="isRecordingVoice" class="muted widget-chat-note">{{ $t('chats.voiceRecordingNow') }}</p>
                        <div v-if="isRecordingVoice" class="section-card widget-chat-record-card">
                            <div class="muted widget-chat-record-card__meta">
                                <span>{{ formattedVoiceRecordDuration }}</span>
                                <span>{{ $t('chats.limitValue', { value: formattedVoiceRecordDurationLimit }) }}</span>
                            </div>
                            <div class="widget-chat-record-card__progress">
                                <div
                                    :style="{
                                        width: `${voiceDurationProgressPercent}%`,
                                        height: '100%',
                                        borderRadius: '999px',
                                        background: 'linear-gradient(90deg, #16a34a 0%, #0ea5e9 100%)',
                                        transition: 'width 180ms linear',
                                    }"
                                ></div>
                            </div>
                        </div>
                        <p v-if="isProcessingVoice" class="muted widget-chat-note">{{ $t('chats.preparingVoiceToSend') }}</p>

                        <p v-if="isRecordingVideo" class="muted widget-chat-note">{{ $t('chats.videoRecordingNow') }}</p>
                        <div v-if="isRecordingVideo" class="section-card widget-chat-record-card">
                            <div class="muted widget-chat-record-card__meta">
                                <span>{{ formattedVideoRecordDuration }}</span>
                                <span>{{ $t('chats.limitValue', { value: formattedVideoRecordDurationLimit }) }}</span>
                            </div>
                            <div class="widget-chat-record-card__progress">
                                <div
                                    :style="{
                                        width: `${videoDurationProgressPercent}%`,
                                        height: '100%',
                                        borderRadius: '999px',
                                        background: 'linear-gradient(90deg, #ec4899 0%, #8b5cf6 50%, #0ea5e9 100%)',
                                        transition: 'width 180ms linear',
                                    }"
                                ></div>
                            </div>
                        </div>
                        <p v-if="isProcessingVideo" class="muted widget-chat-note">{{ $t('chats.preparingVideoToSend') }}</p>

                        <div class="widget-chat-composer-tools-panel">
                            <button
                                class="btn btn-outline btn-sm widget-chat-composer-tools-toggle"
                                type="button"
                                :title="composerToolsToggleTitle"
                                :aria-label="composerToolsToggleTitle"
                                :aria-expanded="showComposerTools ? 'true' : 'false'"
                                aria-controls="widget-chat-composer-tools-panel"
                                :disabled="!activeConversation || activeConversation?.is_blocked"
                                @click="toggleComposerTools"
                            >
                                <span class="widget-chat-composer-tools-toggle__icon-group" aria-hidden="true">
                                    <span class="widget-chat-composer-tools-toggle__icon">
                                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                            <path
                                                fill="currentColor"
                                                d="M12 8.6A3.4 3.4 0 1 0 12 15.4 3.4 3.4 0 0 0 12 8.6zm9 3.4-.02.56a1 1 0 0 1-.84.93l-1.64.25a6.86 6.86 0 0 1-.58 1.4l.99 1.35a1 1 0 0 1-.08 1.26l-.4.4a1 1 0 0 1-1.26.08l-1.35-.99c-.45.24-.92.44-1.4.58l-.25 1.64a1 1 0 0 1-.93.84L12 21l-.56-.02a1 1 0 0 1-.93-.84l-.25-1.64a6.86 6.86 0 0 1-1.4-.58l-1.35.99a1 1 0 0 1-1.26-.08l-.4-.4a1 1 0 0 1-.08-1.26l.99-1.35a6.86 6.86 0 0 1-.58-1.4l-1.64-.25a1 1 0 0 1-.84-.93L3 12l.02-.56a1 1 0 0 1 .84-.93l1.64-.25c.14-.48.34-.95.58-1.4l-.99-1.35a1 1 0 0 1 .08-1.26l.4-.4a1 1 0 0 1 1.26-.08l1.35.99c.45-.24.92-.44 1.4-.58l.25-1.64a1 1 0 0 1 .93-.84L12 3l.56.02a1 1 0 0 1 .93.84l.25 1.64c.48.14.95.34 1.4.58l1.35-.99a1 1 0 0 1 1.26.08l.4.4a1 1 0 0 1 .08 1.26l-.99 1.35c.24.45.44.92.58 1.4l1.64.25a1 1 0 0 1 .84.93L21 12z"
                                            />
                                        </svg>
                                    </span>
                                    <span class="widget-chat-composer-tools-toggle__icon">
                                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                            <path
                                                fill="currentColor"
                                                d="M15 10.5V8a3 3 0 0 0-3-3H6A3 3 0 0 0 3 8v8a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3v-2.5l4.07 3.14A1 1 0 0 0 21 15.86V8.14a1 1 0 0 0-1.93-.78L15 10.5z"
                                            />
                                        </svg>
                                    </span>
                                </span>
                                <span>{{ $t('chats.fileAndRecordingTools') }}</span>
                                <span class="widget-chat-composer-tools-toggle__chevron" aria-hidden="true">{{ showComposerTools ? '▾' : '▸' }}</span>
                            </button>

                            <div
                                v-if="showComposerTools"
                                id="widget-chat-composer-tools-panel"
                                class="widget-chat-composer__actions"
                            >
                                <button
                                    class="btn btn-outline btn-sm"
                                    type="button"
                                    :title="$t('chats.addFileMedia')"
                                    :disabled="composerDisabled"
                                    @click="openFileDialog"
                                >
                                    {{ $t('chats.addFileMedia') }}
                                </button>
                                <button
                                    class="btn btn-outline btn-sm"
                                    type="button"
                                    :title="showStickerTray ? $t('chats.hideStickers') : $t('chats.stickers')"
                                    :disabled="composerDisabled"
                                    @click="toggleStickerTray"
                                >
                                    {{ showStickerTray ? $t('chats.hideStickers') : $t('chats.stickers') }}
                                </button>
                                <button
                                    class="btn btn-outline btn-sm"
                                    type="button"
                                    :title="$t('chats.recordVoice')"
                                    :disabled="composerDisabled || !canRecordVoice"
                                    @click="startVoiceRecording"
                                >
                                    {{ $t('chats.recordVoice') }}
                                </button>
                                <button
                                    class="btn btn-outline btn-sm"
                                    type="button"
                                    :title="$t('chats.recordVideo')"
                                    :disabled="composerDisabled || !canRecordVideo"
                                    @click="startVideoRecording"
                                >
                                    {{ $t('chats.recordVideo') }}
                                </button>
                                <button
                                    class="btn btn-outline btn-sm"
                                    type="button"
                                    :title="isVideoPreviewActive ? $t('chats.closeCameraPreview') : $t('chats.openCameraPreview')"
                                    :disabled="composerDisabled || !canRecordVideo"
                                    @click="toggleVideoPreview"
                                >
                                    {{ isVideoPreviewActive ? $t('chats.closeCameraPreview') : $t('chats.openCameraPreview') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="isRecordingVoice || isRecordingVideo" class="widget-chat-composer-record-actions">
                            <button
                                v-if="isRecordingVoice"
                                class="btn btn-danger btn-sm"
                                type="button"
                                :disabled="voiceStopInProgress"
                                @click="stopVoiceRecording(false)"
                            >
                                {{ $t('chats.stopRecordingWithDuration', { current: formattedVoiceRecordDuration, limit: formattedVoiceRecordDurationLimit }) }}
                            </button>
                            <button
                                v-if="isRecordingVoice"
                                class="btn btn-outline btn-sm"
                                type="button"
                                :disabled="voiceStopInProgress"
                                @click="stopVoiceRecording(true)"
                            >
                                {{ $t('chats.cancelRecording') }}
                            </button>
                            <button
                                v-if="isRecordingVideo"
                                class="btn btn-danger btn-sm"
                                type="button"
                                :disabled="videoStopInProgress"
                                @click="stopVideoRecording(false)"
                            >
                                {{ $t('chats.stopRecordingWithDuration', { current: formattedVideoRecordDuration, limit: formattedVideoRecordDurationLimit }) }}
                            </button>
                            <button
                                v-if="isRecordingVideo"
                                class="btn btn-outline btn-sm"
                                type="button"
                                :disabled="videoStopInProgress"
                                @click="stopVideoRecording(true)"
                            >
                                {{ $t('chats.cancelRecording') }}
                            </button>
                        </div>

                        <p v-if="sendError" class="error-text widget-chat-note">{{ sendError }}</p>
                    </form>
                </section>
            </div>
        </section>
        <MediaLightbox ref="mediaLightbox"></MediaLightbox>
    </aside>
</template>

<script>
import {nextTick} from 'vue'
import MediaLightbox from '../MediaLightbox.vue'
import MediaPlayer from '../MediaPlayer.vue'
import StickerPicker from '../stickers/StickerPicker.vue'
import StickerRichText from '../stickers/StickerRichText.vue'
import {
    replaceMarkedEmojiWithStickerTokens,
    replaceStickerTokensWithMarkedEmoji,
    stickerMarkedEmojiFromId,
    stickerTextToPreview,
    stickerTokenFromId,
} from '../../data/stickerCatalog'

const CHAT_WIDGET_STORAGE_PREFIX = 'social.widgets.chat'
const CHAT_SHARED_SYNC_STORAGE_PREFIX = 'social.chat.shared'
const CHAT_WIDGET_CONVERSATIONS_POLL_MS = 25000
const CHAT_WIDGET_MESSAGES_POLL_MS = 35000
const DESKTOP_FLOATING_BREAKPOINT = 1241
const WIDGET_EDGE_GAP = 72
const CHAT_MESSAGE_REACTION_EMOJIS = ['👍', '❤️', '🔥', '😂', '👏', '😮']
const CHAT_WIDGET_SYNC_EVENT = 'social:chat:sync'
const CHAT_WIDGET_SYNC_SOURCE = 'chat-widget'
const CHAT_WIDGET_SYNC_SOURCE_PAGE = 'chat-page'
const CHAT_WIDGET_SYNC_TYPE_ACTIVE_CONVERSATION = 'active-conversation'
const CHAT_WIDGET_SYNC_TYPE_CONVERSATION_READ = 'conversation-read'
const CHAT_WIDGET_SYNC_TYPE_MESSAGE_UPSERT = 'message-upsert'
const CHAT_WIDGET_SYNC_TYPE_STATE_REFRESH = 'state-refresh'

export default {
    name: 'PersistentChatWidget',

    components: {
        MediaLightbox,
        MediaPlayer,
        StickerPicker,
        StickerRichText,
    },

    props: {
        active: {
            type: Boolean,
            default: false,
        },
        user: {
            type: Object,
            default: null,
        },
    },

    emits: ['unread-updated'],

    data() {
        return {
            expanded: true,
            leftPaneMode: 'conversations',
            conversationSearch: '',
            userSearch: '',
            conversations: [],
            users: [],
            activeConversationId: null,
            messages: [],
            messageBody: '',
            selectedFiles: [],
            selectedFilePreviews: [],
            deletingMessageIds: [],
            deletingAttachmentKeys: [],
            selectedMessageIds: [],
            isBulkDeletingMessages: false,
            togglingMessageReactionKeys: [],
            openMessageReactionPickerId: null,
            moodStatusForm: {
                text: '',
                is_visible_to_all: true,
                hidden_user_ids: [],
            },
            moodStatusSyncInFlight: {},
            isSavingMoodStatus: false,
            showMoodStatusSettings: false,
            suppressMoodStatusFormSyncOnce: false,
            canRecordVoice: false,
            canRecordVideo: false,
            isLoadingMediaDevices: false,
            mediaDeviceError: '',
            audioInputDevices: [],
            videoInputDevices: [],
            selectedAudioInputId: '',
            selectedVideoInputId: '',
            isVideoPreviewActive: false,
            isVideoPreviewLoading: false,
            videoPreviewStream: null,
            isRecordingVoice: false,
            isProcessingVoice: false,
            voiceStopInProgress: false,
            voiceRecordDurationSeconds: 0,
            maxVoiceRecordDurationSeconds: 5 * 60,
            voiceMediaRecorder: null,
            voiceRecordStream: null,
            voiceRecordTimerId: null,
            voiceRecordedChunks: [],
            voiceRecordedMimeType: '',
            voiceRecordStartedAt: null,
            isRecordingVideo: false,
            isProcessingVideo: false,
            videoStopInProgress: false,
            videoRecordDurationSeconds: 0,
            maxVideoRecordDurationSeconds: 3 * 60,
            videoMediaRecorder: null,
            videoRecordStream: null,
            videoRecordTimerId: null,
            videoRecordedChunks: [],
            videoRecordedMimeType: '',
            videoRecordStartedAt: null,
            isLoadingConversations: false,
            isLoadingUsers: false,
            isLoadingMessages: false,
            isSending: false,
            loadError: '',
            usersError: '',
            sendError: '',
            emojis: ['😀', '🔥', '❤️', '😂', '👏', '😎', '👍', '🎉'],
            messageReactionEmojis: CHAT_MESSAGE_REACTION_EMOJIS,
            showStickerTray: false,
            showComposerTools: false,
            showDeviceSettings: false,
            typingStateByConversation: {},
            typingExpireTimerIds: {},
            typingIdleTimerId: null,
            typingLastSentAt: 0,
            conversationPollTimerId: null,
            messagePollTimerId: null,
            subscribedChannels: {},
            isInitializing: false,
            userSearchDebounceTimerId: null,
            loadUsersRequestId: 0,
            incomingNotice: null,
            incomingNoticeTimerId: null,
            isPinned: true,
            floatingPosition: {
                left: 0,
                top: 0,
            },
            isFloatingReady: false,
            isDragging: false,
            dragState: null,
            floatingRecheckTimerId: null,
            viewportWidth: typeof window !== 'undefined' ? window.innerWidth : 0,
            failedAvatarUrls: {},
        }
    },

    watch: {
        active: {
            immediate: true,
            async handler(next) {
                if (next) {
                    await this.initializeWidgetState()
                    return
                }

                this.teardownWidgetState()
            },
        },

        userStorageKey() {
            if (!this.active) {
                return
            }

            this.teardownWidgetState()
            this.initializeWidgetState()
        },

        unreadTotal(next) {
            this.$emit('unread-updated', next)
        },

        expanded() {
            this.persistWidgetState()
            this.refreshFloatingPosition()
            this.scheduleFloatingPositionRecheck()
        },

        conversationSearch() {
            this.persistWidgetState()
        },

        userSearch() {
            this.persistWidgetState()
        },

        leftPaneMode(nextMode) {
            this.persistWidgetState()
            if (nextMode === 'users' && this.users.length === 0) {
                this.loadUsers({silent: true})
            }
        },

        activeConversationId() {
            this.persistWidgetState()
            this.persistSharedSyncConversationId(this.activeConversationId)
            this.restartMessagePolling()
        },
    },

    computed: {
        userStorageKey() {
            const id = Number(this.user?.id ?? 0)
            return Number.isFinite(id) && id > 0 ? String(id) : 'guest'
        },

        storageKey() {
            return `${CHAT_WIDGET_STORAGE_PREFIX}.${this.userStorageKey}`
        },

        sharedSyncStorageKey() {
            return `${CHAT_SHARED_SYNC_STORAGE_PREFIX}.${this.userStorageKey}`
        },

        collapsedButtonHint() {
            return `${this.$t('nav.chats')} · ${this.$t('chats.selectChat')}`
        },

        collapseButtonHint() {
            return `${this.$t('common.close')} ${this.$t('nav.chats')}`
        },

        pinButtonHint() {
            return this.isPinned ? this.$t('common.unpin') : this.$t('common.pin')
        },

        isMovableMode() {
            return this.viewportWidth >= DESKTOP_FLOATING_BREAKPOINT
        },

        floatingStyle() {
            if (!this.isMovableMode) {
                return null
            }

            const basePosition = this.isFloatingReady
                ? this.floatingPosition
                : this.getDefaultFloatingPosition()

            return {
                left: `${Math.round(basePosition.left)}px`,
                top: `${Math.round(basePosition.top)}px`,
                position: 'fixed',
            }
        },

        unreadTotal() {
            return this.conversations.reduce((total, conversation) => {
                const unread = Number(conversation?.unread_count ?? 0)
                return total + (Number.isFinite(unread) && unread > 0 ? unread : 0)
            }, 0)
        },

        unreadBadge() {
            return this.unreadTotal > 99 ? '99+' : String(this.unreadTotal)
        },

        filteredConversations() {
            const query = this.conversationSearch.trim().toLowerCase()
            if (query === '') {
                return this.conversations
            }

            return this.conversations.filter((conversation) => {
                const haystack = `${conversation.title} ${this.messagePreview(conversation)}`.toLowerCase()
                return haystack.includes(query)
            })
        },

        globalConversation() {
            return this.conversations.find((conversation) => String(conversation?.type || '') === 'global') || null
        },

        activeConversation() {
            const targetId = Number(this.activeConversationId)
            if (!Number.isFinite(targetId) || targetId <= 0) {
                return null
            }

            return this.conversations.find((conversation) => Number(conversation.id) === targetId) || null
        },

        activeConversationTitle() {
            return this.activeConversation?.title || this.$t('chats.selectChat')
        },

        activeConversationTypingEntries() {
            const conversationId = Number(this.activeConversation?.id ?? 0)
            if (!Number.isFinite(conversationId) || conversationId <= 0) {
                return []
            }

            const state = this.typingStateByConversation?.[conversationId]
            if (!state || typeof state !== 'object') {
                return []
            }

            return Object.values(state)
                .filter((entry) => Number(entry?.id) > 0)
                .filter((entry) => Number(entry.id) !== Number(this.user?.id ?? 0))
                .sort((first, second) => String(first.display_name || '').localeCompare(String(second.display_name || ''), 'ru'))
        },

        activeTypingStatusLine() {
            const entries = this.activeConversationTypingEntries
            if (entries.length === 0) {
                return ''
            }

            if (entries.length === 1) {
                const entry = entries[0]
                const name = entry.display_name || this.$t('chats.peer')
                const preview = String(entry.preview || '').trim()
                const isSending = Boolean(entry.is_sending)
                const hasAttachments = Boolean(entry.has_attachments)
                const isDirectConversation = this.activeConversation?.type === 'direct'

                if (isSending && hasAttachments) {
                    return this.$t('chats.peerSendingWithAttachments', { name })
                }

                if (isSending) {
                    return this.$t('chats.peerSendingMessage', { name })
                }

                if (preview !== '') {
                    if (isDirectConversation) {
                        return this.$t('chats.peerTypingGeneric')
                    }

                    return this.$t('chats.peerTypingPreview', { name, preview })
                }

                if (hasAttachments) {
                    return this.$t('chats.peerAttachingFiles', { name })
                }

                if (isDirectConversation) {
                    return this.$t('chats.peerTypingGeneric')
                }

                return this.$t('chats.peerTyping', { name })
            }

            const names = entries.slice(0, 2).map((entry) => entry.display_name || this.$t('chats.participant'))
            const suffix = entries.length > 2 ? this.$t('chats.andMoreCount', { count: entries.length - 2 }) : ''

            return this.$t('chats.peopleTyping', { names: names.join(', '), suffix })
        },

        composerDisabled() {
            return !this.activeConversation
                || this.isSending
                || Boolean(this.activeConversation?.is_blocked)
                || this.isRecordingVoice
                || this.isProcessingVoice
                || this.voiceStopInProgress
                || this.isRecordingVideo
                || this.isProcessingVideo
                || this.videoStopInProgress
        },

        canSend() {
            const hasBody = this.messageBody.trim() !== ''
            const hasFiles = this.selectedFiles.length > 0

            return Boolean(
                this.activeConversation
                && (hasBody || hasFiles)
                && !this.isSending
                && !this.activeConversation?.is_blocked
                && !this.isRecordingVoice
                && !this.isProcessingVoice
                && !this.voiceStopInProgress
                && !this.isRecordingVideo
                && !this.isProcessingVideo
                && !this.videoStopInProgress
            )
        },

        deletableMessageIds() {
            return this.messages
                .filter((message) => this.canDeleteMessage(message))
                .map((message) => Number(message.id))
                .filter((id) => Number.isFinite(id) && id > 0)
        },

        selectedDeletableMessageIds() {
            const available = new Set(this.deletableMessageIds.map((id) => Number(id)))
            return this.selectedMessageIds.filter((id) => available.has(Number(id)))
        },

        selectedMessagesCount() {
            return this.selectedDeletableMessageIds.length
        },

        hasSelectableMessages() {
            return this.deletableMessageIds.length > 0
        },

        allSelectableMessagesSelected() {
            const available = this.deletableMessageIds
            if (available.length === 0) {
                return false
            }

            const selected = new Set(this.selectedDeletableMessageIds.map((id) => Number(id)))
            return available.every((id) => selected.has(Number(id)))
        },

        moodStatusSettingsToggleTitle() {
            const label = this.$t('chats.moodStatusSettings')
            return this.showMoodStatusSettings
                ? `${this.$t('common.close')} · ${label}`
                : label
        },

        visibleMoodStatuses() {
            const list = Array.isArray(this.activeConversation?.mood_statuses)
                ? this.activeConversation.mood_statuses
                : []

            return list
                .map((status) => this.normalizeMoodStatus(status))
                .filter((status) => status !== null)
        },

        myMoodStatus() {
            const myId = Number(this.user?.id ?? 0)
            if (!Number.isFinite(myId) || myId <= 0) {
                return null
            }

            return this.visibleMoodStatuses.find((status) => Number(status.user_id) === myId || status.is_owner) || null
        },

        selfProfileMoodStatusText() {
            const text = String(this.myMoodStatus?.text || '').trim()
            return text !== '' ? text : '—'
        },

        selfProfileMoodStatusTitle() {
            return `${this.$t('chats.moodStatusYourComment')}: ${this.selfProfileMoodStatusText}`
        },

        moodStatusVisibilityCandidates() {
            const myId = Number(this.user?.id ?? 0)
            const participants = Array.isArray(this.activeConversation?.participants)
                ? this.activeConversation.participants
                : []

            return participants
                .filter((participant) => Number(participant?.id ?? 0) > 0)
                .filter((participant) => Number(participant.id) !== myId)
        },

        composerToolsToggleTitle() {
            const label = this.$t('chats.fileAndRecordingTools')
            return this.showComposerTools
                ? `${this.$t('common.close')} · ${label}`
                : label
        },

        deviceSettingsToggleTitle() {
            const label = this.$t('chats.recordingDevices')
            return this.showDeviceSettings
                ? `${this.$t('common.close')} · ${label}`
                : label
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

            return Math.max(0, Math.min(100, (this.voiceRecordDurationSeconds / this.maxVoiceRecordDurationSeconds) * 100))
        },

        formattedVideoRecordDuration() {
            const total = Math.max(0, Number(this.videoRecordDurationSeconds) || 0)
            const minutes = Math.floor(total / 60)
            const seconds = total % 60
            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
        },

        formattedVideoRecordDurationLimit() {
            const total = Math.max(0, Number(this.maxVideoRecordDurationSeconds) || 0)
            const minutes = Math.floor(total / 60)
            const seconds = total % 60
            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
        },

        videoDurationProgressPercent() {
            if (this.maxVideoRecordDurationSeconds <= 0) {
                return 0
            }

            return Math.max(0, Math.min(100, (this.videoRecordDurationSeconds / this.maxVideoRecordDurationSeconds) * 100))
        },
    },

    mounted() {
        this.canRecordVoice = this.isVoiceRecordingSupported()
        this.canRecordVideo = this.isVideoRecordingSupported()
        this.loadMediaInputDevices()

        if (typeof window !== 'undefined') {
            window.addEventListener('resize', this.handleViewportResize)
            window.addEventListener(CHAT_WIDGET_SYNC_EVENT, this.handleChatSyncEvent)
            this.viewportWidth = window.innerWidth
        }
        if (typeof navigator !== 'undefined' && navigator.mediaDevices && typeof navigator.mediaDevices.addEventListener === 'function') {
            navigator.mediaDevices.addEventListener('devicechange', this.handleMediaDevicesChanged)
        }

        this.refreshFloatingPosition({forceDefault: !this.isPinned})
    },

    beforeUnmount() {
        this.stopVoiceRecording(true)
        this.stopVideoRecording(true)
        this.stopVideoPreview()
        this.clearFloatingPositionRecheckTimer()
        this.teardownWidgetState()
        this.stopDragging()

        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', this.handleViewportResize)
            window.removeEventListener(CHAT_WIDGET_SYNC_EVENT, this.handleChatSyncEvent)
        }
        if (typeof navigator !== 'undefined' && navigator.mediaDevices && typeof navigator.mediaDevices.removeEventListener === 'function') {
            navigator.mediaDevices.removeEventListener('devicechange', this.handleMediaDevicesChanged)
        }
    },

    methods: {
        collapse() {
            this.stopVideoPreview()
            this.expanded = false
        },

        expand() {
            this.expanded = true
        },

        setLeftPaneMode(mode) {
            if (mode !== 'conversations' && mode !== 'users') {
                return
            }

            this.leftPaneMode = mode
        },

        displayName(user) {
            return user?.display_name || user?.name || this.$t('common.user')
        },

        normalizeAvatarUrl(value) {
            const raw = String(value || '').trim()
            if (raw === '') {
                return ''
            }

            if (typeof window === 'undefined') {
                return raw
            }

            try {
                return new URL(raw, window.location.origin).href
            } catch (_error) {
                return raw
            }
        },

        onAvatarImageError(event) {
            const target = event?.target
            const sources = [
                target?.getAttribute?.('src') || '',
                target?.currentSrc || '',
                target?.src || '',
            ]

            const next = { ...this.failedAvatarUrls }
            for (const source of sources) {
                const normalized = this.normalizeAvatarUrl(source)
                if (normalized !== '') {
                    next[normalized] = true
                }
            }

            this.failedAvatarUrls = next
        },

        avatarUrl(user) {
            const raw = String(user?.avatar_url || '').trim()
            if (raw === '') {
                return null
            }

            const normalized = this.normalizeAvatarUrl(raw)
            return this.failedAvatarUrls[normalized] ? null : raw
        },

        openMedia(url, alt = null) {
            this.$refs.mediaLightbox?.open(url, alt || this.$t('chats.photo'))
        },

        openUserAvatar(user) {
            const avatar = this.avatarUrl(user)
            if (!avatar) {
                return
            }

            this.openMedia(avatar, this.displayName(user))
        },

        userInitial(user) {
            const source = this.displayName(user).trim()
            return source ? source.slice(0, 1).toUpperCase() : 'U'
        },

        isUserDirectChatBlocked(user) {
            return Boolean(user?.is_blocked_by_me) || Boolean(user?.has_blocked_me)
        },

        userStatusText(user) {
            if (user?.is_blocked_by_me) {
                return this.$t('chats.youBlockedUser')
            }

            if (user?.has_blocked_me) {
                return this.$t('chats.userBlockedYou')
            }

            return this.$t('chats.directChatAvailable')
        },

        onUserSearchInput() {
            if (this.userSearchDebounceTimerId) {
                window.clearTimeout(this.userSearchDebounceTimerId)
                this.userSearchDebounceTimerId = null
            }

            this.userSearchDebounceTimerId = window.setTimeout(() => {
                this.loadUsers()
            }, 260)
        },

        async loadUsers(options = {}) {
            const silent = Boolean(options?.silent)
            const requestId = this.loadUsersRequestId + 1
            this.loadUsersRequestId = requestId

            if (!silent) {
                this.isLoadingUsers = true
            }

            try {
                const response = await axios.get('/api/chats/users', {
                    params: {
                        search: this.userSearch,
                        per_page: 100,
                    },
                })

                if (requestId !== this.loadUsersRequestId) {
                    return
                }

                this.users = Array.isArray(response?.data?.data) ? response.data.data : []
                this.usersError = ''
            } catch (error) {
                if (requestId !== this.loadUsersRequestId) {
                    return
                }

                if (!silent) {
                    this.usersError = error?.response?.data?.message || this.$t('chats.usersNotFound')
                }
                this.users = []
            } finally {
                if (!silent && requestId === this.loadUsersRequestId) {
                    this.isLoadingUsers = false
                }
            }
        },

        async startDirectChat(user) {
            const targetUserId = Number(user?.id ?? 0)
            if (!Number.isFinite(targetUserId) || targetUserId <= 0 || this.isUserDirectChatBlocked(user)) {
                return
            }

            try {
                this.usersError = ''
                const response = await axios.post(`/api/chats/direct/${targetUserId}`)
                const conversation = this.normalizeConversation(response?.data?.data)

                await this.loadConversations({silent: true})
                this.setLeftPaneMode('conversations')

                if (conversation) {
                    const target = this.conversations.find((item) => Number(item.id) === Number(conversation.id)) || conversation
                    await this.openConversation(target)
                    return
                }

                if (this.conversations.length > 0) {
                    await this.openConversation(this.conversations[0])
                }
            } catch (error) {
                this.usersError = error?.response?.data?.message || this.$t('chats.openDirectChatFailed')
            }
        },

        async openGlobalConversation() {
            let target = this.globalConversation
            if (!target) {
                await this.loadConversations({silent: true})
                target = this.globalConversation
            }

            if (!target) {
                return
            }

            this.setLeftPaneMode('conversations')
            await this.openConversation(target)
        },

        resolveConversationTitle(conversationId) {
            const resolvedId = Number(conversationId)
            const target = this.conversations.find((conversation) => Number(conversation?.id) === resolvedId)
            return String(target?.title || this.$t('chats.selectChat'))
        },

        setIncomingNotice(message) {
            const normalized = this.normalizeMessage(message)
            if (!normalized || this.isMine(normalized)) {
                return
            }

            const senderName = this.displayName(normalized?.user)
            const text = stickerTextToPreview(this.messageText(normalized))
            this.incomingNotice = {
                messageId: Number(normalized.id),
                conversationId: Number(normalized.conversation_id || 0),
                conversationTitle: this.resolveConversationTitle(normalized.conversation_id),
                sender: senderName,
                text,
            }

            if (this.incomingNoticeTimerId) {
                window.clearTimeout(this.incomingNoticeTimerId)
                this.incomingNoticeTimerId = null
            }

            this.incomingNoticeTimerId = window.setTimeout(() => {
                this.incomingNotice = null
                this.incomingNoticeTimerId = null
            }, 18000)
        },

        clearIncomingNotice() {
            this.incomingNotice = null
            if (this.incomingNoticeTimerId) {
                window.clearTimeout(this.incomingNoticeTimerId)
                this.incomingNoticeTimerId = null
            }
        },

        async openIncomingNoticeConversation() {
            const conversationId = Number(this.incomingNotice?.conversationId || 0)
            if (!Number.isFinite(conversationId) || conversationId <= 0) {
                this.clearIncomingNotice()
                return
            }

            let targetConversation = this.conversations.find((conversation) => Number(conversation?.id) === conversationId) || null
            if (!targetConversation) {
                await this.loadConversations({silent: true})
                targetConversation = this.conversations.find((conversation) => Number(conversation?.id) === conversationId) || null
            }

            this.clearIncomingNotice()
            if (targetConversation) {
                await this.openConversation(targetConversation)
            }
        },

        normalizeTypingPreview(value) {
            const text = String(value || '').replace(/\s+/g, ' ').trim()
            if (text === '') {
                return ''
            }

            const maxLength = 86
            if (text.length <= maxLength) {
                return text
            }

            return `${text.slice(0, maxLength)}...`
        },

        resolveTypingChannelName(conversationId = null) {
            const resolvedConversationId = Number(conversationId ?? this.activeConversation?.id ?? 0)
            if (!Number.isFinite(resolvedConversationId) || resolvedConversationId <= 0) {
                return ''
            }

            return this.subscribedChannels?.[resolvedConversationId] || `chat.conversation.${resolvedConversationId}`
        },

        sendTypingWhisper(isTyping, options = {}) {
            if (typeof window === 'undefined' || !window.Echo || !this.user) {
                return
            }

            const conversationId = Number(options?.conversationId ?? this.activeConversation?.id ?? 0)
            if (!Number.isFinite(conversationId) || conversationId <= 0) {
                return
            }

            const channelName = this.resolveTypingChannelName(conversationId)
            if (channelName === '') {
                return
            }

            const hasAttachments = this.selectedFiles.length > 0
            const payload = {
                conversation_id: conversationId,
                user_id: Number(this.user.id),
                display_name: this.displayName(this.user),
                is_typing: Boolean(isTyping),
                has_attachments: hasAttachments,
                is_sending: Boolean(options?.isSending),
                preview: Boolean(isTyping) ? this.normalizeTypingPreview(this.messageBody) : '',
                at: Date.now(),
            }

            try {
                window.Echo.private(channelName).whisper('typing', payload)
            } catch (_error) {
                // Ignore transient whisper transport glitches.
            }
        },

        notifyTypingActivity(options = {}) {
            if (this.composerDisabled || !this.activeConversation) {
                return
            }

            const hasDraft = this.messageBody.trim() !== ''
                || this.selectedFiles.length > 0
                || this.isRecordingVoice
                || this.isProcessingVoice
                || this.isRecordingVideo
                || this.isProcessingVideo

            const now = Date.now()
            const minIntervalMs = 700
            const shouldSendNow = Boolean(options?.immediate) || (now - Number(this.typingLastSentAt || 0) >= minIntervalMs)

            if (hasDraft && shouldSendNow) {
                this.sendTypingWhisper(true, {
                    isSending: Boolean(options?.isSending),
                })
                this.typingLastSentAt = now
            }

            if (this.typingIdleTimerId) {
                window.clearTimeout(this.typingIdleTimerId)
                this.typingIdleTimerId = null
            }

            if (hasDraft) {
                this.typingIdleTimerId = window.setTimeout(() => {
                    this.sendTypingWhisper(false)
                    this.typingLastSentAt = Date.now()
                }, 2600)
                return
            }

            if (shouldSendNow) {
                this.sendTypingWhisper(false)
                this.typingLastSentAt = now
            }
        },

        notifyTypingStopped(conversationId = null) {
            if (this.typingIdleTimerId) {
                window.clearTimeout(this.typingIdleTimerId)
                this.typingIdleTimerId = null
            }

            this.sendTypingWhisper(false, { conversationId })
            this.typingLastSentAt = Date.now()
        },

        typingStateKey(conversationId, userId) {
            return `${Number(conversationId)}:${Number(userId)}`
        },

        clearTypingExpireTimer(timerKey) {
            const timerId = this.typingExpireTimerIds[timerKey]
            if (timerId) {
                window.clearTimeout(timerId)
            }

            if (Object.prototype.hasOwnProperty.call(this.typingExpireTimerIds, timerKey)) {
                const next = { ...this.typingExpireTimerIds }
                delete next[timerKey]
                this.typingExpireTimerIds = next
            }
        },

        removeTypingStateEntry(conversationId, userId) {
            const normalizedConversationId = Number(conversationId)
            const normalizedUserId = Number(userId)
            if (!Number.isFinite(normalizedConversationId) || normalizedConversationId <= 0 || !Number.isFinite(normalizedUserId) || normalizedUserId <= 0) {
                return
            }

            const currentState = this.typingStateByConversation?.[normalizedConversationId]
            if (!currentState || typeof currentState !== 'object' || !Object.prototype.hasOwnProperty.call(currentState, normalizedUserId)) {
                this.clearTypingExpireTimer(this.typingStateKey(normalizedConversationId, normalizedUserId))
                return
            }

            const nextConversationState = { ...currentState }
            delete nextConversationState[normalizedUserId]

            const nextTypingState = { ...this.typingStateByConversation }
            if (Object.keys(nextConversationState).length === 0) {
                delete nextTypingState[normalizedConversationId]
            } else {
                nextTypingState[normalizedConversationId] = nextConversationState
            }

            this.typingStateByConversation = nextTypingState
            this.clearTypingExpireTimer(this.typingStateKey(normalizedConversationId, normalizedUserId))
        },

        clearConversationTypingState(conversationId) {
            const normalizedConversationId = Number(conversationId)
            if (!Number.isFinite(normalizedConversationId) || normalizedConversationId <= 0) {
                return
            }

            const nextTypingState = { ...this.typingStateByConversation }
            if (Object.prototype.hasOwnProperty.call(nextTypingState, normalizedConversationId)) {
                delete nextTypingState[normalizedConversationId]
                this.typingStateByConversation = nextTypingState
            }

            const keyPrefix = `${normalizedConversationId}:`
            for (const timerKey of Object.keys(this.typingExpireTimerIds)) {
                if (!timerKey.startsWith(keyPrefix)) {
                    continue
                }

                this.clearTypingExpireTimer(timerKey)
            }
        },

        handleTypingWhisper(conversationId, payload) {
            const normalizedConversationId = Number(conversationId ?? payload?.conversation_id ?? 0)
            const senderId = Number(payload?.user_id ?? 0)

            if (!Number.isFinite(normalizedConversationId) || normalizedConversationId <= 0) {
                return
            }

            if (!Number.isFinite(senderId) || senderId <= 0 || senderId === Number(this.user?.id ?? 0)) {
                return
            }

            if (!Boolean(payload?.is_typing)) {
                this.removeTypingStateEntry(normalizedConversationId, senderId)
                return
            }

            const currentConversationState = this.typingStateByConversation?.[normalizedConversationId] || {}
            const nextConversationState = {
                ...currentConversationState,
                [senderId]: {
                    id: senderId,
                    display_name: String(payload?.display_name || this.$t('chats.peer')),
                    preview: this.normalizeTypingPreview(payload?.preview),
                    has_attachments: Boolean(payload?.has_attachments),
                    is_sending: Boolean(payload?.is_sending),
                    updated_at: Date.now(),
                },
            }

            this.typingStateByConversation = {
                ...this.typingStateByConversation,
                [normalizedConversationId]: nextConversationState,
            }

            const timerKey = this.typingStateKey(normalizedConversationId, senderId)
            this.clearTypingExpireTimer(timerKey)
            this.typingExpireTimerIds = {
                ...this.typingExpireTimerIds,
                [timerKey]: window.setTimeout(() => {
                    this.removeTypingStateEntry(normalizedConversationId, senderId)
                }, 7000),
            }
        },

        clearTypingState() {
            if (this.typingIdleTimerId) {
                window.clearTimeout(this.typingIdleTimerId)
                this.typingIdleTimerId = null
            }

            for (const timerId of Object.values(this.typingExpireTimerIds)) {
                window.clearTimeout(timerId)
            }

            this.typingExpireTimerIds = {}
            this.typingStateByConversation = {}
            this.typingLastSentAt = 0
        },

        notifyChatSync(type, conversationId) {
            if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
                return
            }

            const targetId = Number(conversationId)
            if (!Number.isFinite(targetId) || targetId <= 0) {
                return
            }

            window.dispatchEvent(new CustomEvent(CHAT_WIDGET_SYNC_EVENT, {
                detail: {
                    source: CHAT_WIDGET_SYNC_SOURCE,
                    type: String(type || ''),
                    conversationId: targetId,
                    sentAt: Date.now(),
                },
            }))
        },

        notifyChatSyncMessage(message, options = {}) {
            if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
                return
            }

            const normalized = this.normalizeMessage(message)
            const conversationId = Number(normalized?.conversation_id ?? 0)
            if (!normalized || !Number.isFinite(conversationId) || conversationId <= 0) {
                return
            }

            window.dispatchEvent(new CustomEvent(CHAT_WIDGET_SYNC_EVENT, {
                detail: {
                    source: CHAT_WIDGET_SYNC_SOURCE,
                    type: CHAT_WIDGET_SYNC_TYPE_MESSAGE_UPSERT,
                    conversationId,
                    message: normalized,
                    markRead: Boolean(options?.markRead),
                    sentAt: Date.now(),
                },
            }))
        },

        notifyChatSyncStateRefresh(options = {}) {
            if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
                return
            }

            const rawConversationId = Number(options?.conversationId ?? 0)
            const conversationId = Number.isFinite(rawConversationId) && rawConversationId > 0
                ? rawConversationId
                : null

            window.dispatchEvent(new CustomEvent(CHAT_WIDGET_SYNC_EVENT, {
                detail: {
                    source: CHAT_WIDGET_SYNC_SOURCE,
                    type: CHAT_WIDGET_SYNC_TYPE_STATE_REFRESH,
                    conversationId,
                    sentAt: Date.now(),
                },
            }))
        },

        async handleChatSyncEvent(event) {
            if (!this.active) {
                return
            }

            const source = String(event?.detail?.source || '')
            if (source === CHAT_WIDGET_SYNC_SOURCE || source !== CHAT_WIDGET_SYNC_SOURCE_PAGE) {
                return
            }

            const type = String(event?.detail?.type || '')
            const conversationId = Number(event?.detail?.conversationId)

            if (type === CHAT_WIDGET_SYNC_TYPE_CONVERSATION_READ) {
                if (!Number.isFinite(conversationId) || conversationId <= 0) {
                    return
                }

                this.setConversationReadLocally(conversationId)
                return
            }

            if (type === CHAT_WIDGET_SYNC_TYPE_MESSAGE_UPSERT) {
                const syncedMessage = this.normalizeMessage(event?.detail?.message)
                const targetConversationId = Number(syncedMessage?.conversation_id ?? conversationId)
                if (!syncedMessage || !Number.isFinite(targetConversationId) || targetConversationId <= 0) {
                    return
                }

                if (!this.conversations.some((conversation) => Number(conversation?.id) === targetConversationId)) {
                    await this.loadConversations({silent: true})
                }

                this.updateConversationFromMessage(syncedMessage, {
                    incrementUnread: false,
                })

                if (Number(this.activeConversationId) === targetConversationId) {
                    this.upsertMessage(syncedMessage)
                    if (Boolean(event?.detail?.markRead)) {
                        this.setConversationReadLocally(targetConversationId)
                    }

                    await this.scrollMessagesDown()
                }

                return
            }

            if (type === CHAT_WIDGET_SYNC_TYPE_STATE_REFRESH) {
                await this.loadConversations({silent: true})

                const activeConversationId = Number(this.activeConversationId || 0)
                const shouldReloadActiveMessages = activeConversationId > 0
                    && (!Number.isFinite(conversationId) || conversationId <= 0 || activeConversationId === conversationId)

                if (shouldReloadActiveMessages) {
                    await this.loadMessages(activeConversationId, {silent: true})
                }

                return
            }

            if (type !== CHAT_WIDGET_SYNC_TYPE_ACTIVE_CONVERSATION) {
                return
            }

            if (!Number.isFinite(conversationId) || conversationId <= 0) {
                return
            }

            if (Number(this.activeConversationId) === conversationId) {
                return
            }

            let targetConversation = this.conversations.find((conversation) => Number(conversation.id) === conversationId) || null

            if (!targetConversation) {
                try {
                    await this.loadConversations({silent: true})
                    targetConversation = this.conversations.find((conversation) => Number(conversation.id) === conversationId) || null
                } catch (_error) {
                    targetConversation = null
                }
            }

            if (targetConversation) {
                await this.openConversation(targetConversation, {
                    silentSync: true,
                })
            }
        },

        async refreshConversationMoodStatus(conversationId, options = {}) {
            const normalizedConversationId = Number(conversationId)
            if (!Number.isFinite(normalizedConversationId) || normalizedConversationId <= 0) {
                return
            }

            if (this.moodStatusSyncInFlight[normalizedConversationId]) {
                return
            }

            this.moodStatusSyncInFlight = {
                ...this.moodStatusSyncInFlight,
                [normalizedConversationId]: true,
            }

            try {
                const response = await axios.get(`/api/chats/${normalizedConversationId}`)
                const normalizedConversation = this.normalizeConversation(response?.data?.data)
                if (!normalizedConversation) {
                    return
                }

                let hasConversation = false
                this.conversations = this.conversations.map((conversation) => {
                    if (Number(conversation?.id ?? 0) !== normalizedConversationId) {
                        return conversation
                    }

                    hasConversation = true
                    return normalizedConversation
                })

                if (!hasConversation) {
                    this.conversations.push(normalizedConversation)
                }

                this.conversations = [...this.conversations].sort((first, second) => {
                    return new Date(second?.updated_at || 0).getTime() - new Date(first?.updated_at || 0).getTime()
                })

                if (Number(this.activeConversationId || 0) === normalizedConversationId && Boolean(options?.syncMoodForm)) {
                    this.syncMoodStatusFormFromActiveConversation()
                }
            } catch (_error) {
                // Realtime mood sync should stay best-effort.
            } finally {
                const next = { ...this.moodStatusSyncInFlight }
                delete next[normalizedConversationId]
                this.moodStatusSyncInFlight = next
            }
        },

        async handleMoodStatusRealtimeEvent(conversationId, payload = {}) {
            const normalizedConversationId = Number(payload?.conversation_id ?? conversationId ?? 0)
            if (!Number.isFinite(normalizedConversationId) || normalizedConversationId <= 0) {
                return
            }

            await this.refreshConversationMoodStatus(normalizedConversationId, {
                syncMoodForm: true,
            })
        },

        handleViewportResize() {
            if (typeof window === 'undefined') {
                return
            }

            this.viewportWidth = window.innerWidth
            this.refreshFloatingPosition()
            this.scheduleFloatingPositionRecheck()
        },

        scheduleFloatingPositionRecheck() {
            if (typeof window === 'undefined') {
                return
            }

            this.clearFloatingPositionRecheckTimer()
            this.floatingRecheckTimerId = window.setTimeout(() => {
                this.floatingRecheckTimerId = null
                this.refreshFloatingPosition()
            }, 280)
        },

        clearFloatingPositionRecheckTimer() {
            if (typeof window === 'undefined') {
                return
            }

            if (this.floatingRecheckTimerId) {
                window.clearTimeout(this.floatingRecheckTimerId)
                this.floatingRecheckTimerId = null
            }
        },

        getWidgetSize() {
            const element = this.$refs.widgetRoot
            if (!(element instanceof HTMLElement)) {
                return {
                    width: 340,
                    height: 430,
                }
            }

            return {
                width: Math.max(1, Number(element.offsetWidth || 0)),
                height: Math.max(1, Number(element.offsetHeight || 0)),
            }
        },

        clampFloatingPosition(position) {
            if (typeof window === 'undefined') {
                return {
                    left: 0,
                    top: 0,
                }
            }

            const size = this.getWidgetSize()
            const minLeft = WIDGET_EDGE_GAP
            const minTop = WIDGET_EDGE_GAP
            const maxLeft = Math.max(minLeft, window.innerWidth - size.width - WIDGET_EDGE_GAP)
            const maxTop = Math.max(minTop, window.innerHeight - size.height - WIDGET_EDGE_GAP)

            const nextLeft = Math.min(maxLeft, Math.max(minLeft, Number(position?.left || 0)))
            const nextTop = Math.min(maxTop, Math.max(minTop, Number(position?.top || 0)))

            return {
                left: nextLeft,
                top: nextTop,
            }
        },

        getDefaultFloatingPosition() {
            if (typeof window === 'undefined') {
                return {
                    left: 0,
                    top: 0,
                }
            }

            const size = this.getWidgetSize()
            const centeredTop = Math.max(0, Math.round((window.innerHeight - size.height) / 2))
            const rightSideLeft = window.innerWidth - size.width - WIDGET_EDGE_GAP

            return this.clampFloatingPosition({
                left: rightSideLeft,
                top: centeredTop,
            })
        },

        refreshFloatingPosition(options = {}) {
            if (!this.isMovableMode) {
                this.isFloatingReady = false
                return
            }

            const forceDefault = Boolean(options?.forceDefault)
            this.$nextTick(() => {
                const currentLeft = Number(this.floatingPosition?.left)
                const currentTop = Number(this.floatingPosition?.top)
                const hasStoredPosition = Number.isFinite(currentLeft) && Number.isFinite(currentTop) && currentLeft > 0 && currentTop > 0
                const useDefault = forceDefault || this.isPinned || !hasStoredPosition
                const basePosition = useDefault ? this.getDefaultFloatingPosition() : this.floatingPosition
                this.floatingPosition = this.clampFloatingPosition(basePosition)
                this.isFloatingReady = true
            })
        },

        togglePin() {
            this.isPinned = !this.isPinned
            this.persistWidgetState()
        },

        startDrag(event) {
            if (!this.isMovableMode || this.isPinned) {
                return
            }

            if (event?.pointerType === 'mouse' && event.button !== 0) {
                return
            }

            const target = event?.target
            if (target instanceof HTMLElement && target.closest('button')) {
                return
            }

            const startPosition = this.isFloatingReady
                ? this.floatingPosition
                : this.getDefaultFloatingPosition()

            this.floatingPosition = startPosition
            this.isFloatingReady = true
            this.isDragging = true
            this.dragState = {
                pointerId: Number(event?.pointerId || 0),
                startClientX: Number(event?.clientX || 0),
                startClientY: Number(event?.clientY || 0),
                startLeft: startPosition.left,
                startTop: startPosition.top,
            }

            window.addEventListener('pointermove', this.onDragMove)
            window.addEventListener('pointerup', this.stopDrag)
            window.addEventListener('pointercancel', this.stopDrag)
            event.preventDefault()
        },

        onDragMove(event) {
            if (!this.isDragging || !this.dragState) {
                return
            }

            if (this.dragState.pointerId > 0 && Number(event?.pointerId || 0) > 0 && this.dragState.pointerId !== Number(event.pointerId)) {
                return
            }

            const nextLeft = this.dragState.startLeft + (Number(event?.clientX || 0) - this.dragState.startClientX)
            const nextTop = this.dragState.startTop + (Number(event?.clientY || 0) - this.dragState.startClientY)

            this.floatingPosition = this.clampFloatingPosition({
                left: nextLeft,
                top: nextTop,
            })
        },

        stopDrag(event) {
            if (!this.isDragging) {
                return
            }

            if (this.dragState?.pointerId > 0 && Number(event?.pointerId || 0) > 0 && this.dragState.pointerId !== Number(event.pointerId)) {
                return
            }

            this.stopDragging()
        },

        stopDragging() {
            this.isDragging = false
            this.dragState = null

            if (typeof window !== 'undefined') {
                window.removeEventListener('pointermove', this.onDragMove)
                window.removeEventListener('pointerup', this.stopDrag)
                window.removeEventListener('pointercancel', this.stopDrag)
            }

            this.persistWidgetState()
        },

        async initializeWidgetState() {
            if (!this.active || this.isInitializing) {
                return
            }

            this.isInitializing = true
            this.loadError = ''
            this.usersError = ''

            try {
                this.loadWidgetState()
                this.expanded = true
                await Promise.all([
                    this.loadConversations(),
                    this.loadUsers({silent: true}),
                ])

                if (this.conversations.length > 0) {
                    const savedConversation = this.conversations.find((conversation) => {
                        return Number(conversation.id) === Number(this.activeConversationId)
                    })

                    const nextConversation = savedConversation || this.conversations[0]
                    if (nextConversation) {
                        await this.openConversation(nextConversation, {
                            silentSync: true,
                        })
                    }
                }

                this.startConversationPolling()
                this.syncConversationSubscriptions()
                this.refreshFloatingPosition({forceDefault: !this.isPinned})
            } catch (_error) {
                // State initialization should remain resilient.
            } finally {
                this.isInitializing = false
            }
        },

        teardownWidgetState() {
            this.clearFloatingPositionRecheckTimer()
            this.notifyTypingStopped(this.activeConversationId)
            this.stopVoiceRecording(true)
            this.stopVideoRecording(true)
            this.stopVideoPreview()
            this.stopDragging()
            this.stopConversationPolling()
            this.stopMessagePolling()
            this.unsubscribeAllChannels()
            this.clearIncomingNotice()
            this.clearSelectedFiles()
            this.showStickerTray = false
            this.deletingMessageIds = []
            this.deletingAttachmentKeys = []
            this.selectedMessageIds = []
            this.isBulkDeletingMessages = false
            this.openMessageReactionPickerId = null
            this.togglingMessageReactionKeys = []
            this.showMoodStatusSettings = false
            this.moodStatusForm = {
                text: '',
                is_visible_to_all: true,
                hidden_user_ids: [],
            }

            if (this.userSearchDebounceTimerId) {
                window.clearTimeout(this.userSearchDebounceTimerId)
                this.userSearchDebounceTimerId = null
            }

            this.persistWidgetState()

            this.$emit('unread-updated', 0)
        },

        loadSharedSyncConversationId() {
            if (typeof localStorage === 'undefined') {
                return null
            }

            try {
                const raw = localStorage.getItem(this.sharedSyncStorageKey)
                if (!raw) {
                    return null
                }

                const parsed = JSON.parse(raw)
                const conversationId = Number(parsed?.activeConversationId ?? 0)
                return Number.isFinite(conversationId) && conversationId > 0
                    ? conversationId
                    : null
            } catch (_error) {
                return null
            }
        },

        persistSharedSyncConversationId(conversationId) {
            if (typeof localStorage === 'undefined') {
                return
            }

            const targetId = Number(conversationId)
            const normalizedConversationId = Number.isFinite(targetId) && targetId > 0
                ? targetId
                : null

            try {
                localStorage.setItem(this.sharedSyncStorageKey, JSON.stringify({
                    activeConversationId: normalizedConversationId,
                    updatedAt: Date.now(),
                }))
            } catch (_error) {
                // Ignore write issues.
            }
        },

        loadWidgetState() {
            if (typeof localStorage === 'undefined') {
                return
            }

            try {
                const raw = localStorage.getItem(this.storageKey)
                if (!raw) {
                    return
                }

                const parsed = JSON.parse(raw)
                this.expanded = parsed?.expanded !== false
                this.leftPaneMode = parsed?.leftPaneMode === 'users' ? 'users' : 'conversations'
                this.conversationSearch = typeof parsed?.conversationSearch === 'string'
                    ? parsed.conversationSearch
                    : ''
                this.userSearch = typeof parsed?.userSearch === 'string'
                    ? parsed.userSearch
                    : ''

                const conversationId = Number(parsed?.activeConversationId)
                this.activeConversationId = Number.isFinite(conversationId) && conversationId > 0
                    ? conversationId
                    : null

                this.isPinned = parsed?.isPinned !== false
                const storedLeft = Number(parsed?.floatingPosition?.left)
                const storedTop = Number(parsed?.floatingPosition?.top)
                if (Number.isFinite(storedLeft) && Number.isFinite(storedTop)) {
                    this.floatingPosition = {
                        left: storedLeft,
                        top: storedTop,
                    }
                }
            } catch (_error) {
                this.expanded = true
                this.leftPaneMode = 'conversations'
                this.conversationSearch = ''
                this.userSearch = ''
                this.activeConversationId = null
                this.isPinned = true
                this.floatingPosition = {
                    left: 0,
                    top: 0,
                }
            }

            const sharedConversationId = this.loadSharedSyncConversationId()
            if (sharedConversationId !== null) {
                this.activeConversationId = sharedConversationId
            }
        },

        persistWidgetState() {
            if (typeof localStorage === 'undefined') {
                return
            }

            try {
                localStorage.setItem(this.storageKey, JSON.stringify({
                    expanded: this.expanded,
                    leftPaneMode: this.leftPaneMode,
                    conversationSearch: this.conversationSearch,
                    userSearch: this.userSearch,
                    activeConversationId: this.activeConversationId,
                    isPinned: this.isPinned,
                    floatingPosition: this.floatingPosition,
                }))
            } catch (_error) {
                // Ignore write issues.
            }
        },

        normalizeMoodStatus(status) {
            if (!status || typeof status !== 'object') {
                return null
            }

            const userId = Number(status?.user_id ?? status?.user?.id ?? 0)
            if (!Number.isFinite(userId) || userId <= 0) {
                return null
            }

            const text = String(status?.text || '').trim()
            if (text === '') {
                return null
            }

            const visibility = status?.visibility && typeof status.visibility === 'object'
                ? status.visibility
                : {}

            return {
                ...status,
                user_id: userId,
                text,
                is_owner: Boolean(status?.is_owner),
                visibility: {
                    is_visible_to_all: visibility?.is_visible_to_all !== false,
                    hidden_user_ids: Array.isArray(visibility?.hidden_user_ids)
                        ? [...new Set(visibility.hidden_user_ids
                            .map((value) => Number(value))
                            .filter((value) => Number.isFinite(value) && value > 0))]
                        : [],
                },
            }
        },

        normalizeConversation(conversation) {
            if (!conversation || typeof conversation !== 'object') {
                return null
            }

            const id = Number(conversation.id || 0)
            if (!Number.isFinite(id) || id <= 0) {
                return null
            }
            const normalizedMoodStatuses = Array.isArray(conversation?.mood_statuses)
                ? conversation.mood_statuses
                    .map((status) => this.normalizeMoodStatus(status))
                    .filter((status) => status !== null)
                : []

            return {
                ...conversation,
                id,
                unread_count: Math.max(0, Number(conversation.unread_count || 0)),
                mood_statuses: normalizedMoodStatuses,
            }
        },

        syncMoodStatusFormFromActiveConversation() {
            const ownStatus = this.myMoodStatus
            const hiddenUserIds = Array.isArray(ownStatus?.visibility?.hidden_user_ids)
                ? ownStatus.visibility.hidden_user_ids
                : []

            this.moodStatusForm.is_visible_to_all = ownStatus?.visibility?.is_visible_to_all !== false
            this.moodStatusForm.hidden_user_ids = this.normalizeMoodStatusHiddenUserIds(hiddenUserIds)

            if (this.suppressMoodStatusFormSyncOnce) {
                this.suppressMoodStatusFormSyncOnce = false
                this.moodStatusForm.text = ''
                return
            }

            this.moodStatusForm.text = ownStatus?.text || ''
        },

        normalizeMoodStatusHiddenUserIds(userIds) {
            const allowedIds = new Set(this.moodStatusVisibilityCandidates.map((participant) => Number(participant.id)))

            return [...new Set((Array.isArray(userIds) ? userIds : [])
                .map((value) => Number(value))
                .filter((value) => Number.isFinite(value) && value > 0)
                .filter((value) => allowedIds.has(value)))]
        },

        resetMoodStatusForm() {
            this.suppressMoodStatusFormSyncOnce = false
            this.syncMoodStatusFormFromActiveConversation()
        },

        async saveMoodStatus() {
            if (!this.activeConversation || this.isSavingMoodStatus) {
                return
            }

            this.isSavingMoodStatus = true

            try {
                const hiddenUserIds = this.moodStatusForm.is_visible_to_all
                    ? []
                    : this.normalizeMoodStatusHiddenUserIds(this.moodStatusForm.hidden_user_ids)

                const payload = {
                    text: String(this.moodStatusForm.text || '').trim(),
                    is_visible_to_all: Boolean(this.moodStatusForm.is_visible_to_all),
                    hidden_user_ids: hiddenUserIds,
                }

                const response = await axios.patch(`/api/chats/${this.activeConversation.id}/mood-status`, payload)
                const normalizedConversation = this.normalizeConversation(response?.data?.data?.conversation)
                if (normalizedConversation) {
                    const targetId = Number(normalizedConversation.id)
                    this.conversations = this.conversations.map((conversation) => {
                        return Number(conversation?.id ?? 0) === targetId
                            ? normalizedConversation
                            : conversation
                    })

                    if (Number(this.activeConversationId) === targetId) {
                        this.activeConversationId = targetId
                    }
                }

                this.suppressMoodStatusFormSyncOnce = true
                this.moodStatusForm.text = ''
                this.showMoodStatusSettings = false
                this.notifyChatSyncStateRefresh({
                    conversationId: this.activeConversation?.id ?? null,
                })
            } catch (error) {
                this.sendError = this.resolveApiMessage(error, this.$t('chats.saveMoodStatusFailed'))
            } finally {
                this.isSavingMoodStatus = false
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
                .filter((reaction) => reaction !== null)

            normalized.sort((first, second) => {
                const countDelta = Number(second.count) - Number(first.count)
                if (countDelta !== 0) {
                    return countDelta
                }

                return String(first.emoji).localeCompare(String(second.emoji))
            })

            return normalized
        },

        normalizeMessage(message) {
            if (!message || typeof message !== 'object') {
                return null
            }

            const id = Number(message.id || 0)
            if (!Number.isFinite(id) || id <= 0) {
                return null
            }

            const attachments = Array.isArray(message.attachments) ? message.attachments : []

            return {
                ...message,
                id,
                conversation_id: Number(message.conversation_id || 0),
                body: String(message.body || ''),
                reactions: this.normalizeMessageReactions(message.reactions),
                attachments: attachments.map((attachment) => ({
                    ...attachment,
                    download_url: attachment?.download_url || attachment?.url || null,
                })),
            }
        },

        messagePreview(conversation) {
            const lastMessage = conversation?.last_message
            if (!lastMessage) {
                return this.$t('chats.noMessagesYet')
            }

            const body = String(lastMessage?.body || '').trim()
            const attachmentsCount = Array.isArray(lastMessage?.attachments)
                ? lastMessage.attachments.length
                : 0

            const messageText = body !== ''
                ? stickerTextToPreview(body)
                : (attachmentsCount > 0 ? this.$t('chats.attachmentsCount', {count: attachmentsCount}) : this.$t('chats.noMessagesYet'))

            const author = this.isMine(lastMessage)
                ? this.$t('chats.youShort')
                : this.displayName(lastMessage?.user)

            return `${author}: ${messageText}`
        },

        conversationPeer(conversation) {
            if (!conversation || !Array.isArray(conversation.participants) || conversation.participants.length === 0) {
                return null
            }

            if (String(conversation?.type || '') !== 'direct') {
                return conversation.participants[0] ?? null
            }

            const myId = Number(this.user?.id ?? 0)
            return conversation.participants.find((participant) => Number(participant?.id ?? 0) !== myId)
                ?? conversation.participants[0]
                ?? null
        },

        conversationPeerMoodStatus(conversation) {
            if (!conversation || String(conversation?.type || '') !== 'direct') {
                return ''
            }

            const peerId = Number(this.conversationPeer(conversation)?.id ?? 0)
            if (!Number.isFinite(peerId) || peerId <= 0) {
                return ''
            }

            const directStatus = this.conversationUserMoodStatus(conversation, peerId)
            if (directStatus !== '') {
                return directStatus
            }

            return this.resolveUserMoodStatus(peerId, {
                excludeConversationId: Number(conversation?.id ?? 0),
            })
        },

        normalizeStatusText(text) {
            return String(text || '')
                .replace(/\s+/g, ' ')
                .trim()
        },

        truncateStatusText(text, maxLength = 72) {
            const normalized = this.normalizeStatusText(text)
            if (normalized === '') {
                return ''
            }

            if (normalized.length <= maxLength) {
                return normalized
            }

            return `${normalized.slice(0, Math.max(1, maxLength - 1)).trimEnd()}…`
        },

        stripMessagePreviewAuthorPrefix(previewText) {
            const text = this.normalizeStatusText(previewText)
            const separatorIndex = text.indexOf(':')
            if (separatorIndex <= 0) {
                return text
            }

            return this.normalizeStatusText(text.slice(separatorIndex + 1))
        },

        isStatusDuplicateWithPreview(statusText, previewText) {
            const status = this.normalizeStatusText(statusText).toLowerCase()
            const preview = this.stripMessagePreviewAuthorPrefix(previewText).toLowerCase()
            if (status === '' || preview === '') {
                return false
            }

            return status === preview || preview.includes(status)
        },

        conversationPeerMoodStatusLabel(conversation) {
            const statusText = this.conversationPeerMoodStatus(conversation)
            if (statusText === '') {
                return ''
            }

            if (this.isStatusDuplicateWithPreview(statusText, this.messagePreview(conversation))) {
                return ''
            }

            return this.truncateStatusText(statusText, 68)
        },

        userMoodStatus(user) {
            const userId = Number(user?.id ?? 0)
            if (!Number.isFinite(userId) || userId <= 0) {
                return ''
            }

            return this.resolveUserMoodStatus(userId)
        },

        userMoodStatusLabel(user) {
            return this.truncateStatusText(this.userMoodStatus(user), 68)
        },

        conversationUserMoodStatus(conversation, userId) {
            const normalizedUserId = Number(userId ?? 0)
            if (!Number.isFinite(normalizedUserId) || normalizedUserId <= 0) {
                return ''
            }

            const moodStatuses = Array.isArray(conversation?.mood_statuses)
                ? conversation.mood_statuses
                : []

            const peerStatus = moodStatuses
                .map((status) => this.normalizeMoodStatus(status))
                .filter((status) => status !== null)
                .find((status) => Number(status.user_id) === normalizedUserId)

            return String(peerStatus?.text || '').trim()
        },

        resolveUserMoodStatus(userId, options = {}) {
            const normalizedUserId = Number(userId ?? 0)
            if (!Number.isFinite(normalizedUserId) || normalizedUserId <= 0) {
                return ''
            }

            const excludeConversationId = Number(options?.excludeConversationId ?? 0)
            const candidates = []

            const directConversation = this.conversations.find((conversation) => {
                if (String(conversation?.type || '') !== 'direct') {
                    return false
                }

                if (excludeConversationId > 0 && Number(conversation?.id ?? 0) === excludeConversationId) {
                    return false
                }

                const peerId = Number(this.conversationPeer(conversation)?.id ?? 0)
                return peerId === normalizedUserId
            })

            if (directConversation) {
                candidates.push(directConversation)
            }

            const globalConversation = this.conversations.find((conversation) => String(conversation?.type || '') === 'global')
            if (globalConversation) {
                candidates.push(globalConversation)
            }

            for (const conversation of this.conversations) {
                const conversationId = Number(conversation?.id ?? 0)
                if (!Number.isFinite(conversationId) || conversationId <= 0) {
                    continue
                }

                if (excludeConversationId > 0 && conversationId === excludeConversationId) {
                    continue
                }

                if (candidates.some((item) => Number(item?.id ?? 0) === conversationId)) {
                    continue
                }

                candidates.push(conversation)
            }

            for (const conversation of candidates) {
                const statusText = this.conversationUserMoodStatus(conversation, normalizedUserId)
                if (statusText !== '') {
                    return statusText
                }
            }

            return ''
        },

        messageAuthorLabel(message) {
            return this.isMine(message)
                ? this.$t('chats.youShort')
                : this.displayName(message?.user)
        },

        messageAuthorInitial(message) {
            if (this.isMine(message)) {
                return this.userInitial(this.user || message?.user || {})
            }

            const source = this.displayName(message?.user).trim()
            return source ? source.slice(0, 1).toUpperCase() : 'U'
        },

        messageAvatarUrl(message) {
            const senderAvatar = this.avatarUrl(message?.user || null)
            if (senderAvatar) {
                return senderAvatar
            }

            if (this.isMine(message)) {
                const ownAvatar = this.avatarUrl(this.user)
                if (ownAvatar) {
                    return ownAvatar
                }
            }

            return ''
        },

        messageText(message) {
            const body = String(message?.body || '').trim()
            if (body !== '') {
                return body
            }

            const attachmentsCount = Array.isArray(message?.attachments)
                ? message.attachments.length
                : 0

            if (attachmentsCount > 0) {
                return this.$t('chats.attachmentsCount', {count: attachmentsCount})
            }

            return this.$t('chats.message')
        },

        hasMessageBody(message) {
            return String(message?.body || '').trim() !== ''
        },

        attachmentKey(message, attachment, index) {
            const messageId = Number(message?.id || 0)
            const attachmentId = Number(attachment?.id || 0)
            const fallbackUrl = String(attachment?.url || attachment?.download_url || '').trim()
            const fallbackName = String(attachment?.original_name || '').trim()

            if (attachmentId > 0) {
                return `widget-chat-message-${messageId}-attachment-${attachmentId}`
            }

            if (fallbackUrl !== '') {
                return `widget-chat-message-${messageId}-attachment-url-${fallbackUrl}`
            }

            if (fallbackName !== '') {
                return `widget-chat-message-${messageId}-attachment-name-${fallbackName}`
            }

            return `widget-chat-message-${messageId}-attachment-${index}`
        },

        isVideoAttachment(attachment) {
            return String(attachment?.type || '').toLowerCase() === 'video'
        },

        isAudioAttachment(attachment) {
            return String(attachment?.type || '').toLowerCase() === 'audio'
        },

        conversationTime(conversation) {
            if (conversation?.last_message?.date) {
                return conversation.last_message.date
            }

            return ''
        },

        formatUnreadBadge(value) {
            const unread = Number(value || 0)
            if (!Number.isFinite(unread) || unread <= 0) {
                return ''
            }

            return unread > 99 ? '99+' : String(unread)
        },

        messageReactionKey(messageId, emoji) {
            return `${Number(messageId)}:${String(emoji)}`
        },

        isMessageReactionToggling(messageId, emoji) {
            return this.togglingMessageReactionKeys.includes(this.messageReactionKey(messageId, emoji))
        },

        isMessageReactionPickerOpen(messageId) {
            return Number(this.openMessageReactionPickerId) === Number(messageId)
        },

        toggleMessageReactionPicker(messageId) {
            const normalizedId = Number(messageId)
            if (!Number.isFinite(normalizedId) || normalizedId <= 0) {
                this.openMessageReactionPickerId = null
                return
            }

            this.openMessageReactionPickerId = this.isMessageReactionPickerOpen(normalizedId)
                ? null
                : normalizedId
        },

        hasMessageReactionFromMe(message, emoji) {
            if (!Array.isArray(message?.reactions)) {
                return false
            }

            return message.reactions.some((reaction) => reaction.emoji === emoji && Boolean(reaction.reacted_by_me))
        },

        async toggleMessageReaction(message, emoji) {
            if (!this.activeConversation || !message?.id || !this.user) {
                return
            }

            const key = this.messageReactionKey(message.id, emoji)
            if (this.togglingMessageReactionKeys.includes(key)) {
                return
            }

            this.openMessageReactionPickerId = null
            this.togglingMessageReactionKeys = [...this.togglingMessageReactionKeys, key]

            try {
                const response = await axios.post(
                    `/api/chats/${this.activeConversation.id}/messages/${message.id}/reactions`,
                    { emoji }
                )

                const updatedMessage = response?.data?.data?.message
                if (updatedMessage) {
                    const normalizedUpdatedMessage = this.normalizeMessage(updatedMessage)
                    if (normalizedUpdatedMessage) {
                        this.upsertMessage(normalizedUpdatedMessage)
                        this.updateConversationFromMessage(normalizedUpdatedMessage, {
                            incrementUnread: false,
                        })
                        this.notifyChatSyncMessage(normalizedUpdatedMessage, {
                            markRead: false,
                        })
                    }
                }
            } catch (error) {
                alert(this.resolveApiMessage(error, this.$t('chats.updateReactionFailed')))
            } finally {
                this.togglingMessageReactionKeys = this.togglingMessageReactionKeys.filter((item) => item !== key)
            }
        },

        isMine(message) {
            const currentUserId = Number(this.user?.id || 0)
            const senderId = Number(message?.user?.id || 0)

            return currentUserId > 0 && senderId > 0 && currentUserId === senderId
        },

        canDeleteMessage(message) {
            if (!this.user || !message?.id) {
                return false
            }

            if (Boolean(this.user?.is_admin)) {
                return true
            }

            const currentUserId = Number(this.user?.id || 0)
            const senderId = Number(message?.user?.id || 0)

            return currentUserId > 0 && senderId > 0 && currentUserId === senderId
        },

        isMessageSelected(messageId) {
            return this.selectedMessageIds.includes(Number(messageId))
        },

        toggleMessageSelection(message) {
            const messageId = Number(message?.id ?? 0)
            if (!Number.isFinite(messageId) || messageId <= 0) {
                return
            }

            if (!this.canDeleteMessage(message)) {
                return
            }

            if (this.isMessageSelected(messageId)) {
                this.selectedMessageIds = this.selectedMessageIds.filter((id) => id !== messageId)
                return
            }

            this.selectedMessageIds = [...this.selectedMessageIds, messageId]
        },

        clearSelectedMessages() {
            this.selectedMessageIds = []
        },

        toggleAllMessageSelection() {
            const availableIds = this.deletableMessageIds
            if (availableIds.length === 0) {
                this.selectedMessageIds = []
                return
            }

            if (this.allSelectableMessagesSelected) {
                const availableSet = new Set(availableIds.map((id) => Number(id)))
                this.selectedMessageIds = this.selectedMessageIds.filter((id) => !availableSet.has(Number(id)))
                return
            }

            const merged = new Set(this.selectedMessageIds.map((id) => Number(id)))
            for (const id of availableIds) {
                merged.add(Number(id))
            }
            this.selectedMessageIds = [...merged]
        },

        async deleteSelectedMessages() {
            if (!this.activeConversation || this.isBulkDeletingMessages) {
                return
            }

            const messageIds = this.selectedDeletableMessageIds
                .map((id) => Number(id))
                .filter((id) => Number.isFinite(id) && id > 0)

            if (messageIds.length === 0) {
                return
            }

            const confirmed = window.confirm(this.$t('chats.bulkDeleteSelectedConfirm', { count: messageIds.length }))
            if (!confirmed) {
                return
            }

            const deletingNow = new Set(this.deletingMessageIds.map((id) => Number(id)))
            for (const id of messageIds) {
                deletingNow.add(id)
            }
            this.deletingMessageIds = [...deletingNow]
            this.isBulkDeletingMessages = true

            try {
                const results = await Promise.allSettled(messageIds.map((id) => {
                    return axios.delete(`/api/chats/${this.activeConversation.id}/messages/${id}`)
                }))

                const deletedIds = []
                const failedIds = []

                for (let index = 0; index < results.length; index += 1) {
                    const result = results[index]
                    const id = messageIds[index]
                    if (result.status === 'fulfilled') {
                        deletedIds.push(id)
                    } else {
                        failedIds.push(id)
                    }
                }

                for (const id of deletedIds) {
                    this.removeMessageLocally(id)
                }

                if (deletedIds.length > 0) {
                    await this.loadConversations({silent: true})
                    this.notifyChatSyncStateRefresh({
                        conversationId: this.activeConversation?.id ?? null,
                    })
                }

                if (failedIds.length > 0) {
                    alert(this.$t('chats.bulkDeleteSelectedFailed'))
                }
            } finally {
                const processedSet = new Set(messageIds.map((id) => Number(id)))
                this.deletingMessageIds = this.deletingMessageIds.filter((id) => !processedSet.has(Number(id)))
                this.isBulkDeletingMessages = false
            }
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
            if (!Number.isFinite(messageId) || this.isMessageDeleting(messageId) || this.isBulkDeletingMessages) {
                return
            }

            const selectedIds = this.selectedDeletableMessageIds
                .map((id) => Number(id))
                .filter((id) => Number.isFinite(id) && id > 0)
            const bulkSelectedCount = selectedIds.length
            if (bulkSelectedCount > 0 && this.isMessageSelected(messageId)) {
                await this.deleteSelectedMessages()
                return
            }

            const shouldDelete = window.confirm(this.$t('chats.confirmDeleteMessage'))
            if (!shouldDelete) {
                return
            }

            this.deletingMessageIds = [...this.deletingMessageIds, messageId]

            try {
                await axios.delete(`/api/chats/${this.activeConversation.id}/messages/${messageId}`)
                this.removeMessageLocally(messageId)
                this.selectedMessageIds = this.selectedMessageIds.filter((id) => id !== messageId)
                await this.loadConversations({silent: true})
                this.notifyChatSyncStateRefresh({
                    conversationId: this.activeConversation?.id ?? null,
                })
            } catch (error) {
                alert(this.resolveApiMessage(error, this.$t('chats.deleteMessageFailed')))
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

            const shouldDelete = window.confirm(this.$t('chats.confirmDeleteAttachment'))
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

                await this.loadConversations({silent: true})
                this.notifyChatSyncStateRefresh({
                    conversationId: this.activeConversation?.id ?? null,
                })
            } catch (error) {
                alert(this.resolveApiMessage(error, this.$t('chats.deleteAttachmentFailed')))
            } finally {
                this.deletingAttachmentKeys = this.deletingAttachmentKeys.filter((item) => item !== key)
            }
        },

        removeMessageLocally(messageId) {
            const normalizedId = Number(messageId)
            this.messages = this.messages.filter((item) => Number(item.id) !== normalizedId)
            this.selectedMessageIds = this.selectedMessageIds.filter((id) => Number(id) !== normalizedId)
        },

        removeAttachmentLocally(messageId, attachmentId) {
            const messageItem = this.messages.find((item) => Number(item.id) === Number(messageId))
            if (!messageItem || !Array.isArray(messageItem.attachments)) {
                return
            }

            messageItem.attachments = messageItem.attachments.filter((item) => Number(item.id) !== Number(attachmentId))
        },

        async loadConversations(options = {}) {
            const silent = Boolean(options?.silent)

            if (!silent) {
                this.isLoadingConversations = true
            }

            try {
                const response = await axios.get('/api/chats')
                const source = Array.isArray(response?.data?.data) ? response.data.data : []
                this.conversations = source
                    .map((conversation) => this.normalizeConversation(conversation))
                    .filter((conversation) => conversation !== null)

                const activeConversationId = Number(this.activeConversationId || 0)
                if (activeConversationId > 0 && !this.conversations.some((conversation) => Number(conversation.id) === activeConversationId)) {
                    this.clearConversationTypingState(activeConversationId)
                    this.activeConversationId = null
                    this.messages = []
                    this.selectedMessageIds = []
                    this.isBulkDeletingMessages = false
                }
                this.syncMoodStatusFormFromActiveConversation()

                this.loadError = ''
                this.syncConversationSubscriptions()
            } catch (error) {
                if (!silent) {
                    this.loadError = error?.response?.data?.message || this.$t('chats.loadConversationsFailed')
                }
            } finally {
                if (!silent) {
                    this.isLoadingConversations = false
                }
            }
        },

        async openConversation(conversation, options = {}) {
            const conversationId = Number(conversation?.id ?? 0)
            if (!Number.isFinite(conversationId) || conversationId <= 0) {
                return
            }

            const previousConversationId = Number(this.activeConversationId || 0)
            const silentSync = Boolean(options?.silentSync)

            if (previousConversationId > 0 && previousConversationId !== conversationId) {
                this.notifyTypingStopped(previousConversationId)
            }

            this.leftPaneMode = 'conversations'
            this.activeConversationId = conversationId
            this.sendError = ''
            this.loadError = ''
            if (previousConversationId !== conversationId) {
                this.clearConversationTypingState(previousConversationId)
                this.stopVoiceRecording(true)
                this.stopVideoRecording(true)
                this.stopVideoPreview()
                this.messageBody = ''
                this.clearSelectedFiles()
                this.showStickerTray = false
                this.deletingMessageIds = []
                this.deletingAttachmentKeys = []
                this.selectedMessageIds = []
                this.isBulkDeletingMessages = false
                this.openMessageReactionPickerId = null
            }
            this.showMoodStatusSettings = false
            this.syncMoodStatusFormFromActiveConversation()
            await this.loadMessages(conversationId)
            await this.markConversationRead(conversationId, {silentSync})

            if (!silentSync) {
                this.notifyChatSync(CHAT_WIDGET_SYNC_TYPE_ACTIVE_CONVERSATION, conversationId)
            }
        },

        async loadMessages(conversationId, options = {}) {
            const silent = Boolean(options?.silent)

            if (!silent) {
                this.isLoadingMessages = true
            }

            try {
                const response = await axios.get(`/api/chats/${conversationId}/messages`, {
                    params: {
                        per_page: 60,
                    },
                })

                const source = Array.isArray(response?.data?.data) ? response.data.data : []
                const normalizedMessages = source
                    .map((message) => this.normalizeMessage(message))
                    .filter((message) => message !== null)

                this.messages = normalizedMessages
                this.deletingMessageIds = []
                this.deletingAttachmentKeys = []
                this.selectedMessageIds = []
                this.isBulkDeletingMessages = false
                this.openMessageReactionPickerId = null
                if (!silent) {
                    await this.scrollMessagesDown()
                }
            } catch (_error) {
                if (!silent) {
                    this.messages = []
                    this.deletingMessageIds = []
                    this.deletingAttachmentKeys = []
                    this.selectedMessageIds = []
                    this.isBulkDeletingMessages = false
                    this.openMessageReactionPickerId = null
                    this.loadError = this.$t('chats.loadMessagesFailed')
                }
            } finally {
                if (!silent) {
                    this.isLoadingMessages = false
                }
            }
        },

        async markConversationRead(conversationId, options = {}) {
            const targetId = Number(conversationId)
            if (!Number.isFinite(targetId) || targetId <= 0) {
                return
            }

            const silentSync = Boolean(options?.silentSync)
            try {
                await axios.post(`/api/chats/${targetId}/read`)
            } catch (_error) {
                // Ignore temporary mark-read failures.
            }

            this.setConversationReadLocally(targetId)

            if (!silentSync) {
                this.notifyChatSync(CHAT_WIDGET_SYNC_TYPE_CONVERSATION_READ, targetId)
            }
        },

        setConversationReadLocally(conversationId) {
            const targetId = Number(conversationId)
            if (!Number.isFinite(targetId) || targetId <= 0) {
                return
            }

            this.conversations = this.conversations.map((conversation) => {
                if (Number(conversation.id) !== targetId) {
                    return conversation
                }

                return {
                    ...conversation,
                    unread_count: 0,
                    has_unread: false,
                }
            })
        },

        appendEmoji(emoji) {
            if (this.composerDisabled) {
                return
            }

            this.messageBody = `${this.messageBody}${emoji}`
            this.notifyTypingActivity({ immediate: true })
        },

        handleComposerInput() {
            const normalizedBody = this.normalizeStickerAliases(this.messageBody)
            if (normalizedBody !== this.messageBody) {
                this.messageBody = normalizedBody
            }

            this.notifyTypingActivity()
        },

        normalizeStickerAliases(text) {
            return replaceStickerTokensWithMarkedEmoji(
                String(text || '')
                    .replace(/\[sticker:file\]/gi, '[sticker:fire]')
            )
        },

        normalizeStickerTransport(text) {
            return replaceMarkedEmojiWithStickerTokens(this.normalizeStickerAliases(text))
        },

        toggleComposerTools() {
            if (!this.activeConversation || this.activeConversation?.is_blocked) {
                this.showComposerTools = false
                this.showStickerTray = false
                return
            }

            this.showComposerTools = !this.showComposerTools
            if (!this.showComposerTools) {
                this.showStickerTray = false
            }
        },

        toggleStickerTray() {
            if (this.composerDisabled) {
                this.showStickerTray = false
                return
            }

            this.showStickerTray = !this.showStickerTray
        },

        insertSticker(sticker) {
            const token = stickerTokenFromId(sticker?.id)
            if (token === '' || this.composerDisabled) {
                return
            }

            const emoji = stickerMarkedEmojiFromId(sticker?.id)
            const suffix = this.messageBody.trim() === '' ? '' : ' '
            this.messageBody = `${this.messageBody}${suffix}${emoji}`
            this.showStickerTray = false
            this.notifyTypingActivity({ immediate: true })
        },

        openFileDialog() {
            if (this.composerDisabled) {
                return
            }

            this.$refs.messageFiles?.click()
        },

        async handleMediaDevicesChanged() {
            await this.loadMediaInputDevices()
        },

        async refreshMediaDeviceOptions(requestAccess = false) {
            await this.loadMediaInputDevices({ requestAccess: requestAccess === true })
        },

        async loadMediaInputDevices(options = {}) {
            const requestAccess = options?.requestAccess === true
            if (typeof navigator === 'undefined'
                || !navigator.mediaDevices
                || typeof navigator.mediaDevices.enumerateDevices !== 'function') {
                this.mediaDeviceError = this.$t('chats.deviceListUnavailable')
                this.audioInputDevices = []
                this.videoInputDevices = []
                this.selectedAudioInputId = ''
                this.selectedVideoInputId = ''
                return
            }

            this.isLoadingMediaDevices = true
            this.mediaDeviceError = ''

            let accessStream = null
            try {
                if (requestAccess && typeof navigator.mediaDevices.getUserMedia === 'function') {
                    const probeConstraints = {}
                    if (this.canRecordVoice) {
                        probeConstraints.audio = true
                    }
                    if (this.canRecordVideo) {
                        probeConstraints.video = true
                    }
                    if (Object.keys(probeConstraints).length > 0) {
                        try {
                            accessStream = await navigator.mediaDevices.getUserMedia(probeConstraints)
                        } catch (_error) {
                            // Labels can remain hidden without permission.
                        }
                    }
                }

                const devices = await navigator.mediaDevices.enumerateDevices()
                const audioInputs = this.normalizeMediaInputDevices(devices, 'audioinput')
                const videoInputs = this.normalizeMediaInputDevices(devices, 'videoinput')

                this.audioInputDevices = audioInputs
                this.videoInputDevices = videoInputs

                const currentAudioId = String(this.selectedAudioInputId || '')
                if (!currentAudioId || !audioInputs.some((device) => device.deviceId === currentAudioId)) {
                    this.selectedAudioInputId = audioInputs[0]?.deviceId || ''
                }

                const currentVideoId = String(this.selectedVideoInputId || '')
                if (!currentVideoId || !videoInputs.some((device) => device.deviceId === currentVideoId)) {
                    this.selectedVideoInputId = videoInputs[0]?.deviceId || ''
                }
            } catch (_error) {
                this.mediaDeviceError = this.$t('chats.deviceListUnavailable')
                this.audioInputDevices = []
                this.videoInputDevices = []
                this.selectedAudioInputId = ''
                this.selectedVideoInputId = ''
            } finally {
                if (accessStream) {
                    for (const track of accessStream.getTracks()) {
                        track.stop()
                    }
                }
                this.isLoadingMediaDevices = false
            }
        },

        normalizeMediaInputDevices(devices, kind) {
            return (Array.isArray(devices) ? devices : [])
                .filter((device) => String(device?.kind || '') === kind)
                .map((device, index) => {
                    const deviceId = String(device?.deviceId || '')
                    const label = this.resolveDeviceOptionLabel(kind, index, device?.label)

                    return {
                        deviceId,
                        label,
                    }
                })
                .filter((device) => device.deviceId !== '')
        },

        resolveDeviceOptionLabel(kind, index, rawLabel) {
            const normalizedLabel = String(rawLabel || '').trim()
            if (normalizedLabel !== '') {
                return normalizedLabel
            }

            if (kind === 'audioinput') {
                return `${this.$t('chats.microphoneInput')} ${index + 1}`
            }

            return `${this.$t('chats.cameraInput')} ${index + 1}`
        },

        onShiftEnter() {
            // This is handled by default behavior (new line),
            // but we can explicitly add logic if needed.
        },

        onSelectedAudioInputChanged() {
            const selected = String(this.selectedAudioInputId || '')
            if (selected === '' || this.audioInputDevices.some((device) => device.deviceId === selected)) {
                return
            }

            this.selectedAudioInputId = ''
        },

        async onSelectedVideoInputChanged() {
            const selected = String(this.selectedVideoInputId || '')
            if (selected !== '' && !this.videoInputDevices.some((device) => device.deviceId === selected)) {
                this.selectedVideoInputId = ''
                return
            }

            if (this.isVideoPreviewActive && !this.isRecordingVideo && !this.isProcessingVideo && !this.videoStopInProgress) {
                await this.startVideoPreview({ forceRestart: true })
            }
        },

        buildVideoPreviewConstraints() {
            const selectedVideoInputId = String(this.selectedVideoInputId || '').trim()

            return {
                video: {
                    ...(selectedVideoInputId !== '' ? { deviceId: { exact: selectedVideoInputId } } : { facingMode: 'user' }),
                    width: { ideal: 960 },
                    height: { ideal: 540 },
                    frameRate: { ideal: 24, max: 30 },
                },
                audio: false,
            }
        },

        async attachVideoPreviewStream(stream) {
            await this.$nextTick()
            const previewElement = this.$refs.videoPreviewElement
            if (!(previewElement instanceof HTMLVideoElement)) {
                return
            }

            previewElement.srcObject = stream
            previewElement.muted = true
            previewElement.playsInline = true

            try {
                await previewElement.play()
            } catch (_error) {
                // Autoplay can be blocked by browser policy.
            }
        },

        async startVideoPreview(options = {}) {
            const forceRestart = options?.forceRestart === true
            if (!this.canRecordVideo
                || this.isRecordingVideo
                || this.isProcessingVideo
                || this.videoStopInProgress
                || this.isRecordingVoice
                || this.isProcessingVoice
                || this.voiceStopInProgress) {
                return
            }

            if (this.isVideoPreviewActive && !forceRestart) {
                return
            }

            this.stopVideoPreview()
            this.isVideoPreviewLoading = true

            let stream = null
            try {
                try {
                    stream = await navigator.mediaDevices.getUserMedia(this.buildVideoPreviewConstraints())
                } catch (_error) {
                    stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                }

                this.videoPreviewStream = stream
                this.isVideoPreviewActive = true
                await this.attachVideoPreviewStream(stream)
                await this.loadMediaInputDevices()
            } catch (_error) {
                if (stream) {
                    this.stopVideoRecordStreamTracks(stream)
                }
                this.videoPreviewStream = null
                this.isVideoPreviewActive = false
                alert(this.$t('chats.cameraAccessFailed'))
            } finally {
                this.isVideoPreviewLoading = false
            }
        },

        stopVideoPreview() {
            const previewElement = this.$refs.videoPreviewElement
            if (previewElement instanceof HTMLVideoElement) {
                previewElement.pause()
                previewElement.srcObject = null
            }

            if (this.videoPreviewStream) {
                this.stopVideoRecordStreamTracks(this.videoPreviewStream)
            }

            this.videoPreviewStream = null
            this.isVideoPreviewActive = false
            this.isVideoPreviewLoading = false
        },

        async toggleVideoPreview() {
            if (this.isVideoPreviewActive || this.isVideoPreviewLoading) {
                this.stopVideoPreview()
                return
            }

            await this.startVideoPreview()
        },

        isVoiceRecordingSupported() {
            return typeof window !== 'undefined'
                && typeof window.MediaRecorder !== 'undefined'
                && typeof navigator !== 'undefined'
                && Boolean(navigator.mediaDevices)
                && typeof navigator.mediaDevices.getUserMedia === 'function'
        },

        isVideoRecordingSupported() {
            return typeof window !== 'undefined'
                && typeof window.MediaRecorder !== 'undefined'
                && typeof navigator !== 'undefined'
                && Boolean(navigator.mediaDevices)
                && typeof navigator.mediaDevices.getUserMedia === 'function'
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

            const supportsType = typeof window.MediaRecorder.isTypeSupported === 'function'
                ? (candidate) => window.MediaRecorder.isTypeSupported(candidate)
                : () => true

            const match = candidates.find((candidate) => supportsType(candidate) && canPlay(candidate))
            return match || ''
        },

        getPreferredVideoMimeType() {
            if (typeof window === 'undefined' || typeof window.MediaRecorder === 'undefined') {
                return ''
            }

            const candidates = [
                'video/webm;codecs=vp9,opus',
                'video/webm;codecs=vp8,opus',
                'video/webm',
                'video/mp4',
            ]

            const probe = typeof document !== 'undefined' ? document.createElement('video') : null
            const canPlay = (candidate) => {
                if (!probe || typeof probe.canPlayType !== 'function') {
                    return true
                }

                const mime = candidate.split(';')[0]?.trim() || candidate
                return probe.canPlayType(mime) !== ''
            }

            const supportsType = typeof window.MediaRecorder.isTypeSupported === 'function'
                ? (candidate) => window.MediaRecorder.isTypeSupported(candidate)
                : () => true

            const match = candidates.find((candidate) => supportsType(candidate) && canPlay(candidate))
            return match || ''
        },

        buildVoiceCaptureConstraints() {
            const selectedAudioInputId = String(this.selectedAudioInputId || '').trim()

            return {
                audio: {
                    ...(selectedAudioInputId !== '' ? { deviceId: { exact: selectedAudioInputId } } : {}),
                    channelCount: {ideal: 1},
                    echoCancellation: {ideal: true},
                    noiseSuppression: {ideal: true},
                    autoGainControl: {ideal: true},
                },
            }
        },

        buildVideoCaptureConstraints() {
            const selectedAudioInputId = String(this.selectedAudioInputId || '').trim()
            const selectedVideoInputId = String(this.selectedVideoInputId || '').trim()

            return {
                video: {
                    ...(selectedVideoInputId !== '' ? { deviceId: { exact: selectedVideoInputId } } : { facingMode: 'user' }),
                    width: {ideal: 1280},
                    height: {ideal: 720},
                    frameRate: {ideal: 30, max: 30},
                },
                audio: {
                    ...(selectedAudioInputId !== '' ? { deviceId: { exact: selectedAudioInputId } } : {}),
                    echoCancellation: {ideal: true},
                    noiseSuppression: {ideal: true},
                    autoGainControl: {ideal: true},
                },
            }
        },

        async startVoiceRecording() {
            if (!this.canRecordVoice
                || this.composerDisabled
                || this.isRecordingVoice
                || this.isProcessingVoice
                || this.voiceStopInProgress
                || this.isRecordingVideo
                || this.isProcessingVideo
                || this.videoStopInProgress) {
                return
            }

            let stream = null
            try {
                try {
                    stream = await navigator.mediaDevices.getUserMedia(this.buildVoiceCaptureConstraints())
                } catch (_error) {
                    stream = await navigator.mediaDevices.getUserMedia({audio: true})
                }
                await this.loadMediaInputDevices()

                this.isProcessingVoice = false
                this.voiceRecordDurationSeconds = 0
                this.voiceRecordStartedAt = Date.now()
                this.voiceRecordStream = stream
                this.voiceMediaRecorder = null
                this.voiceRecordedChunks = []
                this.voiceRecordedMimeType = ''
                this.isRecordingVoice = true

                const options = {}
                const preferredMimeType = this.getPreferredVoiceMimeType()
                if (preferredMimeType !== '') {
                    options.mimeType = preferredMimeType
                }

                let recorder = null
                try {
                    recorder = new MediaRecorder(stream, options)
                } catch (_error) {
                    recorder = null
                }

                if (!recorder) {
                    throw new Error('media-recorder-init-failed')
                }

                recorder.ondataavailable = (event) => {
                    if (event.data && event.data.size > 0) {
                        this.voiceRecordedChunks.push(event.data)
                        if (!this.voiceRecordedMimeType && typeof event.data.type === 'string' && event.data.type.trim() !== '') {
                            this.voiceRecordedMimeType = event.data.type
                        }
                    }
                }

                recorder.start(250)
                this.voiceMediaRecorder = recorder

                this.voiceRecordTimerId = window.setInterval(() => {
                    this.voiceRecordDurationSeconds += 1

                    if (this.voiceRecordDurationSeconds >= this.maxVoiceRecordDurationSeconds && this.isRecordingVoice && !this.voiceStopInProgress) {
                        this.stopVoiceRecording(false)
                            .finally(() => {
                                alert(this.$t('chats.voiceLimitReached', {limit: this.formattedVoiceRecordDurationLimit}))
                            })
                    }
                }, 1000)
            } catch (_error) {
                if (stream) {
                    this.stopVoiceRecordStreamTracks(stream)
                }

                this.stopVoiceRecordTimer()
                this.voiceRecordStream = null
                this.voiceMediaRecorder = null
                this.voiceRecordedChunks = []
                this.voiceRecordedMimeType = ''
                this.voiceRecordStartedAt = null
                this.isRecordingVoice = false
                this.isProcessingVoice = false
                this.voiceStopInProgress = false
                alert(this.$t('chats.microphoneAccessFailed'))
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
                const recorder = this.voiceMediaRecorder
                const recordedDurationMs = this.voiceRecordStartedAt
                    ? Math.max(0, Date.now() - this.voiceRecordStartedAt)
                    : 0

                this.isRecordingVoice = false

                if (shouldDiscard) {
                    if (recorder && recorder.state !== 'inactive') {
                        try {
                            recorder.stop()
                        } catch (_error) {
                            // Ignore repeated stop calls.
                        }
                    }

                    if (stream) {
                        this.stopVoiceRecordStreamTracks(stream)
                    }

                    this.voiceRecordStream = null
                    this.voiceMediaRecorder = null
                    this.voiceRecordedChunks = []
                    this.voiceRecordedMimeType = ''
                    this.voiceRecordStartedAt = null
                    this.isProcessingVoice = false
                    return
                }

                this.isProcessingVoice = true

                try {
                    if (recorder && recorder.state !== 'inactive') {
                        if (typeof recorder.requestData === 'function') {
                            try {
                                recorder.requestData()
                            } catch (_error) {
                                // Ignore flush errors.
                            }
                        }

                        try {
                            recorder.stop()
                        } catch (_error) {
                            // Ignore stop race errors.
                        }
                    }

                    let appended = false
                    if (recorder) {
                        await this.waitForRecorderInactive(recorder, 2200)
                        await this.waitForRecordedChunks(this.voiceRecordedChunks, 2200)
                    }

                    if (this.voiceRecordedChunks.length > 0) {
                        const mimeType = this.voiceRecordedMimeType || recorder?.mimeType || ''
                        appended = this.appendRecordedVoice(this.voiceRecordedChunks, mimeType, recordedDurationMs)
                    }

                    if (!appended) {
                        alert(this.$t('chats.voiceNotRecordedRetry'))
                    }
                } catch (_error) {
                    let appended = false
                    if (this.voiceRecordedChunks.length > 0) {
                        const mimeType = this.voiceRecordedMimeType || recorder?.mimeType || ''
                        appended = this.appendRecordedVoice(this.voiceRecordedChunks, mimeType, recordedDurationMs)
                    }

                    if (!appended) {
                        alert(this.$t('chats.voiceNotRecordedRetry'))
                    }
                } finally {
                    if (recorder && recorder.state !== 'inactive') {
                        try {
                            recorder.stop()
                        } catch (_error) {
                            // Ignore repeated stop calls.
                        }
                    }

                    if (stream) {
                        this.stopVoiceRecordStreamTracks(stream)
                    }

                    this.voiceRecordStream = null
                    this.voiceMediaRecorder = null
                    this.voiceRecordedChunks = []
                    this.voiceRecordedMimeType = ''
                    this.voiceRecordStartedAt = null
                    this.isProcessingVoice = false
                }
            } finally {
                this.voiceStopInProgress = false
            }
        },

        async startVideoRecording() {
            if (!this.canRecordVideo
                || this.composerDisabled
                || this.isRecordingVideo
                || this.isProcessingVideo
                || this.videoStopInProgress
                || this.isRecordingVoice
                || this.isProcessingVoice
                || this.voiceStopInProgress) {
                return
            }

            let stream = null
            try {
                this.stopVideoPreview()
                try {
                    stream = await navigator.mediaDevices.getUserMedia(this.buildVideoCaptureConstraints())
                } catch (_error) {
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({video: true, audio: true})
                    } catch (_fallbackError) {
                        stream = await navigator.mediaDevices.getUserMedia({video: true, audio: false})
                    }
                }
                await this.loadMediaInputDevices()

                this.isProcessingVideo = false
                this.videoRecordDurationSeconds = 0
                this.videoRecordStartedAt = Date.now()
                this.videoRecordStream = stream
                this.videoMediaRecorder = null
                this.videoRecordedChunks = []
                this.videoRecordedMimeType = ''
                this.isRecordingVideo = true

                const preferredMimeType = this.getPreferredVideoMimeType()
                const tryCreateRecorder = () => {
                    if (preferredMimeType !== '') {
                        try {
                            return new MediaRecorder(stream, { mimeType: preferredMimeType })
                        } catch (_error) {
                            // Fallback to browser default below.
                        }
                    }

                    try {
                        return new MediaRecorder(stream)
                    } catch (_error) {
                        return null
                    }
                }

                const recorder = tryCreateRecorder()
                if (!recorder) {
                    throw new Error('media-recorder-init-failed')
                }

                recorder.ondataavailable = (event) => {
                    if (event.data && event.data.size > 0) {
                        this.videoRecordedChunks.push(event.data)
                        if (!this.videoRecordedMimeType && typeof event.data.type === 'string' && event.data.type.trim() !== '') {
                            this.videoRecordedMimeType = event.data.type
                        }
                    }
                }

                recorder.start(300)
                this.videoMediaRecorder = recorder

                this.videoRecordTimerId = window.setInterval(() => {
                    this.videoRecordDurationSeconds += 1

                    if (this.videoRecordDurationSeconds >= this.maxVideoRecordDurationSeconds && this.isRecordingVideo && !this.videoStopInProgress) {
                        this.stopVideoRecording(false)
                            .finally(() => {
                                alert(this.$t('chats.videoLimitReached', {limit: this.formattedVideoRecordDurationLimit}))
                            })
                    }
                }, 1000)
            } catch (_error) {
                if (stream) {
                    this.stopVideoRecordStreamTracks(stream)
                }

                this.stopVideoRecordTimer()
                this.videoRecordStream = null
                this.videoMediaRecorder = null
                this.videoRecordedChunks = []
                this.videoRecordedMimeType = ''
                this.videoRecordStartedAt = null
                this.isRecordingVideo = false
                this.isProcessingVideo = false
                this.videoStopInProgress = false
                alert(this.$t('chats.cameraAccessFailed'))
            }
        },

        async stopVideoRecording(forceDiscard = false) {
            const shouldDiscard = forceDiscard === true
            if (this.videoStopInProgress) {
                return
            }

            this.videoStopInProgress = true

            try {
                this.stopVideoRecordTimer()
                const stream = this.videoRecordStream
                const recorder = this.videoMediaRecorder
                const recordedDurationMs = this.videoRecordStartedAt
                    ? Math.max(0, Date.now() - this.videoRecordStartedAt)
                    : 0

                this.isRecordingVideo = false

                if (shouldDiscard) {
                    if (recorder && recorder.state !== 'inactive') {
                        try {
                            recorder.stop()
                        } catch (_error) {
                            // Ignore repeated stop calls.
                        }
                    }

                    if (stream) {
                        this.stopVideoRecordStreamTracks(stream)
                    }

                    this.videoRecordStream = null
                    this.videoMediaRecorder = null
                    this.videoRecordedChunks = []
                    this.videoRecordedMimeType = ''
                    this.videoRecordStartedAt = null
                    this.isProcessingVideo = false
                    return
                }

                this.isProcessingVideo = true

                try {
                    if (recorder && recorder.state !== 'inactive') {
                        if (typeof recorder.requestData === 'function') {
                            try {
                                recorder.requestData()
                            } catch (_error) {
                                // Ignore flush errors.
                            }
                        }

                        try {
                            recorder.stop()
                        } catch (_error) {
                            // Ignore stop race errors.
                        }
                    }

                    let appended = false
                    if (recorder) {
                        await this.waitForRecorderInactive(recorder, 2400)
                        await this.waitForRecordedChunks(this.videoRecordedChunks, 2400)
                    }

                    if (this.videoRecordedChunks.length > 0) {
                        const mimeType = this.videoRecordedMimeType || recorder?.mimeType || ''
                        appended = this.appendRecordedVideo(this.videoRecordedChunks, mimeType, recordedDurationMs)
                    }

                    if (!appended) {
                        alert(this.$t('chats.videoNotRecordedRetry'))
                    }
                } catch (_error) {
                    let appended = false
                    if (this.videoRecordedChunks.length > 0) {
                        const mimeType = this.videoRecordedMimeType || recorder?.mimeType || ''
                        appended = this.appendRecordedVideo(this.videoRecordedChunks, mimeType, recordedDurationMs)
                    }

                    if (!appended) {
                        alert(this.$t('chats.videoNotRecordedRetry'))
                    }
                } finally {
                    if (recorder && recorder.state !== 'inactive') {
                        try {
                            recorder.stop()
                        } catch (_error) {
                            // Ignore repeated stop calls.
                        }
                    }

                    if (stream) {
                        this.stopVideoRecordStreamTracks(stream)
                    }

                    this.videoRecordStream = null
                    this.videoMediaRecorder = null
                    this.videoRecordedChunks = []
                    this.videoRecordedMimeType = ''
                    this.videoRecordStartedAt = null
                    this.isProcessingVideo = false
                }
            } finally {
                this.videoStopInProgress = false
            }
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

        stopVideoRecordTimer() {
            if (this.videoRecordTimerId) {
                window.clearInterval(this.videoRecordTimerId)
                this.videoRecordTimerId = null
            }

            this.videoRecordDurationSeconds = 0
        },

        stopVideoRecordStreamTracks(stream) {
            if (!stream) {
                return
            }

            for (const track of stream.getTracks()) {
                track.stop()
            }
        },

        normalizeRecordedAudioMimeType(mimeType) {
            const normalized = String(mimeType || '').toLowerCase().trim()
            if (normalized === '') {
                return ''
            }

            const [baseType] = normalized.split(';')
            return baseType?.trim() || ''
        },

        normalizeRecordedVideoMimeType(mimeType) {
            const normalized = String(mimeType || '').toLowerCase().trim()
            if (normalized === '') {
                return ''
            }

            const [baseType] = normalized.split(';')
            return baseType?.trim() || ''
        },

        appendRecordedVoice(chunks, mimeType, recordedDurationMs = 0) {
            void recordedDurationMs
            const blobType = this.normalizeRecordedAudioMimeType(mimeType)
            const blob = blobType !== ''
                ? new Blob(chunks, {type: blobType})
                : new Blob(chunks)

            if (blob.size === 0) {
                alert(this.$t('chats.voiceEmptyRetry'))
                return false
            }

            const extension = this.fileExtensionFromMime(blobType)
            const timestamp = Date.now()
            const file = new File([blob], `voice-${timestamp}.${extension}`, {type: blobType || 'audio/webm'})
            const key = `voice-${timestamp}-${Math.random().toString(36).slice(2)}`
            const url = URL.createObjectURL(file)

            this.selectedFiles.push({key, file})
            this.selectedFilePreviews.push({
                key,
                url,
                kind: 'audio',
                name: file.name,
                mimeType: file.type || blobType || 'audio/webm',
                size: Number(file.size || 0),
            })

            return true
        },

        appendRecordedVideo(chunks, mimeType, recordedDurationMs = 0) {
            void recordedDurationMs
            const blobType = this.normalizeRecordedVideoMimeType(mimeType)
            const blob = blobType !== ''
                ? new Blob(chunks, {type: blobType})
                : new Blob(chunks)

            if (blob.size === 0) {
                alert(this.$t('chats.videoEmptyRetry'))
                return false
            }

            const extension = this.fileExtensionFromMime(blobType)
            const timestamp = Date.now()
            const file = new File([blob], `video-${timestamp}.${extension}`, {type: blobType || 'video/webm'})
            const key = `video-${timestamp}-${Math.random().toString(36).slice(2)}`
            const url = URL.createObjectURL(file)

            this.selectedFiles.push({key, file})
            this.selectedFilePreviews.push({
                key,
                url,
                kind: 'video',
                name: file.name,
                mimeType: file.type || blobType || 'video/webm',
                size: Number(file.size || 0),
            })

            return true
        },

        fileExtensionFromMime(mimeType) {
            const normalized = String(mimeType || '').toLowerCase()
            if (normalized.includes('ogg')) {
                return 'ogg'
            }
            if (normalized.includes('mp4') || normalized.includes('m4a')) {
                return 'mp4'
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
            const files = Array.from(event?.target?.files || [])
            if (files.length === 0) {
                return
            }

            for (const file of files) {
                const key = `${Date.now()}-${Math.random().toString(36).slice(2)}`
                const url = URL.createObjectURL(file)
                const kind = this.resolvePreviewKind(file)
                this.selectedFiles.push({key, file})
                this.selectedFilePreviews.push({
                    key,
                    url,
                    kind,
                    name: String(file?.name || ''),
                    mimeType: String(file?.type || ''),
                    size: Number(file?.size || 0),
                })
            }

            if (this.$refs.messageFiles) {
                this.$refs.messageFiles.value = null
            }

            this.notifyTypingActivity({ immediate: true })
        },

        resolvePreviewKind(file) {
            const mime = String(file?.type || '').toLowerCase()
            const name = String(file?.name || '').toLowerCase()

            if (mime.startsWith('video/') || /\.(mp4|m4v|mov|avi|webm)$/i.test(name)) {
                return 'video'
            }

            if (mime.startsWith('audio/') || /\.(mp3|wav|ogg|m4a|aac|opus|weba|webm)$/i.test(name)) {
                return 'audio'
            }

            return 'file'
        },

        removeSelectedFile(key) {
            const preview = this.selectedFilePreviews.find((item) => item.key === key)
            if (preview?.url) {
                URL.revokeObjectURL(preview.url)
            }

            this.selectedFiles = this.selectedFiles.filter((item) => item.key !== key)
            this.selectedFilePreviews = this.selectedFilePreviews.filter((item) => item.key !== key)
            this.notifyTypingActivity({ immediate: true })
        },

        clearSelectedFiles() {
            for (const preview of this.selectedFilePreviews) {
                if (preview?.url) {
                    URL.revokeObjectURL(preview.url)
                }
            }

            this.selectedFiles = []
            this.selectedFilePreviews = []

            if (this.$refs.messageFiles) {
                this.$refs.messageFiles.value = null
            }

            this.notifyTypingActivity({ immediate: true })
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
            link.rel = 'noopener noreferrer'

            const userAgent = typeof navigator !== 'undefined' ? String(navigator.userAgent || '') : ''
            if (/iphone|ipad|ipod|android/i.test(userAgent)) {
                link.target = '_blank'
            }

            link.style.display = 'none'
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
        },

        formatBytes(bytes) {
            const value = Number(bytes || 0)
            if (!Number.isFinite(value) || value <= 0) {
                return '0 B'
            }

            const units = ['B', 'KB', 'MB', 'GB']
            let size = value
            let unitIndex = 0

            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024
                unitIndex += 1
            }

            const precision = size >= 100 ? 0 : size >= 10 ? 1 : 2
            return `${size.toFixed(precision)} ${units[unitIndex]}`
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

        async sendMessage() {
            if (!this.canSend || !this.activeConversation) {
                return
            }

            this.isSending = true
            this.sendError = ''
            this.notifyTypingActivity({ immediate: true, isSending: true })

            try {
                let response
                const normalizedBody = this.normalizeStickerTransport(this.messageBody)
                if (this.selectedFiles.length > 0) {
                    const formData = new FormData()
                    formData.append('body', normalizedBody)

                    for (const item of this.selectedFiles) {
                        formData.append('files[]', item.file)
                    }

                    response = await axios.post(`/api/chats/${this.activeConversation.id}/messages`, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        },
                    })
                } else {
                    response = await axios.post(`/api/chats/${this.activeConversation.id}/messages`, {
                        body: normalizedBody,
                    })
                }

                const normalized = this.normalizeMessage(response?.data?.data)
                if (normalized) {
                    this.upsertMessage(normalized)
                    this.updateConversationFromMessage(normalized, {
                        incrementUnread: false,
                    })
                    this.notifyChatSyncMessage(normalized, {
                        markRead: true,
                    })
                }

                this.messageBody = ''
                this.clearSelectedFiles()
                this.showStickerTray = false
                this.setConversationReadLocally(this.activeConversation.id)
                await this.scrollMessagesDown()
            } catch (error) {
                if (Number(error?.response?.status ?? 0) === 423) {
                    await this.loadConversations({silent: true})
                }
                this.sendError = this.resolveApiMessage(error, this.$t('chats.sendMessageFailed'))
            } finally {
                this.isSending = false
                this.notifyTypingStopped(this.activeConversation?.id ?? null)
            }
        },

        upsertMessage(incomingMessage) {
            const normalized = this.normalizeMessage(incomingMessage)
            if (!normalized || Number(normalized.conversation_id) !== Number(this.activeConversationId)) {
                return
            }

            const targetIndex = this.messages.findIndex((message) => Number(message.id) === Number(normalized.id))
            if (targetIndex >= 0) {
                this.messages.splice(targetIndex, 1, {
                    ...this.messages[targetIndex],
                    ...normalized,
                })
            } else {
                this.messages.push(normalized)
            }

            this.messages.sort((first, second) => {
                return new Date(first.created_at).getTime() - new Date(second.created_at).getTime()
            })
        },

        updateConversationFromMessage(message, options = {}) {
            const conversationId = Number(message?.conversation_id ?? 0)
            if (!Number.isFinite(conversationId) || conversationId <= 0) {
                return
            }

            const incrementUnread = Boolean(options?.incrementUnread)
            let wasUpdated = false

            this.conversations = this.conversations.map((conversation) => {
                if (Number(conversation.id) !== conversationId) {
                    return conversation
                }

                wasUpdated = true
                const unread = Number(conversation.unread_count || 0)
                const nextUnread = incrementUnread ? unread + 1 : unread

                return {
                    ...conversation,
                    last_message: message,
                    unread_count: nextUnread,
                    has_unread: nextUnread > 0,
                    updated_at: message.created_at || conversation.updated_at,
                }
            }).sort((first, second) => {
                return new Date(second.updated_at || 0).getTime() - new Date(first.updated_at || 0).getTime()
            })

            if (!wasUpdated) {
                this.loadConversations({silent: true})
            }
        },

        async handleIncomingMessage(payload) {
            const normalized = this.normalizeMessage(payload)
            if (!normalized) {
                return
            }

            const senderId = Number(normalized?.user?.id ?? 0)
            if (senderId > 0) {
                this.removeTypingStateEntry(normalized.conversation_id, senderId)
            }

            const isActiveConversation = Number(this.activeConversationId) === Number(normalized.conversation_id)
            if (isActiveConversation) {
                this.upsertMessage(normalized)
                this.updateConversationFromMessage(normalized, {
                    incrementUnread: false,
                })

                if (!this.isMine(normalized)) {
                    this.setIncomingNotice(normalized)
                    await this.markConversationRead(normalized.conversation_id)
                }

                await this.scrollMessagesDown()
                return
            }

            this.updateConversationFromMessage(normalized, {
                incrementUnread: !this.isMine(normalized),
            })

            this.setIncomingNotice(normalized)
        },

        syncConversationSubscriptions() {
            if (!window.Echo) {
                return
            }

            const activeIds = new Set(this.conversations.map((conversation) => Number(conversation.id)))

            for (const [id, channelName] of Object.entries(this.subscribedChannels)) {
                if (!activeIds.has(Number(id))) {
                    window.Echo.leave(channelName)
                    delete this.subscribedChannels[id]
                    this.clearConversationTypingState(id)
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
                    .listen('.chat.mood-status.updated', (payload) => {
                        this.handleMoodStatusRealtimeEvent(conversation.id, payload)
                    })
                    .listenForWhisper('typing', (payload) => {
                        this.handleTypingWhisper(conversation.id, payload)
                    })

                this.subscribedChannels[conversation.id] = channelName
            }
        },

        unsubscribeAllChannels() {
            if (window.Echo) {
                Object.values(this.subscribedChannels).forEach((channelName) => {
                    window.Echo.leave(channelName)
                })
            }

            this.subscribedChannels = {}
            this.clearTypingState()
        },

        startConversationPolling() {
            this.stopConversationPolling()
            this.conversationPollTimerId = window.setInterval(() => {
                this.loadConversations({silent: true})
            }, CHAT_WIDGET_CONVERSATIONS_POLL_MS)
        },

        stopConversationPolling() {
            if (this.conversationPollTimerId) {
                window.clearInterval(this.conversationPollTimerId)
                this.conversationPollTimerId = null
            }
        },

        restartMessagePolling() {
            this.stopMessagePolling()

            const activeConversationId = Number(this.activeConversationId)
            if (!Number.isFinite(activeConversationId) || activeConversationId <= 0) {
                return
            }

            this.messagePollTimerId = window.setInterval(() => {
                this.loadMessages(activeConversationId, {silent: true})
                this.markConversationRead(activeConversationId)
            }, CHAT_WIDGET_MESSAGES_POLL_MS)
        },

        stopMessagePolling() {
            if (this.messagePollTimerId) {
                window.clearInterval(this.messagePollTimerId)
                this.messagePollTimerId = null
            }
        },

        async scrollMessagesDown() {
            await nextTick()

            const container = this.$refs.messagesContainer
            if (!container) {
                return
            }

            container.scrollTop = container.scrollHeight
        },
    },
}
</script>
