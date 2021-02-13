<template>
  <div v-if="!!library">
    <h1>{{ library.name }}</h1>

    <v-alert v-if="library.needs_sync" dark icon="mdi-alert-outline" color="error">
      <div>Your library needs to be synced!</div>
      <v-btn v-if="!syncing" class="mt-1" outlined @click="syncLibrary">
        <v-icon left>mdi-sync</v-icon>
        Sync now
      </v-btn>
      <v-progress-linear v-else color="white" :indeterminate="syncProgress === null || syncProgress === 0" :value="syncProgress || 0" />
    </v-alert>
  </div>
  <throttled-spinner-container v-else />
</template>

<script>
  import ThrottledSpinnerContainer from '@/components/throttled-spinner-container'
  import {axios} from '@/lib/axios'

  export default {
    name: 'library-view',
    components: {ThrottledSpinnerContainer},
    props: {
      libraryId: String,
    },
    data: () => ({
      library: null,
      syncing: false,
      syncIndeterminate: true,
      syncJobId: null,
    }),
    computed: {
      syncJob() {
        return this.$store.state.jobs[this.syncJobId] ?? null
      },
      syncProgress() {
        if (this.syncJobId === null) {
          return null
        }

        return (this.syncJob.progress_now / (this.syncJob.progress_max || 1)) * 100
      },
    },
    watch: {
      libraryId: {
        immediate: true,
        handler() {
          this.updateLibrary()
        }
      },
      syncJob(job) {
        if (job.status === 'finished') {
          this.updateLibrary()
        }
      }
    },
    methods: {
      async updateLibrary() {
        this.library = await axios.$get(`/library/${this.libraryId}`)
      },
      async syncLibrary() {
        this.syncing = true
        const job = await this.$store.dispatch('startSyncLibraryJob', this.library.id)
        this.syncJobId = job.id
      }
    }
  }
</script>

<style scoped>

</style>
