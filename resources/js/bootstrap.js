import _ from 'lodash';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window._ = _;
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = import.meta.env.VITE_XSRF_COOKIE_NAME ?? 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

window.Pusher = Pusher;
window.Echo = null;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY ?? import.meta.env.VITE_PUSHER_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST ?? import.meta.env.VITE_PUSHER_HOST ?? window.location.hostname;
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? import.meta.env.VITE_PUSHER_PORT ?? 6001);
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? import.meta.env.VITE_PUSHER_SCHEME ?? 'http';
const reverbPath = import.meta.env.VITE_REVERB_PATH ?? import.meta.env.VITE_PUSHER_APP_PATH ?? '';
const reverbCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1';

if (typeof reverbKey === 'string' && reverbKey.trim() !== '') {
    try {
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: reverbKey,
            cluster: reverbCluster,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            wsPath: reverbPath,
            forceTLS: reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            authEndpoint: '/api/broadcasting/auth',
            auth: {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });
    } catch (error) {
        console.error('Echo init failed. App will continue without realtime.', error);
        window.Echo = null;
    }
}

window.axios.interceptors.request.use((config) => {
    if (window.Echo && typeof window.Echo.socketId === 'function') {
        const socketId = window.Echo.socketId();

        if (socketId) {
            config.headers = config.headers ?? {};
            config.headers['X-Socket-Id'] = socketId;
        }
    }

    return config;
});
