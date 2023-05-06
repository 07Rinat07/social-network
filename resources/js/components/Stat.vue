<template>
    <div class="flex justify-between mb-8 text-sm text-center">
        <div>
            Subscribers
            <div>
                {{ stats.subscribers_count}}
            </div>
        </div>
        <div>
            Likes
            <div>
                {{ stats.likes_count}}
            </div>
        </div>
        <div>
            Posts
            <div>
                {{ stats.posts_count}}
            </div>
        </div>
        <div>
            Follows
            <div>
                {{ stats.followings_count}}
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "Stat",

    props: [
        'stats'
    ]
}
</script>

<style scoped>

</style>
