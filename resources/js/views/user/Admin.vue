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
                <button class="btn" :class="activeTab === 'errorLog' ? 'btn-primary' : 'btn-outline'" @click="selectTab('errorLog')">{{ $t('admin.tabErrorLog') }}</button>
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
                    <div class="simple-item admin-dashboard-card admin-dashboard-card--trend">
                        <div class="admin-dashboard-card-head admin-dashboard-card-head--analytics">
                            <div class="admin-dashboard-card-copy">
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

                            <div class="admin-dashboard-head-pills">
                                <div class="admin-dashboard-head-pill">
                                    <small>{{ $t('admin.dashboardAverageShort') }}</small>
                                    <strong>{{ formatDashboardNumber(dashboard.kpis?.subscriptions_avg_month ?? 0) }}</strong>
                                </div>
                                <div class="admin-dashboard-head-pill">
                                    <small>{{ $t('admin.dashboardPeakShort') }}</small>
                                    <strong>{{ formatDashboardNumber(dashboard.kpis?.subscriptions_peak_month?.value) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="admin-dashboard-line-chart">
                            <svg viewBox="0 0 760 250" role="img" aria-hidden="true" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="admin-dashboard-subscriptions-gradient" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="rgba(76, 240, 255, 0.4)" />
                                        <stop offset="100%" stop-color="rgba(76, 240, 255, 0.02)" />
                                    </linearGradient>
                                    <filter id="admin-dashboard-neon-glow">
                                        <feGaussianBlur stdDeviation="3.2" result="coloredBlur" />
                                        <feMerge>
                                            <feMergeNode in="coloredBlur" />
                                            <feMergeNode in="SourceGraphic" />
                                        </feMerge>
                                    </filter>
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
                                    stroke="rgba(76, 240, 255, 0.95)"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    filter="url(#admin-dashboard-neon-glow)"
                                />
                                <circle
                                    v-for="point in dashboardSubscriptionsPoints"
                                    :key="`admin-subscriptions-point-${point.month}`"
                                    :cx="point.x"
                                    :cy="point.y"
                                    r="4"
                                    fill="#4cf0ff"
                                    stroke="#08142d"
                                    stroke-width="1.2"
                                    filter="url(#admin-dashboard-neon-glow)"
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

                    <div class="simple-item admin-dashboard-card admin-dashboard-card--preference">
                        <div class="admin-dashboard-card-head admin-dashboard-card-head--analytics">
                            <div class="admin-dashboard-card-copy">
                                <strong>{{ $t('admin.dashboardFeaturePreferences') }}</strong>
                                <span class="muted">{{ dashboardMethodLabelText }}</span>
                            </div>

                            <div class="admin-dashboard-head-pills">
                                <div class="admin-dashboard-head-pill">
                                    <small>{{ $t('admin.dashboardLeaderShort') }}</small>
                                    <strong>{{ dashboardFeatureLabel(dashboard.preference?.leader_key) }}</strong>
                                </div>
                                <div class="admin-dashboard-head-pill">
                                    <small>{{ $t('admin.dashboardTotalShort') }}</small>
                                    <strong>{{ formatDashboardNumber(dashboard.preference?.total_actions) }}</strong>
                                </div>
                            </div>
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

                <div class="simple-item admin-dashboard-card admin-dashboard-card--activity">
                    <div class="admin-dashboard-card-head admin-dashboard-card-head--activity">
                        <div class="admin-activity-head-copy">
                            <strong>{{ $t('admin.dashboardMonthlyActivity') }}</strong>
                            <p class="muted admin-activity-head-note">
                                {{
                                    $t('admin.dashboardActivityPeak', {
                                        month: formatDashboardMonth(dashboardActivityPeakItem.month),
                                        count: formatDashboardNumber(dashboardActivityPeakItem.total),
                                    })
                                }}
                            </p>
                        </div>

                        <div class="admin-activity-head-badge">
                            <span>{{ $t('admin.dashboardActivityTotalPeriod') }}</span>
                            <strong>{{ formatDashboardNumber(dashboardActivityTotalPeriod) }}</strong>
                            <small>
                                {{ $t('admin.dashboardActivityPeakShare', { percent: dashboardActivityPeakSharePercent }) }}
                            </small>
                        </div>
                    </div>

                    <div class="admin-activity-kpi-grid">
                        <article
                            v-for="item in dashboardActivitySummaryCards"
                            :key="`admin-activity-kpi-${item.key}`"
                            class="admin-activity-kpi-card"
                            :style="{ '--activity-accent': item.color }"
                        >
                            <span class="admin-activity-kpi-card__label">{{ item.label }}</span>
                            <strong class="admin-activity-kpi-card__value">{{ formatDashboardNumber(item.value) }}</strong>
                        </article>
                    </div>

                    <div class="admin-activity-showcase">
                        <section class="admin-activity-chart-panel">
                            <div class="admin-activity-panel-head">
                                <strong>{{ $t('admin.dashboardActivityTrend') }}</strong>
                                <span class="muted">{{ $t('admin.dashboardActivityMatrix') }}</span>
                            </div>

                            <div class="admin-activity-visual">
                                <svg viewBox="0 0 760 228" role="img" aria-hidden="true" preserveAspectRatio="none">
                                    <defs>
                                        <filter id="admin-dashboard-activity-glow">
                                            <feGaussianBlur stdDeviation="2.4" result="coloredBlur" />
                                            <feMerge>
                                                <feMergeNode in="coloredBlur" />
                                                <feMergeNode in="SourceGraphic" />
                                            </feMerge>
                                        </filter>
                                    </defs>

                                    <line
                                        v-for="gridLine in dashboardActivityChartGridLines"
                                        :key="`admin-dashboard-activity-grid-${gridLine.index}`"
                                        x1="18"
                                        :y1="gridLine.y"
                                        x2="742"
                                        :y2="gridLine.y"
                                        stroke="rgba(132, 204, 242, 0.18)"
                                        stroke-dasharray="5 8"
                                        stroke-width="1"
                                    />

                                    <path
                                        v-for="series in dashboardActivityChartSeries"
                                        :key="`admin-dashboard-activity-series-${series.key}`"
                                        :d="series.path"
                                        fill="none"
                                        :stroke="series.color"
                                        stroke-width="3.2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        filter="url(#admin-dashboard-activity-glow)"
                                    />

                                    <g
                                        v-for="series in dashboardActivityChartSeries"
                                        :key="`admin-dashboard-activity-points-${series.key}`"
                                    >
                                        <circle
                                            v-for="point in series.points"
                                            :key="`admin-dashboard-activity-point-${series.key}-${point.month}`"
                                            :cx="point.x"
                                            :cy="point.y"
                                            r="3.35"
                                            :fill="series.color"
                                            stroke="#071a3d"
                                            stroke-width="1.1"
                                            filter="url(#admin-dashboard-activity-glow)"
                                        />
                                    </g>
                                </svg>
                            </div>

                            <div class="admin-activity-axis">
                                <span
                                    v-for="item in dashboardActivityChartMonths"
                                    :key="`admin-dashboard-activity-axis-${item.month}`"
                                >
                                    {{ formatDashboardMonth(item.month) }}
                                </span>
                            </div>

                            <div class="admin-activity-rows">
                                <div
                                    class="admin-activity-row"
                                    :class="{ 'is-peak': Number(item.month) === Number(dashboardActivityPeakItem.month) }"
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
                        </section>

                        <aside class="admin-activity-insights">
                            <article class="admin-activity-insight-card">
                                <span class="admin-activity-insight-label">{{ $t('admin.dashboardActivityLeaderModule') }}</span>
                                <strong class="admin-activity-insight-value">{{ dashboardFeatureLabel(dashboardActivityLeaderItem.key) }}</strong>
                                <small class="muted">{{ formatDashboardNumber(dashboardActivityLeaderItem.value) }}</small>
                            </article>

                            <article class="admin-activity-insight-card">
                                <span class="admin-activity-insight-label">{{ $t('admin.dashboardActivityPeakMonthLabel') }}</span>
                                <strong class="admin-activity-insight-value">{{ formatDashboardMonth(dashboardActivityPeakItem.month) }}</strong>
                                <small class="muted">{{ formatDashboardNumber(dashboardActivityPeakItem.total) }}</small>
                            </article>

                            <article class="admin-activity-insight-card admin-activity-insight-card--modules">
                                <span class="admin-activity-insight-label">{{ $t('admin.dashboardActivityModuleBreakdown') }}</span>

                                <div class="admin-activity-module-list">
                                    <div
                                        class="admin-activity-module-item"
                                        v-for="item in dashboardActivityModuleItems"
                                        :key="`admin-activity-module-${item.key}`"
                                    >
                                        <div class="admin-activity-module-row">
                                            <span class="admin-activity-module-label">
                                                <i class="admin-activity-dot" :style="{ background: item.color }"></i>
                                                {{ item.label }}
                                            </span>
                                            <strong>{{ formatDashboardNumber(item.value) }}</strong>
                                        </div>

                                        <div class="admin-activity-module-meter">
                                            <span
                                                class="admin-activity-module-fill"
                                                :style="{ width: `${item.share.toFixed(2)}%`, '--activity-accent': item.color }"
                                            ></span>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </aside>
                    </div>
                </div>

                <div class="admin-dashboard-analytics-stack">
                    <div class="simple-item admin-dashboard-card admin-dashboard-card--retention">
                        <div class="admin-dashboard-card-head admin-dashboard-card-head--analytics">
                            <div class="admin-dashboard-card-copy">
                                <strong>{{ $t('admin.dashboardRetentionTitle') }}</strong>
                                <span class="muted">{{ $t('admin.dashboardRetentionSubtitle') }}</span>
                            </div>
                        </div>

                        <div class="admin-dashboard-mini-grid">
                            <article
                                v-for="item in dashboardRetentionCards"
                                :key="`admin-retention-${item.key}`"
                                class="admin-dashboard-mini-card"
                            >
                                <span class="admin-dashboard-mini-label">{{ item.label }}</span>
                                <strong class="admin-dashboard-mini-value">
                                    {{ formatDashboardNumber(item.value) }}<template v-if="item.suffix">{{ item.suffix }}</template>
                                </strong>
                            </article>
                        </div>

                        <div class="admin-dashboard-table-wrap">
                            <table class="admin-dashboard-table">
                                <thead>
                                <tr>
                                    <th>{{ $t('admin.dashboardMonthColumn') }}</th>
                                    <th>{{ $t('admin.dashboardRetentionNewUsers') }}</th>
                                    <th>{{ $t('admin.dashboardRetentionRetainedUsers') }}</th>
                                    <th>{{ $t('admin.dashboardRetentionRate') }}</th>
                                    <th>{{ $t('admin.dashboardRetentionPartial') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr
                                    v-for="item in (dashboard.retention?.cohorts ?? [])"
                                    :key="`admin-retention-cohort-${item.month}`"
                                >
                                    <td>{{ formatDashboardMonth(item.month) }}</td>
                                    <td>{{ formatDashboardNumber(item.new_users) }}</td>
                                    <td>{{ formatDashboardNumber(item.retained_users) }}</td>
                                    <td>{{ Number(item.retention_percent ?? 0).toFixed(1) }}%</td>
                                    <td>{{ item.partial ? $t('admin.dashboardPartialYes') : $t('admin.dashboardPartialNo') }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="admin-dashboard-grid admin-dashboard-grid--deep">
                        <div class="simple-item admin-dashboard-card admin-dashboard-card--deep">
                            <div class="admin-dashboard-card-head admin-dashboard-card-head--analytics">
                                <div class="admin-dashboard-card-copy">
                                    <strong>{{ $t('admin.dashboardContentTitle') }}</strong>
                                    <span class="muted">{{ $t('admin.dashboardContentSubtitle') }}</span>
                                </div>
                            </div>

                            <div class="admin-dashboard-mini-grid">
                                <article
                                    v-for="item in dashboardContentMetricCards"
                                    :key="`admin-content-${item.key}`"
                                    class="admin-dashboard-mini-card"
                                >
                                    <span class="admin-dashboard-mini-label">{{ item.label }}</span>
                                    <strong class="admin-dashboard-mini-value">
                                        {{ formatDashboardNumber(item.value) }}<template v-if="item.suffix">{{ item.suffix }}</template>
                                    </strong>
                                </article>
                            </div>

                            <div class="admin-dashboard-split-list">
                                <div class="admin-dashboard-list-card">
                                    <strong>{{ $t('admin.dashboardTopPostsTitle') }}</strong>
                                    <div class="admin-dashboard-entity-list">
                                        <article
                                            v-for="item in (dashboard.content?.top_posts ?? [])"
                                            :key="`admin-top-post-${item.id}`"
                                            class="admin-dashboard-entity-item"
                                        >
                                            <strong>{{ item.title || `#${item.id}` }}</strong>
                                            <span class="muted">{{ item.author_name }}<template v-if="item.author_nickname"> · @{{ item.author_nickname }}</template></span>
                                            <small>
                                                {{ $t('admin.dashboardTopPostMeta', {
                                                    engagement: formatDashboardNumber(item.engagement_score),
                                                    views: formatDashboardNumber(item.views_count),
                                                }) }}
                                            </small>
                                        </article>
                                    </div>
                                </div>

                                <div class="admin-dashboard-list-card">
                                    <strong>{{ $t('admin.dashboardTopAuthorsTitle') }}</strong>
                                    <div class="admin-dashboard-entity-list">
                                        <article
                                            v-for="item in (dashboard.content?.top_authors ?? [])"
                                            :key="`admin-top-author-${item.user_id}`"
                                            class="admin-dashboard-entity-item"
                                        >
                                            <strong>{{ item.name || `#${item.user_id}` }}</strong>
                                            <span class="muted"><template v-if="item.nickname">@{{ item.nickname }}</template><template v-else>#{{ item.user_id }}</template></span>
                                            <small>
                                                {{ $t('admin.dashboardTopAuthorMeta', {
                                                    posts: formatDashboardNumber(item.posts_count),
                                                    engagement: formatDashboardNumber(item.engagement_total),
                                                }) }}
                                            </small>
                                        </article>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="simple-item admin-dashboard-card admin-dashboard-card--deep">
                            <div class="admin-dashboard-card-head admin-dashboard-card-head--analytics">
                                <div class="admin-dashboard-card-copy">
                                    <strong>{{ $t('admin.dashboardChatsTitle') }}</strong>
                                    <span class="muted">{{ $t('admin.dashboardChatsSubtitle') }}</span>
                                </div>
                            </div>

                            <div class="admin-dashboard-mini-grid">
                                <article
                                    v-for="item in dashboardChatMetricCards"
                                    :key="`admin-chats-${item.key}`"
                                    class="admin-dashboard-mini-card"
                                >
                                    <span class="admin-dashboard-mini-label">{{ item.label }}</span>
                                    <strong class="admin-dashboard-mini-value">
                                        {{ formatDashboardNumber(item.value) }}<template v-if="item.suffix">{{ item.suffix }}</template>
                                    </strong>
                                </article>
                            </div>

                            <div class="admin-dashboard-pill-list">
                                <span
                                    v-for="item in (dashboard.chats?.attachment_breakdown ?? [])"
                                    :key="`admin-chat-attachment-${item.type}`"
                                    class="admin-dashboard-type-pill"
                                >
                                    {{ item.type }} · {{ formatDashboardNumber(item.value) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="admin-dashboard-grid admin-dashboard-grid--deep">
                        <div class="simple-item admin-dashboard-card admin-dashboard-card--deep">
                            <div class="admin-dashboard-card-head admin-dashboard-card-head--analytics">
                                <div class="admin-dashboard-card-copy">
                                    <strong>{{ $t('admin.dashboardMediaTitle') }}</strong>
                                    <span class="muted">{{ $t('admin.dashboardMediaSubtitle') }}</span>
                                </div>
                            </div>

                            <div class="admin-dashboard-mini-grid">
                                <article
                                    v-for="item in dashboardMediaMetricCards"
                                    :key="`admin-media-${item.key}`"
                                    class="admin-dashboard-mini-card"
                                >
                                    <span class="admin-dashboard-mini-label">{{ item.label }}</span>
                                    <strong class="admin-dashboard-mini-value">
                                        {{ formatDashboardNumber(item.value) }}<template v-if="item.suffix">{{ item.suffix }}</template>
                                    </strong>
                                </article>
                            </div>
                        </div>

                        <div class="simple-item admin-dashboard-card admin-dashboard-card--deep">
                            <div class="admin-dashboard-card-head admin-dashboard-card-head--analytics">
                                <div class="admin-dashboard-card-copy">
                                    <strong>{{ $t('admin.dashboardTransportTitle') }}</strong>
                                    <span class="muted">{{ $t('admin.dashboardTransportSubtitle') }}</span>
                                </div>
                            </div>

                            <div class="admin-dashboard-dual-stack">
                                <section class="admin-dashboard-stack-block">
                                    <strong>{{ $t('admin.dashboardRadioTitle') }}</strong>
                                    <div class="admin-dashboard-mini-grid admin-dashboard-mini-grid--compact">
                                        <article
                                            v-for="item in dashboardRadioMetricCards"
                                            :key="`admin-radio-${item.key}`"
                                            class="admin-dashboard-mini-card"
                                        >
                                            <span class="admin-dashboard-mini-label">{{ item.label }}</span>
                                            <strong class="admin-dashboard-mini-value">
                                                {{ formatDashboardNumber(item.value) }}<template v-if="item.suffix">{{ item.suffix }}</template>
                                            </strong>
                                        </article>
                                    </div>

                                    <div class="admin-dashboard-entity-list admin-dashboard-entity-list--compact">
                                        <article
                                            v-for="item in (dashboard.radio?.top_stations ?? [])"
                                            :key="`admin-radio-station-${item.entity_key || item.entity_id}`"
                                            class="admin-dashboard-entity-item"
                                        >
                                            <strong>{{ item.label || item.entity_key || item.entity_id }}</strong>
                                            <small>{{ formatDashboardNumber(item.value) }}</small>
                                        </article>
                                    </div>
                                </section>

                                <section class="admin-dashboard-stack-block">
                                    <strong>{{ $t('admin.dashboardIptvTitle') }}</strong>
                                    <div class="admin-dashboard-mini-grid admin-dashboard-mini-grid--compact">
                                        <article
                                            v-for="item in dashboardIptvMetricCards"
                                            :key="`admin-iptv-${item.key}`"
                                            class="admin-dashboard-mini-card"
                                        >
                                            <span class="admin-dashboard-mini-label">{{ item.label }}</span>
                                            <strong class="admin-dashboard-mini-value">
                                                {{ formatDashboardNumber(item.value) }}<template v-if="item.suffix">{{ item.suffix }}</template>
                                            </strong>
                                        </article>
                                    </div>

                                    <div class="admin-dashboard-pill-list">
                                        <span
                                            v-for="item in (dashboard.iptv?.mode_split ?? [])"
                                            :key="`admin-iptv-mode-${item.key}`"
                                            class="admin-dashboard-type-pill"
                                        >
                                            {{ item.key }} · {{ formatDashboardNumber(item.started) }} / {{ Number(item.share ?? 0).toFixed(1) }}%
                                        </span>
                                    </div>

                                    <div class="admin-dashboard-entity-list admin-dashboard-entity-list--compact">
                                        <article
                                            v-for="item in (dashboard.iptv?.top_channels ?? [])"
                                            :key="`admin-iptv-channel-${item.entity_key || item.entity_id}`"
                                            class="admin-dashboard-entity-item"
                                        >
                                            <strong>{{ item.label || item.entity_key || item.entity_id }}</strong>
                                            <small>{{ formatDashboardNumber(item.value) }}</small>
                                        </article>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>

                    <div class="simple-item admin-dashboard-card admin-dashboard-card--quality">
                        <div class="admin-dashboard-card-head admin-dashboard-card-head--analytics">
                            <div class="admin-dashboard-card-copy">
                                <strong>{{ $t('admin.dashboardQualityTitle') }}</strong>
                                <span class="muted">{{ $t('admin.dashboardQualitySubtitle') }}</span>
                            </div>
                        </div>

                        <div class="admin-dashboard-quality-layout">
                            <div class="admin-dashboard-mini-grid">
                                <article
                                    v-for="item in dashboardHealthMetricCards"
                                    :key="`admin-health-${item.key}`"
                                    class="admin-dashboard-mini-card"
                                >
                                    <span class="admin-dashboard-mini-label">{{ item.label }}</span>
                                    <strong class="admin-dashboard-mini-value">{{ formatDashboardNumber(item.value) }}</strong>
                                </article>
                            </div>

                            <section class="admin-dashboard-log-card">
                                <div class="admin-dashboard-log-head">
                                    <div class="admin-dashboard-card-copy">
                                        <strong>{{ $t('admin.dashboardErrorLogTitle') }}</strong>
                                        <span class="muted">{{ $t('admin.dashboardErrorLogSubtitle') }}</span>
                                    </div>

                                    <div class="admin-dashboard-log-actions">
                                        <button
                                            class="btn btn-outline btn-sm"
                                            @click="selectTab('errorLog')"
                                        >
                                            {{ $t('admin.errorLogOpenTab') }}
                                        </button>
                                        <button
                                            class="btn btn-outline btn-sm"
                                            @click="loadErrorLogPreview()"
                                            :disabled="errorLogLoading"
                                        >
                                            {{ errorLogLoading ? $t('common.loading') : $t('admin.dashboardErrorLogRefresh') }}
                                        </button>
                                        <button
                                            class="btn btn-outline btn-sm"
                                            @click="downloadErrorLog"
                                            :disabled="!errorLog.exists || errorLogDownloading"
                                        >
                                            {{ errorLogDownloading ? $t('admin.dashboardErrorLogDownloading') : $t('admin.dashboardErrorLogDownload') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="admin-dashboard-log-meta">
                                    <div class="admin-dashboard-log-pill">
                                        <small>{{ $t('admin.dashboardErrorLogFile') }}</small>
                                        <strong>{{ errorLog.file_name }}</strong>
                                    </div>
                                    <div class="admin-dashboard-log-pill">
                                        <small>{{ $t('admin.dashboardErrorLogSize') }}</small>
                                        <strong>{{ formatDashboardFileSize(errorLog.size_bytes) }}</strong>
                                    </div>
                                    <div class="admin-dashboard-log-pill">
                                        <small>{{ $t('admin.dashboardErrorLogUpdated') }}</small>
                                        <strong>{{ formatDate(errorLog.updated_at) }}</strong>
                                    </div>
                                    <div class="admin-dashboard-log-pill">
                                        <small>{{ $t('admin.dashboardErrorLogPath') }}</small>
                                        <code>{{ errorLog.relative_path }}</code>
                                    </div>
                                </div>

                                <div v-if="errorLog.exists && errorLog.preview" class="admin-dashboard-log-viewer-wrap">
                                    <pre class="admin-dashboard-log-viewer">{{ errorLog.preview }}</pre>
                                </div>
                                <p v-else class="muted admin-dashboard-log-empty">
                                    {{ $t('admin.dashboardErrorLogEmpty') }}
                                </p>

                                <p v-if="errorLog.truncated" class="muted admin-dashboard-log-note">
                                    {{ $t('admin.dashboardErrorLogTruncated') }}
                                </p>
                            </section>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'errorLog'" class="simple-list fade-in">
                <div class="simple-item admin-simple-item-block admin-error-log-panel">
                    <div class="admin-error-log-toolbar">
                        <div>
                            <strong class="admin-section-title">{{ $t('admin.errorLogTabTitle') }}</strong>
                            <p class="muted admin-error-log-toolbar-note">{{ $t('admin.errorLogTabSubtitle') }}</p>
                        </div>

                        <div class="admin-error-log-actions">
                            <button
                                class="btn btn-outline btn-sm"
                                @click="refreshErrorLogWorkspace"
                                :disabled="errorLogEntriesLoading"
                            >
                                {{ errorLogEntriesLoading ? $t('common.loading') : $t('admin.dashboardErrorLogRefresh') }}
                            </button>
                            <button
                                class="btn btn-outline btn-sm"
                                @click="exportFilteredErrorLog"
                                :disabled="errorLogEntriesLoading || errorLogFilteredExporting"
                            >
                                {{ errorLogFilteredExporting ? $t('admin.errorLogExportingFiltered') : $t('admin.errorLogExportFiltered') }}
                            </button>
                            <button
                                class="btn btn-outline btn-sm"
                                @click="downloadErrorLog"
                                :disabled="!errorLog.exists || errorLogDownloading"
                            >
                                {{ errorLogDownloading ? $t('admin.dashboardErrorLogDownloading') : $t('admin.dashboardErrorLogDownload') }}
                            </button>
                        </div>
                    </div>

                    <div class="admin-error-log-filters">
                        <input
                            class="input-field"
                            type="search"
                            v-model.trim="errorLogFilters.search"
                            :placeholder="$t('admin.errorLogSearchPlaceholder')"
                            @keyup.enter="applyErrorLogFilters"
                        >

                        <select class="select-field" v-model="errorLogFilters.type">
                            <option
                                v-for="option in errorLogTypeOptions"
                                :key="`error-log-type-${option.value}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <select class="select-field" v-model.number="errorLogFilters.per_page">
                            <option :value="10">{{ $t('admin.errorLogPerPageOption', { count: 10 }) }}</option>
                            <option :value="20">{{ $t('admin.errorLogPerPageOption', { count: 20 }) }}</option>
                            <option :value="50">{{ $t('admin.errorLogPerPageOption', { count: 50 }) }}</option>
                        </select>

                        <button
                            class="btn btn-primary btn-sm"
                            @click="applyErrorLogFilters"
                            :disabled="errorLogEntriesLoading"
                        >
                            {{ $t('admin.errorLogApply') }}
                        </button>

                        <button
                            class="btn btn-outline btn-sm"
                            @click="resetErrorLogFilters"
                            :disabled="errorLogEntriesLoading"
                        >
                            {{ $t('admin.errorLogReset') }}
                        </button>
                    </div>

                    <div class="admin-dashboard-log-meta">
                        <div class="admin-dashboard-log-pill">
                            <small>{{ $t('admin.dashboardErrorLogFile') }}</small>
                            <strong>{{ errorLog.file_name }}</strong>
                        </div>
                        <div class="admin-dashboard-log-pill">
                            <small>{{ $t('admin.dashboardErrorLogSize') }}</small>
                            <strong>{{ formatDashboardFileSize(errorLog.size_bytes) }}</strong>
                        </div>
                        <div class="admin-dashboard-log-pill">
                            <small>{{ $t('admin.dashboardErrorLogUpdated') }}</small>
                            <strong>{{ formatDate(errorLog.updated_at) }}</strong>
                        </div>
                        <div class="admin-dashboard-log-pill">
                            <small>{{ $t('admin.errorLogResults') }}</small>
                            <strong>
                                {{ $t('admin.errorLogResultsValue', { from: errorLogEntriesMeta.from, to: errorLogEntriesMeta.to, total: errorLogEntriesMeta.total }) }}
                            </strong>
                        </div>
                        <div class="admin-dashboard-log-pill">
                            <small>{{ $t('admin.errorLogArchives') }}</small>
                            <strong>
                                {{ $t('admin.errorLogArchivesValue', { count: errorLog.archive_count, size: formatDashboardFileSize(errorLog.archive_size_bytes) }) }}
                            </strong>
                        </div>
                        <div class="admin-dashboard-log-pill">
                            <small>{{ $t('admin.dashboardErrorLogPath') }}</small>
                            <code>{{ errorLog.relative_path }}</code>
                        </div>
                        <div class="admin-dashboard-log-pill">
                            <small>{{ $t('admin.errorLogArchivePath') }}</small>
                            <code>{{ errorLog.archive_relative_path }}</code>
                        </div>
                    </div>

                    <div v-if="errorLogEntries.length > 0" class="admin-error-log-list">
                        <details
                            v-for="(entry, index) in errorLogEntries"
                            :key="entry.id"
                            class="admin-error-log-entry"
                            :open="index === 0"
                        >
                            <summary class="admin-error-log-entry-summary">
                                <div class="admin-error-log-entry-main">
                                    <div class="admin-error-log-entry-topline">
                                        <span class="admin-error-log-badge" :class="`is-${entry.type}`">
                                            {{ errorLogTypeLabel(entry.type) }}
                                        </span>
                                        <strong>{{ entry.headline }}</strong>
                                    </div>
                                    <p v-if="entry.summary" class="muted admin-error-log-entry-summary-text">{{ entry.summary }}</p>
                                    <div class="admin-error-log-entry-hints">
                                        <span v-if="entry.kind" class="admin-error-log-chip">{{ entry.kind }}</span>
                                        <span v-else-if="entry.event" class="admin-error-log-chip">{{ entry.event }}</span>
                                        <span v-if="entry.status_code" class="admin-error-log-chip">HTTP {{ entry.status_code }}</span>
                                        <span v-if="entry.request_url" class="admin-error-log-chip">{{ entry.request_method || 'GET' }} {{ entry.request_url }}</span>
                                        <span v-else-if="entry.file" class="admin-error-log-chip">{{ entry.file }}</span>
                                    </div>
                                </div>

                                <div class="admin-error-log-entry-side">
                                    <span class="muted">{{ formatDate(entry.timestamp) }}</span>
                                </div>
                            </summary>

                            <div class="admin-error-log-entry-body">
                                <pre class="admin-error-log-entry-raw">{{ entry.raw }}</pre>
                            </div>
                        </details>
                    </div>
                    <p v-else class="muted admin-error-log-empty-state">
                        {{ errorLogEntriesLoading ? $t('common.loading') : $t('admin.errorLogNoResults') }}
                    </p>

                    <div class="admin-error-log-pagination" v-if="errorLogEntriesMeta.last_page > 1">
                        <button
                            class="btn btn-outline btn-sm"
                            @click="changeErrorLogPage(errorLogEntriesMeta.current_page - 1)"
                            :disabled="errorLogEntriesLoading || errorLogEntriesMeta.current_page <= 1"
                        >
                            {{ $t('admin.errorLogPreviousPage') }}
                        </button>

                        <span class="admin-error-log-pagination-status">
                            {{ $t('admin.errorLogPageStatus', { current: errorLogEntriesMeta.current_page, total: errorLogEntriesMeta.last_page }) }}
                        </span>

                        <button
                            class="btn btn-outline btn-sm"
                            @click="changeErrorLogPage(errorLogEntriesMeta.current_page + 1)"
                            :disabled="errorLogEntriesLoading || errorLogEntriesMeta.current_page >= errorLogEntriesMeta.last_page"
                        >
                            {{ $t('admin.errorLogNextPage') }}
                        </button>
                    </div>
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
const DASHBOARD_ACTIVITY_CHART_WIDTH = 760
const DASHBOARD_ACTIVITY_CHART_HEIGHT = 228
const DASHBOARD_ACTIVITY_CHART_PADDING_X = 18
const DASHBOARD_ACTIVITY_CHART_PADDING_TOP = 18
const DASHBOARD_ACTIVITY_CHART_PADDING_BOTTOM = 28
const DASHBOARD_ACTIVITY_CHART_GRID_LINES = 4
const DASHBOARD_MIN_ACTIVITY_BAR_PERCENT = 3
const DASHBOARD_FEATURE_COLORS = {
    social: '#55ddff',
    chats: '#ffbc5b',
    radio: '#4df0bf',
    iptv: '#ff71ad',
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
        retention: {
            dau: 0,
            wau: 0,
            mau: 0,
            stickiness_percent: 0,
            new_active_users_30d: 0,
            returning_users_30d: 0,
            cohorts: [],
        },
        content: {
            posts_total: 0,
            public_posts: 0,
            private_posts: 0,
            carousel_posts: 0,
            engagement_total: 0,
            views_total: 0,
            likes_total: 0,
            comments_total: 0,
            reposts_total: 0,
            engagement_per_post: 0,
            avg_views_per_post: 0,
            view_to_engagement_rate_percent: 0,
            top_posts: [],
            top_authors: [],
        },
        chats: {
            messages_total: 0,
            active_chatters: 0,
            attachments_total: 0,
            attachment_breakdown: [],
            reply_samples: 0,
            avg_reply_minutes: 0,
            median_reply_minutes: 0,
        },
        media: {
            uploads_total: 0,
            post_media_uploads: 0,
            chat_attachments_uploads: 0,
            images_uploaded: 0,
            videos_uploaded: 0,
            avg_upload_size_kb: 0,
            failed_uploads: 0,
            upload_failure_rate_percent: 0,
            video_sessions: 0,
            video_completed_sessions: 0,
            video_completion_rate_percent: 0,
            video_watch_seconds: 0,
            avg_video_completion_percent: 0,
            theater_opens: 0,
            fullscreen_entries: 0,
        },
        radio: {
            active_users_period: 0,
            favorite_additions_period: 0,
            sessions_started: 0,
            failures_total: 0,
            failure_rate_percent: 0,
            top_stations: [],
        },
        iptv: {
            active_users_period: 0,
            saved_channels_period: 0,
            saved_playlists_period: 0,
            sessions_started: 0,
            failures_total: 0,
            failure_rate_percent: 0,
            mode_split: [
                { key: 'direct', started: 0, failed: 0, share: 0 },
                { key: 'proxy', started: 0, failed: 0, share: 0 },
                { key: 'relay', started: 0, failed: 0, share: 0 },
                { key: 'ffmpeg', started: 0, failed: 0, share: 0 },
            ],
            top_channels: [],
        },
        errors_and_moderation: {
            media_upload_failures: 0,
            radio_failures: 0,
            iptv_failures: 0,
            total_tracked_failures: 0,
            active_blocks_total: 0,
            feedback_new_total: 0,
            feedback_in_progress_total: 0,
            feedback_resolved_total: 0,
            feedback_created_period: 0,
        },
    }
}

function buildEmptyErrorLogPayload() {
    return {
        exists: false,
        file_name: 'site-errors.log',
        relative_path: 'storage/logs/site-errors.log',
        size_bytes: 0,
        updated_at: null,
        truncated: false,
        preview: '',
        archive_count: 0,
        archive_size_bytes: 0,
        archive_relative_path: 'storage/logs/site-errors-archive',
    }
}

function buildEmptyErrorLogEntriesMeta() {
    return {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
        from: 0,
        to: 0,
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
            errorLogLoading: false,
            errorLogDownloading: false,
            errorLogFilteredExporting: false,
            errorLog: buildEmptyErrorLogPayload(),
            errorLogEntriesLoading: false,
            errorLogEntries: [],
            errorLogEntriesMeta: buildEmptyErrorLogEntriesMeta(),
            errorLogFilters: {
                search: '',
                type: 'all',
                per_page: 20,
            },
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

        dashboardActivityChartMaxValue() {
            const series = Array.isArray(this.dashboard?.activity_by_month)
                ? this.dashboard.activity_by_month
                : []
            const values = series.flatMap((item) => ([
                Math.max(0, Number(item?.social ?? 0)),
                Math.max(0, Number(item?.chats ?? 0)),
                Math.max(0, Number(item?.radio ?? 0)),
                Math.max(0, Number(item?.iptv ?? 0)),
            ]))

            return Math.max(...values, 1)
        },

        dashboardActivityChartMonths() {
            const series = Array.isArray(this.dashboard?.activity_by_month)
                ? this.dashboard.activity_by_month
                : []

            if (series.length === 0) {
                return []
            }

            const innerWidth = DASHBOARD_ACTIVITY_CHART_WIDTH - (DASHBOARD_ACTIVITY_CHART_PADDING_X * 2)
            const divisor = Math.max(series.length - 1, 1)

            return series.map((item, index) => ({
                month: Number(item?.month ?? (index + 1)),
                x: Number((DASHBOARD_ACTIVITY_CHART_PADDING_X + ((innerWidth * index) / divisor)).toFixed(2)),
            }))
        },

        dashboardActivityChartGridLines() {
            const innerHeight = DASHBOARD_ACTIVITY_CHART_HEIGHT - DASHBOARD_ACTIVITY_CHART_PADDING_TOP - DASHBOARD_ACTIVITY_CHART_PADDING_BOTTOM
            const totalLines = Math.max(DASHBOARD_ACTIVITY_CHART_GRID_LINES, 2)

            return Array.from({ length: totalLines }, (_, index) => ({
                index,
                y: Number((DASHBOARD_ACTIVITY_CHART_PADDING_TOP + ((innerHeight * index) / (totalLines - 1))).toFixed(2)),
            }))
        },

        dashboardActivityChartSeries() {
            const series = Array.isArray(this.dashboard?.activity_by_month)
                ? this.dashboard.activity_by_month
                : []
            const monthPoints = this.dashboardActivityChartMonths
            if (series.length === 0 || monthPoints.length === 0) {
                return []
            }

            const maxValue = Math.max(Number(this.dashboardActivityChartMaxValue ?? 1), 1)
            const innerHeight = DASHBOARD_ACTIVITY_CHART_HEIGHT - DASHBOARD_ACTIVITY_CHART_PADDING_TOP - DASHBOARD_ACTIVITY_CHART_PADDING_BOTTOM
            const baselineY = DASHBOARD_ACTIVITY_CHART_HEIGHT - DASHBOARD_ACTIVITY_CHART_PADDING_BOTTOM

            return ['social', 'chats', 'radio', 'iptv'].map((key) => {
                const points = monthPoints.map((monthPoint, index) => {
                    const source = series[index] ?? {}
                    const value = Math.max(0, Number(source?.[key] ?? 0))
                    const y = baselineY - ((value / maxValue) * innerHeight)

                    return {
                        month: monthPoint.month,
                        value,
                        x: monthPoint.x,
                        y: Number(y.toFixed(2)),
                    }
                })

                return {
                    key,
                    color: this.dashboardFeatureColor(key),
                    path: points
                        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
                        .join(' '),
                    points,
                }
            })
        },

        dashboardActivityTotalPeriod() {
            const series = Array.isArray(this.dashboard?.activity_by_month)
                ? this.dashboard.activity_by_month
                : []

            return series.reduce((sum, item) => sum + Math.max(0, Number(item?.total ?? 0)), 0)
        },

        dashboardActivityPeakItem() {
            const series = Array.isArray(this.dashboard?.activity_by_month)
                ? this.dashboard.activity_by_month
                : []

            return series.reduce((best, item, index) => {
                const candidate = {
                    month: Number(item?.month ?? (index + 1)),
                    social: Math.max(0, Number(item?.social ?? 0)),
                    chats: Math.max(0, Number(item?.chats ?? 0)),
                    radio: Math.max(0, Number(item?.radio ?? 0)),
                    iptv: Math.max(0, Number(item?.iptv ?? 0)),
                    total: Math.max(0, Number(item?.total ?? 0)),
                }

                if (!best || candidate.total > best.total) {
                    return candidate
                }

                return best
            }, null) ?? {
                month: 1,
                social: 0,
                chats: 0,
                radio: 0,
                iptv: 0,
                total: 0,
            }
        },

        dashboardActivityPeakSharePercent() {
            const totalPeriod = Math.max(0, Number(this.dashboardActivityTotalPeriod ?? 0))
            if (totalPeriod <= 0) {
                return '0.0'
            }

            const peakValue = Math.max(0, Number(this.dashboardActivityPeakItem?.total ?? 0))
            return ((peakValue / totalPeriod) * 100).toFixed(1)
        },

        dashboardActivityModuleItems() {
            const series = Array.isArray(this.dashboard?.activity_by_month)
                ? this.dashboard.activity_by_month
                : []
            const totals = {
                social: 0,
                chats: 0,
                radio: 0,
                iptv: 0,
            }

            series.forEach((item) => {
                totals.social += Math.max(0, Number(item?.social ?? 0))
                totals.chats += Math.max(0, Number(item?.chats ?? 0))
                totals.radio += Math.max(0, Number(item?.radio ?? 0))
                totals.iptv += Math.max(0, Number(item?.iptv ?? 0))
            })

            const periodTotal = Math.max(0, Number(this.dashboardActivityTotalPeriod ?? 0))

            return ['social', 'chats', 'radio', 'iptv'].map((key) => {
                const value = Math.max(0, Number(totals[key] ?? 0))

                return {
                    key,
                    label: this.dashboardFeatureLabel(key),
                    color: this.dashboardFeatureColor(key),
                    value,
                    share: periodTotal > 0 ? (value / periodTotal) * 100 : 0,
                }
            })
        },

        dashboardActivityLeaderItem() {
            const items = this.dashboardActivityModuleItems
            if (!Array.isArray(items) || items.length === 0) {
                return {
                    key: 'social',
                    value: 0,
                }
            }

            return items.reduce((best, item) => {
                if (!best || Number(item?.value ?? 0) > Number(best?.value ?? 0)) {
                    return item
                }

                return best
            }, items[0])
        },

        dashboardActivitySummaryCards() {
            return [
                {
                    key: 'users-30d',
                    label: this.$t('admin.dashboardEngagementUsers30d'),
                    value: this.dashboard?.engagement?.active_users_30d ?? 0,
                    color: this.dashboardFeatureColor('social'),
                },
                {
                    key: 'creators-30d',
                    label: this.$t('admin.dashboardEngagementCreators30d'),
                    value: this.dashboard?.engagement?.creators_30d ?? 0,
                    color: '#7dd3fc',
                },
                {
                    key: 'chatters-30d',
                    label: this.$t('admin.dashboardEngagementChatters30d'),
                    value: this.dashboard?.engagement?.chatters_30d ?? 0,
                    color: this.dashboardFeatureColor('chats'),
                },
                {
                    key: 'social-30d',
                    label: this.$t('admin.dashboardEngagementSocial30d'),
                    value: this.dashboard?.engagement?.social_active_users_30d ?? 0,
                    color: this.dashboardFeatureColor('social'),
                },
                {
                    key: 'radio-30d',
                    label: this.$t('admin.dashboardEngagementRadio30d'),
                    value: this.dashboard?.engagement?.radio_active_users_30d ?? 0,
                    color: this.dashboardFeatureColor('radio'),
                },
                {
                    key: 'iptv-30d',
                    label: this.$t('admin.dashboardEngagementIptv30d'),
                    value: this.dashboard?.engagement?.iptv_active_users_30d ?? 0,
                    color: this.dashboardFeatureColor('iptv'),
                },
            ]
        },

        dashboardRetentionCards() {
            return [
                { key: 'dau', label: this.$t('admin.dashboardRetentionDau'), value: this.dashboard?.retention?.dau ?? 0 },
                { key: 'wau', label: this.$t('admin.dashboardRetentionWau'), value: this.dashboard?.retention?.wau ?? 0 },
                { key: 'mau', label: this.$t('admin.dashboardRetentionMau'), value: this.dashboard?.retention?.mau ?? 0 },
                { key: 'stickiness', label: this.$t('admin.dashboardRetentionStickiness'), value: this.dashboard?.retention?.stickiness_percent ?? 0, suffix: '%' },
                { key: 'new-active', label: this.$t('admin.dashboardRetentionNewActive30d'), value: this.dashboard?.retention?.new_active_users_30d ?? 0 },
                { key: 'returning', label: this.$t('admin.dashboardRetentionReturning30d'), value: this.dashboard?.retention?.returning_users_30d ?? 0 },
            ]
        },

        dashboardContentMetricCards() {
            return [
                { key: 'posts', label: this.$t('admin.dashboardContentPosts'), value: this.dashboard?.content?.posts_total ?? 0 },
                { key: 'public', label: this.$t('admin.dashboardContentPublicPosts'), value: this.dashboard?.content?.public_posts ?? 0 },
                { key: 'engagement', label: this.$t('admin.dashboardContentEngagementTotal'), value: this.dashboard?.content?.engagement_total ?? 0 },
                { key: 'engagement-per-post', label: this.$t('admin.dashboardContentEngagementPerPost'), value: this.dashboard?.content?.engagement_per_post ?? 0 },
                { key: 'avg-views', label: this.$t('admin.dashboardContentAvgViewsPerPost'), value: this.dashboard?.content?.avg_views_per_post ?? 0 },
                { key: 'view-rate', label: this.$t('admin.dashboardContentViewRate'), value: this.dashboard?.content?.view_to_engagement_rate_percent ?? 0, suffix: '%' },
            ]
        },

        dashboardChatMetricCards() {
            return [
                { key: 'messages', label: this.$t('admin.dashboardChatsMessages'), value: this.dashboard?.chats?.messages_total ?? 0 },
                { key: 'chatters', label: this.$t('admin.dashboardChatsActiveUsers'), value: this.dashboard?.chats?.active_chatters ?? 0 },
                { key: 'attachments', label: this.$t('admin.dashboardChatsAttachments'), value: this.dashboard?.chats?.attachments_total ?? 0 },
                { key: 'reply-avg', label: this.$t('admin.dashboardChatsAvgReplyMinutes'), value: this.dashboard?.chats?.avg_reply_minutes ?? 0 },
                { key: 'reply-median', label: this.$t('admin.dashboardChatsMedianReplyMinutes'), value: this.dashboard?.chats?.median_reply_minutes ?? 0 },
            ]
        },

        dashboardMediaMetricCards() {
            return [
                { key: 'uploads', label: this.$t('admin.dashboardMediaUploads'), value: this.dashboard?.media?.uploads_total ?? 0 },
                { key: 'failed', label: this.$t('admin.dashboardMediaFailedUploads'), value: this.dashboard?.media?.failed_uploads ?? 0 },
                { key: 'failure-rate', label: this.$t('admin.dashboardMediaFailureRate'), value: this.dashboard?.media?.upload_failure_rate_percent ?? 0, suffix: '%' },
                { key: 'video-sessions', label: this.$t('admin.dashboardMediaVideoSessions'), value: this.dashboard?.media?.video_sessions ?? 0 },
                { key: 'completion-rate', label: this.$t('admin.dashboardMediaCompletionRate'), value: this.dashboard?.media?.video_completion_rate_percent ?? 0, suffix: '%' },
                { key: 'watch-seconds', label: this.$t('admin.dashboardMediaWatchSeconds'), value: this.dashboard?.media?.video_watch_seconds ?? 0 },
                { key: 'theater', label: this.$t('admin.dashboardMediaTheaterOpens'), value: this.dashboard?.media?.theater_opens ?? 0 },
                { key: 'fullscreen', label: this.$t('admin.dashboardMediaFullscreenEntries'), value: this.dashboard?.media?.fullscreen_entries ?? 0 },
            ]
        },

        dashboardRadioMetricCards() {
            return [
                { key: 'sessions', label: this.$t('admin.dashboardRadioSessions'), value: this.dashboard?.radio?.sessions_started ?? 0 },
                { key: 'failures', label: this.$t('admin.dashboardRadioFailures'), value: this.dashboard?.radio?.failures_total ?? 0 },
                { key: 'failure-rate', label: this.$t('admin.dashboardRadioFailureRate'), value: this.dashboard?.radio?.failure_rate_percent ?? 0, suffix: '%' },
                { key: 'favorites', label: this.$t('admin.dashboardRadioFavorites'), value: this.dashboard?.radio?.favorite_additions_period ?? 0 },
            ]
        },

        dashboardIptvMetricCards() {
            return [
                { key: 'sessions', label: this.$t('admin.dashboardIptvSessions'), value: this.dashboard?.iptv?.sessions_started ?? 0 },
                { key: 'failures', label: this.$t('admin.dashboardIptvFailures'), value: this.dashboard?.iptv?.failures_total ?? 0 },
                { key: 'failure-rate', label: this.$t('admin.dashboardIptvFailureRate'), value: this.dashboard?.iptv?.failure_rate_percent ?? 0, suffix: '%' },
                { key: 'saved-channels', label: this.$t('admin.dashboardIptvSavedChannels'), value: this.dashboard?.iptv?.saved_channels_period ?? 0 },
                { key: 'saved-playlists', label: this.$t('admin.dashboardIptvSavedPlaylists'), value: this.dashboard?.iptv?.saved_playlists_period ?? 0 },
            ]
        },

        dashboardHealthMetricCards() {
            return [
                { key: 'tracked', label: this.$t('admin.dashboardErrorsTracked'), value: this.dashboard?.errors_and_moderation?.total_tracked_failures ?? 0 },
                { key: 'radio', label: this.$t('admin.dashboardErrorsRadio'), value: this.dashboard?.errors_and_moderation?.radio_failures ?? 0 },
                { key: 'iptv', label: this.$t('admin.dashboardErrorsIptv'), value: this.dashboard?.errors_and_moderation?.iptv_failures ?? 0 },
                { key: 'upload', label: this.$t('admin.dashboardErrorsUploads'), value: this.dashboard?.errors_and_moderation?.media_upload_failures ?? 0 },
                { key: 'blocks', label: this.$t('admin.dashboardModerationBlocks'), value: this.dashboard?.errors_and_moderation?.active_blocks_total ?? 0 },
                { key: 'feedback-new', label: this.$t('admin.dashboardModerationFeedbackNew'), value: this.dashboard?.errors_and_moderation?.feedback_new_total ?? 0 },
                { key: 'feedback-progress', label: this.$t('admin.dashboardModerationFeedbackProgress'), value: this.dashboard?.errors_and_moderation?.feedback_in_progress_total ?? 0 },
                { key: 'feedback-resolved', label: this.$t('admin.dashboardModerationFeedbackResolved'), value: this.dashboard?.errors_and_moderation?.feedback_resolved_total ?? 0 },
            ]
        },

        dashboardExporting() {
            return this.dashboardExportingFormat !== ''
        },

        errorLogTypeOptions() {
            return [
                { value: 'all', label: this.$t('admin.errorLogTypeAll') },
                { value: 'server_exception', label: this.$t('admin.errorLogTypeServer') },
                { value: 'client_error', label: this.$t('admin.errorLogTypeClient') },
                { value: 'analytics_failure', label: this.$t('admin.errorLogTypeAnalytics') },
            ]
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
            if (tab === 'errorLog') {
                await Promise.all([
                    this.loadErrorLogPreview({ suppressAlert: true }),
                    this.loadErrorLogEntries({ page: this.errorLogEntriesMeta.current_page || 1, suppressAlert: true }),
                ])
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
                retention: {
                    ...fallback.retention,
                    ...(payload?.retention ?? {}),
                    cohorts: Array.isArray(payload?.retention?.cohorts) ? payload.retention.cohorts : fallback.retention.cohorts,
                },
                content: {
                    ...fallback.content,
                    ...(payload?.content ?? {}),
                    top_posts: Array.isArray(payload?.content?.top_posts) ? payload.content.top_posts : fallback.content.top_posts,
                    top_authors: Array.isArray(payload?.content?.top_authors) ? payload.content.top_authors : fallback.content.top_authors,
                },
                chats: {
                    ...fallback.chats,
                    ...(payload?.chats ?? {}),
                    attachment_breakdown: Array.isArray(payload?.chats?.attachment_breakdown)
                        ? payload.chats.attachment_breakdown
                        : fallback.chats.attachment_breakdown,
                },
                media: {
                    ...fallback.media,
                    ...(payload?.media ?? {}),
                },
                radio: {
                    ...fallback.radio,
                    ...(payload?.radio ?? {}),
                    top_stations: Array.isArray(payload?.radio?.top_stations) ? payload.radio.top_stations : fallback.radio.top_stations,
                },
                iptv: {
                    ...fallback.iptv,
                    ...(payload?.iptv ?? {}),
                    mode_split: Array.isArray(payload?.iptv?.mode_split) ? payload.iptv.mode_split : fallback.iptv.mode_split,
                    top_channels: Array.isArray(payload?.iptv?.top_channels) ? payload.iptv.top_channels : fallback.iptv.top_channels,
                },
                errors_and_moderation: {
                    ...fallback.errors_and_moderation,
                    ...(payload?.errors_and_moderation ?? {}),
                },
            }
        },

        normalizeErrorLogPayload(payload) {
            const fallback = buildEmptyErrorLogPayload()
            const sizeBytes = Number(payload?.size_bytes ?? fallback.size_bytes)

            return {
                ...fallback,
                ...payload,
                exists: Boolean(payload?.exists),
                file_name: String(payload?.file_name ?? fallback.file_name),
                relative_path: String(payload?.relative_path ?? fallback.relative_path),
                size_bytes: Number.isFinite(sizeBytes) ? Math.max(0, sizeBytes) : fallback.size_bytes,
                updated_at: typeof payload?.updated_at === 'string' && payload.updated_at.trim() !== ''
                    ? payload.updated_at
                    : null,
                truncated: Boolean(payload?.truncated),
                preview: String(payload?.preview ?? fallback.preview),
                archive_count: Math.max(0, Number(payload?.archive_count ?? fallback.archive_count)),
                archive_size_bytes: Math.max(0, Number(payload?.archive_size_bytes ?? fallback.archive_size_bytes)),
                archive_relative_path: String(payload?.archive_relative_path ?? fallback.archive_relative_path),
            }
        },

        normalizeErrorLogEntriesPayload(payload) {
            const fallbackMeta = buildEmptyErrorLogEntriesMeta()
            const metaSource = payload?.meta ?? {}
            const currentPage = Number(metaSource?.current_page ?? fallbackMeta.current_page)
            const lastPage = Number(metaSource?.last_page ?? fallbackMeta.last_page)
            const perPage = Number(metaSource?.per_page ?? fallbackMeta.per_page)
            const total = Number(metaSource?.total ?? fallbackMeta.total)
            const from = Number(metaSource?.from ?? fallbackMeta.from)
            const to = Number(metaSource?.to ?? fallbackMeta.to)

            return {
                items: Array.isArray(payload?.items)
                    ? payload.items.map((entry) => this.normalizeErrorLogEntry(entry))
                    : [],
                meta: {
                    current_page: Number.isFinite(currentPage) ? Math.max(1, currentPage) : fallbackMeta.current_page,
                    last_page: Number.isFinite(lastPage) ? Math.max(1, lastPage) : fallbackMeta.last_page,
                    per_page: Number.isFinite(perPage) ? Math.max(1, perPage) : fallbackMeta.per_page,
                    total: Number.isFinite(total) ? Math.max(0, total) : fallbackMeta.total,
                    from: Number.isFinite(from) ? Math.max(0, from) : fallbackMeta.from,
                    to: Number.isFinite(to) ? Math.max(0, to) : fallbackMeta.to,
                },
            }
        },

        normalizeErrorLogEntry(entry) {
            return {
                id: String(entry?.id ?? ''),
                timestamp: typeof entry?.timestamp === 'string' && entry.timestamp.trim() !== '' ? entry.timestamp : null,
                type: ['server_exception', 'client_error', 'analytics_failure'].includes(String(entry?.type ?? ''))
                    ? String(entry.type)
                    : 'all',
                headline: String(entry?.headline ?? '').trim() || String(entry?.message ?? '').trim() || 'Log entry',
                message: String(entry?.message ?? '').trim(),
                summary: String(entry?.summary ?? '').trim(),
                exception: String(entry?.exception ?? '').trim(),
                file: String(entry?.file ?? '').trim(),
                feature: String(entry?.feature ?? '').trim(),
                event: String(entry?.event ?? '').trim(),
                kind: String(entry?.kind ?? '').trim(),
                status_code: String(entry?.status_code ?? '').trim(),
                page_url: String(entry?.page_url ?? '').trim(),
                request_url: String(entry?.request_url ?? '').trim(),
                request_method: String(entry?.request_method ?? '').trim().toUpperCase(),
                raw: String(entry?.raw ?? '').trim(),
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

            await this.loadErrorLogPreview({ suppressAlert: true })
        },

        async loadErrorLogPreview(options = {}) {
            const suppressAlert = Boolean(options?.suppressAlert)
            this.errorLogLoading = true

            try {
                const response = await axios.get('/api/admin/error-log')
                this.errorLog = this.normalizeErrorLogPayload(response.data?.data ?? {})
            } catch (error) {
                if (!suppressAlert) {
                    alert(error.response?.data?.message ?? this.$t('admin.dashboardErrorLogLoadFailed'))
                }
            } finally {
                this.errorLogLoading = false
            }
        },

        async loadErrorLogEntries(options = {}) {
            const suppressAlert = Boolean(options?.suppressAlert)
            const rawRequestedPage = Number(options?.page ?? this.errorLogEntriesMeta.current_page ?? 1)
            const requestedPage = Number.isFinite(rawRequestedPage) ? Math.max(1, rawRequestedPage) : 1
            const safePerPage = [10, 20, 50].includes(Number(this.errorLogFilters.per_page))
                ? Number(this.errorLogFilters.per_page)
                : 20

            this.errorLogEntriesLoading = true

            try {
                const response = await axios.get('/api/admin/error-log/entries', {
                    params: {
                        search: String(this.errorLogFilters.search ?? '').trim() || undefined,
                        type: this.errorLogFilters.type || 'all',
                        page: requestedPage,
                        per_page: safePerPage,
                    },
                })

                const normalized = this.normalizeErrorLogEntriesPayload(response.data?.data ?? {})
                this.errorLogEntries = normalized.items
                this.errorLogEntriesMeta = normalized.meta
                this.errorLogFilters.per_page = safePerPage
            } catch (error) {
                if (!suppressAlert) {
                    alert(error.response?.data?.message ?? this.$t('admin.errorLogEntriesLoadFailed'))
                }
            } finally {
                this.errorLogEntriesLoading = false
            }
        },

        async applyErrorLogFilters() {
            await this.loadErrorLogEntries({ page: 1 })
        },

        async refreshErrorLogWorkspace() {
            await Promise.all([
                this.loadErrorLogPreview({ suppressAlert: true }),
                this.loadErrorLogEntries({ page: this.errorLogEntriesMeta.current_page || 1, suppressAlert: true }),
            ])
        },

        async exportFilteredErrorLog() {
            if (this.errorLogFilteredExporting) {
                return
            }

            this.errorLogFilteredExporting = true

            try {
                const params = {
                    type: this.errorLogFilters.type || 'all',
                }

                const search = String(this.errorLogFilters.search ?? '').trim()
                if (search !== '') {
                    params.search = search
                }

                const response = await axios.get('/api/admin/error-log/export', {
                    params,
                    responseType: 'blob',
                })

                const fallbackName = 'site-errors-filtered.log'
                const contentDisposition = response.headers?.['content-disposition'] ?? ''
                const fileName = this.extractDashboardDownloadFileName(contentDisposition, fallbackName)

                this.triggerDashboardDownloadBlob(response.data, fileName)
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.errorLogExportFilteredFailed'))
            } finally {
                this.errorLogFilteredExporting = false
            }
        },

        async resetErrorLogFilters() {
            this.errorLogFilters = {
                search: '',
                type: 'all',
                per_page: 20,
            }

            await this.loadErrorLogEntries({ page: 1 })
        },

        async changeErrorLogPage(page) {
            const nextPage = Math.max(1, Number(page ?? 1))
            if (!Number.isFinite(nextPage)) {
                return
            }

            await this.loadErrorLogEntries({ page: nextPage })
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
                const exportLocale = this.$locale?.value === 'en' ? 'en' : 'ru'

                const response = await axios.get('/api/admin/dashboard/export', {
                    params: {
                        year: yearParam,
                        date_from: range.from,
                        date_to: range.to,
                        format: safeFormat,
                        locale: exportLocale,
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

        async downloadErrorLog() {
            if (this.errorLogDownloading || !this.errorLog.exists) {
                return
            }

            this.errorLogDownloading = true

            try {
                const response = await axios.get('/api/admin/error-log/download', {
                    responseType: 'blob',
                })

                const fallbackName = this.errorLog?.file_name || 'site-errors.log'
                const contentDisposition = response.headers?.['content-disposition'] ?? ''
                const fileName = this.extractDashboardDownloadFileName(contentDisposition, fallbackName)

                this.triggerDashboardDownloadBlob(response.data, fileName)
            } catch (error) {
                alert(error.response?.data?.message ?? this.$t('admin.dashboardErrorLogDownloadFailed'))
            } finally {
                this.errorLogDownloading = false
            }
        },

        errorLogTypeLabel(type) {
            const key = String(type ?? '').trim()
            if (key === 'server_exception') {
                return this.$t('admin.errorLogTypeServer')
            }
            if (key === 'client_error') {
                return this.$t('admin.errorLogTypeClient')
            }
            if (key === 'analytics_failure') {
                return this.$t('admin.errorLogTypeAnalytics')
            }

            return this.$t('admin.errorLogTypeAll')
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

        formatDashboardFileSize(value) {
            const rawSize = Number(value ?? 0)
            const size = Number.isFinite(rawSize) ? Math.max(0, rawSize) : 0
            const units = ['B', 'KB', 'MB', 'GB']

            if (size <= 0) {
                return '0 B'
            }

            const exponent = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1)
            const normalized = size / (1024 ** exponent)
            const digits = exponent === 0 ? 0 : normalized >= 10 ? 1 : 2

            return `${normalized.toFixed(digits)} ${units[exponent]}`
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
