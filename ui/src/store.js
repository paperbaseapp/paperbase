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
    jobs: {},
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
    setJob(state, job) {
      Vue.set(state.jobs, job.id, job)
    }
  },
  getters: {
    isLoggedIn(state) {
      return state.user !== null
    },
  },
  actions: {
    async updateJobs({commit, state}) {
      const jobsToFetch = []
      for (const job of Object.values(state.jobs)) {
        if (!['failed', 'finished'].includes(job.status)) {
          jobsToFetch.push(job.id)
        }
      }

      if (jobsToFetch.length > 0) {
        const jobs = await axios.$get('/job', {
          params: {
            ids: jobsToFetch,
          },
        })
        for (const job of jobs) {
          commit('setJob', job)
        }
      }
    },
    async startSyncLibraryJob({commit}, libraryId) {
      const job = await axios.$post(`/library/${libraryId}/sync`)
      commit('setJob', job)
      return job
    },
  }
})


// Update job statuses
const scheduleJobsUpdate = () => {
  setTimeout(async () => {
    try {
      await store.dispatch('updateJobs')
    } catch (e) {
      console.error(e)
    }
    scheduleJobsUpdate()
  }, 1000)
}
scheduleJobsUpdate()

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
