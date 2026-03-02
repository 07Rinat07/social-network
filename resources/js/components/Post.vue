<template>
    <article :class="postCardClasses">
        <header class="post-head">
            <div class="post-author-row">
                <router-link class="post-avatar-link" :to="{name: 'user.show', params: {id: post.user.id}}">
                    <img
                        v-if="avatarUrl(post.user)"
                        class="avatar post-avatar"
                        :src="avatarUrl(post.user)"
                        :alt="displayName(post.user)"
                        @error="onAvatarImageError"
                    >
                    <span v-else class="avatar post-avatar avatar-placeholder">{{ initials(post.user) }}</span>
                </router-link>

                <div class="post-author-details">
                    <h3 class="post-title">{{ post.title }}</h3>
                    <router-link class="post-author" :to="{name: 'user.show', params: {id: post.user.id}}">
                        {{ displayName(post.user) }}
                    </router-link>
                </div>
            </div>
            <div class="post-meta">
                <span class="post-date">{{ post.date }}</span>
                <span class="post-views-badge">
                    <span class="post-views-icon" aria-hidden="true">👁</span>
                    <span>{{ $t('post.viewsCounter', { count: post.views_count ?? 0 }) }}</span>
                </span>
            </div>
        </header>

        <StickerRichText
            as="p"
            class="post-content"
            :text="post.content"
        ></StickerRichText>

        <div :class="mediaGridClasses" v-if="normalizedMedia.length > 0">
            <template v-for="media in normalizedMedia" :key="`post-media-${post.id}-${media.id ?? media.url}`">
                <div class="post-media-item">
                    <button
                        v-if="media.type === 'image'"
                        type="button"
                        class="media-open-btn"
                        @click="openMedia(media.url, post.title)"
                    >
                        <img
                            class="media-preview"
                            :src="media.url"
                            :alt="post.title"
                            loading="lazy"
                            decoding="async"
                            @error="handlePreviewError($event, post.title)"
                            @load="handlePreviewLoad"
                        >
                    </button>
                    <div v-else :class="mediaVideoContainerClasses">
                        <div class="media-video-actions">
                            <button
                                type="button"
                                class="btn btn-outline btn-sm media-video-action-btn"
                                @click="openVideoTheater(media, post.title)"
                            >
                                {{ $t('common.openTheater') }}
                            </button>
                            <a :href="media.url" :download="mediaDownloadName(media)" class="btn btn-outline btn-sm media-video-action-btn" target="_blank">
                                {{ mediaDownloadLabel(media) }}
                            </a>
                        </div>
                        <MediaPlayer
                            ref="postMediaPlayers"
                            type="video"
                            :src="media.url"
                            :mime-type="media.mime_type"
                            :preload="mediaPlayerPreload(media)"
                            player-class="media-video"
                            :shell-class="mediaPlayerShellClass(media)"
                            @playbackstate="handleInlineMediaPlaybackState(media, 'inline', $event)"
                            @enterfullscreen="handleInlineMediaFullscreenEnter(media, 'inline')"
                        ></MediaPlayer>
                    </div>
                    <button
                        v-if="media.can_delete"
                        type="button"
                        class="btn btn-danger btn-sm post-media-remove-btn"
                        @click.prevent="removeMedia(post, media)"
                    >
                        {{ $t('post.removeFile') }}
                    </button>
                </div>
            </template>
        </div>

        <section v-if="post.reposted_post" class="repost-box">
            <strong>{{ $t('post.originalRepost') }}</strong>
            <p style="margin: 0;"><strong>{{ post.reposted_post.title }}</strong></p>
            <router-link class="post-author" :to="{name: 'user.show', params: {id: post.reposted_post.user.id}}">
                {{ displayName(post.reposted_post.user) }}
            </router-link>
            <StickerRichText
                as="p"
                style="margin: 0;"
                :text="post.reposted_post.content"
            ></StickerRichText>

            <div :class="mediaGridClasses" v-if="normalizedRepostMedia.length > 0">
                <template v-for="media in normalizedRepostMedia" :key="`repost-media-${post.id}-${media.id ?? media.url}`">
                    <button
                        v-if="media.type === 'image'"
                        type="button"
                        class="media-open-btn"
                        @click="openMedia(media.url, post.reposted_post.title)"
                    >
                        <img
                            class="media-preview"
                            :src="media.url"
                            :alt="post.reposted_post.title"
                            loading="lazy"
                            decoding="async"
                            @error="handlePreviewError($event, post.reposted_post.title)"
                            @load="handlePreviewLoad"
                        >
                    </button>
                    <div v-else :class="mediaVideoContainerClasses">
                        <div class="media-video-actions">
                            <button
                                type="button"
                                class="btn btn-outline btn-sm media-video-action-btn"
                                @click="openVideoTheater(media, post.reposted_post.title)"
                            >
                                {{ $t('common.openTheater') }}
                            </button>
                            <a :href="media.url" :download="mediaDownloadName(media)" class="btn btn-outline btn-sm media-video-action-btn" target="_blank">
                                {{ mediaDownloadLabel(media) }}
                            </a>
                        </div>
                        <MediaPlayer
                            ref="postMediaPlayers"
                            type="video"
                            :src="media.url"
                            :mime-type="media.mime_type"
                            :preload="mediaPlayerPreload(media)"
                            player-class="media-video"
                            :shell-class="mediaPlayerShellClass(media)"
                            @playbackstate="handleInlineMediaPlaybackState(media, 'repost-inline', $event)"
                            @enterfullscreen="handleInlineMediaFullscreenEnter(media, 'repost-inline')"
                        ></MediaPlayer>
                    </div>
                </template>
            </div>
        </section>

        <div class="post-actions">
            <button class="icon-btn" :class="{'active': post.is_liked}" @click.prevent="toggleLike(post)">
                ❤️ {{ post.likes_count }}
            </button>
            <button v-if="post.is_liked" class="icon-btn" @click.prevent="removeLike(post)">
                ❌ {{ $t('post.removeLike') }}
            </button>
            <button class="icon-btn" :disabled="isPersonal()" @click.prevent="toggleRepostForm">
                🔁 {{ post.reposted_by_posts_count }}
            </button>
            <button class="icon-btn" :class="{'active': isCommentsOpened}" @click.prevent="toggleComments(post)">
                💬 {{ post.comments_count }}
            </button>
        </div>

        <div v-if="isRepostOpened" class="repost-box">
            <input v-model.trim="title" class="input-field" type="text" :placeholder="$t('post.repostTitlePlaceholder')">
            <textarea
                v-model.trim="content"
                class="textarea-field"
                :placeholder="$t('post.repostCommentPlaceholder')"
                @input="handleRepostInput"
            ></textarea>
            <button class="btn btn-outline btn-sm" type="button" @click.prevent="toggleRepostStickerTray">
                {{ showRepostStickerTray ? $t('post.hideStickers') : $t('post.stickers') }}
            </button>
            <div v-if="showRepostStickerTray" class="chat-sticker-tray">
                <StickerPicker
                    :category-label="$t('radio.genreFilterLabel')"
                    @select="insertRepostSticker"
                ></StickerPicker>
            </div>
            <button class="btn btn-primary" @click.prevent="repost(post)">{{ $t('post.publishRepost') }}</button>
        </div>

        <div class="comments-box">
            <div class="form-grid">
                <div v-if="comment" class="muted" style="font-size: 0.82rem;">
                    {{ $t('post.replyToUser', { name: displayName(comment.user) }) }}
                    <button class="btn btn-outline btn-sm" style="margin-left: 0.5rem;" @click.prevent="comment = null">{{ $t('post.cancelReply') }}</button>
                </div>

                <input
                    v-model.trim="body"
                    class="input-field"
                    type="text"
                    :placeholder="$t('post.commentPlaceholder')"
                    @input="handleCommentInput"
                >

                <div class="emoji-row">
                    <button v-for="emoji in emojis" :key="emoji" type="button" class="emoji-btn" @click="appendEmoji(emoji)">
                        {{ emoji }}
                    </button>
                </div>

                <button class="btn btn-outline btn-sm" type="button" @click="toggleCommentStickerTray">
                    {{ showCommentStickerTray ? $t('post.hideStickers') : $t('post.stickers') }}
                </button>

                <div v-if="showCommentStickerTray" class="chat-sticker-tray">
                    <StickerPicker
                        :category-label="$t('radio.genreFilterLabel')"
                        @select="insertCommentSticker"
                    ></StickerPicker>
                </div>

                <button class="btn btn-sun" @click.prevent="storeComment(post)">{{ $t('post.sendComment') }}</button>
            </div>

            <div v-if="isCommentsOpened">
                <div v-if="comments.length === 0" class="muted">{{ $t('post.noComments') }}</div>

                <div v-for="commentItem in comments" :key="commentItem.id" class="comment-item">
                    <div class="comment-head">
                        <span>{{ displayName(commentItem.user) }}</span>
                        <span>{{ commentItem.date }}</span>
                    </div>
                    <p class="comment-body">
                        <strong v-if="commentItem.answered_for_user" style="color: var(--accent-strong);">@{{ commentItem.answered_for_user }} </strong>
                        <StickerRichText as="span" :text="commentItem.body"></StickerRichText>
                    </p>
                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                        <button class="btn btn-outline btn-sm" @click="setParentId(commentItem)">{{ $t('post.reply') }}</button>
                        <button
                            v-if="commentItem.can_delete"
                            class="btn btn-danger btn-sm"
                            @click.prevent="removeComment(post, commentItem)"
                        >
                            {{ $t('post.deleteComment') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <MediaLightbox ref="mediaLightbox"></MediaLightbox>
        <PostMediaTheater ref="postMediaTheater"></PostMediaTheater>
    </article>
</template>

<script>
import MediaLightbox from './MediaLightbox.vue'
import PostMediaTheater from './PostMediaTheater.vue'
import MediaPlayer from './MediaPlayer.vue'
import StickerPicker from './stickers/StickerPicker.vue'
import StickerRichText from './stickers/StickerRichText.vue'
import { ANALYTICS_EVENTS, ANALYTICS_FEATURES, createAnalyticsSessionId, reportAnalyticsEvent } from '../utils/analyticsTracker.mjs'
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../utils/mediaPreview'
import {
    replaceMarkedEmojiWithStickerTokens,
    replaceStickerTokensWithMarkedEmoji,
    stickerMarkedEmojiFromId,
    stickerTokenFromId,
} from '../data/stickerCatalog'

export default {
    name: 'Post',

    props: {
        post: {
            type: Object,
            required: true,
        },
        displayMode: {
            type: String,
            default: 'default',
            validator(value) {
                return ['default', 'carousel-modal'].includes(value)
            },
        },
    },

    components: {
        MediaLightbox,
        PostMediaTheater,
        MediaPlayer,
        StickerPicker,
        StickerRichText,
    },

    data() {
        return {
            title: '',
            content: '',
            body: '',
            isRepostOpened: false,
            comments: [],
            isCommentsOpened: false,
            commentsLoaded: false,
            comment: null,
            emojis: ['🔥', '👍', '❤️', '👏', '😂', '😎'],
            showCommentStickerTray: false,
            showRepostStickerTray: false,
            failedAvatarUrls: {},
            videoAnalyticsSessions: {},
        }
    },

    computed: {
        isCarouselModalDisplay() {
            return this.displayMode === 'carousel-modal'
        },

        postCardClasses() {
            return {
                'post-card': true,
                'post-card--carousel-modal': this.isCarouselModalDisplay,
            }
        },

        mediaGridClasses() {
            return {
                'media-grid': true,
                'media-grid--carousel-modal': this.isCarouselModalDisplay,
            }
        },

        mediaVideoContainerClasses() {
            return {
                'media-video-container': true,
                'media-video-container--carousel-modal': this.isCarouselModalDisplay,
            }
        },

        normalizedMedia() {
            if (Array.isArray(this.post.media) && this.post.media.length > 0) {
                return this.post.media
            }

            if (this.post.image_url) {
                return [{id: `legacy-${this.post.id}`, type: 'image', url: this.post.image_url}]
            }

            return []
        },

        normalizedRepostMedia() {
            if (!this.post.reposted_post) {
                return []
            }

            if (Array.isArray(this.post.reposted_post.media) && this.post.reposted_post.media.length > 0) {
                return this.post.reposted_post.media
            }

            if (this.post.reposted_post.image_url) {
                return [{id: `legacy-repost-${this.post.id}`, type: 'image', url: this.post.reposted_post.image_url}]
            }

            return []
        },
    },

    mounted() {
        this.markViewed()
    },

    beforeUnmount() {
        this.finalizeAllInlineMediaAnalytics()
    },

    methods: {
        handlePreviewError(event, label = '') {
            applyImagePreviewFallback(event, this.previewFallbackLabel(label))
        },

        handlePreviewLoad(event) {
            resetImagePreviewFallback(event)
        },

        displayName(user) {
            return user?.display_name || user?.name || this.$t('common.user')
        },

        openMedia(url, alt = '') {
            this.$refs.mediaLightbox?.open(url, this.mediaAltText(alt))
        },

        mediaPlayerPreload(media) {
            if (this.isCarouselModalDisplay && media?.type === 'video') {
                return 'auto'
            }

            return 'none'
        },

        mediaPlayerShellClass(media) {
            if (this.isCarouselModalDisplay && media?.type === 'video') {
                return 'media-player-shell--carousel-modal'
            }

            return ''
        },

        inlineVideoAnalyticsKey(media, scope = 'inline') {
            const mediaId = Number.isInteger(Number(media?.id)) ? Number(media.id) : 0
            const source = String(media?.url || '').trim()
            return [String(this.post?.id || 'post'), String(scope || 'inline'), mediaId > 0 ? `id-${mediaId}` : source]
                .filter(Boolean)
                .join('::')
        },

        ensureInlineVideoAnalyticsSession(media, scope = 'inline') {
            const key = this.inlineVideoAnalyticsKey(media, scope)
            const existing = this.videoAnalyticsSessions[key]
            if (existing) {
                return existing
            }

            const next = {
                sessionId: createAnalyticsSessionId(`video-${scope}`),
                watchSeconds: 0,
                lastTime: 0,
                maxTime: 0,
                duration: 0,
                completed: false,
                mediaId: Number.isInteger(Number(media?.id)) ? Number(media.id) : null,
                title: String(this.post?.title || '').trim(),
                url: String(media?.url || '').trim(),
            }

            this.videoAnalyticsSessions = {
                ...this.videoAnalyticsSessions,
                [key]: next,
            }

            return next
        },

        async finalizeInlineVideoAnalytics(media, scope = 'inline') {
            const key = this.inlineVideoAnalyticsKey(media, scope)
            const session = this.videoAnalyticsSessions[key]
            if (!session?.sessionId) {
                return
            }

            const duration = Math.max(0, Number(session.duration || 0))
            const maxTime = Math.max(0, Number(session.maxTime || 0))
            const watchSeconds = Math.max(0, Math.round(session.watchSeconds || 0))
            const completionPercent = duration > 0
                ? Math.min(100, Number(((maxTime / duration) * 100).toFixed(1)))
                : 0
            const completed = Boolean(session.completed) || (duration > 0 && maxTime >= duration * 0.95)

            await reportAnalyticsEvent({
                feature: ANALYTICS_FEATURES.MEDIA,
                event_name: ANALYTICS_EVENTS.VIDEO_SESSION,
                entity_type: 'post_media',
                entity_id: session.mediaId,
                entity_key: session.url,
                session_id: session.sessionId,
                duration_seconds: watchSeconds,
                metric_value: completionPercent,
                context: {
                    completed,
                    theater_used: false,
                    post_id: Number(this.post?.id || 0),
                    title: session.title,
                    player_scope: scope,
                    duration_seconds: duration,
                    watched_max_seconds: maxTime,
                },
            })

            const nextSessions = { ...this.videoAnalyticsSessions }
            delete nextSessions[key]
            this.videoAnalyticsSessions = nextSessions
        },

        async finalizeAllInlineMediaAnalytics() {
            const sessions = Object.entries(this.videoAnalyticsSessions)
            if (sessions.length === 0) {
                return
            }

            for (const [key, session] of sessions) {
                const scope = String(key.split('::')[1] || 'inline')
                await this.finalizeInlineVideoAnalytics({
                    id: session.mediaId,
                    url: session.url,
                }, scope)
            }
        },

        handleInlineMediaPlaybackState(media, scope = 'inline', payload = {}) {
            const state = String(payload?.state || '')
            const currentTime = Math.max(0, Number(payload?.currentTime || 0))
            const duration = Math.max(0, Number(payload?.duration || 0))
            const key = this.inlineVideoAnalyticsKey(media, scope)
            let session = this.videoAnalyticsSessions[key]

            if (!session && !['play', 'timeupdate', 'pause', 'ended'].includes(state)) {
                return
            }

            if (!session) {
                session = this.ensureInlineVideoAnalyticsSession(media, scope)
            }

            if (duration > 0) {
                session.duration = Math.max(Number(session.duration || 0), duration)
            }

            if (currentTime >= Number(session.lastTime || 0) && (currentTime - Number(session.lastTime || 0)) <= 15) {
                session.watchSeconds += Math.max(0, currentTime - Number(session.lastTime || 0))
            }

            session.lastTime = currentTime
            session.maxTime = Math.max(Number(session.maxTime || 0), currentTime)

            this.videoAnalyticsSessions = {
                ...this.videoAnalyticsSessions,
                [key]: session,
            }

            if (state === 'ended') {
                session.completed = true
                void this.finalizeInlineVideoAnalytics(media, scope)
                return
            }

            if (state === 'pause' && Number(session.maxTime || 0) > 0) {
                void this.finalizeInlineVideoAnalytics(media, scope)
            }
        },

        async handleInlineMediaFullscreenEnter(media, scope = 'inline') {
            const session = this.ensureInlineVideoAnalyticsSession(media, scope)

            await reportAnalyticsEvent({
                feature: ANALYTICS_FEATURES.MEDIA,
                event_name: ANALYTICS_EVENTS.VIDEO_FULLSCREEN_ENTER,
                entity_type: 'post_media',
                entity_id: session.mediaId,
                entity_key: session.url,
                session_id: session.sessionId,
                context: {
                    post_id: Number(this.post?.id || 0),
                    title: session.title,
                    player_scope: scope,
                },
            })
        },

        pauseAllInlineMediaPlayers() {
            const refs = Array.isArray(this.$refs.postMediaPlayers)
                ? this.$refs.postMediaPlayers
                : [this.$refs.postMediaPlayers].filter(Boolean)

            for (const player of refs) {
                player?.pause?.()
            }
        },

        openVideoTheater(media, title = '') {
            if (!media?.url) {
                return
            }

            this.pauseAllInlineMediaPlayers()
            this.$refs.postMediaTheater?.open({
                source: media.url,
                mimeType: media.mime_type,
                mediaId: Number.isInteger(Number(media?.id)) ? Number(media.id) : null,
                postId: Number.isInteger(Number(this.post?.id)) ? Number(this.post.id) : null,
                title: String(title || this.post?.title || this.$t('common.video')).trim() || this.$t('common.video'),
                downloadHref: media.url,
                downloadName: this.mediaDownloadName(media),
                downloadLabel: this.mediaDownloadLabel(media),
            })
        },

        extractFileExtension(fileName) {
            const normalized = String(fileName || '').trim().toLowerCase()
            const lastDotIndex = normalized.lastIndexOf('.')

            if (lastDotIndex === -1 || lastDotIndex === normalized.length - 1) {
                return ''
            }

            return normalized.slice(lastDotIndex + 1)
        },

        mediaFileExtension(media) {
            const originalName = String(media?.original_name || '').trim()
            const extensionFromName = this.extractFileExtension(originalName)
            if (extensionFromName !== '') {
                return extensionFromName
            }

            const mimeType = String(media?.mime_type || '').trim().toLowerCase()
            if (mimeType.includes('matroska')) {
                return 'mkv'
            }
            if (mimeType.includes('webm')) {
                return 'webm'
            }
            if (mimeType.includes('quicktime')) {
                return 'mov'
            }
            if (mimeType.includes('m4v')) {
                return 'm4v'
            }
            if (mimeType.includes('avi') || mimeType.includes('x-msvideo')) {
                return 'avi'
            }
            if (mimeType.includes('mp4')) {
                return 'mp4'
            }
            if (mimeType.includes('jpeg')) {
                return 'jpg'
            }
            if (mimeType.includes('png')) {
                return 'png'
            }
            if (mimeType.includes('webp')) {
                return 'webp'
            }
            if (mimeType.includes('gif')) {
                return 'gif'
            }

            return media?.type === 'video' ? 'mp4' : 'bin'
        },

        mediaDownloadName(media) {
            const originalName = String(media?.original_name || '').trim()
            if (originalName !== '') {
                return originalName
            }

            return `media-${media?.id || 'download'}.${this.mediaFileExtension(media)}`
        },

        mediaDownloadLabel(media) {
            const extension = this.mediaFileExtension(media).toUpperCase()
            return extension !== ''
                ? this.$t('post.downloadFormat', { format: extension })
                : this.$t('post.downloadFile')
        },

        mediaAltText(value = '') {
            const label = String(value || '').trim()
            return label !== '' ? label : this.$t('post.mediaAlt')
        },

        previewFallbackLabel(value = '') {
            const label = String(value || '').trim()
            return label !== '' ? label : this.$t('post.previewUnavailable')
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

        initials(user) {
            const source = this.displayName(user).trim()
            return source ? source.slice(0, 1).toUpperCase() : 'U'
        },

        async markViewed() {
            if (!this.post || !this.post.id || !this.post.is_public) {
                return
            }

            if (this.isPersonal()) {
                return
            }

            try {
                const response = await axios.post(`/api/posts/${this.post.id}/view`)
                const viewsCount = response.data?.data?.views_count
                if (Number.isFinite(viewsCount)) {
                    this.post.views_count = viewsCount
                }
            } catch (error) {
                // Ignore view tracking errors for user flow.
            }
        },

        toggleLike(post) {
            axios.post(`/api/posts/${post.id}/toggle_like`)
                .then((response) => {
                    post.is_liked = response.data.is_liked
                    post.likes_count = response.data.likes_count
                })
        },

        removeLike(post) {
            if (!post?.is_liked) {
                return
            }

            axios.delete(`/api/posts/${post.id}/like`)
                .then((response) => {
                    post.is_liked = response.data.is_liked
                    post.likes_count = response.data.likes_count
                })
        },

        setParentId(comment) {
            this.comment = comment
        },

        appendEmoji(emoji) {
            this.body = `${this.body}${emoji}`
        },

        handleCommentInput() {
            const normalized = this.normalizeStickerAliases(this.body)
            if (normalized !== this.body) {
                this.body = normalized
            }
        },

        handleRepostInput() {
            const normalized = this.normalizeStickerAliases(this.content)
            if (normalized !== this.content) {
                this.content = normalized
            }
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

        toggleCommentStickerTray() {
            this.showCommentStickerTray = !this.showCommentStickerTray
        },

        insertCommentSticker(sticker) {
            const token = stickerTokenFromId(sticker?.id)
            if (token === '') {
                return
            }

            const emoji = stickerMarkedEmojiFromId(sticker?.id)
            const suffix = this.body.trim() === '' ? '' : ' '
            this.body = `${this.body}${suffix}${emoji}`
            this.showCommentStickerTray = false
        },

        toggleRepostStickerTray() {
            this.showRepostStickerTray = !this.showRepostStickerTray
        },

        insertRepostSticker(sticker) {
            const token = stickerTokenFromId(sticker?.id)
            if (token === '') {
                return
            }

            const emoji = stickerMarkedEmojiFromId(sticker?.id)
            const suffix = this.content.trim() === '' ? '' : ' '
            this.content = `${this.content}${suffix}${emoji}`
            this.showRepostStickerTray = false
        },

        storeComment(post) {
            const normalizedBody = this.normalizeStickerTransport(this.body)
            if (!normalizedBody.trim()) {
                return
            }

            const commentId = this.comment ? this.comment.id : null
            axios.post(`/api/posts/${post.id}/comment`, {body: normalizedBody, parent_id: commentId})
                .then((response) => {
                    this.body = ''
                    this.showCommentStickerTray = false
                    this.comments.unshift(response.data.data)
                    this.comment = null
                    post.comments_count += 1
                    this.isCommentsOpened = true
                    this.commentsLoaded = true
                })
        },

        toggleComments(post) {
            if (this.isCommentsOpened) {
                this.isCommentsOpened = false
                return
            }

            if (this.commentsLoaded) {
                this.isCommentsOpened = true
                return
            }

            axios.get(`/api/posts/${post.id}/comment`, {params: {per_page: 100}})
                .then((response) => {
                    this.comments = response.data.data ?? []
                    this.commentsLoaded = true
                    this.isCommentsOpened = true
                })
        },

        removeComment(post, commentItem) {
            if (!commentItem?.id) {
                return
            }

            axios.delete(`/api/posts/${post.id}/comments/${commentItem.id}`)
                .then(() => {
                    this.comments = this.comments.filter((item) => Number(item.id) !== Number(commentItem.id))
                    if (this.comment && Number(this.comment.id) === Number(commentItem.id)) {
                        this.comment = null
                    }
                    post.comments_count = Math.max(0, Number(post.comments_count || 0) - 1)
                })
        },

        removeMedia(post, media) {
            if (!media?.id) {
                return
            }

            axios.delete(`/api/posts/${post.id}/media/${media.id}`)
                .then(() => {
                    const nextMedia = Array.isArray(post.media)
                        ? post.media.filter((item) => Number(item.id) !== Number(media.id))
                        : []

                    post.media = nextMedia

                    const firstImage = nextMedia.find((item) => item.type === 'image')
                    post.image_url = firstImage?.url ?? null
                })
        },

        toggleRepostForm() {
            if (this.isPersonal()) {
                return
            }
            this.isRepostOpened = !this.isRepostOpened
        },

        repost(post) {
            const normalizedContent = this.normalizeStickerTransport(this.content)
            if (this.isPersonal() || !this.title || !normalizedContent.trim()) {
                return
            }

            axios.post(`/api/posts/${post.id}/repost`, {title: this.title, content: normalizedContent})
                .then(() => {
                    this.title = ''
                    this.content = ''
                    this.isRepostOpened = false
                    this.showRepostStickerTray = false
                    post.reposted_by_posts_count += 1
                })
        },

        isPersonal() {
            return this.$route.name === 'user.personal'
        }
    }
}
</script>
