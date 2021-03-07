<template>
  <v-container>
    <portal to="app-bar-end">
      <v-select
        v-model="selectedLibraryId"
        :items="librariesSelect"
        flat
        hide-details
        item-text="name"
        item-value="id"
        placeholder="Select Library"
        background-color="secondary"
        solo
      >
        <template v-slot:item="{ item }">
          <v-icon v-if="item.id === 'new'" left>mdi-plus</v-icon>
          <span style="line-height: 1">{{ item.name }}</span>
        </template>
      </v-select>
    </portal>

    <v-dialog v-model="newLibraryDialogOpen" max-width="600" transition="slide-y-reverse-transition">
      <v-card>
        <v-card-title>New Library</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="newLibraryName"
            :error-messages="newLibraryErrorMessages"
            autofocus
            label="Name"
            @keydown.enter="createLibrary"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="primary" text @click="createLibrary">
            Create
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <template v-if="!!realSelectedLibraryId">
      <library-search-bar class="mt-5" :library-id="realSelectedLibraryId" />
      <library-view :library-id="realSelectedLibraryId" />
    </template>
  </v-container>
</template>

<script>
  import {axios} from '@/lib/axios'
  import LibraryView from '@/components/library-view'
  import LibrarySearchBar from '@/components/library-search-bar'

  export default {
    name: 'browse-page',
    components: {LibrarySearchBar, LibraryView},
    data: vm => ({
      newLibraryDialogOpen: false,
      newLibraryName: '',
      libraries: [],
      newLibraryErrorMessages: [],
      selectedLibraryId: vm.$route.params.libraryId,
      realSelectedLibraryId: vm.$route.params.libraryId,
    }),
    watch: {
      selectedLibraryId(value, oldValue) {
        if (value === 'new') {
          this.newLibraryDialogOpen = true
          this.$nextTick(() => this.selectedLibraryId = oldValue)
        } else if (this.realSelectedLibraryId !== value) {
          this.$router.push({params: {libraryId: value}})
        }
      },
      '$route.params.libraryId'(value) {
        this.realSelectedLibraryId = value
      },
    },
    computed: {
      librariesSelect() {
        return this.libraries.concat([
          {name: 'New Library', id: 'new'},
        ])
      },
    },
    async mounted() {
      await this.updateLibraries()
    },
    methods: {
      async createLibrary() {
        try {
          const library = await axios.$post('/library', {
            name: this.newLibraryName,
          })
          this.selectedLibraryId = library.id
          await this.updateLibraries()
          this.newLibraryDialogOpen = false
          this.newLibraryName = ''
          this.newLibraryErrorMessages = []
        } catch (e) {
          console.error(e)
          if (e.response?.status === 422) {
            this.newLibraryErrorMessages = ['There is already a library with that name']
          } else {
            this.newLibraryErrorMessages = ['An error occurred']
          }
        }
      },
      async updateLibraries() {
        this.libraries = await axios.$get('/library')
      },
    },
  }
</script>

<style scoped>

</style>
