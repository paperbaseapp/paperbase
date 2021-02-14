<template>
  <v-container>
    <portal to="app-bar-end">
      <v-select
        v-model="selectedLibrary"
        placeholder="Select Library"
        solo
        flat
        hide-details
        return-object
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

    <library-view v-if="!!selectedLibrary" :library-id="selectedLibrary.id" />
  </v-container>
</template>

<script>
import {axios} from '@/lib/axios'
import LibraryView from '@/components/library-view'

export default {
  name: 'browse-page',
  components: {LibraryView},
  data: () => ({
    newLibraryDialogOpen: false,
    newLibraryName: '',
    libraries: [],
    selectedLibrary: null,
  }),
  watch: {
    selectedLibrary(value, oldValue) {
      if (value.id === 'new') {
        this.$nextTick(() => this.selectedLibrary = oldValue)
        this.newLibraryDialogOpen = true
      }
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
    if (this.libraries.length > 0) {
      this.selectedLibrary = this.libraries[0]
    }
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
