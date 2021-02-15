<template>
  <v-container>
    <portal to="app-bar-end">
      <v-select
        v-model="selectedLibraryId"
        placeholder="Select Library"
        solo
        flat
        hide-details
        :items="librariesSelect"
        item-text="name"
        item-value="id"
      >
        <template v-slot:item="{ item }">
          <v-icon v-if="item.id === 'new'" left>mdi-plus</v-icon>
          <span style="line-height: 1">{{ item.name }}</span>
        </template>
      </v-select>
    </portal>

    <v-dialog v-model="newLibraryDialogOpen" transition="slide-y-reverse-transition" max-width="600">
      <v-card>
        <v-card-title>New Library</v-card-title>
        <v-card-text>
          <v-text-field v-model="newLibraryName" label="Name" autofocus/>
        </v-card-text>
        <v-card-actions>
          <v-spacer/>
          <v-btn text color="primary" @click="createLibrary">
            Create
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <library-search-bar :library-id="selectedLibraryId" />

    <library-view v-if="!!realSelectedLibraryId" :library-id="realSelectedLibraryId" />
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
    selectedLibraryId: vm.$route.params.libraryId,
    realSelectedLibraryId: vm.$route.params.libraryId,
  }),
  watch: {
    selectedLibraryId(value) {
      if (value === 'new') {
        this.newLibraryDialogOpen = true
      } else {
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
        {name: 'New Library', id: 'new'}
      ])
    }
  },
  async mounted() {
    await this.updateLibraries()
  },
  methods: {
    async createLibrary() {
      try {
        await axios.$post('/library', {
          name: this.newLibraryName,
        })
        await this.updateLibraries()
        this.newLibraryDialogOpen = false
      } catch (e) {
        console.error(e)
        if (e.response.data === 402) {
          this.error = ['Please pick another name']
        }
      }
    },
    async updateLibraries() {
      this.libraries = await axios.$get('/library')
    }
  }
}
</script>

<style scoped>

</style>
