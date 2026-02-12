import {createRouter, createWebHistory} from 'vue-router'

let currentUserRequest = null

const fetchCurrentUser = async () => {
    if (currentUserRequest) {
        return currentUserRequest
    }

    currentUserRequest = axios.get('/api/user')
        .then((response) => response.data)
        .catch(() => null)
        .finally(() => {
            currentUserRequest = null
        })

    return currentUserRequest
}

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: () => import('../views/Home.vue'),
            name: 'home',
            meta: {public: true}
        },
        {
            path: '/users/index',
            component: () => import('../views/user/Index.vue'),
            name: 'user.index',
            meta: {requiresAuth: true}
        },
        {
            path: '/users/:id/show',
            component: () => import('../views/user/Show.vue'),
            name: 'user.show',
            meta: {requiresAuth: true}
        },
        {
            path: '/users/feed',
            component: () => import('../views/user/Feed.vue'),
            name: 'user.feed',
            meta: {requiresAuth: true}
        },
        {
            path: '/users/login',
            component: () => import('../views/user/Login.vue'),
            name: 'user.login',
            meta: {public: true, guestOnly: true}
        },
        {
            path: '/users/registration',
            component: () => import('../views/user/Registration.vue'),
            name: 'user.registration',
            meta: {public: true, guestOnly: true}
        },
        {
            path: '/email/verify',
            component: () => import('../views/user/EmailVerification.vue'),
            name: 'auth.verify',
            meta: {requiresAuth: true}
        },
        {
            path: '/users/personal',
            component: () => import('../views/user/Personal.vue'),
            name: 'user.personal',
            meta: {requiresAuth: true}
        },
        {
            path: '/users/feedback',
            component: () => import('../views/user/FeedbackHistory.vue'),
            name: 'user.feedback',
            meta: {requiresAuth: true}
        },
        {
            path: '/chats',
            component: () => import('../views/user/Chats.vue'),
            name: 'chat.index',
            meta: {requiresAuth: true}
        },
        {
            path: '/radio',
            component: () => import('../views/user/Radio.vue'),
            name: 'radio.index',
            meta: {requiresAuth: true}
        },
        {
            path: '/iptv',
            component: () => import('../views/user/Iptv.vue'),
            name: 'iptv.index',
            meta: {requiresAuth: true}
        },
        {
            path: '/admin',
            component: () => import('../views/user/Admin.vue'),
            name: 'admin.index',
            meta: {requiresAuth: true, requiresAdmin: true}
        },
    ]
})

router.beforeEach(async (to) => {
    const user = await fetchCurrentUser()

    const isAuthenticated = Boolean(user)
    const isEmailVerified = Boolean(user?.email_verified_at)

    if (to.meta.requiresAuth && !isAuthenticated) {
        return {name: 'user.login'}
    }

    if (to.meta.guestOnly && isAuthenticated) {
        return {name: isEmailVerified ? 'user.personal' : 'auth.verify'}
    }

    if (to.meta.requiresAuth && isAuthenticated && !isEmailVerified && to.name !== 'auth.verify') {
        return {name: 'auth.verify'}
    }

    if (to.name === 'auth.verify' && isAuthenticated && isEmailVerified) {
        return {name: 'user.personal'}
    }

    if (to.meta.requiresAdmin && (!user || !user.is_admin)) {
        return {name: isEmailVerified ? 'user.personal' : 'auth.verify'}
    }

    return true
})

export default router
