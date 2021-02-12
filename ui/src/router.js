import Vue from 'vue'
import VueRouter from 'vue-router'
import {store} from './store'

Vue.use(VueRouter)

const routes = [
  {
    path: '/',
    name: 'dashboard',
    meta: {title: 'Dashboard'},
    component: require('./pages/dashboard-page').default,
  },
  {
    path: '/login',
    name: 'login',
    component: require('./pages/login-page').default,
    meta: {disableGuard: true},
  },

  // Catchall route
  {
    path: '*',
    redirect: {name: 'dashboard'},
    meta: {catchall: true},
  },
]

const router = new VueRouter({
  mode: 'history',
  base: process.env.BASE_URL,
  routes,
})

/**
 * The route guard.
 * Checks for login state and permission restrictions
 */
router.beforeEach((to, from, next) => {
  // Ignore the guard when the according
  // meta tag is set:
  if (to.matched.some(route => !route.meta.disableGuard)) {
    if (!store.getters.isLoggedIn) {
      store.commit('setRedirectRoute', to)
      next({name: 'login'})
      return
    }

    next()
  }

  next()
})

export {router}
