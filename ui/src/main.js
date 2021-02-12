import Vue from 'vue'
import App from './app.vue'
import './registerServiceWorker'
import {router} from './router'
import vuetify from './plugins/vuetify';
import 'roboto-fontface/css/roboto/roboto-fontface.css'
import '@mdi/font/css/materialdesignicons.css'
import {store} from './store'
import {isTouchDevice} from '@/lib/isTouchDevice'
import PortalVue from 'portal-vue'

Vue.config.productionTip = false

Vue.prototype.$app = {
    isTouch: isTouchDevice(),
}

Vue.use(PortalVue)

new Vue({
    router,
    vuetify,
    store,
    render: h => h(App)
}).$mount('#app')
