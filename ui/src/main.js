import Vue from 'vue'
import App from './app.vue'
import './registerServiceWorker'
import {router} from './router'
import vuetify from './plugins/vuetify'
import 'roboto-fontface/css/roboto/roboto-fontface.css'
import '@mdi/font/css/materialdesignicons.css'
import {store} from './store'
import {isTouchDevice} from '@/lib/isTouchDevice'
import PortalVue from 'portal-vue'
import './style/global.scss'

import 'overlayscrollbars/overlayscrollbars.css';
import { OverlayScrollbars } from 'overlayscrollbars';

Vue.config.productionTip = false

Vue.prototype.$app = {
  isTouch: isTouchDevice(),
}

Vue.use(PortalVue)

new Vue({
  router,
  vuetify,
  store,
  mounted() {
    const scrollbar = OverlayScrollbars(document.querySelectorAll('body'), {
      nativeScrollbarsOverlaid: {
        initialize: false,
      }
    })
    const htmlObserver = new MutationObserver(() => {
      scrollbar.options('overflowBehavior.y',
        document.documentElement.classList.contains('overflow-y-hidden')
          ? 'hidden'
          : 'scroll',
      )
    })
    htmlObserver.observe(document.documentElement, {attributes: true, attributeFilter: ['style', 'class']})
  },
  render: h => h(App),
}).$mount('#app')
