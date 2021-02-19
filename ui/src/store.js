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
    batches: {},
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
      const oldJob = state.jobs[job.id] ?? null

      if (job.status === 'finished' && (!oldJob || oldJob.status !== 'finished')) {
        switch (job.type) {
          case 'App\\Jobs\\SyncLibraryJob':
            if (job.output?.thumbnail_batch) {
              Vue.set(state.batches, job.output.thumbnail_batch.id, job.output.thumbnail_batch)
            }

            if (job.output?.ocr_batch) {
              Vue.set(state.batches, job.output.ocr_batch.id, job.output.ocr_batch)
            }
        }
      }

      Vue.set(state.jobs, job.id, job)
    },
    setBatch(state, batch) {
      Vue.set(state.batches, batch.id, batch)
    }
  },
  getters: {
    isLoggedIn(state) {
      return state.user !== null
    },
    globalBatchProgress(state) {
      const batches = Object.values(state.batches)
      if (batches.length === 0) {
        return null
      }

      return batches.reduce((acc, batch) => acc + batch.processedJobs + batch.failedJobs, 0) /
        batches.reduce((acc, batch) => acc + batch.totalJobs, 0) * 100
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
    async updateBatches({commit, state}) {
      const batchesToFetch = []
      for (const batch of Object.values(state.batches)) {
        if (batch.pendingJobs - batch.failedJobs !== 0) {
          batchesToFetch.push(batch.id)
        }
      }

      if (batchesToFetch.length > 0) {
        const batches = await axios.$get('/job/batch', {
          params: {
            ids: batchesToFetch,
          },
        })
        for (const batch of batches) {
          commit('setBatch', batch)
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
      await Promise.all([
        store.dispatch('updateJobs'),
        store.dispatch('updateBatches'),
      ])
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
