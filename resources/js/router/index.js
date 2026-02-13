import {createRouter, createWebHistory} from 'vue-router'
import {getLocaleFromPath, getPreferredLocale, setLocale} from '../i18n'

let currentUserRequest = null
const HASH_SCROLL_OFFSET = 96
const HASH_SCROLL_BASE_DURATION_MS = 420
const HASH_SCROLL_DURATION_MS = Math.max(140, Math.round(HASH_SCROLL_BASE_DURATION_MS / 2))

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

const localeRoutePath = (path = '/') => {
    const normalized = path === '/' ? '' : path
    return `/:locale(ru|en)?${normalized}`
}

const localizedRedirect = (name, locale) => ({
    name,
    params: {locale},
})

const resolveHashTargetY = (hash, offset = HASH_SCROLL_OFFSET) => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return null
    }

    const rawHash = String(hash || '').trim()
    if (rawHash === '') {
        return null
    }

    const normalized = rawHash.startsWith('#') ? rawHash : `#${rawHash}`
    const selector = (typeof CSS !== 'undefined' && typeof CSS.escape === 'function')
        ? `#${CSS.escape(normalized.slice(1))}`
        : normalized
    const element = document.querySelector(selector)
    if (!element) {
        return null
    }

    const rect = element.getBoundingClientRect()
    const currentY = window.scrollY || window.pageYOffset || 0
    return Math.max(0, Math.round(currentY + rect.top - offset))
}

const animateWindowScrollTo = (targetY, durationMs = HASH_SCROLL_DURATION_MS) => {
    if (typeof window === 'undefined') {
        return
    }

    const startY = window.scrollY || window.pageYOffset || 0
    const distance = targetY - startY
    if (Math.abs(distance) < 1) {
        window.scrollTo({top: targetY, behavior: 'auto'})
        return
    }

    const startedAt = performance.now()
    const easeOutCubic = (value) => 1 - Math.pow(1 - value, 3)

    const tick = (now) => {
        const progress = Math.min(1, (now - startedAt) / durationMs)
        const eased = easeOutCubic(progress)
        const nextY = startY + (distance * eased)
        window.scrollTo({top: Math.round(nextY), behavior: 'auto'})

        if (progress < 1) {
            window.requestAnimationFrame(tick)
        }
    }

    window.requestAnimationFrame(tick)
}

const SEO_CONTENT = {
    ru: {
        default: {
            title: 'Solid Social — социальная сеть с чатами, IPTV и радио',
            description: 'Современная социальная сеть: публикации, realtime-чаты, IPTV, радио и гибкие настройки контента.',
            keywords: 'социальная сеть, чаты онлайн, realtime чат, IPTV, интернет радио, лента публикаций, карусель медиа, личный кабинет, админ панель',
        },
        pages: {
            home: {
                title: 'Solid Social — главная',
                description: 'Главная страница Solid Social: публикации сообщества, карусель медиа, быстрый доступ к чатам и обратной связи.',
                keywords: 'главная, публикации, лента, карусель медиа, обратная связь',
            },
            'user.index': {
                title: 'Пользователи — Solid Social',
                description: 'Ищите пользователей, подписывайтесь и развивайте собственную ленту.',
                keywords: 'пользователи, поиск пользователей, подписки, социальная лента',
            },
            'user.feed': {
                title: 'Лента подписок — Solid Social',
                description: 'Лента публикаций пользователей, на которых вы подписаны.',
                keywords: 'лента подписок, посты, социальная лента',
            },
            'user.personal': {
                title: 'Личный кабинет — Solid Social',
                description: 'Личный кабинет для публикации постов, загрузки медиа и настройки профиля.',
                keywords: 'личный кабинет, профиль, посты, загрузка фото, загрузка видео',
            },
            'chat.index': {
                title: 'Чаты — Solid Social',
                description: 'Realtime-чат: личные и общие диалоги, вложения и онлайн-статусы.',
                keywords: 'чат, realtime, личные сообщения, общий чат, онлайн статус',
            },
            'radio.index': {
                title: 'Радио — Solid Social',
                description: 'Онлайн-радио: поиск станций, прослушивание в браузере и избранные станции.',
                keywords: 'онлайн радио, радиостанции, слушать радио, избранные станции',
            },
            'iptv.index': {
                title: 'IPTV — Solid Social',
                description: 'IPTV-плеер с загрузкой плейлистов, фильтрацией каналов и просмотром в браузере.',
                keywords: 'iptv, iptv плеер, m3u, m3u8, телеканалы онлайн',
            },
            'user.feedback': {
                title: 'Мои обращения — Solid Social',
                description: 'История обращений в администрацию и статусы их обработки.',
                keywords: 'обратная связь, обращения, статус обращения',
            },
            'user.login': {
                title: 'Вход — Solid Social',
                description: 'Войдите в аккаунт Solid Social для доступа к чатам, ленте и личному кабинету.',
                keywords: 'вход, авторизация, аккаунт',
            },
            'user.registration': {
                title: 'Регистрация — Solid Social',
                description: 'Создайте аккаунт в Solid Social для публикаций, чатов и работы с медиа.',
                keywords: 'регистрация, создать аккаунт, социальная сеть',
            },
            'auth.verify': {
                title: 'Подтверждение email — Solid Social',
                description: 'Подтвердите email, чтобы открыть доступ ко всем возможностям платформы.',
                keywords: 'подтверждение email, верификация аккаунта',
            },
            'admin.index': {
                title: 'Админ-панель — Solid Social',
                description: 'Панель администратора: управление пользователями, контентом и настройками сайта.',
                keywords: 'админ панель, модерация, настройки сайта, управление пользователями',
            },
            'user.show': {
                title: 'Профиль пользователя — Solid Social',
                description: 'Просмотр профиля пользователя и его публикаций.',
                keywords: 'профиль пользователя, публикации пользователя',
            },
        },
    },
    en: {
        default: {
            title: 'Solid Social — social network with chats, IPTV and radio',
            description: 'Modern social network with posts, realtime chats, IPTV, radio, and flexible content controls.',
            keywords: 'social network, realtime chat, IPTV player, internet radio, media carousel, user profile, admin panel',
        },
        pages: {
            home: {
                title: 'Solid Social — home',
                description: 'Solid Social home page with community feeds, media carousel, quick chat access and feedback.',
                keywords: 'home page, community feed, media carousel, feedback',
            },
            'user.index': {
                title: 'Users — Solid Social',
                description: 'Find users, follow accounts, and build your own feed.',
                keywords: 'users, user search, following, social feed',
            },
            'user.feed': {
                title: 'Following feed — Solid Social',
                description: 'Feed with publications from users you follow.',
                keywords: 'following feed, posts, social feed',
            },
            'user.personal': {
                title: 'Personal cabinet — Solid Social',
                description: 'Personal cabinet for creating posts, uploading media, and editing profile settings.',
                keywords: 'personal cabinet, profile settings, post publishing, media upload',
            },
            'chat.index': {
                title: 'Chats — Solid Social',
                description: 'Realtime chats with direct and global dialogs, attachments, and online indicators.',
                keywords: 'chat, realtime, direct messages, global chat, online presence',
            },
            'radio.index': {
                title: 'Radio — Solid Social',
                description: 'Internet radio search, in-browser playback, and favorite stations.',
                keywords: 'internet radio, radio stations, listen radio, favorites',
            },
            'iptv.index': {
                title: 'IPTV — Solid Social',
                description: 'IPTV player with playlist import, channel filtering, and browser playback.',
                keywords: 'iptv, iptv player, m3u playlist, m3u8 stream, online tv',
            },
            'user.feedback': {
                title: 'My requests — Solid Social',
                description: 'Request history sent to administration with live processing statuses.',
                keywords: 'feedback, support requests, request status',
            },
            'user.login': {
                title: 'Login — Solid Social',
                description: 'Sign in to Solid Social to access chats, feed, and personal cabinet.',
                keywords: 'login, sign in, account access',
            },
            'user.registration': {
                title: 'Sign up — Solid Social',
                description: 'Create your Solid Social account for posting, chatting, and media sharing.',
                keywords: 'registration, sign up, create account',
            },
            'auth.verify': {
                title: 'Email verification — Solid Social',
                description: 'Verify your email to unlock all platform features.',
                keywords: 'email verification, account verification',
            },
            'admin.index': {
                title: 'Admin panel — Solid Social',
                description: 'Admin dashboard for managing users, moderation, and site settings.',
                keywords: 'admin panel, moderation, site settings, user management',
            },
            'user.show': {
                title: 'User profile — Solid Social',
                description: 'View user profile and published posts.',
                keywords: 'user profile, user posts',
            },
        },
    },
}

const normalizeSeoLocale = (value) => value === 'en' ? 'en' : 'ru'

const ensureMetaByName = (name) => {
    if (typeof document === 'undefined' || !name) {
        return null
    }

    let element = document.head.querySelector(`meta[name="${name}"]`)
    if (!element) {
        element = document.createElement('meta')
        element.setAttribute('name', name)
        document.head.appendChild(element)
    }

    return element
}

const ensureMetaByProperty = (property) => {
    if (typeof document === 'undefined' || !property) {
        return null
    }

    let element = document.head.querySelector(`meta[property="${property}"]`)
    if (!element) {
        element = document.createElement('meta')
        element.setAttribute('property', property)
        document.head.appendChild(element)
    }

    return element
}

const ensureLinkElement = (selector, attrs) => {
    if (typeof document === 'undefined') {
        return null
    }

    let element = document.head.querySelector(selector)
    if (!element) {
        element = document.createElement('link')
        Object.entries(attrs || {}).forEach(([key, value]) => {
            element.setAttribute(key, value)
        })
        document.head.appendChild(element)
    }

    return element
}

const resolveSeoMeta = (route, locale) => {
    const normalizedLocale = normalizeSeoLocale(locale)
    const localeSeo = SEO_CONTENT[normalizedLocale] ?? SEO_CONTENT.ru
    const routeName = String(route?.name || '')
    const routeSeo = localeSeo.pages?.[routeName] ?? {}

    return {
        title: routeSeo.title || localeSeo.default.title,
        description: routeSeo.description || localeSeo.default.description,
        keywords: routeSeo.keywords || localeSeo.default.keywords,
    }
}

const stripLocalePrefix = (path) => {
    const value = String(path || '').trim()
    const normalized = value === '' ? '/' : value
    const withoutLocale = normalized.replace(/^\/(ru|en)(?=\/|$)/i, '')
    return withoutLocale === '' ? '/' : withoutLocale
}

const buildLocalizedPath = (locale, basePath) => {
    const normalizedLocale = normalizeSeoLocale(locale)
    const normalizedBasePath = String(basePath || '/')
    const suffix = normalizedBasePath === '/' ? '' : normalizedBasePath
    return `/${normalizedLocale}${suffix}`
}

const applySeoMeta = (to) => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return
    }

    const locale = normalizeSeoLocale(to?.params?.locale)
    const seo = resolveSeoMeta(to, locale)
    const isPrivatePage = Boolean(to?.meta?.requiresAuth || to?.meta?.requiresAdmin)
    const robotsValue = isPrivatePage
        ? 'noindex, nofollow, noarchive'
        : 'index, follow, max-image-preview:large'
    const origin = window.location.origin
    const basePath = stripLocalePrefix(to?.path || window.location.pathname)
    const canonicalPath = buildLocalizedPath(locale, basePath)
    const canonicalUrl = `${origin}${canonicalPath}`

    const descriptionMeta = ensureMetaByName('description')
    const keywordsMeta = ensureMetaByName('keywords')
    const robotsMeta = ensureMetaByName('robots')
    const twitterTitleMeta = ensureMetaByName('twitter:title')
    const twitterDescriptionMeta = ensureMetaByName('twitter:description')
    const ogTitleMeta = ensureMetaByProperty('og:title')
    const ogDescriptionMeta = ensureMetaByProperty('og:description')
    const ogUrlMeta = ensureMetaByProperty('og:url')
    const ogLocaleMeta = ensureMetaByProperty('og:locale')

    if (descriptionMeta) {
        descriptionMeta.setAttribute('content', seo.description)
    }
    if (keywordsMeta) {
        keywordsMeta.setAttribute('content', seo.keywords)
    }
    if (robotsMeta) {
        robotsMeta.setAttribute('content', robotsValue)
    }
    if (twitterTitleMeta) {
        twitterTitleMeta.setAttribute('content', seo.title)
    }
    if (twitterDescriptionMeta) {
        twitterDescriptionMeta.setAttribute('content', seo.description)
    }
    if (ogTitleMeta) {
        ogTitleMeta.setAttribute('content', seo.title)
    }
    if (ogDescriptionMeta) {
        ogDescriptionMeta.setAttribute('content', seo.description)
    }
    if (ogUrlMeta) {
        ogUrlMeta.setAttribute('content', canonicalUrl)
    }
    if (ogLocaleMeta) {
        ogLocaleMeta.setAttribute('content', locale === 'en' ? 'en_US' : 'ru_RU')
    }

    const canonicalLink = ensureLinkElement('link[rel="canonical"]', {rel: 'canonical'})
    if (canonicalLink) {
        canonicalLink.setAttribute('href', canonicalUrl)
    }

    const ruPath = buildLocalizedPath('ru', basePath)
    const enPath = buildLocalizedPath('en', basePath)

    const ruAltLink = ensureLinkElement('link[rel="alternate"][hreflang="ru"]', {rel: 'alternate', hreflang: 'ru'})
    const enAltLink = ensureLinkElement('link[rel="alternate"][hreflang="en"]', {rel: 'alternate', hreflang: 'en'})
    const defaultAltLink = ensureLinkElement('link[rel="alternate"][hreflang="x-default"]', {rel: 'alternate', hreflang: 'x-default'})

    if (ruAltLink) {
        ruAltLink.setAttribute('href', `${origin}${ruPath}`)
    }
    if (enAltLink) {
        enAltLink.setAttribute('href', `${origin}${enPath}`)
    }
    if (defaultAltLink) {
        defaultAltLink.setAttribute('href', `${origin}${ruPath}`)
    }

    document.title = seo.title
}

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition
        }

        if (to.hash) {
            const targetY = resolveHashTargetY(to.hash, HASH_SCROLL_OFFSET)
            if (targetY !== null) {
                animateWindowScrollTo(targetY, HASH_SCROLL_DURATION_MS)
                return false
            }

            return {
                el: to.hash,
                top: HASH_SCROLL_OFFSET,
                behavior: 'smooth',
            }
        }

        return {top: 0}
    },
    routes: [
        {
            path: localeRoutePath('/'),
            component: () => import('../views/Home.vue'),
            name: 'home',
            meta: {public: true}
        },
        {
            path: localeRoutePath('/users/index'),
            component: () => import('../views/user/Index.vue'),
            name: 'user.index',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/users/:id/show'),
            component: () => import('../views/user/Show.vue'),
            name: 'user.show',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/users/feed'),
            component: () => import('../views/user/Feed.vue'),
            name: 'user.feed',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/users/login'),
            component: () => import('../views/user/Login.vue'),
            name: 'user.login',
            meta: {public: true, guestOnly: true}
        },
        {
            path: localeRoutePath('/users/registration'),
            component: () => import('../views/user/Registration.vue'),
            name: 'user.registration',
            meta: {public: true, guestOnly: true}
        },
        {
            path: localeRoutePath('/email/verify'),
            component: () => import('../views/user/EmailVerification.vue'),
            name: 'auth.verify',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/users/personal'),
            component: () => import('../views/user/Personal.vue'),
            name: 'user.personal',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/users/feedback'),
            component: () => import('../views/user/FeedbackHistory.vue'),
            name: 'user.feedback',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/chats'),
            component: () => import('../views/user/Chats.vue'),
            name: 'chat.index',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/radio'),
            component: () => import('../views/user/Radio.vue'),
            name: 'radio.index',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/iptv'),
            component: () => import('../views/user/Iptv.vue'),
            name: 'iptv.index',
            meta: {requiresAuth: true}
        },
        {
            path: localeRoutePath('/admin'),
            component: () => import('../views/user/Admin.vue'),
            name: 'admin.index',
            meta: {requiresAuth: true, requiresAdmin: true}
        },
    ]
})

router.beforeEach(async (to) => {
    const preferredLocale = getPreferredLocale()
    const locale = getLocaleFromPath(to.path)

    if (!locale) {
        const basePath = to.path === '/' ? '' : to.path
        return {
            path: `/${preferredLocale}${basePath}`,
            query: to.query,
            hash: to.hash,
            replace: true,
        }
    }

    setLocale(locale)

    const user = await fetchCurrentUser()

    const isAuthenticated = Boolean(user)
    const isEmailVerified = Boolean(user?.email_verified_at)

    if (to.meta.requiresAuth && !isAuthenticated) {
        return localizedRedirect('user.login', locale)
    }

    if (to.meta.guestOnly && isAuthenticated) {
        return localizedRedirect(isEmailVerified ? 'user.personal' : 'auth.verify', locale)
    }

    if (to.meta.requiresAuth && isAuthenticated && !isEmailVerified && to.name !== 'auth.verify') {
        return localizedRedirect('auth.verify', locale)
    }

    if (to.name === 'auth.verify' && isAuthenticated && isEmailVerified) {
        return localizedRedirect('user.personal', locale)
    }

    if (to.meta.requiresAdmin && (!user || !user.is_admin)) {
        return localizedRedirect(isEmailVerified ? 'user.personal' : 'auth.verify', locale)
    }

    return true
})

router.afterEach((to) => {
    applySeoMeta(to)
})

export default router
