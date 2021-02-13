<template>
  <v-container>
    <portal to="app-bar-end">
      <div class="d-flex align-center">
        <v-select
          v-model="selectedLibrary"
          placeholder="Select Library"
          solo
          flat
          hide-details
          :items="[
            {text: 'New Library', value: 'new'}
          ]"
        >
          <template v-slot:item="{ item }">
            <v-icon v-if="item.value === 'new'" left>mdi-plus</v-icon>
            <span style="line-height: 1">{{ item.text }}</span>
          </template>
        </v-select>
      </div>
    </portal>

    <v-dialog v-model="newLibraryDialogOpen" transition="slide-y-reverse-transition" max-width="600">
      <v-card>
        <v-card-title>New Library</v-card-title>
        <v-card-text>
          <v-text-field  v-model="newLibraryName" label="Name" autofocus />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text color="primary" @click="createLibrary">
            Create
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
  import {axios} from '@/lib/axios'

  export default {
    name: 'dashboard-page',
    data: () => ({
      selectedLibrary: null,
      newLibraryDialogOpen: false,
      newLibraryName: '',
    }),
    watch: {
      selectedLibrary(value) {
        if (value === 'new') {
          this.$nextTick(() => this.selectedLibrary = null)
          this.newLibraryDialogOpen = true
        }
      },
    },
    methods: {
      async createLibrary() {
        try {
          await axios.$post('/library', {
            name: this.newLibraryName,
          })
        } catch (e) {
          console.error(e)
        }
      }
    }
  }
</script>

<style scoped>

</style>
