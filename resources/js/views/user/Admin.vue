<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">Админ-панель</h1>
            <p class="section-subtitle">Полный контроль пользователей, контента, чатов и фидбека.</p>

            <div class="stats-grid" style="margin-bottom: 1rem;">
                <div class="stat-card">
                    <span class="stat-label">Пользователи</span>
                    <div class="stat-value">{{ summary.users ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Посты</span>
                    <div class="stat-value">{{ summary.posts ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Комментарии</span>
                    <div class="stat-value">{{ summary.comments ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Сообщения чатов</span>
                    <div class="stat-value">{{ summary.messages ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Публичные посты</span>
                    <div class="stat-value">{{ summary.public_posts ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">В карусели</span>
                    <div class="stat-value">{{ summary.carousel_posts ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Вложения чатов</span>
                    <div class="stat-value">{{ summary.chat_attachments ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Активные блокировки</span>
                    <div class="stat-value">{{ summary.active_blocks ?? 0 }}</div>
                </div>
            </div>

            <div class="admin-tabs">
                <button class="btn" :class="activeTab === 'users' ? 'btn-primary' : 'btn-outline'" @click="selectTab('users')">Пользователи</button>
                <button class="btn" :class="activeTab === 'posts' ? 'btn-primary' : 'btn-outline'" @click="selectTab('posts')">Посты</button>
                <button class="btn" :class="activeTab === 'comments' ? 'btn-primary' : 'btn-outline'" @click="selectTab('comments')">Комментарии</button>
                <button class="btn" :class="activeTab === 'feedback' ? 'btn-primary' : 'btn-outline'" @click="selectTab('feedback')">Фидбек</button>
                <button class="btn" :class="activeTab === 'conversations' ? 'btn-primary' : 'btn-outline'" @click="selectTab('conversations')">Чаты</button>
                <button class="btn" :class="activeTab === 'messages' ? 'btn-primary' : 'btn-outline'" @click="selectTab('messages')">Сообщения</button>
                <button class="btn" :class="activeTab === 'blocks' ? 'btn-primary' : 'btn-outline'" @click="selectTab('blocks')">Блокировки</button>
                <button class="btn" :class="activeTab === 'settings' ? 'btn-primary' : 'btn-outline'" @click="selectTab('settings')">Настройки сайта</button>
            </div>

            <div v-if="activeTab === 'users'" class="table-wrap fade-in">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Имя</th>
                        <th>Email</th>
                        <th>Админ</th>
                        <th>Постов</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="user in users" :key="`admin-user-${user.id}`">
                        <td>{{ user.id }}</td>
                        <td>
                            <input class="input-field" v-model="user.name" type="text">
                        </td>
                        <td>
                            <input class="input-field" v-model="user.email" type="email">
                        </td>
                        <td>
                            <select class="select-field" v-model="user.is_admin">
                                <option :value="false">Нет</option>
                                <option :value="true">Да</option>
                            </select>
                        </td>
                        <td>{{ user.posts_count ?? 0 }}</td>
                        <td>
                            <div style="display: flex; gap: 0.4rem;">
                                <button class="btn btn-success btn-sm" @click="saveUser(user)">Сохранить</button>
                                <button class="btn btn-danger btn-sm" @click="removeUser(user)">Удалить</button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="activeTab === 'posts'" class="simple-list fade-in">
                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">Создать пост от имени любого пользователя</strong>
                    <div class="form-grid">
                        <input
                            class="input-field"
                            type="number"
                            min="1"
                            v-model.number="postCreateForm.user_id"
                            placeholder="ID автора"
                        >
                        <input
                            class="input-field"
                            type="text"
                            maxlength="255"
                            v-model.trim="postCreateForm.title"
                            placeholder="Заголовок"
                        >
                        <textarea
                            class="textarea-field"
                            style="min-height: 120px;"
                            maxlength="5000"
                            v-model.trim="postCreateForm.content"
                            placeholder="Текст поста"
                        ></textarea>
                        <input
                            class="input-field"
                            type="number"
                            min="1"
                            v-model.number="postCreateForm.reposted_id"
                            placeholder="ID репоста (необязательно)"
                        >

                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="postCreateForm.is_public">
                            Публичный пост
                        </label>
                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="postCreateForm.show_in_feed" :disabled="!postCreateForm.is_public">
                            Показывать в ленте
                        </label>
                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="postCreateForm.show_in_carousel" :disabled="!postCreateForm.is_public">
                            Показывать в карусели
                        </label>

                        <button class="btn btn-primary btn-sm" @click="createPost">Создать пост</button>
                    </div>
                </div>

                <div class="simple-item" v-for="post in posts" :key="`admin-post-${post.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap; align-items: center;">
                        <strong>#{{ post.id }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removePost(post)">Удалить</button>
                    </div>

                    <div class="form-grid" style="margin-top: 0.6rem;">
                        <p class="muted" style="margin: 0;">Текущий автор: {{ post.user?.name ?? '—' }} (ID: {{ post.user?.id ?? post.user_id }})</p>
                        <input
                            class="input-field"
                            type="number"
                            min="1"
                            v-model.number="post.user_id"
                            placeholder="ID автора"
                        >
                        <input
                            class="input-field"
                            type="text"
                            maxlength="255"
                            v-model.trim="post.title"
                            placeholder="Заголовок"
                        >
                        <textarea
                            class="textarea-field"
                            style="min-height: 120px;"
                            maxlength="5000"
                            v-model.trim="post.content"
                            placeholder="Текст поста"
                        ></textarea>
                        <input
                            class="input-field"
                            type="number"
                            min="1"
                            v-model.number="post.reposted_id"
                            placeholder="ID репоста (необязательно)"
                        >

                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="post.is_public">
                            Публичный пост
                        </label>
                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="post.show_in_feed" :disabled="!post.is_public">
                            Показывать в ленте
                        </label>
                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="post.show_in_carousel" :disabled="!post.is_public">
                            Показывать в карусели
                        </label>

                        <button class="btn btn-success btn-sm" @click="savePost(post)">Сохранить пост</button>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'comments'" class="simple-list fade-in">
                <div class="simple-item" v-for="comment in comments" :key="`admin-comment-${comment.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap;">
                        <strong>#{{ comment.id }} · {{ comment.user?.name ?? '—' }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeComment(comment)">Удалить</button>
                    </div>
                    <p style="margin: 0.35rem 0 0;">{{ comment.body }}</p>
                </div>
            </div>

            <div v-if="activeTab === 'feedback'" class="simple-list fade-in">
                <div class="simple-item" v-for="item in feedback" :key="`admin-feedback-${item.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap; align-items: center;">
                        <div>
                            <strong>{{ item.name }}</strong>
                            <p class="muted" style="margin: 0.2rem 0 0;">{{ item.email }}</p>
                        </div>
                        <div style="display: flex; gap: 0.45rem;">
                            <select class="select-field" v-model="item.status" style="min-width: 170px;">
                                <option value="new">new</option>
                                <option value="in_progress">in_progress</option>
                                <option value="resolved">resolved</option>
                            </select>
                            <button class="btn btn-success btn-sm" @click="saveFeedback(item)">Обновить</button>
                            <button class="btn btn-danger btn-sm" @click="removeFeedback(item)">Удалить</button>
                        </div>
                    </div>
                    <p style="margin: 0.45rem 0 0;">{{ item.message }}</p>
                </div>
            </div>

            <div v-if="activeTab === 'conversations'" class="simple-list fade-in">
                <div class="simple-item" v-for="conversation in conversations" :key="`admin-conversation-${conversation.id}`" style="display: block;">
                    <strong>#{{ conversation.id }} · {{ conversation.display_title || conversation.title }}</strong>
                    <p class="muted" style="margin: 0.25rem 0 0;">
                        Тип: {{ conversation.type }} · Участников: {{ conversation.participants?.length ?? 0 }} · Сообщений: {{ conversation.messages_count ?? 0 }}
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'messages'" class="simple-list fade-in">
                <div class="simple-item" v-for="message in messages" :key="`admin-message-${message.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap;">
                        <strong>#{{ message.id }} · {{ message.user?.name ?? '—' }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeMessage(message)">Удалить</button>
                    </div>
                    <p class="muted" style="margin: 0.2rem 0 0;">
                        Чат #{{ message.conversation_id }}
                    </p>
                    <p style="margin: 0.35rem 0 0;">{{ message.body || 'Только вложения' }}</p>
                    <p class="muted" style="margin: 0.25rem 0 0;" v-if="(message.attachments?.length ?? 0) > 0">
                        Вложений: {{ message.attachments.length }}
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'blocks'" class="simple-list fade-in">
                <div class="simple-item" v-for="block in blocks" :key="`admin-block-${block.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap;">
                        <div>
                            <strong>#{{ block.id }} · {{ block.blocker?.name ?? '—' }} → {{ block.blocked_user?.name ?? '—' }}</strong>
                            <p class="muted" style="margin: 0.2rem 0 0;">
                                Статус: {{ block.expires_at ? `до ${formatDate(block.expires_at)}` : 'постоянная' }}
                            </p>
                        </div>
                        <button class="btn btn-danger btn-sm" @click="removeBlock(block)">Удалить</button>
                    </div>

                    <div class="form-grid" style="margin-top: 0.6rem;">
                        <input
                            class="input-field"
                            type="datetime-local"
                            v-model="block.expires_at_local"
                        >
                        <input
                            class="input-field"
                            type="text"
                            placeholder="Причина"
                            maxlength="500"
                            v-model="block.reason"
                        >

                        <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                            <button class="btn btn-success btn-sm" @click="saveBlock(block)">Сохранить</button>
                            <button class="btn btn-outline btn-sm" @click="setPermanentBlock(block)">Сделать постоянной</button>
                            <button class="btn btn-outline btn-sm" @click="extendBlockFor24Hours(block)">+24 часа</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'settings'" class="simple-list fade-in">
                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">Контент главной страницы</strong>
                    <p class="muted" style="margin: 0 0 0.65rem;">
                        Этот блок управляет текстами главной страницы (бейдж, заголовок, описание, преимущества и обратная связь).
                    </p>

                    <div class="form-grid">
                        <label class="muted" style="font-size: 0.82rem;">Бейдж</label>
                        <input class="input-field" type="text" maxlength="80" v-model.trim="homeContentForm.badge">

                        <label class="muted" style="font-size: 0.82rem;">Главный заголовок</label>
                        <textarea class="textarea-field" style="min-height: 90px;" maxlength="300" v-model.trim="homeContentForm.hero_title"></textarea>

                        <label class="muted" style="font-size: 0.82rem;">Описание под заголовком</label>
                        <textarea class="textarea-field" style="min-height: 120px;" maxlength="3000" v-model.trim="homeContentForm.hero_note"></textarea>

                        <label class="muted" style="font-size: 0.82rem;">Пункты преимуществ</label>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <div
                                v-for="(item, index) in homeContentForm.feature_items"
                                :key="`home-feature-item-${index}`"
                                style="display: flex; gap: 0.45rem; align-items: center;"
                            >
                                <input
                                    class="input-field"
                                    type="text"
                                    maxlength="220"
                                    :placeholder="`Пункт #${index + 1}`"
                                    v-model.trim="homeContentForm.feature_items[index]"
                                >
                                <button
                                    class="btn btn-danger btn-sm"
                                    type="button"
                                    @click="removeHomeFeatureItem(index)"
                                    :disabled="homeContentForm.feature_items.length <= 1"
                                >
                                    Удалить
                                </button>
                            </div>
                            <button
                                class="btn btn-outline btn-sm"
                                type="button"
                                @click="addHomeFeatureItem"
                                :disabled="homeContentForm.feature_items.length >= 8"
                            >
                                Добавить пункт
                            </button>
                        </div>

                        <label class="muted" style="font-size: 0.82rem;">Заголовок обратной связи</label>
                        <input class="input-field" type="text" maxlength="180" v-model.trim="homeContentForm.feedback_title">

                        <label class="muted" style="font-size: 0.82rem;">Описание обратной связи</label>
                        <textarea class="textarea-field" style="min-height: 90px;" maxlength="500" v-model.trim="homeContentForm.feedback_subtitle"></textarea>

                        <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                            <button class="btn btn-success btn-sm" @click="saveHomeContent">Сохранить контент главной</button>
                            <button class="btn btn-outline btn-sm" @click="resetHomeContent">Сбросить к стандартному</button>
                        </div>
                    </div>
                </div>

                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">Где хранить фото и видео</strong>
                    <p class="muted" style="margin: 0 0 0.65rem;">
                        Админ выбирает: хранить на сервере сайта, в облаке, либо дать выбор пользователям.
                    </p>

                    <div class="form-grid">
                        <select class="select-field" v-model="storageSettings.media_storage_mode">
                            <option value="server_local">Только сервер сайта</option>
                            <option value="cloud">Только облачное хранилище</option>
                            <option value="user_choice">Пользователь выбирает сам</option>
                        </select>

                        <label class="muted" style="font-size: 0.82rem;">Диск сервера</label>
                        <input class="input-field" v-model.trim="storageSettings.server_media_disk" type="text" placeholder="public">

                        <label class="muted" style="font-size: 0.82rem;">Диск облака</label>
                        <input class="input-field" v-model.trim="storageSettings.cloud_media_disk" type="text" placeholder="s3">

                        <button class="btn btn-success btn-sm" @click="saveStorageSettings">Сохранить настройки хранилища</button>
                    </div>
                </div>

                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">Создать настройку сайта</strong>
                    <div class="form-grid">
                        <input class="input-field" v-model.trim="newSetting.key" type="text" placeholder="key_name">
                        <select class="select-field" v-model="newSetting.type">
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="float">float</option>
                            <option value="boolean">boolean</option>
                            <option value="json">json</option>
                        </select>
                        <textarea class="textarea-field" style="min-height: 90px;" v-model="newSetting.valueText" placeholder="значение"></textarea>
                        <input class="input-field" v-model.trim="newSetting.description" type="text" placeholder="Описание (необязательно)">
                        <button class="btn btn-primary btn-sm" @click="createSetting">Создать</button>
                    </div>
                </div>

                <div class="simple-item" v-for="setting in settings" :key="`admin-setting-${setting.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap; align-items: center;">
                        <strong>#{{ setting.id }} · {{ setting.key }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeSetting(setting)">Удалить</button>
                    </div>

                    <div class="form-grid" style="margin-top: 0.6rem;">
                        <input class="input-field" type="text" v-model.trim="setting.key">
                        <select class="select-field" v-model="setting.type">
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="float">float</option>
                            <option value="boolean">boolean</option>
                            <option value="json">json</option>
                        </select>
                        <textarea class="textarea-field" style="min-height: 90px;" v-model="setting.valueText"></textarea>
                        <input class="input-field" type="text" v-model.trim="setting.description" placeholder="Описание">
                        <button class="btn btn-success btn-sm" @click="saveSetting(setting)">Сохранить</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: 'Admin',

    data() {
        return {
            activeTab: 'users',
            summary: {},
            users: [],
            posts: [],
            postCreateForm: {
                user_id: null,
                title: '',
                content: '',
                reposted_id: null,
                is_public: true,
                show_in_feed: true,
                show_in_carousel: false,
            },
            comments: [],
            feedback: [],
            conversations: [],
            messages: [],
            blocks: [],
            settings: [],
            homeContentForm: {
                badge: 'Социальная сеть SPA',
                hero_title: 'Современная платформа с постами, чатами, каруселью медиа и гибкими настройками хранения.',
                hero_note: 'Публикуйте контент, общайтесь, продвигайте лучшие посты и управляйте видимостью своих материалов. Администратор контролирует настройки сайта и политику хранения фото/видео.',
                feature_items: [
                    'Публичные и приватные посты с гибким показом в ленте/карусели.',
                    'Личные и общие чаты с realtime-доставкой.',
                    'Админ-панель с полным управлением настройками платформы.',
                ],
                feedback_title: 'Обратная связь для администрации',
                feedback_subtitle: 'Напишите нам предложение, жалобу или вопрос. Сообщение сразу попадёт в админ-панель.',
            },
            storageSettings: {
                media_storage_mode: 'server_local',
                server_media_disk: 'public',
                cloud_media_disk: 's3',
            },
            newSetting: {
                key: '',
                type: 'string',
                valueText: '',
                description: '',
            },
        }
    },

    async mounted() {
        await this.loadSummary()
        await this.selectTab(this.activeTab)
    },

    methods: {
        async selectTab(tab) {
            this.activeTab = tab

            if (tab === 'users') {
                await this.loadUsers()
                return
            }
            if (tab === 'posts') {
                await this.loadPosts()
                return
            }
            if (tab === 'comments') {
                await this.loadComments()
                return
            }
            if (tab === 'feedback') {
                await this.loadFeedback()
                return
            }
            if (tab === 'conversations') {
                await this.loadConversations()
                return
            }
            if (tab === 'messages') {
                await this.loadMessages()
                return
            }
            if (tab === 'blocks') {
                await this.loadBlocks()
                return
            }
            if (tab === 'settings') {
                await Promise.all([this.loadSettings(), this.loadStorageSettings(), this.loadHomeContentSettings()])
            }
        },

        async loadSummary() {
            const response = await axios.get('/api/admin/summary')
            this.summary = response.data.data ?? {}
        },

        async loadUsers() {
            const response = await axios.get('/api/admin/users', { params: { per_page: 50 } })
            this.users = (response.data.data ?? []).map((user) => ({
                ...user,
                is_admin: Boolean(user.is_admin),
            }))
        },

        async saveUser(user) {
            try {
                await axios.patch(`/api/admin/users/${user.id}`, {
                    name: user.name,
                    email: user.email,
                    is_admin: Boolean(user.is_admin),
                })
                await this.loadSummary()
            } catch (error) {
                alert(error.response?.data?.message ?? 'Не удалось сохранить пользователя.')
            }
        },

        async removeUser(user) {
            if (!confirm(`Удалить пользователя ${user.name}?`)) {
                return
            }

            try {
                await axios.delete(`/api/admin/users/${user.id}`)
                await this.loadUsers()
                await this.loadSummary()
            } catch (error) {
                alert(error.response?.data?.message ?? 'Не удалось удалить пользователя.')
            }
        },

        async loadPosts() {
            const response = await axios.get('/api/admin/posts', { params: { all: 1 } })
            this.posts = (response.data.data ?? []).map((post) => this.normalizeAdminPost(post))
        },

        normalizeAdminPost(post) {
            const repostedId = post.reposted_post?.id ?? null

            return {
                ...post,
                user_id: Number(post.user?.id ?? post.user_id ?? 0) || null,
                reposted_id: repostedId ? Number(repostedId) : null,
                is_public: Boolean(post.is_public),
                show_in_feed: Boolean(post.show_in_feed),
                show_in_carousel: Boolean(post.show_in_carousel),
            }
        },

        buildAdminPostPayload(source) {
            return {
                user_id: Number(source.user_id) || null,
                title: String(source.title ?? '').trim(),
                content: String(source.content ?? '').trim(),
                reposted_id: source.reposted_id ? Number(source.reposted_id) : null,
                is_public: Boolean(source.is_public),
                show_in_feed: Boolean(source.show_in_feed),
                show_in_carousel: Boolean(source.show_in_carousel),
            }
        },

        async createPost() {
            try {
                await axios.post('/api/admin/posts', this.buildAdminPostPayload(this.postCreateForm))

                this.postCreateForm = {
                    user_id: null,
                    title: '',
                    content: '',
                    reposted_id: null,
                    is_public: true,
                    show_in_feed: true,
                    show_in_carousel: false,
                }

                await this.loadPosts()
                await this.loadSummary()
            } catch (error) {
                const firstError = Object.values(error.response?.data?.errors ?? {})
                    .flat()
                    .find(Boolean)
                alert(firstError ?? error.response?.data?.message ?? 'Не удалось создать пост.')
            }
        },

        async savePost(post) {
            try {
                await axios.patch(`/api/admin/posts/${post.id}`, this.buildAdminPostPayload(post))
                await this.loadPosts()
                await this.loadSummary()
            } catch (error) {
                const firstError = Object.values(error.response?.data?.errors ?? {})
                    .flat()
                    .find(Boolean)
                alert(firstError ?? error.response?.data?.message ?? 'Не удалось обновить пост.')
            }
        },

        async removePost(post) {
            if (!confirm(`Удалить пост #${post.id}?`)) {
                return
            }

            await axios.delete(`/api/admin/posts/${post.id}`)
            await this.loadPosts()
            await this.loadSummary()
        },

        async loadComments() {
            const response = await axios.get('/api/admin/comments', { params: { per_page: 50 } })
            this.comments = response.data.data ?? []
        },

        async removeComment(comment) {
            if (!confirm(`Удалить комментарий #${comment.id}?`)) {
                return
            }

            await axios.delete(`/api/admin/comments/${comment.id}`)
            await this.loadComments()
            await this.loadSummary()
        },

        async loadFeedback() {
            const response = await axios.get('/api/admin/feedback', { params: { per_page: 50 } })
            this.feedback = response.data.data ?? []
        },

        async saveFeedback(item) {
            await axios.patch(`/api/admin/feedback/${item.id}`, {
                status: item.status,
                admin_note: item.admin_note ?? null,
            })
            await this.loadSummary()
        },

        async removeFeedback(item) {
            if (!confirm(`Удалить feedback #${item.id}?`)) {
                return
            }

            await axios.delete(`/api/admin/feedback/${item.id}`)
            await this.loadFeedback()
            await this.loadSummary()
        },

        async loadConversations() {
            const response = await axios.get('/api/admin/conversations', { params: { per_page: 50 } })
            this.conversations = response.data.data ?? []
        },

        async loadMessages() {
            const response = await axios.get('/api/admin/messages', { params: { per_page: 80 } })
            this.messages = response.data.data ?? []
        },

        async loadBlocks() {
            const response = await axios.get('/api/admin/blocks', { params: { per_page: 100 } })

            this.blocks = (response.data.data ?? []).map((item) => ({
                ...item,
                reason: item.reason ?? '',
                expires_at_local: this.toDatetimeLocal(item.expires_at),
            }))
        },

        async removeMessage(message) {
            if (!confirm(`Удалить сообщение #${message.id}?`)) {
                return
            }

            await axios.delete(`/api/admin/messages/${message.id}`)
            await this.loadMessages()
            await this.loadSummary()
        },

        async saveBlock(block) {
            try {
                await axios.patch(`/api/admin/blocks/${block.id}`, {
                    reason: block.reason?.trim() || null,
                    expires_at: block.expires_at_local ? this.fromDatetimeLocal(block.expires_at_local) : null,
                })

                await this.loadBlocks()
                await this.loadSummary()
            } catch (error) {
                alert(error.response?.data?.message ?? 'Не удалось обновить блокировку.')
            }
        },

        async setPermanentBlock(block) {
            block.expires_at_local = ''
            await this.saveBlock(block)
        },

        async extendBlockFor24Hours(block) {
            const current = block.expires_at_local
                ? new Date(block.expires_at_local)
                : new Date()
            const extended = new Date(current.getTime() + 24 * 60 * 60 * 1000)

            block.expires_at_local = this.toDatetimeLocal(extended.toISOString())
            await this.saveBlock(block)
        },

        async removeBlock(block) {
            if (!confirm(`Удалить блокировку #${block.id}?`)) {
                return
            }

            await axios.delete(`/api/admin/blocks/${block.id}`)
            await this.loadBlocks()
            await this.loadSummary()
        },

        async loadSettings() {
            const response = await axios.get('/api/admin/settings', { params: { per_page: 100 } })
            this.settings = (response.data.data ?? []).map((item) => ({
                ...item,
                valueText: this.stringifySettingValue(item.value),
            }))
        },

        async loadStorageSettings() {
            const response = await axios.get('/api/site/config')
            const config = response.data.data ?? {}

            this.storageSettings = {
                media_storage_mode: config.media_storage_mode ?? 'server_local',
                server_media_disk: config.server_media_disk ?? 'public',
                cloud_media_disk: config.cloud_media_disk ?? 's3',
            }
        },

        defaultHomeContentPayload() {
            return {
                badge: 'Социальная сеть SPA',
                hero_title: 'Современная платформа с постами, чатами, каруселью медиа и гибкими настройками хранения.',
                hero_note: 'Публикуйте контент, общайтесь, продвигайте лучшие посты и управляйте видимостью своих материалов. Администратор контролирует настройки сайта и политику хранения фото/видео.',
                feature_items: [
                    'Публичные и приватные посты с гибким показом в ленте/карусели.',
                    'Личные и общие чаты с realtime-доставкой.',
                    'Админ-панель с полным управлением настройками платформы.',
                ],
                feedback_title: 'Обратная связь для администрации',
                feedback_subtitle: 'Напишите нам предложение, жалобу или вопрос. Сообщение сразу попадёт в админ-панель.',
            }
        },

        normalizeHomeContentPayload(payload) {
            const fallback = this.defaultHomeContentPayload()
            const featureItems = Array.isArray(payload?.feature_items)
                ? payload.feature_items.map((item) => String(item ?? '').trim()).filter((item) => item !== '').slice(0, 8)
                : []

            return {
                badge: String(payload?.badge ?? '').trim() || fallback.badge,
                hero_title: String(payload?.hero_title ?? '').trim() || fallback.hero_title,
                hero_note: String(payload?.hero_note ?? '').trim() || fallback.hero_note,
                feature_items: featureItems.length > 0 ? featureItems : fallback.feature_items,
                feedback_title: String(payload?.feedback_title ?? '').trim() || fallback.feedback_title,
                feedback_subtitle: String(payload?.feedback_subtitle ?? '').trim() || fallback.feedback_subtitle,
            }
        },

        async loadHomeContentSettings() {
            try {
                const response = await axios.get('/api/site/home-content')
                this.homeContentForm = this.normalizeHomeContentPayload(response.data.data ?? {})
            } catch (error) {
                this.homeContentForm = this.defaultHomeContentPayload()
            }
        },

        addHomeFeatureItem() {
            if (this.homeContentForm.feature_items.length >= 8) {
                return
            }

            this.homeContentForm.feature_items.push('')
        },

        removeHomeFeatureItem(index) {
            if (this.homeContentForm.feature_items.length <= 1) {
                return
            }

            this.homeContentForm.feature_items.splice(index, 1)
        },

        async saveHomeContent() {
            try {
                const response = await axios.patch('/api/admin/settings/home-content', this.normalizeHomeContentPayload(this.homeContentForm))
                this.homeContentForm = this.normalizeHomeContentPayload(response.data.data ?? {})
            } catch (error) {
                const firstError = Object.values(error.response?.data?.errors ?? {})
                    .flat()
                    .find(Boolean)
                alert(firstError ?? error.response?.data?.message ?? 'Не удалось сохранить контент главной страницы.')
            }
        },

        async resetHomeContent() {
            if (!confirm('Сбросить контент главной страницы к стандартному виду?')) {
                return
            }

            try {
                const response = await axios.delete('/api/admin/settings/home-content')
                this.homeContentForm = this.normalizeHomeContentPayload(response.data.data ?? {})
            } catch (error) {
                alert(error.response?.data?.message ?? 'Не удалось сбросить контент главной страницы.')
            }
        },

        async saveStorageSettings() {
            try {
                await axios.patch('/api/admin/settings/storage', {
                    media_storage_mode: this.storageSettings.media_storage_mode,
                    server_media_disk: this.storageSettings.server_media_disk,
                    cloud_media_disk: this.storageSettings.cloud_media_disk,
                })
                await this.loadStorageSettings()
                await this.loadSummary()
            } catch (error) {
                alert(error.response?.data?.message ?? 'Не удалось сохранить настройки хранилища.')
            }
        },

        async createSetting() {
            if (!this.newSetting.key) {
                alert('Укажите ключ настройки.')
                return
            }

            try {
                await axios.post('/api/admin/settings', {
                    key: this.newSetting.key,
                    type: this.newSetting.type,
                    value: this.parseSettingValue(this.newSetting.type, this.newSetting.valueText),
                    description: this.newSetting.description || null,
                })

                this.newSetting = {
                    key: '',
                    type: 'string',
                    valueText: '',
                    description: '',
                }

                await this.loadSettings()
            } catch (error) {
                alert(error.response?.data?.message ?? 'Не удалось создать настройку.')
            }
        },

        async saveSetting(setting) {
            try {
                await axios.patch(`/api/admin/settings/${setting.id}`, {
                    key: setting.key,
                    type: setting.type,
                    value: this.parseSettingValue(setting.type, setting.valueText),
                    description: setting.description || null,
                })
                await this.loadSettings()
            } catch (error) {
                alert(error.response?.data?.message ?? 'Не удалось сохранить настройку.')
            }
        },

        async removeSetting(setting) {
            if (!confirm(`Удалить настройку "${setting.key}"?`)) {
                return
            }

            await axios.delete(`/api/admin/settings/${setting.id}`)
            await this.loadSettings()
        },

        stringifySettingValue(value) {
            if (value === null || value === undefined) {
                return ''
            }

            if (typeof value === 'object') {
                return JSON.stringify(value, null, 2)
            }

            return String(value)
        },

        parseSettingValue(type, valueText) {
            const raw = (valueText ?? '').trim()
            if (raw === '') {
                return null
            }

            if (type === 'integer') {
                return Number.parseInt(raw, 10)
            }
            if (type === 'float') {
                return Number.parseFloat(raw)
            }
            if (type === 'boolean') {
                return ['1', 'true', 'yes', 'on'].includes(raw.toLowerCase())
            }
            if (type === 'json') {
                try {
                    return JSON.parse(raw)
                } catch (error) {
                    return raw
                }
            }

            return raw
        },

        formatDate(value) {
            if (!value) {
                return '—'
            }

            const date = new Date(value)
            if (Number.isNaN(date.getTime())) {
                return value
            }

            return date.toLocaleString('ru-RU')
        },

        toDatetimeLocal(value) {
            if (!value) {
                return ''
            }

            const date = new Date(value)
            if (Number.isNaN(date.getTime())) {
                return ''
            }

            const year = date.getFullYear()
            const month = `${date.getMonth() + 1}`.padStart(2, '0')
            const day = `${date.getDate()}`.padStart(2, '0')
            const hours = `${date.getHours()}`.padStart(2, '0')
            const minutes = `${date.getMinutes()}`.padStart(2, '0')

            return `${year}-${month}-${day}T${hours}:${minutes}`
        },

        fromDatetimeLocal(value) {
            if (!value) {
                return null
            }

            const date = new Date(value)
            if (Number.isNaN(date.getTime())) {
                return null
            }

            return date.toISOString()
        },
    }
}
</script>
