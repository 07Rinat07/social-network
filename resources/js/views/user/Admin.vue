<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">{{ $t('admin.title') }}</h1>
            <p class="section-subtitle">{{ $t('admin.subtitle') }}</p>

            <div class="stats-grid" style="margin-bottom: 1rem;">
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsUsers') }}</span>
                    <div class="stat-value">{{ summary.users ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsPosts') }}</span>
                    <div class="stat-value">{{ summary.posts ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsComments') }}</span>
                    <div class="stat-value">{{ summary.comments ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsLikes') }}</span>
                    <div class="stat-value">{{ summary.likes ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsMessages') }}</span>
                    <div class="stat-value">{{ summary.messages ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsPublicPosts') }}</span>
                    <div class="stat-value">{{ summary.public_posts ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsCarousel') }}</span>
                    <div class="stat-value">{{ summary.carousel_posts ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsAttachments') }}</span>
                    <div class="stat-value">{{ summary.chat_attachments ?? 0 }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">{{ $t('admin.statsBlocks') }}</span>
                    <div class="stat-value">{{ summary.active_blocks ?? 0 }}</div>
                </div>
            </div>

            <div class="admin-tabs">
                <button class="btn" :class="activeTab === 'users' ? 'btn-primary' : 'btn-outline'" @click="selectTab('users')">{{ $t('admin.tabUsers') }}</button>
                <button class="btn" :class="activeTab === 'posts' ? 'btn-primary' : 'btn-outline'" @click="selectTab('posts')">{{ $t('admin.tabPosts') }}</button>
                <button class="btn" :class="activeTab === 'comments' ? 'btn-primary' : 'btn-outline'" @click="selectTab('comments')">{{ $t('admin.tabComments') }}</button>
                <button class="btn" :class="activeTab === 'feedback' ? 'btn-primary' : 'btn-outline'" @click="selectTab('feedback')">{{ $t('admin.tabFeedback') }}</button>
                <button class="btn" :class="activeTab === 'conversations' ? 'btn-primary' : 'btn-outline'" @click="selectTab('conversations')">{{ $t('admin.tabConversations') }}</button>
                <button class="btn" :class="activeTab === 'messages' ? 'btn-primary' : 'btn-outline'" @click="selectTab('messages')">{{ $t('admin.tabMessages') }}</button>
                <button class="btn" :class="activeTab === 'blocks' ? 'btn-primary' : 'btn-outline'" @click="selectTab('blocks')">{{ $t('admin.tabBlocks') }}</button>
                <button class="btn" :class="activeTab === 'iptvSeeds' ? 'btn-primary' : 'btn-outline'" @click="selectTab('iptvSeeds')">{{ $t('admin.tabIptvSeeds') }}</button>
                <button class="btn" :class="activeTab === 'settings' ? 'btn-primary' : 'btn-outline'" @click="selectTab('settings')">{{ $t('admin.tabSettings') }}</button>
            </div>

            <div v-if="activeTab === 'users'" class="table-wrap fade-in">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ $t('admin.name') }}</th>
                        <th>Email</th>
                        <th>{{ $t('admin.adminColumn') }}</th>
                        <th>{{ $t('admin.postsColumn') }}</th>
                        <th>{{ $t('admin.statusColumn') }}</th>
                        <th>{{ $t('admin.actionsColumn') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr
                        v-for="user in users"
                        :key="`admin-user-${user.id}`"
                        :class="{ 'admin-user-row--dirty': hasUserChanges(user) }"
                    >
                        <td>{{ user.id }}</td>
                        <td>
                            <input
                                class="input-field"
                                :class="{ 'admin-user-field--dirty': hasUserChanges(user) }"
                                v-model="user.name"
                                type="text"
                            >
                        </td>
                        <td>
                            <input
                                class="input-field"
                                :class="{ 'admin-user-field--dirty': hasUserChanges(user) }"
                                v-model="user.email"
                                type="email"
                            >
                        </td>
                        <td>
                            <select
                                class="select-field"
                                :class="{ 'admin-user-field--dirty': hasUserChanges(user) }"
                                v-model="user.is_admin"
                            >
                                <option :value="false">{{ $t('admin.no') }}</option>
                                <option :value="true">{{ $t('admin.yes') }}</option>
                            </select>
                        </td>
                        <td>{{ user.posts_count ?? 0 }}</td>
                        <td>
                            <span
                                class="admin-user-status"
                                :class="`is-${userStatusMeta(user).kind}`"
                            >
                                {{ userStatusMeta(user).label }}
                            </span>
                        </td>
                        <td>
                            <div class="admin-user-actions">
                                <button
                                    class="btn btn-success btn-sm"
                                    @click="saveUser(user)"
                                    :disabled="!canSaveUser(user)"
                                >
                                    {{ user._save_state === 'saving' ? $t('admin.saving') : $t('admin.save') }}
                                </button>
                                <button class="btn btn-danger btn-sm" @click="removeUser(user)">{{ $t('common.delete') }}</button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="activeTab === 'posts'" class="simple-list fade-in">
                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">{{ $t('admin.createPostAsAnyUser') }}</strong>
                    <div class="form-grid">
                        <input
                            class="input-field"
                            type="number"
                            min="1"
                            v-model.number="postCreateForm.user_id"
                            :placeholder="$t('admin.authorId')"
                        >
                        <input
                            class="input-field"
                            type="text"
                            maxlength="255"
                            v-model.trim="postCreateForm.title"
                            :placeholder="$t('admin.postTitle')"
                        >
                        <textarea
                            class="textarea-field"
                            style="min-height: 120px;"
                            maxlength="5000"
                            v-model.trim="postCreateForm.content"
                            :placeholder="$t('admin.postText')"
                        ></textarea>
                        <input
                            class="input-field"
                            type="number"
                            min="1"
                            v-model.number="postCreateForm.reposted_id"
                            :placeholder="$t('admin.repostIdOptional')"
                        >

                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="postCreateForm.is_public">
                            {{ $t('admin.publicPost') }}
                        </label>
                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="postCreateForm.show_in_feed" :disabled="!postCreateForm.is_public">
                            {{ $t('admin.showInFeed') }}
                        </label>
                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="postCreateForm.show_in_carousel" :disabled="!postCreateForm.is_public">
                            {{ $t('admin.showInCarousel') }}
                        </label>

                        <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                            <button class="btn btn-primary btn-sm" @click="createPost">{{ $t('admin.createPost') }}</button>
                            <button class="btn btn-danger btn-sm" @click="clearAllLikes">{{ $t('admin.clearAllLikes') }}</button>
                        </div>
                    </div>
                </div>

                <div class="simple-item" v-for="post in posts" :key="`admin-post-${post.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap; align-items: center;">
                        <strong>#{{ post.id }} · {{ $t('admin.likesCount', { count: post.likes_count ?? 0 }) }}</strong>
                        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                            <button class="btn btn-outline btn-sm" @click="clearPostLikes(post)">{{ $t('admin.clearLikes') }}</button>
                            <button class="btn btn-danger btn-sm" @click="removePost(post)">{{ $t('common.delete') }}</button>
                        </div>
                    </div>

                    <div class="form-grid" style="margin-top: 0.6rem;">
                        <p class="muted" style="margin: 0;">{{ $t('admin.currentAuthor', { name: post.user?.name ?? '—', id: post.user?.id ?? post.user_id }) }}</p>
                        <input
                            class="input-field"
                            type="number"
                            min="1"
                            v-model.number="post.user_id"
                            :placeholder="$t('admin.authorId')"
                        >
                        <input
                            class="input-field"
                            type="text"
                            maxlength="255"
                            v-model.trim="post.title"
                            :placeholder="$t('admin.postTitle')"
                        >
                        <textarea
                            class="textarea-field"
                            style="min-height: 120px;"
                            maxlength="5000"
                            v-model.trim="post.content"
                            :placeholder="$t('admin.postText')"
                        ></textarea>
                        <input
                            class="input-field"
                            type="number"
                            min="1"
                            v-model.number="post.reposted_id"
                            :placeholder="$t('admin.repostIdOptional')"
                        >

                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="post.is_public">
                            {{ $t('admin.publicPost') }}
                        </label>
                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="post.show_in_feed" :disabled="!post.is_public">
                            {{ $t('admin.showInFeed') }}
                        </label>
                        <label class="muted" style="display: flex; align-items: center; gap: 0.45rem;">
                            <input type="checkbox" v-model="post.show_in_carousel" :disabled="!post.is_public">
                            {{ $t('admin.showInCarousel') }}
                        </label>

                        <button class="btn btn-success btn-sm" @click="savePost(post)">{{ $t('admin.savePost') }}</button>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'comments'" class="simple-list fade-in">
                <div class="simple-item" v-for="comment in comments" :key="`admin-comment-${comment.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap;">
                        <strong>#{{ comment.id }} · {{ comment.user?.name ?? '—' }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeComment(comment)">{{ $t('common.delete') }}</button>
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
                            <select class="select-field" v-model="item.status" style="min-width: min(170px, 100%);">
                                <option value="new">new</option>
                                <option value="in_progress">in_progress</option>
                                <option value="resolved">resolved</option>
                            </select>
                            <button class="btn btn-success btn-sm" @click="saveFeedback(item)">{{ $t('admin.update') }}</button>
                            <button class="btn btn-danger btn-sm" @click="removeFeedback(item)">{{ $t('common.delete') }}</button>
                        </div>
                    </div>
                    <p style="margin: 0.45rem 0 0;">{{ item.message }}</p>
                </div>
            </div>

            <div v-if="activeTab === 'conversations'" class="simple-list fade-in">
                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.45rem;">{{ $t('admin.bulkChatActions') }}</strong>
                    <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                        <button class="btn btn-outline btn-sm" @click="clearAllConversations">{{ $t('admin.clearAllChats') }}</button>
                        <button class="btn btn-danger btn-sm" @click="removeAllConversations">{{ $t('admin.deleteAllChats') }}</button>
                    </div>
                </div>

                <div class="simple-item" v-for="conversation in conversations" :key="`admin-conversation-${conversation.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap; align-items: center;">
                        <strong>#{{ conversation.id }} · {{ conversation.display_title || conversation.title }}</strong>
                        <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                            <button class="btn btn-outline btn-sm" @click="clearConversationMessages(conversation)">{{ $t('admin.clearChat') }}</button>
                            <button class="btn btn-danger btn-sm" @click="removeConversation(conversation)">{{ $t('admin.deleteChat') }}</button>
                        </div>
                    </div>
                    <p class="muted" style="margin: 0.25rem 0 0;">
                        {{ $t('admin.chatMeta', {
                            type: conversation.type,
                            participants: conversation.participants?.length ?? 0,
                            messages: conversation.messages_count ?? 0,
                        }) }}
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'messages'" class="simple-list fade-in">
                <div class="simple-item" v-for="message in messages" :key="`admin-message-${message.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap;">
                        <strong>#{{ message.id }} · {{ message.user?.name ?? '—' }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeMessage(message)">{{ $t('common.delete') }}</button>
                    </div>
                    <p class="muted" style="margin: 0.2rem 0 0;">
                        {{ $t('admin.chatNumber', { id: message.conversation_id }) }}
                    </p>
                    <p style="margin: 0.35rem 0 0;">{{ message.body || $t('admin.onlyAttachments') }}</p>
                    <p class="muted" style="margin: 0.25rem 0 0;" v-if="(message.attachments?.length ?? 0) > 0">
                        {{ $t('admin.attachmentsCount', { count: message.attachments.length }) }}
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'blocks'" class="simple-list fade-in">
                <div class="simple-item" v-for="block in blocks" :key="`admin-block-${block.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap;">
                        <div>
                            <strong>#{{ block.id }} · {{ block.blocker?.name ?? '—' }} → {{ block.blocked_user?.name ?? '—' }}</strong>
                            <p class="muted" style="margin: 0.2rem 0 0;">
                                {{ $t('admin.blockStatus') }}: {{ block.expires_at ? $t('admin.blockUntil', { date: formatDate(block.expires_at) }) : $t('admin.blockPermanent') }}
                            </p>
                        </div>
                        <button class="btn btn-danger btn-sm" @click="removeBlock(block)">{{ $t('common.delete') }}</button>
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
                            :placeholder="$t('admin.reason')"
                            maxlength="500"
                            v-model="block.reason"
                        >

                        <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                            <button class="btn btn-success btn-sm" @click="saveBlock(block)">{{ $t('admin.save') }}</button>
                            <button class="btn btn-outline btn-sm" @click="setPermanentBlock(block)">{{ $t('admin.makePermanent') }}</button>
                            <button class="btn btn-outline btn-sm" @click="extendBlockFor24Hours(block)">{{ $t('admin.add24h') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'iptvSeeds'" class="simple-list fade-in">
                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">{{ $t('admin.iptvSeedsTitle') }}</strong>
                    <p class="muted" style="margin: 0 0 1rem;">{{ $t('admin.iptvSeedsSubtitle') }}</p>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem; border-bottom: 1px solid var(--line); padding-bottom: 1.5rem;">
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <input class="input-field" type="text" v-model.trim="newIptvSeed.name" :placeholder="$t('admin.name')" style="flex: 1; min-width: 200px;">
                            <input class="input-field" type="url" v-model.trim="newIptvSeed.url" placeholder="URL (m3u/m3u8)" style="flex: 2; min-width: 300px;">
                        </div>
                        <div style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                            <div style="display: flex; gap: 1rem; align-items: center;">
                                <input class="input-field" type="number" v-model.number="newIptvSeed.sort_order" :placeholder="$t('admin.sortOrder')" style="width: 100px;">
                                <label style="display: flex; gap: 0.4rem; align-items: center; cursor: pointer;">
                                    <input type="checkbox" v-model="newIptvSeed.is_active">
                                    <small>{{ $t('admin.isActive') }}</small>
                                </label>
                            </div>
                            <button class="btn btn-primary" @click="createIptvSeed" :disabled="!newIptvSeed.name || !newIptvSeed.url">
                                {{ $t('admin.create') }}
                            </button>
                        </div>
                    </div>

                    <div v-for="seed in iptvSeeds" :key="`admin-seed-${seed.id}`" class="simple-item" style="border-left: 3px solid var(--accent); padding: 1rem;">
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; width: 100%;">
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <input class="input-field" type="text" v-model.trim="seed.name" :placeholder="$t('admin.name')" style="flex: 1; min-width: 200px;">
                                <input class="input-field" type="url" v-model.trim="seed.url" placeholder="URL" style="flex: 2; min-width: 300px;">
                            </div>
                            <div style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                                <div style="display: flex; gap: 1rem; align-items: center;">
                                    <input class="input-field" type="number" v-model.number="seed.sort_order" :placeholder="$t('admin.sortOrder')" style="width: 100px;">
                                    <label style="display: flex; gap: 0.4rem; align-items: center; cursor: pointer;">
                                        <input type="checkbox" v-model="seed.is_active">
                                        <small>{{ $t('admin.isActive') }}</small>
                                    </label>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button class="btn btn-success btn-sm" @click="updateIptvSeed(seed)">{{ $t('admin.save') }}</button>
                                    <button class="btn btn-danger btn-sm" @click="removeIptvSeed(seed)">{{ $t('common.delete') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'settings'" class="simple-list fade-in">
                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">{{ $t('admin.homeContentTitle') }}</strong>
                    <p class="muted" style="margin: 0 0 0.65rem;">
                        {{ $t('admin.homeContentSubtitle') }}
                    </p>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; margin: 0 0 0.75rem; flex-wrap: wrap;">
                        <span class="muted" style="font-size: 0.82rem;">
                            {{ $t('admin.editLanguage') }}:
                            <strong>{{ homeContentActiveLocale === 'en' ? $t('admin.languageNameEn') : $t('admin.languageNameRu') }}</strong>
                        </span>
                        <div style="display: flex; gap: 0.45rem;">
                            <button
                                class="btn btn-sm"
                                :class="homeContentActiveLocale === 'ru' ? 'btn-primary' : 'btn-outline'"
                                type="button"
                                @click="setHomeContentLocale('ru')"
                            >
                                RU
                            </button>
                            <button
                                class="btn btn-sm"
                                :class="homeContentActiveLocale === 'en' ? 'btn-primary' : 'btn-outline'"
                                type="button"
                                @click="setHomeContentLocale('en')"
                            >
                                EN
                            </button>
                        </div>
                    </div>

                    <div class="form-grid">
                        <label class="muted" style="font-size: 0.82rem;">{{ $t('admin.badge') }}</label>
                        <input class="input-field" type="text" maxlength="80" v-model.trim="activeHomeContentLocalePayload.badge">

                        <label class="muted" style="font-size: 0.82rem;">{{ $t('admin.heroTitle') }}</label>
                        <textarea class="textarea-field" style="min-height: 90px;" maxlength="300" v-model.trim="activeHomeContentLocalePayload.hero_title"></textarea>

                        <label class="muted" style="font-size: 0.82rem;">{{ $t('admin.heroNote') }}</label>
                        <textarea class="textarea-field" style="min-height: 120px;" maxlength="3000" v-model.trim="activeHomeContentLocalePayload.hero_note"></textarea>

                        <label class="muted" style="font-size: 0.82rem;">{{ $t('admin.featureItems') }}</label>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <div
                                v-for="(item, index) in activeHomeContentLocalePayload.feature_items"
                                :key="`home-feature-item-${index}`"
                                style="display: flex; gap: 0.45rem; align-items: center;"
                            >
                                <input
                                    class="input-field"
                                    type="text"
                                    maxlength="220"
                                    :placeholder="$t('admin.featureItemWithIndex', { index: index + 1 })"
                                    v-model.trim="activeHomeContentLocalePayload.feature_items[index]"
                                >
                                <button
                                    class="btn btn-danger btn-sm"
                                    type="button"
                                    @click="removeHomeFeatureItem(index)"
                                    :disabled="activeHomeContentLocalePayload.feature_items.length <= 1"
                                >
                                    {{ $t('common.delete') }}
                                </button>
                            </div>
                            <button
                                class="btn btn-outline btn-sm"
                                type="button"
                                @click="addHomeFeatureItem"
                                :disabled="activeHomeContentLocalePayload.feature_items.length >= 8"
                            >
                                {{ $t('admin.addItem') }}
                            </button>
                        </div>

                        <label class="muted" style="font-size: 0.82rem;">{{ $t('admin.feedbackTitle') }}</label>
                        <input class="input-field" type="text" maxlength="180" v-model.trim="activeHomeContentLocalePayload.feedback_title">

                        <label class="muted" style="font-size: 0.82rem;">{{ $t('admin.feedbackSubtitle') }}</label>
                        <textarea class="textarea-field" style="min-height: 90px;" maxlength="500" v-model.trim="activeHomeContentLocalePayload.feedback_subtitle"></textarea>

                        <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                            <button class="btn btn-success btn-sm" @click="saveHomeContent">{{ $t('admin.saveHomeContent') }}</button>
                            <button class="btn btn-outline btn-sm" @click="resetHomeContent">{{ $t('admin.resetToDefault') }}</button>
                        </div>
                    </div>
                </div>

                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">{{ $t('admin.storageTitle') }}</strong>
                    <p class="muted" style="margin: 0 0 0.65rem;">
                        {{ $t('admin.storageSubtitle') }}
                    </p>

                    <div class="form-grid">
                        <select class="select-field" v-model="storageSettings.media_storage_mode">
                            <option value="server_local">{{ $t('admin.storageServerOnly') }}</option>
                            <option value="cloud">{{ $t('admin.storageCloudOnly') }}</option>
                            <option value="user_choice">{{ $t('admin.storageUserChoice') }}</option>
                        </select>

                        <label class="muted" style="font-size: 0.82rem;">{{ $t('admin.serverDisk') }}</label>
                        <input class="input-field" v-model.trim="storageSettings.server_media_disk" type="text" placeholder="public">

                        <label class="muted" style="font-size: 0.82rem;">{{ $t('admin.cloudDisk') }}</label>
                        <input class="input-field" v-model.trim="storageSettings.cloud_media_disk" type="text" placeholder="s3">

                        <button class="btn btn-success btn-sm" @click="saveStorageSettings">{{ $t('admin.saveStorage') }}</button>
                    </div>
                </div>

                <div class="simple-item" style="display: block;">
                    <strong style="display: block; margin-bottom: 0.5rem;">{{ $t('admin.createSiteSetting') }}</strong>
                    <div class="form-grid">
                        <input class="input-field" v-model.trim="newSetting.key" type="text" placeholder="key_name">
                        <select class="select-field" v-model="newSetting.type">
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="float">float</option>
                            <option value="boolean">boolean</option>
                            <option value="json">json</option>
                        </select>
                        <textarea class="textarea-field" style="min-height: 90px;" v-model="newSetting.valueText" :placeholder="$t('admin.value')"></textarea>
                        <input class="input-field" v-model.trim="newSetting.description" type="text" :placeholder="$t('admin.descriptionOptional')">
                        <button class="btn btn-primary btn-sm" @click="createSetting">{{ $t('admin.create') }}</button>
                    </div>
                </div>

                <div class="simple-item" v-for="setting in settings" :key="`admin-setting-${setting.id}`" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 0.8rem; flex-wrap: wrap; align-items: center;">
                        <strong>#{{ setting.id }} · {{ setting.key }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeSetting(setting)">{{ $t('common.delete') }}</button>
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
                        <input class="input-field" type="text" v-model.trim="setting.description" :placeholder="$t('admin.description')">
                        <button class="btn btn-success btn-sm" @click="saveSetting(setting)">{{ $t('admin.save') }}</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
import enMessages from '../../i18n/messages/en'
import ruMessages from '../../i18n/messages/ru'

const CHAT_WIDGET_SYNC_EVENT = 'social:chat:sync'
const CHAT_WIDGET_SYNC_SOURCE_PAGE = 'chat-page'
const CHAT_WIDGET_SYNC_TYPE_STATE_REFRESH = 'state-refresh'

function resolveMessage(messages, key, fallback = '') {
    if (!messages || typeof messages !== 'object' || typeof key !== 'string' || key.trim() === '') {
        return fallback
    }

    const value = key.split('.').reduce((cursor, part) => {
        if (!cursor || typeof cursor !== 'object') {
            return null
        }
        return Object.prototype.hasOwnProperty.call(cursor, part) ? cursor[part] : null
    }, messages)

    return value ?? fallback
}

function defaultHomeContentFromMessages(locale = 'ru') {
    const dictionary = locale === 'en' ? enMessages : ruMessages

    const featureItems = resolveMessage(dictionary, 'admin.defaultHome.featureItems', [])
    return {
        badge: String(resolveMessage(dictionary, 'admin.defaultHome.badge', '')),
        hero_title: String(resolveMessage(dictionary, 'admin.defaultHome.heroTitle', '')),
        hero_note: String(resolveMessage(dictionary, 'admin.defaultHome.heroNote', '')),
        feature_items: Array.isArray(featureItems)
            ? featureItems.map((item) => String(item ?? '').trim()).filter((item) => item !== '').slice(0, 8)
            : [],
        feedback_title: String(resolveMessage(dictionary, 'admin.defaultHome.feedbackTitle', '')),
        feedback_subtitle: String(resolveMessage(dictionary, 'admin.defaultHome.feedbackSubtitle', '')),
    }
}

function buildDefaultHomeContentLocalesPayload() {
    return {
        locale: 'ru',
        locales: {
            ru: defaultHomeContentFromMessages('ru'),
            en: defaultHomeContentFromMessages('en'),
        },
    }
}

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
            iptvSeeds: [],
            newIptvSeed: {
                name: '',
                url: '',
                sort_order: 0,
                is_active: true,
            },
            settings: [],
            homeContentActiveLocale: 'ru',
            homeContentForm: buildDefaultHomeContentLocalesPayload(),
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

    computed: {
        activeHomeContentLocalePayload() {
            if (!this.homeContentForm?.locales || typeof this.homeContentForm.locales !== 'object') {
                return this.defaultHomeContentPayload(this.homeContentActiveLocale)
            }

            return this.homeContentForm.locales[this.homeContentActiveLocale]
                ?? this.homeContentForm.locales.ru
                ?? this.defaultHomeContentPayload('ru')
        },
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
            if (tab === 'iptvSeeds') {
                await this.loadIptvSeeds()
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
            this.users = (response.data.data ?? []).map((user) => this.normalizeAdminUser(user))
        },

        normalizeAdminUser(user) {
            const normalized = {
                ...user,
                is_admin: Boolean(user.is_admin),
            }

            return {
                ...normalized,
                _save_state: 'idle',
                _save_message: '',
                _error_snapshot: null,
                _snapshot: this.buildAdminUserSnapshot(normalized),
            }
        },

        buildAdminUserSnapshot(user) {
            return {
                name: String(user?.name ?? '').trim(),
                email: String(user?.email ?? '').trim(),
                is_admin: Boolean(user?.is_admin),
            }
        },

        hasUserChanges(user) {
            const snapshot = user?._snapshot ?? this.buildAdminUserSnapshot(user)
            const current = this.buildAdminUserSnapshot(user)

            return current.name !== snapshot.name
                || current.email !== snapshot.email
                || current.is_admin !== snapshot.is_admin
        },

        canSaveUser(user) {
            if (!user || user._save_state === 'saving') {
                return false
            }

            const current = this.buildAdminUserSnapshot(user)
            if (current.name === '' || current.email === '') {
                return false
            }

            return this.hasUserChanges(user)
        },

        userStatusMeta(user) {
            if (!user) {
                return { kind: 'idle', label: '—' }
            }

            if (user._save_state === 'saving') {
                return { kind: 'saving', label: this.$t('admin.saving') }
            }

            if (user._save_state === 'error') {
                const errorSnapshot = user?._error_snapshot
                if (errorSnapshot) {
                    const current = this.buildAdminUserSnapshot(user)
                    const changedSinceError = current.name !== errorSnapshot.name
                        || current.email !== errorSnapshot.email
                        || current.is_admin !== errorSnapshot.is_admin

                    if (changedSinceError) {
                        return { kind: 'dirty', label: this.$t('admin.unsavedChanges') }
                    }
                }

                return {
                    kind: 'error',
                    label: user._save_message || this.$t('admin.saveError'),
                }
            }

            if (this.hasUserChanges(user)) {
                return { kind: 'dirty', label: this.$t('admin.unsavedChanges') }
            }

            if (user._save_state === 'success') {
                return {
                    kind: 'success',
                    label: user._save_message || this.$t('admin.saved'),
                }
            }

            return { kind: 'idle', label: this.$t('admin.noChanges') }
        },

        extractRequestError(error, fallback) {
            const firstError = Object.values(error.response?.data?.errors ?? {})
                .flat()
                .find(Boolean)

            return firstError ?? error.response?.data?.message ?? fallback
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

        async saveUser(user) {
            if (!this.canSaveUser(user)) {
                return
            }

            const previousSnapshot = user._snapshot ?? this.buildAdminUserSnapshot(user)
            const currentSnapshot = this.buildAdminUserSnapshot(user)
            const nameWasChanged = previousSnapshot.name !== currentSnapshot.name

            user._save_state = 'saving'
            user._save_message = ''
            user._error_snapshot = null

            try {
                const response = await axios.patch(`/api/admin/users/${user.id}`, {
                    name: user.name,
                    email: user.email,
                    is_admin: Boolean(user.is_admin),
                })

                const updatedUser = response.data?.data ?? {}
                user.name = updatedUser.name ?? user.name
                user.email = updatedUser.email ?? user.email
                user.is_admin = Boolean(updatedUser.is_admin ?? user.is_admin)
                user._snapshot = this.buildAdminUserSnapshot(user)

                const savedAt = new Date().toLocaleTimeString('ru-RU', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                })
                user._save_state = 'success'
                user._save_message = nameWasChanged
                    ? this.$t('admin.nameSavedAt', { time: savedAt })
                    : this.$t('admin.savedAt', { time: savedAt })

                await this.loadSummary()
            } catch (error) {
                user._save_state = 'error'
                user._save_message = this.extractRequestError(error, this.$t('admin.saveUserFailed'))
                user._error_snapshot = this.buildAdminUserSnapshot(user)
            }
        },

        async removeUser(user) {
            if (!confirm(this.$t('admin.confirmDeleteUser', { name: user.name }))) {
                return
            }

            try {
                await axios.delete(`/api/admin/users/${user.id}`)
                await this.loadUsers()
                await this.loadSummary()
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.deleteUserFailed'))
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
                alert(firstError ?? error.response?.data?.message ?? this.$t('admin.createPostFailed'))
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
                alert(firstError ?? error.response?.data?.message ?? this.$t('admin.updatePostFailed'))
            }
        },

        async removePost(post) {
            if (!confirm(this.$t('admin.confirmDeletePost', { id: post.id }))) {
                return
            }

            await axios.delete(`/api/admin/posts/${post.id}`)
            await this.loadPosts()
            await this.loadSummary()
        },

        async clearPostLikes(post) {
            if (!confirm(this.$t('admin.confirmClearPostLikes', { id: post.id }))) {
                return
            }

            await axios.delete(`/api/admin/posts/${post.id}/likes`)
            await this.loadPosts()
            await this.loadSummary()
        },

        async clearAllLikes() {
            if (!confirm(this.$t('admin.confirmClearAllLikes'))) {
                return
            }

            await axios.delete('/api/admin/likes')
            await this.loadPosts()
            await this.loadSummary()
        },

        async loadComments() {
            const response = await axios.get('/api/admin/comments', { params: { per_page: 50 } })
            this.comments = response.data.data ?? []
        },

        async removeComment(comment) {
            if (!confirm(this.$t('admin.confirmDeleteComment', { id: comment.id }))) {
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
            if (!confirm(this.$t('admin.confirmDeleteFeedback', { id: item.id }))) {
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

        async clearConversationMessages(conversation) {
            if (!confirm(this.$t('admin.confirmClearChatMessages', { id: conversation.id }))) {
                return
            }

            await axios.delete(`/api/admin/conversations/${conversation.id}/messages`)
            await Promise.all([this.loadConversations(), this.loadMessages(), this.loadSummary()])
            this.notifyChatSyncStateRefresh({
                conversationId: conversation?.id ?? null,
            })
        },

        async removeConversation(conversation) {
            if (!confirm(this.$t('admin.confirmDeleteChat', { id: conversation.id }))) {
                return
            }

            await axios.delete(`/api/admin/conversations/${conversation.id}`)
            await Promise.all([this.loadConversations(), this.loadMessages(), this.loadSummary()])
            this.notifyChatSyncStateRefresh({
                conversationId: conversation?.id ?? null,
            })
        },

        async clearAllConversations() {
            if (!confirm(this.$t('admin.confirmClearAllChatsMessages'))) {
                return
            }

            await axios.delete('/api/admin/conversations/messages')
            await Promise.all([this.loadConversations(), this.loadMessages(), this.loadSummary()])
            this.notifyChatSyncStateRefresh()
        },

        async removeAllConversations() {
            if (!confirm(this.$t('admin.confirmDeleteAllChats'))) {
                return
            }

            await axios.delete('/api/admin/conversations')
            await Promise.all([this.loadConversations(), this.loadMessages(), this.loadSummary()])
            this.notifyChatSyncStateRefresh()
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
            if (!confirm(this.$t('admin.confirmDeleteMessage', { id: message.id }))) {
                return
            }

            await axios.delete(`/api/admin/messages/${message.id}`)
            await this.loadMessages()
            await this.loadSummary()
            this.notifyChatSyncStateRefresh({
                conversationId: message?.conversation_id ?? null,
            })
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
                alert(error.response?.data?.message ?? this.$t('admin.updateBlockFailed'))
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
            if (!confirm(this.$t('admin.confirmDeleteBlock', { id: block.id }))) {
                return
            }

            await axios.delete(`/api/admin/blocks/${block.id}`)
            await this.loadBlocks()
            await this.loadSummary()
        },

        async loadIptvSeeds() {
            const response = await axios.get('/api/admin/iptv-seeds')
            this.iptvSeeds = response.data ?? []
        },

        async createIptvSeed() {
            try {
                await axios.post('/api/admin/iptv-seeds', this.newIptvSeed)
                this.newIptvSeed = {
                    name: '',
                    url: '',
                    sort_order: 0,
                    is_active: true,
                }
                await this.loadIptvSeeds()
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.createIptvSeedFailed'))
            }
        },

        async updateIptvSeed(seed) {
            try {
                await axios.patch(`/api/admin/iptv-seeds/${seed.id}`, {
                    name: seed.name,
                    url: seed.url,
                    sort_order: seed.sort_order,
                    is_active: seed.is_active,
                })
                await this.loadIptvSeeds()
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.updateIptvSeedFailed'))
            }
        },

        async removeIptvSeed(seed) {
            if (!confirm(this.$t('admin.confirmDeleteIptvSeed', { name: seed.name }))) {
                return
            }

            try {
                await axios.delete(`/api/admin/iptv-seeds/${seed.id}`)
                await this.loadIptvSeeds()
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.deleteIptvSeedFailed'))
            }
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

        defaultHomeContentPayload(locale = 'ru') {
            return defaultHomeContentFromMessages(locale === 'en' ? 'en' : 'ru')
        },

        defaultHomeContentLocalesPayload() {
            return buildDefaultHomeContentLocalesPayload()
        },

        normalizeSingleHomeLocalePayload(payload, locale = 'ru') {
            const fallback = this.defaultHomeContentPayload(locale)
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

        normalizeHomeContentPayload(payload) {
            const sourceLocales = payload?.locales && typeof payload.locales === 'object'
                ? payload.locales
                : {
                    ru: payload,
                    en: this.defaultHomeContentPayload('en'),
                }

            return {
                locale: payload?.locale === 'en' ? 'en' : 'ru',
                locales: {
                    ru: this.normalizeSingleHomeLocalePayload(sourceLocales.ru, 'ru'),
                    en: this.normalizeSingleHomeLocalePayload(sourceLocales.en, 'en'),
                },
            }
        },

        setHomeContentLocale(locale) {
            this.homeContentActiveLocale = locale === 'en' ? 'en' : 'ru'
        },

        async loadHomeContentSettings() {
            try {
                const response = await axios.get('/api/site/home-content', {
                    params: {
                        locale: this.homeContentActiveLocale,
                    },
                })
                this.homeContentForm = this.normalizeHomeContentPayload(response.data.data ?? {})
                this.homeContentActiveLocale = this.homeContentForm.locale === 'en' ? 'en' : 'ru'
            } catch (error) {
                this.homeContentForm = this.defaultHomeContentLocalesPayload()
                this.homeContentActiveLocale = 'ru'
            }
        },

        addHomeFeatureItem() {
            if (!Array.isArray(this.activeHomeContentLocalePayload?.feature_items)) {
                return
            }

            if (this.activeHomeContentLocalePayload.feature_items.length >= 8) {
                return
            }

            this.activeHomeContentLocalePayload.feature_items.push('')
        },

        removeHomeFeatureItem(index) {
            if (!Array.isArray(this.activeHomeContentLocalePayload?.feature_items)) {
                return
            }

            if (this.activeHomeContentLocalePayload.feature_items.length <= 1) {
                return
            }

            this.activeHomeContentLocalePayload.feature_items.splice(index, 1)
        },

        async saveHomeContent() {
            try {
                const response = await axios.patch('/api/admin/settings/home-content', {
                    locale: this.homeContentActiveLocale,
                    ...this.normalizeHomeContentPayload(this.homeContentForm),
                })
                this.homeContentForm = this.normalizeHomeContentPayload(response.data.data ?? {})
                this.homeContentActiveLocale = this.homeContentForm.locale === 'en' ? 'en' : 'ru'
            } catch (error) {
                const firstError = Object.values(error.response?.data?.errors ?? {})
                    .flat()
                    .find(Boolean)
                alert(firstError ?? error.response?.data?.message ?? this.$t('admin.saveHomeContentFailed'))
            }
        },

        async resetHomeContent() {
            if (!confirm(this.$t('admin.confirmResetHomeContent'))) {
                return
            }

            try {
                const response = await axios.delete('/api/admin/settings/home-content', {
                    params: {
                        locale: this.homeContentActiveLocale,
                    },
                })
                this.homeContentForm = this.normalizeHomeContentPayload(response.data.data ?? {})
                this.homeContentActiveLocale = this.homeContentForm.locale === 'en' ? 'en' : 'ru'
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.resetHomeContentFailed'))
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
                alert(error.response?.data?.message ?? this.$t('admin.saveStorageFailed'))
            }
        },

        async createSetting() {
            if (!this.newSetting.key) {
                alert(this.$t('admin.settingKeyRequired'))
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
                alert(error.response?.data?.message ?? this.$t('admin.createSettingFailed'))
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
                alert(error.response?.data?.message ?? this.$t('admin.saveSettingFailed'))
            }
        },

        async removeSetting(setting) {
            if (!confirm(this.$t('admin.confirmDeleteSetting', { key: setting.key }))) {
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

            return date.toLocaleString(this.$route?.params?.locale === 'en' ? 'en-GB' : 'ru-RU')
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
