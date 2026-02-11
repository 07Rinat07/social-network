<template>
    <article class="post-card">
        <header class="post-head">
            <div class="post-author-row">
                <router-link class="post-avatar-link" :to="{name: 'user.show', params: {id: post.user.id}}">
                    <img v-if="avatarUrl(post.user)" class="avatar post-avatar" :src="avatarUrl(post.user)" :alt="displayName(post.user)">
                    <span v-else class="avatar post-avatar avatar-placeholder">{{ initials(post.user) }}</span>
                </router-link>

                <div>
                <h3 class="post-title">{{ post.title }}</h3>
                <router-link class="post-author" :to="{name: 'user.show', params: {id: post.user.id}}">
                    {{ displayName(post.user) }}
                </router-link>
                </div>
            </div>
            <span class="post-date">{{ post.date }}</span>
        </header>

        <p class="muted" style="margin: -0.35rem 0 0; font-size: 0.8rem;">
            👁 {{ post.views_count ?? 0 }} просмотров
        </p>

        <p class="post-content">{{ post.content }}</p>

        <div class="media-grid" v-if="normalizedMedia.length > 0">
            <template v-for="media in normalizedMedia" :key="`post-media-${post.id}-${media.id ?? media.url}`">
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
                        @error="handlePreviewError($event, post.title || 'media')"
                        @load="handlePreviewLoad"
                    >
                </button>
                <MediaPlayer v-else type="video" :src="media.url" player-class="media-video"></MediaPlayer>
            </template>
        </div>

        <section v-if="post.reposted_post" class="repost-box">
            <strong>Репост оригинала</strong>
            <p style="margin: 0;"><strong>{{ post.reposted_post.title }}</strong></p>
            <router-link class="post-author" :to="{name: 'user.show', params: {id: post.reposted_post.user.id}}">
                {{ displayName(post.reposted_post.user) }}
            </router-link>
            <p style="margin: 0;">{{ post.reposted_post.content }}</p>

            <div class="media-grid" v-if="normalizedRepostMedia.length > 0">
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
                            @error="handlePreviewError($event, post.reposted_post.title || 'media')"
                            @load="handlePreviewLoad"
                        >
                    </button>
                    <MediaPlayer v-else type="video" :src="media.url" player-class="media-video"></MediaPlayer>
                </template>
            </div>
        </section>

        <div class="post-actions">
            <button class="icon-btn" :class="{'active': post.is_liked}" @click.prevent="toggleLike(post)">
                ❤️ {{ post.likes_count }}
            </button>
            <button class="icon-btn" :disabled="isPersonal()" @click.prevent="toggleRepostForm">
                🔁 {{ post.reposted_by_posts_count }}
            </button>
            <button class="icon-btn" :class="{'active': isCommentsOpened}" @click.prevent="toggleComments(post)">
                💬 {{ post.comments_count }}
            </button>
        </div>

        <div v-if="isRepostOpened" class="repost-box">
            <input v-model.trim="title" class="input-field" type="text" placeholder="Заголовок репоста">
            <textarea v-model.trim="content" class="textarea-field" placeholder="Комментарий к репосту"></textarea>
            <button class="btn btn-primary" @click.prevent="repost(post)">Опубликовать репост</button>
        </div>

        <div class="comments-box">
            <div class="form-grid">
                <div v-if="comment" class="muted" style="font-size: 0.82rem;">
                    Ответ пользователю {{ displayName(comment.user) }}
                    <button class="btn btn-outline btn-sm" style="margin-left: 0.5rem;" @click.prevent="comment = null">Отменить</button>
                </div>

                <input
                    v-model.trim="body"
                    class="input-field"
                    type="text"
                    placeholder="Ваш комментарий..."
                >

                <div class="emoji-row">
                    <button v-for="emoji in emojis" :key="emoji" type="button" class="emoji-btn" @click="appendEmoji(emoji)">
                        {{ emoji }}
                    </button>
                </div>

                <button class="btn btn-sun" @click.prevent="storeComment(post)">Отправить комментарий</button>
            </div>

            <div v-if="isCommentsOpened">
                <div v-if="comments.length === 0" class="muted">Комментариев пока нет.</div>

                <div v-for="commentItem in comments" :key="commentItem.id" class="comment-item">
                    <div class="comment-head">
                        <span>{{ displayName(commentItem.user) }}</span>
                        <span>{{ commentItem.date }}</span>
                    </div>
                    <p class="comment-body">
                        <strong v-if="commentItem.answered_for_user" style="color: var(--accent-strong);">@{{ commentItem.answered_for_user }} </strong>
                        {{ commentItem.body }}
                    </p>
                    <button class="btn btn-outline btn-sm" @click="setParentId(commentItem)">Ответить</button>
                </div>
            </div>
        </div>

        <MediaLightbox ref="mediaLightbox"></MediaLightbox>
    </article>
</template>

<script>
import MediaLightbox from './MediaLightbox.vue'
import MediaPlayer from './MediaPlayer.vue'
import { applyImagePreviewFallback, resetImagePreviewFallback } from '../utils/mediaPreview'

export default {
    name: 'Post',

    props: ['post'],

    components: {
        MediaLightbox,
        MediaPlayer,
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
            emojis: ['🔥', '👍', '❤️', '👏', '😂', '😎']
        }
    },

    computed: {
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

    methods: {
        handlePreviewError(event, label = 'Preview unavailable') {
            applyImagePreviewFallback(event, label)
        },

        handlePreviewLoad(event) {
            resetImagePreviewFallback(event)
        },

        displayName(user) {
            return user?.display_name || user?.name || 'Пользователь'
        },

        openMedia(url, alt = 'Фото') {
            this.$refs.mediaLightbox?.open(url, alt)
        },

        avatarUrl(user) {
            return user?.avatar_url || null
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

        setParentId(comment) {
            this.comment = comment
        },

        appendEmoji(emoji) {
            this.body = `${this.body}${emoji}`
        },

        storeComment(post) {
            if (!this.body) {
                return
            }

            const commentId = this.comment ? this.comment.id : null
            axios.post(`/api/posts/${post.id}/comment`, {body: this.body, parent_id: commentId})
                .then((response) => {
                    this.body = ''
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

        toggleRepostForm() {
            if (this.isPersonal()) {
                return
            }
            this.isRepostOpened = !this.isRepostOpened
        },

        repost(post) {
            if (this.isPersonal() || !this.title || !this.content) {
                return
            }

            axios.post(`/api/posts/${post.id}/repost`, {title: this.title, content: this.content})
                .then(() => {
                    this.title = ''
                    this.content = ''
                    this.isRepostOpened = false
                    post.reposted_by_posts_count += 1
                })
        },

        isPersonal() {
            return this.$route.name === 'user.personal'
        }
    }
}
</script>
