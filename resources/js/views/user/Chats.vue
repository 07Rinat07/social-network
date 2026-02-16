<template>
    <div class="page-wrap chat-layout chat-screen">
        <section class="chat-list fade-in chat-sidebar">
            <div class="chat-sidebar-head">
                <div>
                    <h2 class="section-title chat-sidebar-title">{{ $t('chats.title') }}</h2>
                    <p class="section-subtitle chat-sidebar-subtitle">
                        {{ $t('chats.allDialogsOnline', { all: conversationCounters.all, online: siteOnlineUsers.length }) }}
                    </p>
                </div>
                <div class="chat-pane-switch">
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="leftPaneMode === 'conversations' ? 'btn-primary' : 'btn-outline'"
                        @click="setLeftPaneMode('conversations')"
                    >
                        {{ $t('chats.dialogs') }}
                    </button>
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="leftPaneMode === 'users' ? 'btn-primary' : 'btn-outline'"
                        @click="setLeftPaneMode('users')"
                    >
                        {{ $t('chats.people') }}
                    </button>
                </div>
            </div>

            <div class="chat-sidebar-controls">
                <input
                    v-if="leftPaneMode === 'conversations'"
                    class="input-field chat-search-field"
                    v-model.trim="conversationSearch"
                    type="text"
                    :placeholder="$t('chats.searchPlaceholder')"
                >
                <input
                    v-else
                    class="input-field chat-search-field"
                    v-model.trim="userSearch"
                    type="text"
                    :placeholder="$t('chats.userSearch')"
                    @input="onUserSearchInput"
                >

                <div class="chat-filter-row" v-if="leftPaneMode === 'conversations'">
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="conversationFilter === 'all' ? 'btn-primary' : 'btn-outline'"
                        @click="setConversationFilter('all')"
                    >
                        {{ $t('chats.filterAll', { count: conversationCounters.all }) }}
                    </button>
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="conversationFilter === 'unread' ? 'btn-primary' : 'btn-outline'"
                        @click="setConversationFilter('unread')"
                    >
                        {{ $t('chats.filterUnread', { count: conversationCounters.unread }) }}
                    </button>
                    <button
                        class="btn btn-sm"
                        type="button"
                        :class="conversationFilter === 'blocked' ? 'btn-primary' : 'btn-outline'"
                        @click="setConversationFilter('blocked')"
                    >
                        {{ $t('chats.filterBlocked', { count: conversationCounters.blocked }) }}
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
                            <div class="chat-item-title-wrap">
                                <strong class="chat-item-title">{{ conversation.title }}</strong>
                                <span
                                    v-if="conversation.type === 'direct' && isConversationPeerOnline(conversation)"
                                    class="chat-online-dot"
                                    :title="$t('chats.peerOnlineNow')"
                                ></span>
                            </div>
                            <span class="chat-item-time">{{ conversationTime(conversation) }}</span>
                        </div>
                        <p class="muted chat-item-preview">{{ messagePreview(conversation) }}</p>
                        <p v-if="conversation.is_blocked" class="error-text chat-item-warning">{{ $t('chats.dialogBlocked') }}</p>
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
                            :title="isConversationPinned(conversation.id) ? $t('chats.unpinDialog') : $t('chats.pinDialog')"
                        >
                            {{ isConversationPinned(conversation.id) ? '★' : '☆' }}
                        </button>
                    </div>
                </div>

                <p v-if="filteredConversations.length === 0" class="muted chat-empty">
                    {{ $t('chats.dialogsNotFound') }}
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
                            <div class="chat-user-presence-row">
                                <span class="chat-presence-pill" :class="{ 'is-online': isUserOnline(user) }">
                                    {{ isUserOnline(user) ? $t('chats.online') : $t('chats.offline') }}
                                </span>
                                <span v-if="isUserInActiveChat(user)" class="chat-presence-pill is-in-chat">
                                    {{ $t('chats.inThisChat') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <p class="muted chat-user-status">
                        <span v-if="isBlockedByMe(user)">{{ $t('chats.youBlockedUser') }}</span>
                        <span v-else-if="isBlockedByUser(user)">{{ $t('chats.userBlockedYou') }}</span>
                        <span v-else>{{ $t('chats.directChatAvailable') }}</span>
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
                            {{ $t('chats.directChat') }}
                        </button>
                        <button
                            v-if="!isBlockedByMe(user)"
                            class="btn btn-danger btn-sm"
                            @click="blockUser(user, 'temporary')"
                        >
                            {{ $t('chats.block24h') }}
                        </button>
                        <button
                            v-if="!isBlockedByMe(user)"
                            class="btn btn-danger btn-sm"
                            @click="blockUser(user, 'permanent')"
                        >
                            {{ $t('chats.blockForever') }}
                        </button>
                        <button
                            v-if="isBlockedByMe(user)"
                            class="btn btn-success btn-sm"
                            @click="unblockUser(user)"
                        >
                            {{ $t('chats.unblock') }}
                        </button>
                    </div>
                </div>

                <p v-if="users.length === 0" class="muted chat-empty">
                    {{ $t('chats.usersNotFound') }}
                </p>
            </div>

            <details class="chat-sound-panel">
                <summary>{{ $t('chats.notificationSound') }}</summary>
                <div class="form-grid chat-sound-grid">
                    <label class="muted chat-sound-toggle">
                        <input type="checkbox" v-model="notificationSettings.enabled" @change="saveNotificationSettings">
                        {{ $t('chats.enableIncomingSound') }}
                    </label>
                    <select class="select-field" v-model="notificationSettings.sound" @change="saveNotificationSettings">
                        <option
                            v-for="preset in notificationSoundPresets"
                            :key="preset.id"
                            :value="preset.id"
                        >
                            {{ preset.label }}
                        </option>
                        <option value="custom">{{ $t('chats.customSoundFromFile') }}</option>
                    </select>
                    <label class="muted">{{ $t('chats.volumePercent', { value: notificationSettings.volume }) }}</label>
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
                        {{ $t('chats.testSound') }}
                    </button>
                    <input
                        class="input-field"
                        type="file"
                        accept="audio/*"
                        @change="onCustomSoundSelected"
                    >
                    <p class="muted chat-sound-note">{{ $t('chats.customSoundNote') }}</p>
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
                            {{ activeConversation ? activeConversation.title : $t('chats.selectChat') }}
                        </h3>
                        <p class="muted chat-window-subtitle">{{ activeConversationSubtitle }}</p>
                        <p v-if="activeConversationPresenceLine" class="muted chat-window-presence">
                            {{ activeConversationPresenceLine }}
                        </p>
                        <p v-if="activeTypingStatusLine" class="chat-window-typing">
                            {{ activeTypingStatusLine }}
                        </p>
                        <p v-if="activeConversation && activeConversation.is_blocked" class="error-text chat-window-warning">
                            {{ $t('chats.dialogBlockedReadonly') }}
                        </p>
                    </div>
                </div>

                <div class="chat-window-tools">
                    <input
                        class="input-field chat-inline-search"
                        v-model.trim="messageSearch"
                        type="text"
                        :placeholder="$t('chats.messageSearch')"
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
                            {{ $t('chats.all') }}
                        </button>
                        <button
                            class="btn btn-sm"
                            type="button"
                            :class="messageFilter === 'files_only' ? 'btn-primary' : 'btn-outline'"
                            :disabled="!activeConversation"
                            @click="setMessageFilter('files_only')"
                        >
                            {{ $t('chats.filesOnly') }}
                        </button>
                    </div>
                    <button class="btn btn-outline btn-sm" type="button" @click="scrollMessagesDown" :disabled="!activeConversation">
                        {{ $t('chats.down') }}
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
                                    {{ isMessageDeleting(message.id) ? $t('chats.deleting') : $t('common.delete') }}
                                </button>
                            </div>
                            <StickerRichText
                                v-if="message.body"
                                as="div"
                                class="chat-message-body"
                                :text="message.body"
                            ></StickerRichText>

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
                                        <strong class="chat-file-name">{{ attachment.original_name || $t('chats.file') }}</strong>
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
                                        {{ $t('chats.download') }}
                                    </button>
                                    <button
                                        v-if="canDeleteMessage(message)"
                                        class="btn btn-danger btn-sm chat-attachment-remove-btn"
                                        type="button"
                                        :disabled="isAttachmentDeleting(message.id, attachment.id)"
                                        @click.stop="deleteAttachment(message, attachment)"
                                    >
                                        {{ isAttachmentDeleting(message.id, attachment.id) ? $t('chats.deleting') : $t('chats.deleteFile') }}
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
                                    :title="$t('chats.reactionWithEmoji', { emoji })"
                                    :disabled="isMessageReactionToggling(message.id, emoji)"
                                    @click.stop="toggleMessageReaction(message, emoji)"
                                >
                                    {{ emoji }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="messages.length === 0" class="muted chat-empty">{{ $t('chats.noMessagesYet') }}</p>
                    <p v-else-if="displayedMessages.length === 0" class="muted chat-empty">
                        {{ messageFilter === 'files_only' ? $t('chats.noMessagesWithFiles') : $t('chats.nothingFoundByQuery') }}
                    </p>
                    <div v-if="activeTypingStatusLine" class="chat-typing-bubble">
                        {{ activeTypingStatusLine }}
                    </div>
                </template>

                <p v-else class="muted chat-empty">{{ $t('chats.openChatToStart') }}</p>
            </div>

            <form class="form-grid chat-composer" @submit.prevent="sendMessage">
                <div class="chat-composer-meta">
                    <div class="chat-composer-hints">
                        <p class="muted">{{ $t('chats.ctrlEnterHint') }}</p>
                        <p v-if="composerStatusLabel" class="chat-composer-status">{{ composerStatusLabel }}</p>
                    </div>
                    <span class="muted chat-char-counter">{{ messageBody.length }}/4000</span>
                </div>

                <textarea
                    class="textarea-field chat-composer-input"
                    v-model="messageBody"
                    :placeholder="$t('chats.enterMessage')"
                    maxlength="4000"
                    :disabled="isComposerDisabled"
                    @input="handleComposerInput"
                    @focus="handleComposerInput"
                    @blur="notifyTypingStopped"
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
                        {{ $t('chats.addFileMedia') }}
                    </button>
                    <button
                        class="btn btn-outline chat-sticker-toggle"
                        type="button"
                        @click="toggleStickerTray"
                        :disabled="isComposerDisabled"
                    >
                        {{ showStickerTray ? $t('chats.hideStickers') : $t('chats.stickers') }}
                    </button>
                    <button
                        class="btn btn-outline"
                        type="button"
                        @click="startVoiceRecording"
                        :disabled="isComposerDisabled || isRecordingVoice || isProcessingVoice || voiceStopInProgress || isRecordingVideo || isProcessingVideo || videoStopInProgress || !canRecordVoice"
                    >
                        {{ $t('chats.recordVoice') }}
                    </button>
                    <button
                        class="btn btn-outline"
                        type="button"
                        @click="startVideoRecording"
                        :disabled="isComposerDisabled || isRecordingVideo || isProcessingVideo || videoStopInProgress || isRecordingVoice || isProcessingVoice || voiceStopInProgress || !canRecordVideo"
                    >
                        {{ $t('chats.recordVideo') }}
                    </button>
                    <button
                        class="btn btn-outline"
                        type="button"
                        @click="toggleVideoPreview"
                        :disabled="isComposerDisabled || isRecordingVideo || isProcessingVideo || videoStopInProgress || isRecordingVoice || isProcessingVoice || voiceStopInProgress || !canRecordVideo"
                    >
                        {{ isVideoPreviewActive ? $t('chats.closeCameraPreview') : $t('chats.openCameraPreview') }}
                    </button>
                    <button
                        class="btn btn-danger"
                        type="button"
                        @click="stopVoiceRecording"
                        :disabled="voiceStopInProgress"
                        v-if="isRecordingVoice"
                    >
                        {{ $t('chats.stopRecordingWithDuration', { current: formattedVoiceRecordDuration, limit: formattedVoiceRecordDurationLimit }) }}
                    </button>
                    <button
                        class="btn btn-outline"
                        type="button"
                        @click="stopVoiceRecording(true)"
                        :disabled="voiceStopInProgress"
                        v-if="isRecordingVoice"
                    >
                        {{ $t('chats.cancelRecording') }}
                    </button>
                    <button
                        class="btn btn-danger"
                        type="button"
                        @click="stopVideoRecording"
                        :disabled="videoStopInProgress"
                        v-if="isRecordingVideo"
                    >
                        {{ $t('chats.stopRecordingWithDuration', { current: formattedVideoRecordDuration, limit: formattedVideoRecordDurationLimit }) }}
                    </button>
                    <button
                        class="btn btn-outline"
                        type="button"
                        @click="stopVideoRecording(true)"
                        :disabled="videoStopInProgress"
                        v-if="isRecordingVideo"
                    >
                        {{ $t('chats.cancelRecording') }}
                    </button>
                </div>

                <div v-if="showStickerTray" class="chat-sticker-tray">
                    <StickerPicker
                        :disabled="isComposerDisabled"
                        :category-label="$t('radio.genreFilterLabel')"
                        @select="insertSticker"
                    ></StickerPicker>
                    <p class="muted chat-sticker-note">{{ $t('chats.stickerHint') }}</p>
                </div>

                <p class="muted" v-if="!canRecordVoice">
                    {{ $t('chats.voiceUnavailable') }}
                </p>
                <p class="muted" v-if="!canRecordVideo">
                    {{ $t('chats.cameraUnavailable') }}
                </p>
                <div v-if="canRecordVoice || canRecordVideo" class="section-card chat-device-card">
                    <div class="chat-device-card__head">
                        <strong>{{ $t('chats.recordingDevices') }}</strong>
                        <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            @click="refreshMediaDeviceOptions(true)"
                            :disabled="isLoadingMediaDevices"
                        >
                            {{ isLoadingMediaDevices ? $t('common.refreshing') : $t('common.refresh') }}
                        </button>
                    </div>
                    <div class="chat-device-card__grid">
                        <label v-if="canRecordVoice" class="chat-device-card__field">
                            <span>{{ $t('chats.microphoneInput') }}</span>
                            <select
                                v-model="selectedAudioInputId"
                                class="select-field chat-device-card__select"
                                :disabled="isLoadingMediaDevices || isRecordingVoice || isProcessingVoice || voiceStopInProgress"
                                @change="onSelectedAudioInputChanged"
                            >
                                <option value="">{{ $t('chats.defaultDevice') }}</option>
                                <option
                                    v-for="device in audioInputDevices"
                                    :key="`chat-mic-${device.deviceId}`"
                                    :value="device.deviceId"
                                >
                                    {{ device.label }}
                                </option>
                            </select>
                        </label>
                        <label v-if="canRecordVideo" class="chat-device-card__field">
                            <span>{{ $t('chats.cameraInput') }}</span>
                            <select
                                v-model="selectedVideoInputId"
                                class="select-field chat-device-card__select"
                                :disabled="isLoadingMediaDevices || isRecordingVideo || isProcessingVideo || videoStopInProgress"
                                @change="onSelectedVideoInputChanged"
                            >
                                <option value="">{{ $t('chats.defaultDevice') }}</option>
                                <option
                                    v-for="device in videoInputDevices"
                                    :key="`chat-camera-${device.deviceId}`"
                                    :value="device.deviceId"
                                >
                                    {{ device.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <p v-if="mediaDeviceError" class="error-text chat-device-card__error">{{ mediaDeviceError }}</p>
                </div>
                <div v-if="isVideoPreviewActive || isVideoPreviewLoading" class="section-card chat-video-preview-card">
                    <p class="muted chat-video-preview-card__status">
                        {{ isVideoPreviewLoading ? $t('chats.previewLoading') : $t('chats.cameraPreviewReady') }}
                    </p>
                    <video
                        ref="videoPreviewElement"
                        class="chat-video-preview-card__video"
                        autoplay
                        muted
                        playsinline
                    ></video>
                </div>
                <p class="muted" v-if="isRecordingVoice">
                    {{ $t('chats.voiceRecordingNow') }}
                </p>
                <div
                    v-if="isRecordingVoice"
                    class="section-card chat-voice-card"
                >
                    <div class="muted chat-voice-meta">
                        <span>{{ $t('chats.micLevel', { value: Math.round(voiceLevelPercent) }) }}</span>
                        <span>{{ $t('chats.limitValue', { value: formattedVoiceRecordDurationLimit }) }}</span>
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
                    {{ $t('chats.processingRecording') }}
                </p>
                <p class="muted" v-if="isRecordingVideo">
                    {{ $t('chats.videoRecordingNow') }}
                </p>
                <div
                    v-if="isRecordingVideo"
                    class="section-card chat-voice-card"
                >
                    <div class="muted chat-voice-meta">
                        <span>{{ $t('chats.recordingDuration', { value: formattedVideoRecordDuration }) }}</span>
                        <span>{{ $t('chats.limitValue', { value: formattedVideoRecordDurationLimit }) }}</span>
                    </div>
                    <div class="chat-voice-progress chat-voice-progress-duration">
                        <div
                            :style="{
                                width: `${videoDurationProgressPercent}%`,
                                height: '100%',
                                borderRadius: '999px',
                                background: 'linear-gradient(90deg, #ec4899 0%, #8b5cf6 50%, #0ea5e9 100%)',
                                transition: 'width 200ms linear',
                            }"
                        ></div>
                    </div>
                </div>
                <p class="muted" v-if="isProcessingVideo">
                    {{ $t('chats.preparingVideoToSend') }}
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
                            <strong class="chat-file-name">{{ item.name || $t('chats.file') }}</strong>
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
                            {{ $t('common.remove') }}
                        </button>
                    </div>
                </div>

                <button class="btn btn-primary chat-send-btn" type="submit" :disabled="isComposerDisabled || isSending || isRecordingVoice || isProcessingVoice || voiceStopInProgress || isRecordingVideo || isProcessingVideo || videoStopInProgress || !canSendCurrentMessage">
                    {{ isSending ? $t('common.sending') : $t('chats.send') }}
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
                            <span class="muted">{{ $t('chats.type') }}</span>
                            <strong>{{ activeConversation.type === 'global' ? $t('chats.globalChat') : $t('chats.directChat') }}</strong>
                        </div>
                        <div class="chat-inspector-metric">
                            <span class="muted">{{ $t('chats.participants') }}</span>
                            <strong>{{ Array.isArray(activeConversation.participants) ? activeConversation.participants.length : 0 }}</strong>
                        </div>
                        <div class="chat-inspector-metric">
                            <span class="muted">{{ $t('chats.unread') }}</span>
                            <strong>{{ Number(activeConversation.unread_count ?? 0) }}</strong>
                        </div>
                        <div class="chat-inspector-metric">
                            <span class="muted">{{ $t('chats.onlineSite') }}</span>
                            <strong>{{ activeConversationParticipantsOnlineCount }}</strong>
                        </div>
                        <div class="chat-inspector-metric">
                            <span class="muted">{{ $t('chats.inChatNow') }}</span>
                            <strong>{{ activeConversationParticipantsInChatCount }}</strong>
                        </div>
                    </div>

                    <div class="chat-inspector-actions">
                        <button class="btn btn-outline btn-sm" type="button" @click="markConversationRead(activeConversation.id)">
                            {{ $t('chats.markRead') }}
                        </button>
                        <button class="btn btn-outline btn-sm" type="button" @click="setLeftPaneMode('users')">
                            {{ $t('chats.newDialog') }}
                        </button>
                    </div>
                </div>

                <div class="chat-inspector-card" v-if="activeConversationPeer">
                    <h4 class="chat-inspector-card-title">{{ $t('chats.quickActions') }}</h4>
                    <div class="chat-inspector-actions">
                        <button
                            v-if="!isBlockedByMe(activeConversationPeer)"
                            class="btn btn-danger btn-sm"
                            type="button"
                            @click="blockUser(activeConversationPeer, 'temporary')"
                        >
                            {{ $t('chats.block24h') }}
                        </button>
                        <button
                            v-if="!isBlockedByMe(activeConversationPeer)"
                            class="btn btn-danger btn-sm"
                            type="button"
                            @click="blockUser(activeConversationPeer, 'permanent')"
                        >
                            {{ $t('chats.blockForever') }}
                        </button>
                        <button
                            v-if="isBlockedByMe(activeConversationPeer)"
                            class="btn btn-success btn-sm"
                            type="button"
                            @click="unblockUser(activeConversationPeer)"
                        >
                            {{ $t('chats.unblock') }}
                        </button>
                    </div>
                    <p v-if="isBlockedByMe(activeConversationPeer) && getMyBlockStatusLabel(activeConversationPeer)" class="muted">
                        {{ getMyBlockStatusLabel(activeConversationPeer) }}
                    </p>
                </div>

                <div class="chat-inspector-card">
                    <h4 class="chat-inspector-card-title">{{ $t('chats.dialogStats') }}</h4>
                    <ul class="chat-stats-list">
                        <li>
                            <span class="muted">{{ $t('chats.messagesInWindow') }}</span>
                            <strong>{{ messages.length }}</strong>
                        </li>
                        <li>
                            <span class="muted">{{ $t('chats.mediaInAttachments') }}</span>
                            <strong>{{ activeConversationMediaCount }}</strong>
                        </li>
                        <li>
                            <span class="muted">{{ $t('chats.pinned') }}</span>
                            <strong>{{ isConversationPinned(activeConversation.id) ? $t('admin.yes') : $t('admin.no') }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="chat-inspector-card">
                    <h4 class="chat-inspector-card-title">{{ $t('chats.onlineNow') }}</h4>
                    <p class="muted">
                        {{ $t('chats.onlineUsersCount', { count: siteOnlineUsers.length }) }}
                    </p>
                    <ul class="chat-online-list" v-if="siteOnlineUsers.length > 0">
                        <li
                            v-for="onlineUser in siteOnlineUsers"
                            :key="`online-user-${onlineUser.id}`"
                            class="chat-online-item"
                        >
                            <div class="chat-online-user">
                                <img
                                    v-if="avatarUrl(onlineUser)"
                                    :src="avatarUrl(onlineUser)"
                                    alt="avatar"
                                    class="avatar avatar-sm"
                                >
                                <span v-else class="avatar avatar-sm avatar-placeholder">{{ initials(onlineUser) }}</span>
                                <div class="chat-online-user-meta">
                                    <strong>{{ displayName(onlineUser) }}</strong>
                                    <p class="muted" v-if="onlineUser.nickname">@{{ onlineUser.nickname }}</p>
                                </div>
                            </div>
                            <span v-if="isUserInActiveChat(onlineUser)" class="chat-presence-pill is-in-chat">{{ $t('chats.inThisChat') }}</span>
                        </li>
                    </ul>
                </div>
            </template>

            <div class="chat-inspector-card" v-else>
                <h4 class="chat-inspector-card-title">{{ $t('chats.info') }}</h4>
                <p class="muted">{{ $t('chats.selectChatForDetails') }}</p>
            </div>

            <div class="chat-inspector-card">
                <h4 class="chat-inspector-card-title">{{ $t('chats.historyStorage') }}</h4>
                <p class="muted chat-storage-note">
                    {{ $t('chats.historyStorageNote') }}
                </p>

                <label class="chat-setting-check">
                    <input type="checkbox" v-model="chatStorageForm.save_text_messages">
                    <span>{{ $t('chats.saveTextMessages') }}</span>
                </label>
                <label class="chat-setting-check">
                    <input type="checkbox" v-model="chatStorageForm.save_media_attachments">
                    <span>{{ $t('chats.saveMediaAttachments') }}</span>
                </label>
                <label class="chat-setting-check">
                    <input type="checkbox" v-model="chatStorageForm.save_file_attachments">
                    <span>{{ $t('chats.saveFileAttachments') }}</span>
                </label>
                <label class="chat-setting-check">
                    <input type="checkbox" v-model="chatStorageForm.auto_archive_enabled">
                    <span>{{ $t('chats.enableAutoArchive') }}</span>
                </label>

                <div class="chat-setting-inline">
                    <label for="chatRetentionDays" class="muted">{{ $t('chats.retentionDays') }}</label>
                    <input
                        id="chatRetentionDays"
                        class="input-field"
                        type="number"
                        min="1"
                        max="3650"
                        step="1"
                        v-model.trim="chatStorageForm.retention_days"
                        :placeholder="$t('chats.emptyUnlimited')"
                    >
                </div>

                <div class="chat-inspector-actions">
                    <button
                        class="btn btn-primary btn-sm"
                        type="button"
                        :disabled="isSavingChatStorageSettings || isLoadingChatStorageSettings"
                        @click="saveChatStorageSettings"
                    >
                        {{ isSavingChatStorageSettings ? $t('admin.saving') : $t('chats.saveSettings') }}
                    </button>
                    <button
                        class="btn btn-outline btn-sm"
                        type="button"
                        :disabled="isSavingChatStorageSettings || isLoadingChatStorageSettings"
                        @click="resetChatStorageForm"
                    >
                        {{ $t('chats.reset') }}
                    </button>
                </div>
            </div>

            <div class="chat-inspector-card">
                <h4 class="chat-inspector-card-title">{{ $t('chats.chatArchives') }}</h4>

                <div class="chat-inspector-actions">
                    <button
                        class="btn btn-outline btn-sm"
                        type="button"
                        :disabled="archiveCreateScopeInProgress === 'all'"
                        @click="createChatArchive('all')"
                    >
                        {{ archiveCreateScopeInProgress === 'all' ? $t('chats.creating') : $t('chats.archiveAllChats') }}
                    </button>
                    <button
                        class="btn btn-outline btn-sm"
                        type="button"
                        :disabled="!activeConversation || archiveCreateScopeInProgress === 'conversation'"
                        @click="createChatArchive('conversation')"
                    >
                        {{ archiveCreateScopeInProgress === 'conversation' ? $t('chats.creating') : $t('chats.archiveCurrentChat') }}
                    </button>
                </div>

                <p v-if="isLoadingChatArchives" class="muted">{{ $t('chats.loadingArchives') }}</p>
                <p v-else-if="chatArchives.length === 0" class="muted">{{ $t('chats.noArchivesYet') }}</p>

                <ul v-else class="chat-archive-list">
                    <li v-for="archive in chatArchives" :key="archive.id" class="chat-archive-item">
                        <div class="chat-archive-head">
                            <strong class="chat-archive-title">{{ archive.title }}</strong>
                            <span class="muted">{{ formatDateTime(archive.created_at) }}</span>
                        </div>
                        <p class="muted chat-archive-meta">
                            {{ $t('chats.archiveMeta', { messages: archive.messages_count, chats: archive.conversations_count }) }}
                        </p>
                        <p v-if="archive.restored_at" class="muted chat-archive-meta">
                            {{ $t('chats.restoredAt', { date: formatDateTime(archive.restored_at) }) }}
                        </p>

                        <div class="chat-inspector-actions">
                            <button
                                class="btn btn-outline btn-sm"
                                type="button"
                                :disabled="isArchiveDownloading(archive.id)"
                                @click="downloadChatArchive(archive)"
                            >
                                {{ isArchiveDownloading(archive.id) ? $t('chats.downloading') : $t('chats.download') }}
                            </button>
                            <button
                                class="btn btn-outline btn-sm"
                                type="button"
                                :disabled="isArchiveRestoring(archive.id)"
                                @click="restoreChatArchive(archive)"
                            >
                                {{ isArchiveRestoring(archive.id) ? $t('chats.restoring') : $t('chats.restore') }}
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
import StickerPicker from '../../components/stickers/StickerPicker.vue'
import StickerRichText from '../../components/stickers/StickerRichText.vue'
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../../utils/mediaPreview'
import { stickerTextToPreview, stickerTokenFromId } from '../../data/stickerCatalog'

const CHAT_SOUND_STORAGE_KEY = 'chat_notification_settings_v1'
const CHAT_UI_STORAGE_KEY = 'chat_ui_settings_v1'
const CHAT_SHARED_SYNC_STORAGE_PREFIX = 'social.chat.shared'
const CHAT_MESSAGE_REACTION_EMOJIS = ['👍', '❤️', '🔥', '😂', '👏', '😮']
const CHAT_WIDGET_SYNC_EVENT = 'social:chat:sync'
const CHAT_WIDGET_SYNC_SOURCE_PAGE = 'chat-page'
const CHAT_WIDGET_SYNC_SOURCE_WIDGET = 'chat-widget'
const CHAT_WIDGET_SYNC_TYPE_ACTIVE_CONVERSATION = 'active-conversation'
const CHAT_WIDGET_SYNC_TYPE_CONVERSATION_READ = 'conversation-read'
const CHAT_WIDGET_SYNC_TYPE_MESSAGE_UPSERT = 'message-upsert'
const CHAT_WIDGET_SYNC_TYPE_STATE_REFRESH = 'state-refresh'
const DEFAULT_NOTIFICATION_SOUND_ID = 'beep_short'
const MAX_CUSTOM_NOTIFICATION_SOUND_BYTES = 15 * 1024 * 1024
const MAX_PERSISTED_CUSTOM_NOTIFICATION_SOUND_BYTES = 2 * 1024 * 1024
const LEGACY_NOTIFICATION_SOUND_MAP = {
    ping: 'beep_short',
    bell: 'alarm_clock',
    chime: 'pop',
}

const NOTIFICATION_SOUND_PRESETS = [
    { id: 'beep_short', labelKey: 'chats.soundBeepShort', url: '/sounds/notifications/beep_short.ogg' },
    { id: 'pop', labelKey: 'chats.soundPop', url: '/sounds/notifications/pop.ogg' },
    { id: 'swoosh', labelKey: 'chats.soundSwoosh', url: '/sounds/notifications/swoosh.ogg' },
    { id: 'cartoon_boing', labelKey: 'chats.soundBoing', url: '/sounds/notifications/cartoon_boing.ogg' },
    { id: 'wood_plank_flicks', labelKey: 'chats.soundWoodClick', url: '/sounds/notifications/wood_plank_flicks.ogg' },
    { id: 'slide_whistle', labelKey: 'chats.soundWhistle', url: '/sounds/notifications/slide_whistle.ogg' },
    { id: 'clang_and_wobble', labelKey: 'chats.soundClangWobble', url: '/sounds/notifications/clang_and_wobble.ogg' },
    { id: 'concussive_hit_guitar_boing', labelKey: 'chats.soundGuitarBoing', url: '/sounds/notifications/concussive_hit_guitar_boing.ogg' },
    { id: 'alarm_clock', labelKey: 'chats.soundAlarmClock', url: '/sounds/notifications/alarm_clock.ogg' },
    { id: 'cartoon_cowbell', labelKey: 'chats.soundCowbell', url: '/sounds/notifications/cartoon_cowbell.ogg' },
    { id: 'wood_pecker', labelKey: 'chats.soundWoodPecker', url: '/sounds/notifications/wood_pecker.ogg' },
    { id: 'medium_bell_ringing_near', labelKey: 'chats.soundBell', url: '/sounds/notifications/medium_bell_ringing_near.ogg' },
    { id: 'digital_watch_alarm_long', labelKey: 'chats.soundDigitalAlarm', url: '/sounds/notifications/digital_watch_alarm_long.ogg' },
]

const NOTIFICATION_SOUND_PRESET_IDS = new Set(NOTIFICATION_SOUND_PRESETS.map((preset) => preset.id))

export default {
    name: 'Chats',

    components: {
        MediaLightbox,
        MediaPlayer,
        StickerPicker,
        StickerRichText,
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
            sitePresenceSubscribed: false,
            siteOnlineUsers: [],
            activeConversationOnlineUsers: [],
            activeConversationPresenceChannelName: '',
            typingStateByConversation: {},
            typingExpireTimerIds: {},
            typingIdleTimerId: null,
            typingLastSentAt: 0,
            emojis: ['😀', '🔥', '❤️', '😂', '👏', '😎', '👍', '🎉', '🤝', '🤩'],
            showStickerTray: false,
            messageReactionEmojis: CHAT_MESSAGE_REACTION_EMOJIS,
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
            isRecordingVideo: false,
            isProcessingVideo: false,
            videoRecordDurationSeconds: 0,
            videoStopInProgress: false,
            maxVideoRecordDurationSeconds: 3 * 60,
            videoMediaRecorder: null,
            videoRecordStream: null,
            videoRecordTimerId: null,
            videoRecordStartedAt: null,
            videoRecordedChunks: [],
            videoRecordedMimeType: '',
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
            return NOTIFICATION_SOUND_PRESETS.map((preset) => ({
                ...preset,
                label: this.$t(preset.labelKey),
            }))
        },

        onlineUserIdsSet() {
            return new Set(this.siteOnlineUsers.map((user) => Number(user?.id)).filter((id) => Number.isFinite(id) && id > 0))
        },

        activeConversationOnlineIdsSet() {
            return new Set(this.activeConversationOnlineUsers
                .map((user) => Number(user?.id))
                .filter((id) => Number.isFinite(id) && id > 0))
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
                .filter((entry) => Number(entry.id) !== Number(this.currentUser?.id ?? 0))
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

                if (isSending && hasAttachments) {
                    return this.$t('chats.peerSendingWithAttachments', { name })
                }

                if (isSending) {
                    return this.$t('chats.peerSendingMessage', { name })
                }

                if (preview !== '') {
                    return this.$t('chats.peerTypingPreview', { name, preview })
                }

                if (hasAttachments) {
                    return this.$t('chats.peerAttachingFiles', { name })
                }

                return this.$t('chats.peerTyping', { name })
            }

            const names = entries.slice(0, 2).map((entry) => entry.display_name || this.$t('chats.participant'))
            const suffix = entries.length > 2 ? this.$t('chats.andMoreCount', { count: entries.length - 2 }) : ''

            return this.$t('chats.peopleTyping', { names: names.join(', '), suffix })
        },

        activeConversationPresenceLine() {
            if (!this.activeConversation) {
                return ''
            }

            const onlineOnSite = this.activeConversationParticipantsOnlineCount
            const onlineInChat = this.activeConversationParticipantsInChatCount

            if (this.activeConversation.type === 'direct') {
                const peer = this.activeConversationPeer
                if (!peer) {
                    return ''
                }

                const peerOnline = this.isUserOnline(peer)
                const peerInChat = this.isUserInActiveChat(peer)

                return this.$t('chats.peerStatusLine', {
                    online: peerOnline ? this.$t('chats.online') : this.$t('chats.notOnline'),
                    inChat: peerInChat ? this.$t('admin.yes') : this.$t('admin.no'),
                })
            }

            return this.$t('chats.participantsPresenceLine', {
                siteOnline: onlineOnSite,
                total: this.activeConversationParticipantsTotal,
                inChat: onlineInChat,
            })
        },

        activeConversationParticipantIds() {
            const participants = Array.isArray(this.activeConversation?.participants)
                ? this.activeConversation.participants
                : []

            return participants
                .map((participant) => Number(participant?.id))
                .filter((id) => Number.isFinite(id) && id > 0)
        },

        activeConversationParticipantsTotal() {
            return this.activeConversationParticipantIds.length
        },

        activeConversationParticipantsOnlineCount() {
            const participantIds = new Set(this.activeConversationParticipantIds)
            if (participantIds.size === 0) {
                return 0
            }

            return this.siteOnlineUsers
                .filter((user) => participantIds.has(Number(user.id)))
                .length
        },

        activeConversationParticipantsInChatCount() {
            const participantIds = new Set(this.activeConversationParticipantIds)
            if (participantIds.size === 0) {
                return 0
            }

            return this.activeConversationOnlineUsers
                .filter((user) => participantIds.has(Number(user.id)))
                .length
        },

        composerStatusLabel() {
            if (this.isSending) {
                return this.selectedFiles.length > 0
                    ? this.$t('chats.sendingMessageWithFiles')
                    : this.$t('chats.sendingMessage')
            }

            if (this.isRecordingVoice) {
                return this.$t('chats.voiceRecordingNow')
            }

            if (this.isProcessingVoice) {
                return this.$t('chats.preparingVoiceToSend')
            }

            if (this.isRecordingVideo) {
                return this.$t('chats.videoRecordingNow')
            }

            if (this.isProcessingVideo) {
                return this.$t('chats.preparingVideoToSend')
            }

            const activeTypingLine = this.activeTypingStatusLine
            if (activeTypingLine !== '') {
                return activeTypingLine
            }

            return ''
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
                return this.$t('chats.pickGlobalOrDirect')
            }

            const participantsCount = Array.isArray(this.activeConversation.participants)
                ? this.activeConversation.participants.length
                : 0

            if (this.activeConversation.is_blocked) {
                return this.$t('chats.readonlyUntilUnblock')
            }

            if (this.activeConversation.type === 'global') {
                return this.$t('chats.globalChannelParticipants', {
                    count: participantsCount > 0 ? participantsCount : this.$t('chats.many'),
                })
            }

            if (this.activeConversationPeer?.nickname) {
                return this.$t('chats.realtimeDialogWithNickname', { nickname: this.activeConversationPeer.nickname })
            }

            return this.$t('chats.directDialogParticipants', { count: participantsCount })
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

            return Math.max(
                0,
                Math.min(100, (this.videoRecordDurationSeconds / this.maxVideoRecordDurationSeconds) * 100)
            )
        }
    },

    async mounted() {
        if (typeof window !== 'undefined') {
            window.addEventListener(CHAT_WIDGET_SYNC_EVENT, this.handleChatSyncEvent)
        }
        if (typeof navigator !== 'undefined' && navigator.mediaDevices && typeof navigator.mediaDevices.addEventListener === 'function') {
            navigator.mediaDevices.addEventListener('devicechange', this.handleMediaDevicesChanged)
        }

        this.loadNotificationSettings()
        this.loadChatUiSettings()
        this.canRecordVoice = this.isVoiceRecordingSupported()
        this.canRecordVideo = this.isVideoRecordingSupported()
        await this.loadMediaInputDevices()
        await this.loadCurrentUser()
        this.syncSitePresenceChannel()
        await Promise.all([
            this.loadConversations(),
            this.loadUsers(),
            this.loadMyBlocks(),
            this.loadChatStorageSettings(),
            this.loadChatArchives(),
        ])

        if (this.conversations.length > 0) {
            const syncedConversationId = this.loadSharedSyncConversationId()
            const preferredConversation = syncedConversationId !== null
                ? this.conversations.find((conversation) => Number(conversation.id) === syncedConversationId) || null
                : null

            await this.openConversation(preferredConversation || this.conversations[0])
        }
    },

    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener(CHAT_WIDGET_SYNC_EVENT, this.handleChatSyncEvent)
        }
        if (typeof navigator !== 'undefined' && navigator.mediaDevices && typeof navigator.mediaDevices.removeEventListener === 'function') {
            navigator.mediaDevices.removeEventListener('devicechange', this.handleMediaDevicesChanged)
        }

        this.stopVoiceRecording(true)
        this.stopVideoRecording(true)
        this.stopVideoPreview()
        this.notifyTypingStopped()
        if (this.userSearchDebounceTimerId) {
            window.clearTimeout(this.userSearchDebounceTimerId)
            this.userSearchDebounceTimerId = null
        }
        this.revokeRuntimeCustomNotificationSoundUrl()
        this.unsubscribeAllChannels()
        this.unsubscribePresenceChannels()
        this.clearTypingState()
        this.clearSelectedFiles()
    },

    methods: {
        handlePreviewError(event, label = 'Preview unavailable') {
            applyImagePreviewFallback(event, label)
        },

        handlePreviewLoad(event) {
            resetImagePreviewFallback(event)
        },

        openMedia(url, alt = null) {
            this.$refs.mediaLightbox?.open(url, alt || this.$t('chats.photo'))
        },

        sharedSyncStorageKey() {
            const userId = Number(this.currentUser?.id ?? 0)
            return Number.isFinite(userId) && userId > 0
                ? `${CHAT_SHARED_SYNC_STORAGE_PREFIX}.${userId}`
                : `${CHAT_SHARED_SYNC_STORAGE_PREFIX}.guest`
        },

        loadSharedSyncConversationId() {
            if (typeof localStorage === 'undefined') {
                return null
            }

            try {
                const raw = localStorage.getItem(this.sharedSyncStorageKey())
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
                localStorage.setItem(this.sharedSyncStorageKey(), JSON.stringify({
                    activeConversationId: normalizedConversationId,
                    updatedAt: Date.now(),
                }))
            } catch (_error) {
                // Ignore write issues.
            }
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
                    source: CHAT_WIDGET_SYNC_SOURCE_PAGE,
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
                    source: CHAT_WIDGET_SYNC_SOURCE_PAGE,
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
                    source: CHAT_WIDGET_SYNC_SOURCE_PAGE,
                    type: CHAT_WIDGET_SYNC_TYPE_STATE_REFRESH,
                    conversationId,
                    sentAt: Date.now(),
                },
            }))
        },

        async handleChatSyncEvent(event) {
            const source = String(event?.detail?.source || '')
            if (source === CHAT_WIDGET_SYNC_SOURCE_PAGE || source !== CHAT_WIDGET_SYNC_SOURCE_WIDGET) {
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
                    await this.loadConversations()
                }

                this.updateConversationFromIncoming(syncedMessage, {
                    incrementUnread: false,
                })

                if (Number(this.activeConversation?.id ?? 0) === targetConversationId) {
                    this.upsertMessage(syncedMessage)
                    if (Boolean(event?.detail?.markRead)) {
                        this.setConversationReadLocally(targetConversationId)
                    }

                    this.$nextTick(() => this.scrollMessagesDown())
                }

                return
            }

            if (type === CHAT_WIDGET_SYNC_TYPE_STATE_REFRESH) {
                await this.loadConversations()

                const activeConversationId = Number(this.activeConversation?.id ?? 0)
                const shouldReloadMessages = activeConversationId > 0
                    && (!Number.isFinite(conversationId) || conversationId <= 0 || activeConversationId === conversationId)

                if (shouldReloadMessages) {
                    await this.loadMessages({
                        silentSync: true,
                    })
                }

                return
            }

            if (type !== CHAT_WIDGET_SYNC_TYPE_ACTIVE_CONVERSATION) {
                return
            }

            if (!Number.isFinite(conversationId) || conversationId <= 0) {
                return
            }

            if (Number(this.activeConversation?.id ?? 0) === conversationId) {
                return
            }

            let targetConversation = this.conversations.find((conversation) => Number(conversation.id) === conversationId) || null

            if (!targetConversation) {
                try {
                    await this.loadConversations()
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

        handleComposerInput() {
            this.notifyTypingActivity()
        },

        normalizePresenceUser(user) {
            const id = Number(user?.id ?? 0)
            if (!Number.isFinite(id) || id <= 0) {
                return null
            }

            const displayName = String(user?.display_name || user?.name || this.$t('common.user')).trim() || this.$t('common.user')

            return {
                id,
                name: String(user?.name || displayName),
                display_name: displayName,
                nickname: user?.nickname || null,
                avatar_url: user?.avatar_url || null,
                is_admin: Boolean(user?.is_admin),
            }
        },

        normalizePresenceUsers(users) {
            const byId = new Map()
            const source = Array.isArray(users) ? users : []

            for (const user of source) {
                const normalized = this.normalizePresenceUser(user)
                if (!normalized) {
                    continue
                }

                byId.set(normalized.id, normalized)
            }

            return Array.from(byId.values()).sort((first, second) => {
                return String(first.display_name).localeCompare(String(second.display_name), 'ru')
            })
        },

        syncSitePresenceChannel() {
            if (typeof window === 'undefined' || !window.Echo || this.sitePresenceSubscribed) {
                return
            }

            try {
                window.Echo.join('site.online')
                    .here((users) => {
                        this.siteOnlineUsers = this.normalizePresenceUsers(users)
                    })
                    .joining((user) => {
                        const incoming = this.normalizePresenceUser(user)
                        if (!incoming) {
                            return
                        }

                        const next = [...this.siteOnlineUsers.filter((item) => Number(item.id) !== incoming.id), incoming]
                        this.siteOnlineUsers = this.normalizePresenceUsers(next)
                    })
                    .leaving((user) => {
                        const userId = Number(user?.id ?? 0)
                        if (!Number.isFinite(userId) || userId <= 0) {
                            return
                        }

                        this.siteOnlineUsers = this.siteOnlineUsers.filter((item) => Number(item.id) !== userId)
                    })

                this.sitePresenceSubscribed = true
            } catch (error) {
                this.sitePresenceSubscribed = false
            }
        },

        syncActiveConversationPresenceChannel() {
            if (typeof window === 'undefined' || !window.Echo) {
                this.activeConversationOnlineUsers = []
                this.activeConversationPresenceChannelName = ''
                return
            }

            const nextConversationId = Number(this.activeConversation?.id ?? 0)
            const nextChannelName = Number.isFinite(nextConversationId) && nextConversationId > 0
                ? `chat.presence.${nextConversationId}`
                : ''

            if (this.activeConversationPresenceChannelName && this.activeConversationPresenceChannelName !== nextChannelName) {
                window.Echo.leave(this.activeConversationPresenceChannelName)
                this.activeConversationPresenceChannelName = ''
                this.activeConversationOnlineUsers = []
            }

            if (nextChannelName === '' || this.activeConversationPresenceChannelName === nextChannelName) {
                return
            }

            try {
                window.Echo.join(nextChannelName)
                    .here((users) => {
                        this.activeConversationOnlineUsers = this.normalizePresenceUsers(users)
                    })
                    .joining((user) => {
                        const incoming = this.normalizePresenceUser(user)
                        if (!incoming) {
                            return
                        }

                        const next = [...this.activeConversationOnlineUsers.filter((item) => Number(item.id) !== incoming.id), incoming]
                        this.activeConversationOnlineUsers = this.normalizePresenceUsers(next)
                    })
                    .leaving((user) => {
                        const userId = Number(user?.id ?? 0)
                        if (!Number.isFinite(userId) || userId <= 0) {
                            return
                        }

                        this.activeConversationOnlineUsers = this.activeConversationOnlineUsers
                            .filter((item) => Number(item.id) !== userId)
                    })

                this.activeConversationPresenceChannelName = nextChannelName
            } catch (error) {
                this.activeConversationPresenceChannelName = ''
                this.activeConversationOnlineUsers = []
            }
        },

        unsubscribePresenceChannels() {
            if (typeof window !== 'undefined' && window.Echo) {
                if (this.sitePresenceSubscribed) {
                    window.Echo.leave('site.online')
                }

                if (this.activeConversationPresenceChannelName) {
                    window.Echo.leave(this.activeConversationPresenceChannelName)
                }
            }

            this.sitePresenceSubscribed = false
            this.activeConversationPresenceChannelName = ''
            this.siteOnlineUsers = []
            this.activeConversationOnlineUsers = []
        },

        isUserOnline(user) {
            const userId = Number(user?.id ?? 0)
            if (!Number.isFinite(userId) || userId <= 0) {
                return false
            }

            return this.onlineUserIdsSet.has(userId)
        },

        isUserInActiveChat(user) {
            const userId = Number(user?.id ?? 0)
            if (!Number.isFinite(userId) || userId <= 0) {
                return false
            }

            return this.activeConversationOnlineIdsSet.has(userId)
        },

        isConversationPeerOnline(conversation) {
            const peer = this.conversationPeer(conversation)

            return Boolean(peer && this.isUserOnline(peer))
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
            if (typeof window === 'undefined' || !window.Echo || !this.currentUser) {
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
                user_id: Number(this.currentUser.id),
                display_name: this.displayName(this.currentUser),
                is_typing: Boolean(isTyping),
                has_attachments: hasAttachments,
                is_sending: Boolean(options?.isSending),
                preview: Boolean(isTyping) ? this.normalizeTypingPreview(this.messageBody) : '',
                at: Date.now(),
            }

            try {
                window.Echo.private(channelName).whisper('typing', payload)
            } catch (error) {
                // Ignore whisper transport glitches to avoid blocking chat input.
            }
        },

        notifyTypingActivity(options = {}) {
            if (this.isComposerDisabled || !this.activeConversation) {
                return
            }

            const hasDraft = this.messageBody.trim() !== ''
                || this.selectedFiles.length > 0
                || this.isRecordingVoice
                || this.isProcessingVoice

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

            if (!Number.isFinite(senderId) || senderId <= 0 || senderId === Number(this.currentUser?.id ?? 0)) {
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
                alert(this.resolveApiMessage(error, this.$t('chats.loadStorageSettingsFailed')))
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
                    alert(this.$t('chats.retentionRangeError', { max: maxRetentionDays }))
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
                alert(this.resolveApiMessage(error, this.$t('chats.saveStorageSettingsFailed')))
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
                title: String(archive?.title || this.$t('chats.archiveWithId', { id: archiveId })),
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
                alert(this.resolveApiMessage(error, this.$t('chats.loadArchivesFailed')))
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
                alert(this.resolveApiMessage(error, this.$t('chats.createArchiveFailed')))
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
                alert(this.resolveApiMessage(error, this.$t('chats.downloadArchiveFailed')))
            } finally {
                this.downloadingArchiveIds = this.downloadingArchiveIds.filter((id) => id !== archiveId)
            }
        },

        async restoreChatArchive(archive) {
            const archiveId = Number(archive?.id)
            if (!Number.isInteger(archiveId) || archiveId <= 0 || this.isArchiveRestoring(archiveId)) {
                return
            }

            const shouldRestore = window.confirm(this.$t('chats.confirmRestoreArchive'))
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
                alert(this.resolveApiMessage(error, this.$t('chats.restoreArchiveFailed')))
            } finally {
                this.restoringArchiveIds = this.restoringArchiveIds.filter((id) => id !== archiveId)
            }
        },

        displayName(user) {
            return user?.display_name || user?.name || this.$t('common.user')
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
                return this.$t('chats.noMessagesYet')
            }

            const message = conversation.last_message
            const author = this.isMine(message)
                ? this.$t('chats.youShort')
                : this.displayName(message.user)
            const text = message.body
                ? stickerTextToPreview(message.body)
                : this.attachmentSummary(message)

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
                } else {
                    this.activeConversation = null
                    this.persistSharedSyncConversationId(null)
                }
            }

            this.syncActiveConversationPresenceChannel()
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
                    per_page: 100,
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
                alert(this.resolveApiMessage(error, this.$t('chats.openDirectChatFailed')))
            }
        },

        async blockUser(user, mode) {
            try {
                const payload = mode === 'temporary'
                    ? {mode: 'temporary', duration_minutes: 24 * 60}
                    : {mode: 'permanent'}

                await axios.post(`/api/users/${user.id}/block`, payload)

                await Promise.all([this.loadUsers(), this.loadMyBlocks(), this.loadConversations()])
                this.notifyChatSyncStateRefresh()
            } catch (error) {
                alert(this.resolveApiMessage(error, this.$t('chats.blockUserFailed')))
            }
        },

        async unblockUser(user) {
            try {
                await axios.delete(`/api/users/${user.id}/block`)
                await Promise.all([this.loadUsers(), this.loadMyBlocks(), this.loadConversations()])
                this.notifyChatSyncStateRefresh()
            } catch (error) {
                alert(this.resolveApiMessage(error, this.$t('chats.unblockUserFailed')))
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
                return this.$t('chats.blockTypePermanent')
            }

            return this.$t('chats.blockTypeUntil', { date: this.formatDateTime(block.expires_at) })
        },

        async openConversation(conversation, options = {}) {
            const previousConversationId = Number(this.activeConversation?.id ?? 0)
            const nextConversationId = Number(conversation?.id ?? 0)
            if (!Number.isFinite(nextConversationId) || nextConversationId <= 0) {
                return
            }

            const silentSync = Boolean(options?.silentSync)
            if (previousConversationId > 0 && previousConversationId !== nextConversationId) {
                this.notifyTypingStopped(previousConversationId)
                this.stopVoiceRecording(true)
                this.stopVideoRecording(true)
                this.stopVideoPreview()
            }

            this.activeConversation = conversation
            this.persistSharedSyncConversationId(nextConversationId)
            this.leftPaneMode = 'conversations'
            this.showStickerTray = false
            this.saveChatUiSettings()
            this.messageSearch = ''
            this.syncActiveConversationPresenceChannel()
            await this.loadMessages({silentSync})

            if (!silentSync) {
                this.notifyChatSync(CHAT_WIDGET_SYNC_TYPE_ACTIVE_CONVERSATION, nextConversationId)
            }
        },

        async loadMessages(options = {}) {
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
                await this.markConversationRead(this.activeConversation.id, {
                    silentSync: Boolean(options?.silentSync),
                })
                this.$nextTick(() => this.scrollMessagesDown())
            } catch (error) {
                this.messages = []
                if (error.response?.status === 403) {
                    alert(this.$t('chats.noAccessToChat'))
                }
            }
        },

        async markConversationRead(conversationId, options = {}) {
            if (!conversationId) {
                return
            }

            const silentSync = Boolean(options?.silentSync)
            try {
                await axios.post(`/api/chats/${conversationId}/read`)
                this.setConversationReadLocally(conversationId)

                if (!silentSync) {
                    this.notifyChatSync(CHAT_WIDGET_SYNC_TYPE_CONVERSATION_READ, conversationId)
                }
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
            this.notifyTypingActivity({ immediate: true })
        },

        toggleStickerTray() {
            if (this.isComposerDisabled) {
                this.showStickerTray = false
                return
            }

            this.showStickerTray = !this.showStickerTray
        },

        insertSticker(sticker) {
            const token = stickerTokenFromId(sticker?.id)
            if (token === '' || this.isComposerDisabled) {
                return
            }

            const suffix = this.messageBody.trim() === '' ? '' : ' '
            this.messageBody = `${this.messageBody}${suffix}${token}`
            this.showStickerTray = false
            this.notifyTypingActivity({ immediate: true })
        },

        openFileDialog() {
            this.$refs.messageFiles.click()
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
                            // Labels can still be unavailable without permission.
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
            const hasAudioContext = typeof window !== 'undefined'
                && Boolean(window.AudioContext || window.webkitAudioContext)

            return hasAudioContext
                && typeof window !== 'undefined'
                && typeof window.MediaRecorder !== 'undefined'
                && typeof navigator !== 'undefined'
                && Boolean(navigator.mediaDevices?.getUserMedia)
        },

        isVideoRecordingSupported() {
            return typeof window !== 'undefined'
                && typeof window.MediaRecorder !== 'undefined'
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

            const recorderAndPlayerSupported = candidates.find((candidate) => {
                return window.MediaRecorder.isTypeSupported(candidate) && canPlay(candidate)
            })

            return recorderAndPlayerSupported || ''
        },

        buildVoiceCaptureConstraints() {
            const selectedAudioInputId = String(this.selectedAudioInputId || '').trim()

            return {
                audio: {
                    ...(selectedAudioInputId !== '' ? { deviceId: { exact: selectedAudioInputId } } : {}),
                    channelCount: { ideal: 1 },
                    echoCancellation: { ideal: true },
                    noiseSuppression: { ideal: true },
                    autoGainControl: { ideal: true },
                    sampleRate: { ideal: 48000 },
                    sampleSize: { ideal: 16 },
                },
            }
        },

        buildVideoCaptureConstraints() {
            const selectedAudioInputId = String(this.selectedAudioInputId || '').trim()
            const selectedVideoInputId = String(this.selectedVideoInputId || '').trim()

            return {
                video: {
                    ...(selectedVideoInputId !== '' ? { deviceId: { exact: selectedVideoInputId } } : { facingMode: 'user' }),
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    frameRate: { ideal: 30, max: 30 },
                },
                audio: {
                    ...(selectedAudioInputId !== '' ? { deviceId: { exact: selectedAudioInputId } } : {}),
                    echoCancellation: { ideal: true },
                    noiseSuppression: { ideal: true },
                    autoGainControl: { ideal: true },
                },
            }
        },

        async startVoiceRecording() {
            if (!this.canRecordVoice
                || this.isComposerDisabled
                || this.isRecordingVoice
                || this.isProcessingVoice
                || this.voiceStopInProgress
                || this.isRecordingVideo
                || this.isProcessingVideo
                || this.videoStopInProgress) {
                return
            }

            try {
                let stream = null
                try {
                    stream = await navigator.mediaDevices.getUserMedia(this.buildVoiceCaptureConstraints())
                } catch (error) {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                }
                await this.loadMediaInputDevices()
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
                this.notifyTypingActivity({ immediate: true })
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
                                alert(this.$t('chats.voiceLimitReached', { limit: this.formattedVoiceRecordDurationLimit }))
                            })
                    }
                }, 1000)
            } catch (error) {
                this.resetVoicePcmCaptureState(true)
                this.stopVoiceRecording(true)
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
                    this.notifyTypingActivity({ immediate: true })
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
                        alert(this.$t('chats.voiceNotRecordedRetry'))
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
                        alert(this.$t('chats.voiceNotRecordedRetry'))
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
                    this.notifyTypingActivity({ immediate: true })
                }
            } finally {
                this.voiceStopInProgress = false
            }
        },

        async startVideoRecording() {
            if (!this.canRecordVideo
                || this.isComposerDisabled
                || this.isRecordingVideo
                || this.isProcessingVideo
                || this.videoStopInProgress
                || this.isRecordingVoice
                || this.isProcessingVoice
                || this.voiceStopInProgress) {
                return
            }

            try {
                this.stopVideoPreview()
                let stream = null
                try {
                    stream = await navigator.mediaDevices.getUserMedia(this.buildVideoCaptureConstraints())
                } catch (error) {
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({video: true, audio: true})
                    } catch (fallbackError) {
                        stream = await navigator.mediaDevices.getUserMedia({video: true, audio: false})
                    }
                }
                await this.loadMediaInputDevices()

                const recordStartedAt = Date.now()
                this.isProcessingVideo = false
                this.videoRecordDurationSeconds = 0
                this.videoRecordStartedAt = recordStartedAt
                this.videoRecordStream = stream
                this.videoMediaRecorder = null
                this.videoRecordedChunks = []
                this.videoRecordedMimeType = ''
                this.isRecordingVideo = true
                this.notifyTypingActivity({ immediate: true })

                if (typeof window !== 'undefined' && typeof window.MediaRecorder !== 'undefined') {
                    const options = {}
                    const preferredMimeType = this.getPreferredVideoMimeType()
                    if (preferredMimeType !== '') {
                        options.mimeType = preferredMimeType
                    }

                    try {
                        const recorder = new MediaRecorder(stream, options)
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
                    } catch (error) {
                        this.videoMediaRecorder = null
                    }
                }

                this.videoRecordTimerId = window.setInterval(() => {
                    this.videoRecordDurationSeconds += 1

                    if (this.videoRecordDurationSeconds >= this.maxVideoRecordDurationSeconds) {
                        this.stopVideoRecording(false)
                            .finally(() => {
                                alert(this.$t('chats.videoLimitReached', { limit: this.formattedVideoRecordDurationLimit }))
                            })
                    }
                }, 1000)
            } catch (error) {
                await this.stopVideoRecording(true)
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
                    if (stream) {
                        this.stopVideoRecordStreamTracks(stream)
                    }
                    this.videoRecordStream = null
                    this.videoMediaRecorder = null
                    this.videoRecordedChunks = []
                    this.videoRecordedMimeType = ''
                    this.videoRecordStartedAt = null
                    this.isProcessingVideo = false
                    this.notifyTypingActivity({ immediate: true })
                    return
                }

                this.isProcessingVideo = true

                try {
                    if (recorder && recorder.state !== 'inactive') {
                        if (typeof recorder.requestData === 'function') {
                            try {
                                recorder.requestData()
                            } catch (_error) {
                                // Ignore data flush errors.
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

                        if (this.videoRecordedChunks.length > 0) {
                            const mimeType = this.videoRecordedMimeType || recorder.mimeType || ''
                            appended = this.appendRecordedVideo(this.videoRecordedChunks, mimeType, recordedDurationMs)
                        }
                    }

                    if (!appended && this.videoRecordedChunks.length > 0) {
                        const mimeType = this.videoRecordedMimeType || recorder?.mimeType || ''
                        appended = this.appendRecordedVideo(this.videoRecordedChunks, mimeType, recordedDurationMs)
                    }

                    if (!appended) {
                        alert(this.$t('chats.videoNotRecordedRetry'))
                    }
                } catch (error) {
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
                    this.notifyTypingActivity({ immediate: true })
                }
            } finally {
                this.videoStopInProgress = false
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
            if (!normalized) {
                return ''
            }

            const [baseType] = normalized.split(';')

            return baseType?.trim() || ''
        },

        normalizeRecordedVideoMimeType(mimeType) {
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
                alert(this.$t('chats.voiceEmptyRetry'))
                return false
            }

            const extension = this.fileExtensionFromMime(blobType)
            const timestamp = Date.now()
            const file = new File([blob], `voice-${timestamp}.${extension}`, { type: blobType })
            const key = `voice-${timestamp}-${Math.random().toString(36).slice(2)}`
            this.appendVoiceFileToComposer(file, key, file.type || blobType || '')

            return true
        },

        appendRecordedVideo(chunks, mimeType, recordedDurationMs = 0) {
            const blobType = this.normalizeRecordedVideoMimeType(mimeType)
            const blob = blobType !== ''
                ? new Blob(chunks, { type: blobType })
                : new Blob(chunks)

            if (blob.size === 0) {
                alert(this.$t('chats.videoEmptyRetry'))
                return false
            }

            const extension = this.fileExtensionFromMime(blobType)
            const timestamp = Date.now()
            const file = new File([blob], `video-${timestamp}.${extension}`, { type: blobType || 'video/webm' })
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
            this.notifyTypingActivity({ immediate: true })
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
            this.notifyTypingActivity({ immediate: true })
        },

        clearSelectedFiles() {
            for (const preview of this.selectedFilePreviews) {
                URL.revokeObjectURL(preview.url)
            }

            this.selectedFiles = []
            this.selectedFilePreviews = []
            this.notifyTypingActivity({ immediate: true })
        },

        async sendMessage() {
            if (!this.activeConversation
                || !this.canSendCurrentMessage
                || this.activeConversation.is_blocked
                || this.isRecordingVoice
                || this.isProcessingVoice
                || this.voiceStopInProgress
                || this.isRecordingVideo
                || this.isProcessingVideo
                || this.videoStopInProgress) {
                return
            }

            this.isSending = true
            this.notifyTypingActivity({ immediate: true, isSending: true })
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

                const normalizedMessage = this.normalizeMessage(response.data.data)
                this.upsertMessage(normalizedMessage)
                this.messageBody = ''
                this.clearSelectedFiles()
                this.updateConversationFromIncoming(normalizedMessage)
                this.notifyChatSyncMessage(normalizedMessage, {
                    markRead: true,
                })
                this.$nextTick(() => this.scrollMessagesDown())
                this.notifyTypingStopped()
            } catch (error) {
                if (error.response?.status === 423) {
                    await this.loadConversations()
                }
                alert(this.resolveApiMessage(error, this.$t('chats.sendMessageFailed')))
                this.notifyTypingActivity({ immediate: true })
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
                    .listenForWhisper('typing', (payload) => {
                        this.handleTypingWhisper(conversation.id, payload)
                    })

                this.subscribedChannels[conversation.id] = channelName
            }
        },

        unsubscribeAllChannels() {
            if (!window.Echo) {
                this.subscribedChannels = {}
                this.clearTypingState()
                return
            }

            for (const channelName of Object.values(this.subscribedChannels)) {
                window.Echo.leave(channelName)
            }

            this.subscribedChannels = {}
            this.clearTypingState()
        },

        handleIncomingMessage(payload) {
            const normalizedPayload = this.normalizeMessage(payload)
            const mine = this.isMine(payload)
            const isActiveConversation = Boolean(this.activeConversation && normalizedPayload.conversation_id === this.activeConversation.id)
            const senderId = Number(normalizedPayload?.user?.id ?? 0)
            if (senderId > 0) {
                this.removeTypingStateEntry(normalizedPayload.conversation_id, senderId)
            }

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
                return this.$t('chats.message')
            }

            const audioCount = attachments.filter((item) => item.type === 'audio').length
            const videoCount = attachments.filter((item) => item.type === 'video').length
            const fileCount = attachments.filter((item) => item.type === 'file').length
            const mediaCount = count - audioCount - videoCount - fileCount

            const parts = []
            if (audioCount > 0) {
                parts.push(audioCount === 1
                    ? this.$t('chats.voiceSingle')
                    : this.$t('chats.voiceCount', { count: audioCount }))
            }
            if (videoCount > 0) {
                parts.push(videoCount === 1
                    ? this.$t('chats.videoSingle')
                    : this.$t('chats.videoCount', { count: videoCount }))
            }
            if (mediaCount > 0) {
                parts.push(mediaCount === 1
                    ? this.$t('chats.mediaSingle')
                    : this.$t('chats.mediaCount', { count: mediaCount }))
            }
            if (fileCount > 0) {
                parts.push(fileCount === 1
                    ? this.$t('chats.file')
                    : this.$t('chats.fileCount', { count: fileCount }))
            }

            return parts.join(' · ') || this.$t('chats.attachmentsCount', { count })
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
                alert(this.resolveApiMessage(error, this.$t('chats.updateReactionFailed')))
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

            const shouldDelete = window.confirm(this.$t('chats.confirmDeleteMessage'))
            if (!shouldDelete) {
                return
            }

            this.deletingMessageIds = [...this.deletingMessageIds, messageId]

            try {
                await axios.delete(`/api/chats/${this.activeConversation.id}/messages/${messageId}`)
                this.removeMessageLocally(messageId)
                await this.loadConversations()
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

                await this.loadConversations()
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
                alert(this.$t('chats.onlyAudioFileAllowed'))
                return
            }

            if (file.size > MAX_CUSTOM_NOTIFICATION_SOUND_BYTES) {
                alert(this.$t('chats.soundFileTooLarge'))
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
                alert(this.$t('chats.soundFileLoadedRuntimeOnly'))
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
