import Vue from 'vue'
import Vuex from 'vuex'
import {axios} from './lib/axios'
import {router} from './router'

Vue.use(Vuex)

export const store = new Vuex.Store({
  state: {
    loggingIn: false,
    user: null,
    redirectRoute: null,
    modelCache: {},
  },
  mutations: {
    setUser(state, user) {
      state.user = user
    },
    setLoggingIn(state, loggingIn) {
      state.loggingIn = loggingIn
    },
    setRedirectRoute(state, redirectRoute) {
      state.redirectRoute = redirectRoute
    },
  },
  getters: {
    isLoggedIn(state) {
      return state.user !== null
    },
  },
})

store.commit('setLoggingIn', true)

;(async () => {
  try {
    store.commit('setUser', await axios.$get('/auth/user'))
  } catch (e) {
    store.commit('setUser', null)

    if (router.currentRoute.name !== 'login') {
      store.commit('setRedirectRoute', router.currentRoute)
    }

    console.error(e)
  }

  store.commit('setLoggingIn', false)
})()
