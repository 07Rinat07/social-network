import './bootstrap';
import {createApp} from 'vue'
import App from "./App.vue";
import router from "./router";
import i18n from './i18n'
import { installClientErrorReporter } from './utils/siteErrorReporter'

const app = createApp(App)
app.use(i18n)
app.use(router)
installClientErrorReporter({
    app,
    router,
    axios: window.axios,
})
app.mount('#app')
