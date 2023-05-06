import {createRouter, createWebHistory} from 'vue-router'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/users/index', component: () => import('../views/user/Index.vue'),
            name: 'user.index'
        },
        {
            path: '/users/:id/show', component: () => import('../views/user/Show.vue'),
            name: 'user.show'
        },
        {
            path: '/users/feed', component: () => import('../views/user/Feed.vue'),
            name: 'user.feed'
        },
        {
            path: '/users/login', component: () => import('../views/user/Login.vue'),
            name: 'user.login'
        },
        {
            path: '/users/registration', component: () => import('../views/user/Registration.vue'),
            name: 'user.registration'
        },
        {
            path: '/users/personal', component: () => import('../views/user/Personal.vue'),
            name: 'user.personal'
        },
    ]
})


router.beforeEach((to, from, next) => {

    axios.get('/api/user')
        .then( res => {
            console.log(res.data);
        })
        .catch(e => {
            if (e.response.status === 401) {
                localStorage.key('x_xsrf_token') ? localStorage.removeItem('x_xsrf_token') : ''
            }
        })

    const token = localStorage.getItem('x_xsrf_token')

    if (!token) {
        if (to.name === 'user.login' || to.name === 'user.registration') {
            return next()
        } else {
            return next({
                name: 'user.login'
            })
        }
    }

    if (to.name === 'user.login' || to.name === 'user.registration' && token) {
        return next({
            name: 'user.personal'
        })
    }

    next()

})

export default router
