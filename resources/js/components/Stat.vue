<template>
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Подписчики</span>
            <div class="stat-value">{{ stats.subscribers_count ?? 0 }}</div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Лайки на постах</span>
            <div class="stat-value">{{ stats.likes_count ?? 0 }}</div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Посты</span>
            <div class="stat-value">{{ stats.posts_count ?? 0 }}</div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Подписки</span>
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
