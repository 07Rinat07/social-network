<template>
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">{{ $t('stats.subscribers') }}</span>
            <div class="stat-value">{{ stats.subscribers_count ?? 0 }}</div>
        </div>
        <div class="stat-card">
            <span class="stat-label">{{ $t('stats.likes') }}</span>
            <div class="stat-value">{{ stats.likes_count ?? 0 }}</div>
        </div>
        <div class="stat-card">
            <span class="stat-label">{{ $t('stats.posts') }}</span>
            <div class="stat-value">{{ stats.posts_count ?? 0 }}</div>
        </div>
        <div class="stat-card">
            <span class="stat-label">{{ $t('stats.followings') }}</span>
            <div class="stat-value">{{ stats.followings_count ?? 0 }}</div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Stat',
    props: ['stats']
}
</script>
