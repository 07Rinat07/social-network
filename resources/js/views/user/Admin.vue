<template>
    <div class="page-wrap grid-layout">
        <section class="section-card">
            <h1 class="section-title">{{ $t('admin.title') }}</h1>
            <p class="section-subtitle">{{ $t('admin.subtitle') }}</p>

            <div class="stats-grid admin-summary-grid">
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
                <button class="btn" :class="activeTab === 'dashboard' ? 'btn-primary' : 'btn-outline'" @click="selectTab('dashboard')">{{ $t('admin.tabDashboard') }}</button>
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

            <div v-if="activeTab === 'dashboard'" class="simple-list fade-in admin-dashboard">
                <div class="simple-item admin-dashboard-head">
                    <div>
                        <strong>{{ $t('admin.dashboardTitle') }}</strong>
                        <p class="muted admin-dashboard-subtitle">{{ $t('admin.dashboardSubtitle') }}</p>
                    </div>
                    <div class="admin-dashboard-controls">
                        <div class="admin-dashboard-control-field admin-dashboard-control-field-year">
                            <label class="muted" for="admin-dashboard-year">{{ $t('admin.dashboardYear') }}</label>
                            <select
                                id="admin-dashboard-year"
                                class="select-field admin-dashboard-control-input"
                                v-model.number="dashboardYear"
                                @change="onDashboardYearChanged"
                            >
                                <option
                                    v-for="year in (dashboard.available_years ?? [currentYear])"
                                    :key="`admin-dashboard-year-${year}`"
                                    :value="year"
                                >
                                    {{ year }}
                                </option>
                            </select>
                        </div>

                        <button
                            class="btn btn-outline btn-sm admin-dashboard-control-btn"
                            @click="loadDashboard"
                            :disabled="dashboardLoading"
                        >
                            {{ dashboardLoading ? $t('common.loading') : $t('admin.dashboardRefresh') }}
                        </button>

                        <div class="admin-dashboard-control-field admin-dashboard-control-field-date">
                            <label class="muted" for="admin-dashboard-export-from">{{ $t('admin.dashboardExportFrom') }}</label>
                            <input
                                id="admin-dashboard-export-from"
                                class="input-field admin-dashboard-control-input"
                                type="date"
                                v-model="dashboardExportDateFrom"
                            >
                        </div>

                        <div class="admin-dashboard-control-field admin-dashboard-control-field-date">
                            <label class="muted" for="admin-dashboard-export-to">{{ $t('admin.dashboardExportTo') }}</label>
                            <input
                                id="admin-dashboard-export-to"
                                class="input-field admin-dashboard-control-input"
                                type="date"
                                v-model="dashboardExportDateTo"
                            >
                        </div>

                        <button
                            class="btn btn-outline btn-sm admin-dashboard-control-btn"
                            @click="exportDashboard('xls')"
                            :disabled="dashboardLoading || dashboardExporting"
                        >
                            {{ isDashboardExporting('xls') ? $t('admin.dashboardExporting') : $t('admin.dashboardExportExcel') }}
                        </button>
                        <button
                            class="btn btn-outline btn-sm admin-dashboard-control-btn"
                            @click="exportDashboard('json')"
                            :disabled="dashboardLoading || dashboardExporting"
                        >
                            {{ isDashboardExporting('json') ? $t('admin.dashboardExporting') : $t('admin.dashboardExportJson') }}
                        </button>
                    </div>
                </div>
                <p class="muted admin-dashboard-period-note">
                    {{
                        $t('admin.dashboardPeriodActive', {
                            from: dashboard.period?.from ?? dashboardExportDateFrom,
                            to: dashboard.period?.to ?? dashboardExportDateTo,
                        })
                    }}
                </p>

                <div class="admin-dashboard-kpi-grid">
                    <article class="admin-dashboard-kpi-card">
                        <span class="admin-dashboard-kpi-label">{{ $t('admin.dashboardKpiUsers') }}</span>
                        <strong class="admin-dashboard-kpi-value">{{ formatDashboardNumber(dashboard.kpis?.users_total) }}</strong>
                        <small class="muted">
                            {{ $t('admin.dashboardKpiUsersDelta', { count: formatDashboardNumber(dashboard.kpis?.users_new_year) }) }}
                        </small>
                    </article>
                    <article class="admin-dashboard-kpi-card">
                        <span class="admin-dashboard-kpi-label">{{ $t('admin.dashboardKpiSubscriptions') }}</span>
                        <strong class="admin-dashboard-kpi-value">{{ formatDashboardNumber(dashboard.kpis?.subscriptions_year) }}</strong>
                        <small class="muted">
                            {{ $t('admin.dashboardKpiSubscriptionsAvg', { count: dashboard.kpis?.subscriptions_avg_month ?? 0 }) }}
                        </small>
                    </article>
                    <article class="admin-dashboard-kpi-card">
                        <span class="admin-dashboard-kpi-label">{{ dashboardKpiPrimaryLabel }}</span>
                        <strong class="admin-dashboard-kpi-value">{{ formatDashboardNumber(dashboard.preference?.total_actions) }}</strong>
                        <small class="muted">
                            {{ $t('admin.dashboardKpiLeader', { feature: dashboardFeatureLabel(dashboard.preference?.leader_key) }) }}
                        </small>
                    </article>
                    <article class="admin-dashboard-kpi-card">
                        <span class="admin-dashboard-kpi-label">{{ $t('admin.dashboardKpiActiveUsers') }}</span>
                        <strong class="admin-dashboard-kpi-value">{{ formatDashboardNumber(dashboard.engagement?.active_users_30d) }}</strong>
                        <small class="muted">
                            {{ $t('admin.dashboardKpiChatters', { count: formatDashboardNumber(dashboard.engagement?.chatters_30d) }) }}
                        </small>
                    </article>
                </div>

                <div class="admin-dashboard-grid">
                    <div class="simple-item admin-dashboard-card">
                        <div class="admin-dashboard-card-head">
                            <strong>{{ $t('admin.dashboardSubscriptionsTrend') }}</strong>
                            <span class="muted">
                                {{
                                    $t('admin.dashboardPeakMonth', {
                                        month: formatDashboardMonth(dashboard.kpis?.subscriptions_peak_month?.month),
                                        count: formatDashboardNumber(dashboard.kpis?.subscriptions_peak_month?.value),
                                    })
                                }}
                            </span>
                        </div>

                        <div class="admin-dashboard-line-chart">
                            <svg viewBox="0 0 760 250" role="img" aria-hidden="true" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="admin-dashboard-subscriptions-gradient" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="rgba(15, 108, 242, 0.35)" />
                                        <stop offset="100%" stop-color="rgba(15, 108, 242, 0.03)" />
                                    </linearGradient>
                                </defs>

                                <path
                                    v-if="dashboardSubscriptionsAreaPath"
                                    :d="dashboardSubscriptionsAreaPath"
                                    fill="url(#admin-dashboard-subscriptions-gradient)"
                                />
                                <path
                                    v-if="dashboardSubscriptionsLinePath"
                                    :d="dashboardSubscriptionsLinePath"
                                    fill="none"
                                    stroke="rgba(15, 108, 242, 0.95)"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    v-for="point in dashboardSubscriptionsPoints"
                                    :key="`admin-subscriptions-point-${point.month}`"
                                    :cx="point.x"
                                    :cy="point.y"
                                    r="4"
                                    fill="#0f6cf2"
                                />
                            </svg>
                        </div>

                        <div class="admin-dashboard-months-row">
                            <div
                                class="admin-dashboard-month-chip"
                                v-for="item in (dashboard.subscriptions_by_month ?? [])"
                                :key="`admin-dashboard-subscriptions-${item.month}`"
                            >
                                <span>{{ formatDashboardMonth(item.month) }}</span>
                                <strong>{{ formatDashboardNumber(item.value) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="simple-item admin-dashboard-card">
                        <div class="admin-dashboard-card-head">
                            <strong>{{ $t('admin.dashboardFeaturePreferences') }}</strong>
                            <span class="muted">{{ dashboardMethodLabelText }}</span>
                        </div>

                        <div class="admin-dashboard-preference">
                            <div class="admin-dashboard-donut" :style="{ '--donut-gradient': dashboardPreferenceGradient }">
                                <div class="admin-dashboard-donut-core">
                                    <strong>{{ formatDashboardNumber(dashboard.preference?.total_actions) }}</strong>
                                    <span>{{ dashboardPreferenceUnitLabel }}</span>
                                </div>
                            </div>

                            <div class="admin-dashboard-preference-legend">
                                <div
                                    class="admin-dashboard-legend-item"
                                    v-for="item in (dashboard.preference?.items ?? [])"
                                    :key="`admin-dashboard-preference-${item.key}`"
                                >
                                    <span class="admin-dashboard-legend-marker" :style="{ background: dashboardFeatureColor(item.key) }"></span>
                                    <span>{{ dashboardFeatureLabel(item.key) }}</span>
                                    <strong>{{ formatDashboardNumber(item.value) }}</strong>
                                    <small>{{ Number(item.share ?? 0).toFixed(1) }}%</small>
                                </div>
                            </div>
                        </div>

                        <p class="muted admin-dashboard-method-note">
                            {{ dashboardMethodNoteText }}
                        </p>
                    </div>
                </div>

                <div class="simple-item admin-dashboard-card">
                    <div class="admin-dashboard-card-head">
                        <strong>{{ $t('admin.dashboardMonthlyActivity') }}</strong>
                        <span class="muted">
                            {{
                                $t('admin.dashboardActivityPeak', {
                                    month: formatDashboardMonth(dashboard.highlights?.activity_peak_month),
                                    count: formatDashboardNumber(dashboard.highlights?.activity_peak_value),
                                })
                            }}
                        </span>
                    </div>

                    <div class="admin-activity-rows">
                        <div
                            class="admin-activity-row"
                            v-for="item in (dashboard.activity_by_month ?? [])"
                            :key="`admin-dashboard-activity-${item.month}`"
                        >
                            <span class="admin-activity-month">{{ formatDashboardMonth(item.month) }}</span>
                            <div class="admin-activity-track">
                                <div class="admin-activity-stack" :style="{ width: dashboardActivityRowWidth(item.total) }">
                                    <span class="admin-activity-segment is-social" :style="{ width: dashboardActivitySegmentWidth(item.social, item.total) }"></span>
                                    <span class="admin-activity-segment is-chats" :style="{ width: dashboardActivitySegmentWidth(item.chats, item.total) }"></span>
                                    <span class="admin-activity-segment is-radio" :style="{ width: dashboardActivitySegmentWidth(item.radio, item.total) }"></span>
                                    <span class="admin-activity-segment is-iptv" :style="{ width: dashboardActivitySegmentWidth(item.iptv, item.total) }"></span>
                                </div>
                            </div>
                            <strong class="admin-activity-total">{{ formatDashboardNumber(item.total) }}</strong>
                        </div>
                    </div>

                    <div class="admin-activity-legend">
                        <span><i class="admin-activity-dot is-social"></i>{{ dashboardFeatureLabel('social') }}</span>
                        <span><i class="admin-activity-dot is-chats"></i>{{ dashboardFeatureLabel('chats') }}</span>
                        <span><i class="admin-activity-dot is-radio"></i>{{ dashboardFeatureLabel('radio') }}</span>
                        <span><i class="admin-activity-dot is-iptv"></i>{{ dashboardFeatureLabel('iptv') }}</span>
                    </div>
                </div>

                <div class="admin-dashboard-engagement-grid">
                    <article class="admin-dashboard-mini-card">
                        <span>{{ $t('admin.dashboardEngagementUsers30d') }}</span>
                        <strong>{{ formatDashboardNumber(dashboard.engagement?.active_users_30d) }}</strong>
                    </article>
                    <article class="admin-dashboard-mini-card">
                        <span>{{ $t('admin.dashboardEngagementCreators30d') }}</span>
                        <strong>{{ formatDashboardNumber(dashboard.engagement?.creators_30d) }}</strong>
                    </article>
                    <article class="admin-dashboard-mini-card">
                        <span>{{ $t('admin.dashboardEngagementChatters30d') }}</span>
                        <strong>{{ formatDashboardNumber(dashboard.engagement?.chatters_30d) }}</strong>
                    </article>
                    <article class="admin-dashboard-mini-card">
                        <span>{{ $t('admin.dashboardEngagementSocial30d') }}</span>
                        <strong>{{ formatDashboardNumber(dashboard.engagement?.social_active_users_30d) }}</strong>
                    </article>
                    <article class="admin-dashboard-mini-card">
                        <span>{{ $t('admin.dashboardEngagementRadio30d') }}</span>
                        <strong>{{ formatDashboardNumber(dashboard.engagement?.radio_active_users_30d) }}</strong>
                    </article>
                    <article class="admin-dashboard-mini-card">
                        <span>{{ $t('admin.dashboardEngagementIptv30d') }}</span>
                        <strong>{{ formatDashboardNumber(dashboard.engagement?.iptv_active_users_30d) }}</strong>
                    </article>
                </div>
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
                <div class="simple-item admin-simple-item-block">
                    <strong class="admin-section-title">{{ $t('admin.createPostAsAnyUser') }}</strong>
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
                            class="textarea-field admin-textarea-tall"
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

                        <label class="muted admin-check-toggle">
                            <input type="checkbox" v-model="postCreateForm.is_public">
                            {{ $t('admin.publicPost') }}
                        </label>
                        <label class="muted admin-check-toggle">
                            <input type="checkbox" v-model="postCreateForm.show_in_feed" :disabled="!postCreateForm.is_public">
                            {{ $t('admin.showInFeed') }}
                        </label>
                        <label class="muted admin-check-toggle">
                            <input type="checkbox" v-model="postCreateForm.show_in_carousel" :disabled="!postCreateForm.is_public">
                            {{ $t('admin.showInCarousel') }}
                        </label>

                        <div class="admin-actions-wrap">
                            <button class="btn btn-primary btn-sm" @click="createPost">{{ $t('admin.createPost') }}</button>
                            <button class="btn btn-danger btn-sm" @click="clearAllLikes">{{ $t('admin.clearAllLikes') }}</button>
                        </div>
                    </div>
                </div>

                <div class="simple-item admin-simple-item-block" v-for="post in posts" :key="`admin-post-${post.id}`">
                    <div class="admin-row-between">
                        <strong>#{{ post.id }} · {{ $t('admin.likesCount', { count: post.likes_count ?? 0 }) }}</strong>
                        <div class="admin-actions-wrap-tight">
                            <button class="btn btn-outline btn-sm" @click="clearPostLikes(post)">{{ $t('admin.clearLikes') }}</button>
                            <button class="btn btn-danger btn-sm" @click="removePost(post)">{{ $t('common.delete') }}</button>
                        </div>
                    </div>

                    <div class="form-grid admin-form-grid-offset">
                        <p class="muted admin-muted-reset">{{ $t('admin.currentAuthor', { name: post.user?.name ?? '—', id: post.user?.id ?? post.user_id }) }}</p>
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
                            class="textarea-field admin-textarea-tall"
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

                        <label class="muted admin-check-toggle">
                            <input type="checkbox" v-model="post.is_public">
                            {{ $t('admin.publicPost') }}
                        </label>
                        <label class="muted admin-check-toggle">
                            <input type="checkbox" v-model="post.show_in_feed" :disabled="!post.is_public">
                            {{ $t('admin.showInFeed') }}
                        </label>
                        <label class="muted admin-check-toggle">
                            <input type="checkbox" v-model="post.show_in_carousel" :disabled="!post.is_public">
                            {{ $t('admin.showInCarousel') }}
                        </label>

                        <button class="btn btn-success btn-sm" @click="savePost(post)">{{ $t('admin.savePost') }}</button>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'comments'" class="simple-list fade-in">
                <div class="simple-item admin-simple-item-block" v-for="comment in comments" :key="`admin-comment-${comment.id}`">
                    <div class="admin-row-between-start">
                        <strong>#{{ comment.id }} · {{ comment.user?.name ?? '—' }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeComment(comment)">{{ $t('common.delete') }}</button>
                    </div>
                    <p class="admin-copy-top">{{ comment.body }}</p>
                </div>
            </div>

            <div v-if="activeTab === 'feedback'" class="simple-list fade-in">
                <div class="simple-item admin-simple-item-block" v-for="item in feedback" :key="`admin-feedback-${item.id}`">
                    <div class="admin-row-between">
                        <div>
                            <strong>{{ item.name }}</strong>
                            <p class="muted admin-muted-top">{{ item.email }}</p>
                        </div>
                        <div class="admin-feedback-actions">
                            <select class="select-field admin-feedback-status-field" v-model="item.status">
                                <option value="new">new</option>
                                <option value="in_progress">in_progress</option>
                                <option value="resolved">resolved</option>
                            </select>
                            <button class="btn btn-success btn-sm" @click="saveFeedback(item)">{{ $t('admin.update') }}</button>
                            <button class="btn btn-danger btn-sm" @click="removeFeedback(item)">{{ $t('common.delete') }}</button>
                        </div>
                    </div>
                    <p class="admin-copy-top-lg">{{ item.message }}</p>
                </div>
            </div>

            <div v-if="activeTab === 'conversations'" class="simple-list fade-in">
                <div class="simple-item admin-simple-item-block">
                    <strong class="admin-section-title-sm">{{ $t('admin.bulkChatActions') }}</strong>
                    <div class="admin-actions-wrap">
                        <button class="btn btn-outline btn-sm" @click="clearAllConversations">{{ $t('admin.clearAllChats') }}</button>
                        <button class="btn btn-danger btn-sm" @click="removeAllConversations">{{ $t('admin.deleteAllChats') }}</button>
                    </div>
                </div>

                <div class="simple-item admin-simple-item-block" v-for="conversation in conversations" :key="`admin-conversation-${conversation.id}`">
                    <div class="admin-row-between">
                        <strong>#{{ conversation.id }} · {{ conversation.display_title || conversation.title }}</strong>
                        <div class="admin-actions-wrap">
                            <button class="btn btn-outline btn-sm" @click="clearConversationMessages(conversation)">{{ $t('admin.clearChat') }}</button>
                            <button class="btn btn-danger btn-sm" @click="removeConversation(conversation)">{{ $t('admin.deleteChat') }}</button>
                        </div>
                    </div>
                    <p class="muted admin-muted-top-sm">
                        {{ $t('admin.chatMeta', {
                            type: conversation.type,
                            participants: conversation.participants?.length ?? 0,
                            messages: conversation.messages_count ?? 0,
                        }) }}
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'messages'" class="simple-list fade-in">
                <div class="simple-item admin-simple-item-block" v-for="message in messages" :key="`admin-message-${message.id}`">
                    <div class="admin-row-between-start">
                        <strong>#{{ message.id }} · {{ message.user?.name ?? '—' }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeMessage(message)">{{ $t('common.delete') }}</button>
                    </div>
                    <p class="muted admin-muted-top">
                        {{ $t('admin.chatNumber', { id: message.conversation_id }) }}
                    </p>
                    <p class="admin-copy-top">{{ message.body || $t('admin.onlyAttachments') }}</p>
                    <p class="muted admin-muted-top-sm" v-if="(message.attachments?.length ?? 0) > 0">
                        {{ $t('admin.attachmentsCount', { count: message.attachments.length }) }}
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'blocks'" class="simple-list fade-in">
                <div class="simple-item admin-simple-item-block" v-for="block in blocks" :key="`admin-block-${block.id}`">
                    <div class="admin-row-between-start">
                        <div>
                            <strong>#{{ block.id }} · {{ block.blocker?.name ?? '—' }} → {{ block.blocked_user?.name ?? '—' }}</strong>
                            <p class="muted admin-muted-top">
                                {{ $t('admin.blockStatus') }}: {{ block.expires_at ? $t('admin.blockUntil', { date: formatDate(block.expires_at) }) : $t('admin.blockPermanent') }}
                            </p>
                        </div>
                        <button class="btn btn-danger btn-sm" @click="removeBlock(block)">{{ $t('common.delete') }}</button>
                    </div>

                    <div class="form-grid admin-form-grid-offset">
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

                        <div class="admin-actions-wrap">
                            <button class="btn btn-success btn-sm" @click="saveBlock(block)">{{ $t('admin.save') }}</button>
                            <button class="btn btn-outline btn-sm" @click="setPermanentBlock(block)">{{ $t('admin.makePermanent') }}</button>
                            <button class="btn btn-outline btn-sm" @click="extendBlockFor24Hours(block)">{{ $t('admin.add24h') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'iptvSeeds'" class="simple-list fade-in">
                <div class="simple-item admin-simple-item-block">
                    <strong class="admin-section-title">{{ $t('admin.iptvSeedsTitle') }}</strong>
                    <p class="muted admin-paragraph-bottom">{{ $t('admin.iptvSeedsSubtitle') }}</p>

                    <div class="admin-iptv-seeds-create">
                        <div class="admin-iptv-seeds-fields">
                            <input class="input-field admin-iptv-seed-input is-name" type="text" v-model.trim="newIptvSeed.name" :placeholder="$t('admin.name')">
                            <input class="input-field admin-iptv-seed-input is-url" type="url" v-model.trim="newIptvSeed.url" placeholder="URL (m3u/m3u8)">
                        </div>
                        <div class="admin-iptv-seed-meta-row">
                            <div class="admin-iptv-seed-meta-controls">
                                <input class="input-field admin-iptv-seed-sort-field" type="number" v-model.number="newIptvSeed.sort_order" :placeholder="$t('admin.sortOrder')">
                                <label class="admin-iptv-seed-active-toggle">
                                    <input type="checkbox" v-model="newIptvSeed.is_active">
                                    <small>{{ $t('admin.isActive') }}</small>
                                </label>
                            </div>
                            <button class="btn btn-primary admin-iptv-seed-create-btn" @click="createIptvSeed" :disabled="!newIptvSeed.name || !newIptvSeed.url">
                                {{ $t('admin.create') }}
                            </button>
                        </div>
                    </div>

                    <div v-for="seed in iptvSeeds" :key="`admin-seed-${seed.id}`" class="simple-item admin-iptv-seed-item">
                        <div class="admin-iptv-seed-item-body">
                            <div class="admin-iptv-seeds-fields">
                                <input class="input-field admin-iptv-seed-input is-name" type="text" v-model.trim="seed.name" :placeholder="$t('admin.name')">
                                <input class="input-field admin-iptv-seed-input is-url" type="url" v-model.trim="seed.url" placeholder="URL">
                            </div>
                            <div class="admin-iptv-seed-meta-row">
                                <div class="admin-iptv-seed-meta-controls">
                                    <input class="input-field admin-iptv-seed-sort-field" type="number" v-model.number="seed.sort_order" :placeholder="$t('admin.sortOrder')">
                                    <label class="admin-iptv-seed-active-toggle">
                                        <input type="checkbox" v-model="seed.is_active">
                                        <small>{{ $t('admin.isActive') }}</small>
                                    </label>
                                </div>
                                <div class="admin-iptv-seed-actions">
                                    <button class="btn btn-success btn-sm" @click="updateIptvSeed(seed)">{{ $t('admin.save') }}</button>
                                    <button class="btn btn-danger btn-sm" @click="removeIptvSeed(seed)">{{ $t('common.delete') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'settings'" class="simple-list fade-in">
                <div class="simple-item admin-simple-item-block">
                    <strong class="admin-section-title">{{ $t('admin.homeContentTitle') }}</strong>
                    <p class="muted admin-paragraph-compact-bottom">
                        {{ $t('admin.homeContentSubtitle') }}
                    </p>
                    <div class="admin-home-locale-row">
                        <span class="muted admin-muted-label">
                            {{ $t('admin.editLanguage') }}:
                            <strong>{{ homeContentActiveLocale === 'en' ? $t('admin.languageNameEn') : $t('admin.languageNameRu') }}</strong>
                        </span>
                        <div class="admin-actions-wrap">
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
                        <label class="muted admin-muted-label">{{ $t('admin.badge') }}</label>
                        <input class="input-field" type="text" maxlength="80" v-model.trim="activeHomeContentLocalePayload.badge">

                        <label class="muted admin-muted-label">{{ $t('admin.heroTitle') }}</label>
                        <textarea class="textarea-field admin-textarea-medium" maxlength="300" v-model.trim="activeHomeContentLocalePayload.hero_title"></textarea>

                        <label class="muted admin-muted-label">{{ $t('admin.heroNote') }}</label>
                        <textarea class="textarea-field admin-textarea-tall" maxlength="3000" v-model.trim="activeHomeContentLocalePayload.hero_note"></textarea>

                        <label class="muted admin-muted-label">{{ $t('admin.featureItems') }}</label>
                        <div class="admin-feature-items">
                            <div
                                v-for="(item, index) in activeHomeContentLocalePayload.feature_items"
                                :key="`home-feature-item-${index}`"
                                class="admin-feature-item-row"
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

                        <label class="muted admin-muted-label">{{ $t('admin.feedbackTitle') }}</label>
                        <input class="input-field" type="text" maxlength="180" v-model.trim="activeHomeContentLocalePayload.feedback_title">

                        <label class="muted admin-muted-label">{{ $t('admin.feedbackSubtitle') }}</label>
                        <textarea class="textarea-field admin-textarea-medium" maxlength="500" v-model.trim="activeHomeContentLocalePayload.feedback_subtitle"></textarea>

                        <div class="admin-actions-wrap">
                            <button class="btn btn-success btn-sm" @click="saveHomeContent">{{ $t('admin.saveHomeContent') }}</button>
                            <button class="btn btn-outline btn-sm" @click="resetHomeContent">{{ $t('admin.resetToDefault') }}</button>
                        </div>
                    </div>
                </div>

                <div class="simple-item admin-simple-item-block">
                    <strong class="admin-section-title">{{ $t('admin.storageTitle') }}</strong>
                    <p class="muted admin-paragraph-compact-bottom">
                        {{ $t('admin.storageSubtitle') }}
                    </p>

                    <div class="form-grid">
                        <select class="select-field" v-model="storageSettings.media_storage_mode">
                            <option value="server_local">{{ $t('admin.storageServerOnly') }}</option>
                            <option value="cloud">{{ $t('admin.storageCloudOnly') }}</option>
                            <option value="user_choice">{{ $t('admin.storageUserChoice') }}</option>
                        </select>

                        <label class="muted admin-muted-label">{{ $t('admin.serverDisk') }}</label>
                        <input class="input-field" v-model.trim="storageSettings.server_media_disk" type="text" placeholder="public">

                        <label class="muted admin-muted-label">{{ $t('admin.cloudDisk') }}</label>
                        <input class="input-field" v-model.trim="storageSettings.cloud_media_disk" type="text" placeholder="s3">

                        <button class="btn btn-success btn-sm" @click="saveStorageSettings">{{ $t('admin.saveStorage') }}</button>
                    </div>
                </div>

                <div class="simple-item admin-simple-item-block">
                    <strong class="admin-section-title">{{ $t('admin.createSiteSetting') }}</strong>
                    <div class="form-grid">
                        <input class="input-field" v-model.trim="newSetting.key" type="text" placeholder="key_name">
                        <select class="select-field" v-model="newSetting.type">
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="float">float</option>
                            <option value="boolean">boolean</option>
                            <option value="json">json</option>
                        </select>
                        <textarea class="textarea-field admin-textarea-medium" v-model="newSetting.valueText" :placeholder="$t('admin.value')"></textarea>
                        <input class="input-field" v-model.trim="newSetting.description" type="text" :placeholder="$t('admin.descriptionOptional')">
                        <button class="btn btn-primary btn-sm" @click="createSetting">{{ $t('admin.create') }}</button>
                    </div>
                </div>

                <div class="simple-item admin-simple-item-block" v-for="setting in settings" :key="`admin-setting-${setting.id}`">
                    <div class="admin-row-between">
                        <strong>#{{ setting.id }} · {{ setting.key }}</strong>
                        <button class="btn btn-danger btn-sm" @click="removeSetting(setting)">{{ $t('common.delete') }}</button>
                    </div>

                    <div class="form-grid admin-form-grid-offset">
                        <input class="input-field" type="text" v-model.trim="setting.key">
                        <select class="select-field" v-model="setting.type">
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="float">float</option>
                            <option value="boolean">boolean</option>
                            <option value="json">json</option>
                        </select>
                        <textarea class="textarea-field admin-textarea-medium" v-model="setting.valueText"></textarea>
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
const DASHBOARD_CHART_WIDTH = 760
const DASHBOARD_CHART_HEIGHT = 250
const DASHBOARD_CHART_PADDING_X = 26
const DASHBOARD_CHART_PADDING_TOP = 16
const DASHBOARD_CHART_PADDING_BOTTOM = 28
const DASHBOARD_MIN_ACTIVITY_BAR_PERCENT = 3
const DASHBOARD_FEATURE_COLORS = {
    social: '#0f6cf2',
    chats: '#f97316',
    radio: '#0d9488',
    iptv: '#be123c',
}

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

function buildEmptyDashboardPayload(year = new Date().getFullYear()) {
    const safeYear = Number.isFinite(Number(year)) ? Number(year) : new Date().getFullYear()
    const subscriptionsByMonth = Array.from({ length: 12 }, (_, index) => ({
        month: index + 1,
        value: 0,
    }))
    const registrationsByMonth = Array.from({ length: 12 }, (_, index) => ({
        month: index + 1,
        value: 0,
    }))
    const activityByMonth = Array.from({ length: 12 }, (_, index) => ({
        month: index + 1,
        social: 0,
        chats: 0,
        radio: 0,
        iptv: 0,
        total: 0,
    }))

    return {
        selected_year: safeYear,
        available_years: [safeYear],
        period: {
            mode: 'year',
            from: `${safeYear}-01-01`,
            to: `${safeYear}-12-31`,
            requested_from: null,
            requested_to: null,
            is_clamped: false,
        },
        kpis: {
            users_total: 0,
            users_new_year: 0,
            users_new_period: 0,
            subscriptions_total: 0,
            subscriptions_year: 0,
            subscriptions_period: 0,
            subscriptions_previous_year: 0,
            subscriptions_change_percent: null,
            subscriptions_avg_month: 0,
            period_months: 12,
            subscriptions_peak_month: {
                month: 1,
                value: 0,
            },
        },
        subscriptions_by_month: subscriptionsByMonth,
        registrations_by_month: registrationsByMonth,
        activity_by_month: activityByMonth,
        preference: {
            method: 'actions',
            total_actions: 0,
            leader_key: null,
            items: [
                { key: 'social', value: 0, share: 0 },
                { key: 'chats', value: 0, share: 0 },
                { key: 'radio', value: 0, share: 0 },
                { key: 'iptv', value: 0, share: 0 },
            ],
        },
        engagement: {
            active_users_30d: 0,
            creators_30d: 0,
            chatters_30d: 0,
            new_users_30d: 0,
            social_active_users_30d: 0,
            chat_active_users_30d: 0,
            radio_active_users_30d: 0,
            iptv_active_users_30d: 0,
        },
        highlights: {
            subscriptions_peak_month: 1,
            activity_peak_month: 1,
            activity_peak_value: 0,
        },
    }
}

export default {
    name: 'Admin',

    data() {
        const currentYear = new Date().getFullYear()

        return {
            activeTab: 'dashboard',
            currentYear,
            dashboardYear: currentYear,
            dashboardLoading: false,
            dashboardExportDateFrom: `${currentYear}-01-01`,
            dashboardExportDateTo: `${currentYear}-12-31`,
            dashboardExportingFormat: '',
            dashboard: buildEmptyDashboardPayload(currentYear),
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
        dashboardUsesTrackedTime() {
            return this.dashboard?.preference?.method === 'time_minutes'
        },

        dashboardKpiPrimaryLabel() {
            return this.dashboardUsesTrackedTime
                ? this.$t('admin.dashboardKpiMinutes')
                : this.$t('admin.dashboardKpiActions')
        },

        dashboardMethodLabelText() {
            return this.dashboardUsesTrackedTime
                ? this.$t('admin.dashboardMethodLabelTime')
                : this.$t('admin.dashboardMethodLabelActions')
        },

        dashboardMethodNoteText() {
            return this.dashboardUsesTrackedTime
                ? this.$t('admin.dashboardMethodNoteTime')
                : this.$t('admin.dashboardMethodNoteActions')
        },

        dashboardPreferenceUnitLabel() {
            return this.dashboardUsesTrackedTime
                ? this.$t('admin.dashboardMinutes')
                : this.$t('admin.dashboardActions')
        },

        dashboardLocale() {
            return this.$route?.params?.locale === 'en' ? 'en-US' : 'ru-RU'
        },

        dashboardSubscriptionsPoints() {
            const series = Array.isArray(this.dashboard?.subscriptions_by_month)
                ? this.dashboard.subscriptions_by_month
                : []

            if (series.length === 0) {
                return []
            }

            const maxValue = Math.max(
                ...series.map((item) => Math.max(0, Number(item?.value ?? 0))),
                1
            )

            const innerWidth = DASHBOARD_CHART_WIDTH - (DASHBOARD_CHART_PADDING_X * 2)
            const innerHeight = DASHBOARD_CHART_HEIGHT - DASHBOARD_CHART_PADDING_TOP - DASHBOARD_CHART_PADDING_BOTTOM
            const baselineY = DASHBOARD_CHART_HEIGHT - DASHBOARD_CHART_PADDING_BOTTOM
            const divisor = Math.max(series.length - 1, 1)

            return series.map((item, index) => {
                const value = Math.max(0, Number(item?.value ?? 0))
                const x = DASHBOARD_CHART_PADDING_X + ((innerWidth * index) / divisor)
                const y = baselineY - ((value / maxValue) * innerHeight)

                return {
                    month: Number(item?.month ?? (index + 1)),
                    value,
                    x: Number(x.toFixed(2)),
                    y: Number(y.toFixed(2)),
                    baselineY: Number(baselineY.toFixed(2)),
                }
            })
        },

        dashboardSubscriptionsLinePath() {
            const points = this.dashboardSubscriptionsPoints
            if (points.length === 0) {
                return ''
            }

            return points
                .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
                .join(' ')
        },

        dashboardSubscriptionsAreaPath() {
            const points = this.dashboardSubscriptionsPoints
            if (points.length === 0) {
                return ''
            }

            const first = points[0]
            const last = points[points.length - 1]
            const line = points
                .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
                .join(' ')

            return `M ${first.x} ${first.baselineY} L ${first.x} ${first.y} ${line.replace(/^M [\d.]+ [\d.]+\s*/, '')} L ${last.x} ${last.baselineY} Z`
        },

        dashboardPreferenceGradient() {
            const items = Array.isArray(this.dashboard?.preference?.items)
                ? this.dashboard.preference.items
                : []

            if (items.length === 0) {
                return 'conic-gradient(#d8e6f9 0deg 360deg)'
            }

            let cursor = 0
            const segments = []

            items.forEach((item) => {
                const share = Math.max(0, Number(item?.share ?? 0))
                if (share <= 0) {
                    return
                }

                const start = cursor
                const next = Math.min(360, cursor + ((share / 100) * 360))
                const color = this.dashboardFeatureColor(item?.key)
                segments.push(`${color} ${start.toFixed(2)}deg ${next.toFixed(2)}deg`)
                cursor = next
            })

            if (segments.length === 0) {
                return 'conic-gradient(#d8e6f9 0deg 360deg)'
            }

            if (cursor < 360) {
                segments.push(`#d8e6f9 ${cursor.toFixed(2)}deg 360deg`)
            }

            return `conic-gradient(${segments.join(', ')})`
        },

        dashboardActivityMaxTotal() {
            const values = Array.isArray(this.dashboard?.activity_by_month)
                ? this.dashboard.activity_by_month.map((item) => Math.max(0, Number(item?.total ?? 0)))
                : []

            return Math.max(...values, 1)
        },

        dashboardExporting() {
            return this.dashboardExportingFormat !== ''
        },

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

            if (tab === 'dashboard') {
                await this.loadDashboard()
                return
            }
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

        normalizeDashboardMonthlySeries(series) {
            const map = new Map()

            if (Array.isArray(series)) {
                series.forEach((item) => {
                    const month = Number(item?.month ?? 0)
                    if (!Number.isFinite(month) || month < 1 || month > 12) {
                        return
                    }

                    const value = Math.max(0, Number(item?.value ?? 0))
                    map.set(month, Number.isFinite(value) ? value : 0)
                })
            }

            return Array.from({ length: 12 }, (_, index) => {
                const month = index + 1
                return {
                    month,
                    value: Number(map.get(month) ?? 0),
                }
            })
        },

        normalizeDashboardActivitySeries(series) {
            const map = new Map()

            if (Array.isArray(series)) {
                series.forEach((item) => {
                    const month = Number(item?.month ?? 0)
                    if (!Number.isFinite(month) || month < 1 || month > 12) {
                        return
                    }

                    const social = Math.max(0, Number(item?.social ?? 0))
                    const chats = Math.max(0, Number(item?.chats ?? 0))
                    const radio = Math.max(0, Number(item?.radio ?? 0))
                    const iptv = Math.max(0, Number(item?.iptv ?? 0))

                    map.set(month, {
                        month,
                        social: Number.isFinite(social) ? social : 0,
                        chats: Number.isFinite(chats) ? chats : 0,
                        radio: Number.isFinite(radio) ? radio : 0,
                        iptv: Number.isFinite(iptv) ? iptv : 0,
                    })
                })
            }

            return Array.from({ length: 12 }, (_, index) => {
                const month = index + 1
                const item = map.get(month) ?? {
                    month,
                    social: 0,
                    chats: 0,
                    radio: 0,
                    iptv: 0,
                }

                const total = Math.max(0, Number(item.social) + Number(item.chats) + Number(item.radio) + Number(item.iptv))

                return {
                    month,
                    social: Math.max(0, Number(item.social) || 0),
                    chats: Math.max(0, Number(item.chats) || 0),
                    radio: Math.max(0, Number(item.radio) || 0),
                    iptv: Math.max(0, Number(item.iptv) || 0),
                    total,
                }
            })
        },

        normalizeDashboardPayload(payload) {
            const fallback = buildEmptyDashboardPayload(this.dashboardYear || this.currentYear)
            const selectedYearRaw = Number(payload?.selected_year ?? fallback.selected_year)
            const selectedYear = Number.isFinite(selectedYearRaw) && selectedYearRaw >= 2000
                ? selectedYearRaw
                : fallback.selected_year

            const availableYears = Array.isArray(payload?.available_years)
                ? payload.available_years
                    .map((year) => Number(year))
                    .filter((year) => Number.isFinite(year) && year >= 2000)
                : []

            const preferenceItems = Array.isArray(payload?.preference?.items)
                ? payload.preference.items
                    .map((item) => ({
                        key: String(item?.key ?? ''),
                        value: Math.max(0, Number(item?.value ?? 0)),
                        share: Math.max(0, Number(item?.share ?? 0)),
                    }))
                    .filter((item) => item.key !== '')
                : fallback.preference.items

            const normalizedPeriod = {
                ...fallback.period,
                ...(payload?.period ?? {}),
            }

            return {
                ...fallback,
                ...payload,
                selected_year: selectedYear,
                available_years: availableYears.length > 0
                    ? availableYears
                    : [selectedYear],
                period: normalizedPeriod,
                kpis: {
                    ...fallback.kpis,
                    ...(payload?.kpis ?? {}),
                    subscriptions_peak_month: {
                        ...fallback.kpis.subscriptions_peak_month,
                        ...(payload?.kpis?.subscriptions_peak_month ?? {}),
                    },
                },
                subscriptions_by_month: this.normalizeDashboardMonthlySeries(payload?.subscriptions_by_month),
                registrations_by_month: this.normalizeDashboardMonthlySeries(payload?.registrations_by_month),
                activity_by_month: this.normalizeDashboardActivitySeries(payload?.activity_by_month),
                preference: {
                    ...fallback.preference,
                    ...(payload?.preference ?? {}),
                    items: preferenceItems,
                },
                engagement: {
                    ...fallback.engagement,
                    ...(payload?.engagement ?? {}),
                },
                highlights: {
                    ...fallback.highlights,
                    ...(payload?.highlights ?? {}),
                },
            }
        },

        async onDashboardYearChanged() {
            this.setDashboardDateRangeToYear(this.dashboardYear)
            await this.loadDashboard()
        },

        setDashboardDateRangeToYear(year) {
            const numericYear = Number(year)
            const safeYear = Number.isFinite(numericYear) && numericYear >= 2000
                ? Math.trunc(numericYear)
                : this.currentYear

            this.dashboardExportDateFrom = `${safeYear}-01-01`
            this.dashboardExportDateTo = `${safeYear}-12-31`
        },

        resolveDashboardRangeForRequest() {
            const range = this.ensureDashboardExportRange()
            if (range.valid) {
                return range
            }

            const numericYear = Number(this.dashboardYear)
            const safeYear = Number.isFinite(numericYear) && numericYear >= 2000
                ? Math.trunc(numericYear)
                : this.currentYear

            return {
                valid: true,
                from: `${safeYear}-01-01`,
                to: `${safeYear}-12-31`,
            }
        },

        async loadDashboard() {
            const range = this.resolveDashboardRangeForRequest()
            this.dashboardExportDateFrom = range.from
            this.dashboardExportDateTo = range.to

            const rangeYear = Number.parseInt(range.to.slice(0, 4), 10)
            const yearParam = Number.isFinite(rangeYear) ? rangeYear : this.dashboardYear

            this.dashboardLoading = true

            try {
                const response = await axios.get('/api/admin/dashboard', {
                    params: {
                        year: yearParam,
                        date_from: range.from,
                        date_to: range.to,
                    },
                })

                this.dashboard = this.normalizeDashboardPayload(response.data?.data ?? {})
                this.dashboardYear = Number(this.dashboard.selected_year ?? this.dashboardYear)

                const normalizedFrom = this.normalizeDateInput(this.dashboard?.period?.from)
                const normalizedTo = this.normalizeDateInput(this.dashboard?.period?.to)
                if (normalizedFrom && normalizedTo) {
                    this.dashboardExportDateFrom = normalizedFrom
                    this.dashboardExportDateTo = normalizedTo
                }
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.dashboardLoadFailed'))
            } finally {
                this.dashboardLoading = false
            }
        },

        isDashboardExporting(format) {
            return this.dashboardExportingFormat === String(format || '')
        },

        normalizeDateInput(value) {
            const raw = String(value || '').trim()
            return /^\d{4}-\d{2}-\d{2}$/.test(raw) ? raw : ''
        },

        ensureDashboardExportRange() {
            const from = this.normalizeDateInput(this.dashboardExportDateFrom)
            const to = this.normalizeDateInput(this.dashboardExportDateTo)

            if (!from || !to) {
                return { valid: false, from: '', to: '' }
            }

            const fromTimestamp = Date.parse(`${from}T00:00:00Z`)
            const toTimestamp = Date.parse(`${to}T00:00:00Z`)
            if (!Number.isFinite(fromTimestamp) || !Number.isFinite(toTimestamp) || fromTimestamp > toTimestamp) {
                return { valid: false, from: '', to: '' }
            }

            return { valid: true, from, to }
        },

        extractDashboardDownloadFileName(contentDisposition, fallbackName) {
            const header = String(contentDisposition || '')
            const fallback = String(fallbackName || 'admin-dashboard-export')

            const utfMatch = header.match(/filename\*=UTF-8''([^;]+)/i)
            if (utfMatch && utfMatch[1]) {
                try {
                    return decodeURIComponent(utfMatch[1].trim())
                } catch (_error) {
                    return utfMatch[1].trim()
                }
            }

            const quotedMatch = header.match(/filename=\"([^\"]+)\"/i)
            if (quotedMatch && quotedMatch[1]) {
                return quotedMatch[1].trim()
            }

            const plainMatch = header.match(/filename=([^;]+)/i)
            if (plainMatch && plainMatch[1]) {
                return plainMatch[1].replace(/^\"|\"$/g, '').trim()
            }

            return fallback
        },

        triggerDashboardDownloadBlob(blob, fileName) {
            const safeBlob = blob instanceof Blob
                ? blob
                : new Blob([blob], { type: 'application/octet-stream' })

            const link = document.createElement('a')
            const blobUrl = window.URL.createObjectURL(safeBlob)
            link.href = blobUrl
            link.download = fileName
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            window.URL.revokeObjectURL(blobUrl)
        },

        async exportDashboard(format = 'xls') {
            const safeFormat = format === 'json' ? 'json' : 'xls'

            if (this.dashboardExporting) {
                return
            }

            const range = this.ensureDashboardExportRange()
            if (!range.valid) {
                alert(this.$t('admin.dashboardExportRangeInvalid'))
                return
            }

            const rangeYear = Number.parseInt(range.to.slice(0, 4), 10)
            const yearParam = Number.isFinite(rangeYear) ? rangeYear : this.dashboardYear

            this.dashboardExportingFormat = safeFormat

            try {
                const response = await axios.get('/api/admin/dashboard/export', {
                    params: {
                        year: yearParam,
                        date_from: range.from,
                        date_to: range.to,
                        format: safeFormat,
                    },
                    responseType: 'blob',
                })

                const fallbackName = `admin-dashboard-${range.from}-${range.to}.${safeFormat}`
                const contentDisposition = response.headers?.['content-disposition'] ?? ''
                const fileName = this.extractDashboardDownloadFileName(contentDisposition, fallbackName)

                this.triggerDashboardDownloadBlob(response.data, fileName)
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.dashboardExportFailed'))
            } finally {
                this.dashboardExportingFormat = ''
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

        formatDashboardNumber(value) {
            const numeric = Number(value ?? 0)
            const safeValue = Number.isFinite(numeric) ? numeric : 0

            if (Math.abs(safeValue) % 1 === 0) {
                return new Intl.NumberFormat(this.dashboardLocale, {
                    maximumFractionDigits: 0,
                }).format(safeValue)
            }

            return new Intl.NumberFormat(this.dashboardLocale, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 1,
            }).format(safeValue)
        },

        formatDashboardMonth(monthNumber) {
            const month = Number(monthNumber ?? 1)
            if (!Number.isFinite(month) || month < 1 || month > 12) {
                return '—'
            }

            const label = new Intl.DateTimeFormat(this.dashboardLocale, {
                month: 'short',
            }).format(new Date(Date.UTC(2020, month - 1, 1)))

            return label.charAt(0).toUpperCase() + label.slice(1)
        },

        dashboardFeatureLabel(featureKey) {
            const key = String(featureKey ?? '').toLowerCase()

            if (key === 'social') {
                return this.$t('admin.dashboardFeatureSocial')
            }
            if (key === 'chats') {
                return this.$t('admin.dashboardFeatureChats')
            }
            if (key === 'radio') {
                return this.$t('admin.dashboardFeatureRadio')
            }
            if (key === 'iptv') {
                return this.$t('admin.dashboardFeatureIptv')
            }

            return this.$t('admin.dashboardFeatureUnknown')
        },

        dashboardFeatureColor(featureKey) {
            const key = String(featureKey ?? '').toLowerCase()
            return DASHBOARD_FEATURE_COLORS[key] ?? '#8ea4c8'
        },

        dashboardActivityRowWidth(total) {
            const maxTotal = Math.max(Number(this.dashboardActivityMaxTotal ?? 1), 1)
            const value = Math.max(0, Number(total ?? 0))
            const ratio = Math.max((value / maxTotal) * 100, value > 0 ? DASHBOARD_MIN_ACTIVITY_BAR_PERCENT : 0)

            return `${Math.min(ratio, 100).toFixed(2)}%`
        },

        dashboardActivitySegmentWidth(value, total) {
            const numericTotal = Math.max(0, Number(total ?? 0))
            if (numericTotal <= 0) {
                return '0%'
            }

            const numericValue = Math.max(0, Number(value ?? 0))
            const ratio = (numericValue / numericTotal) * 100
            return `${Math.min(Math.max(ratio, 0), 100).toFixed(2)}%`
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
