<template>
  <div>
    <div class="caption mb-2">Location: {{ library.name }} / {{ currentPath.replaceAll('/', ' / ') }}</div>
    <v-card class="overflow-hidden">
      <div class="d-flex px-3 pt-3">
        <v-btn :disabled="parentPath === null" @click="navigateToPath(parentPath)" depressed>
          <v-icon>mdi-arrow-up</v-icon>
        </v-btn>
        <v-spacer />
        <v-btn-toggle dense class="ma-0" mandatory v-model="displayMode">
          <v-btn value="grid">
            <v-icon>mdi-view-module-outline</v-icon>
          </v-btn>
          <v-btn value="list">
            <v-icon>mdi-view-list-outline</v-icon>
          </v-btn>
        </v-btn-toggle>
      </div>

      <v-scroll-x-transition leave-absolute>
        <v-data-table
          v-if="displayMode === 'list'"
          :loading="throttledLoading"
          :headers="[
            {value: 'type', width: 1, filterable: false, sortable: false},
            {text: 'Name', value: 'basename'},
            {text: 'Size', value: 'size'},
          ]"
          :options="paginationOptions"
          :items="items"
          @click:row="navigateToItem"
          :item-class="() => 'hand-cursor'"
          class="full-width"
        >
          <template v-slot:item.type="{item}">
            <v-icon v-text="getItemIcon(item)"></v-icon>
          </template>
          <template v-slot:item.size="{item}">
            <template v-if="item.type === 'file'">
              {{ formatBytes(item.size) }}
            </template>
          </template>
        </v-data-table>
        <v-data-iterator
          v-else
          :loading="throttledLoading"
          :options="paginationOptions"
          :items="items"
          class="full-width"
        >
          <template v-slot:no-data>
            <v-fade-transition>
              <div class="pa-3 text-center title">
                No files
              </div>
            </v-fade-transition>
          </template>

          <template v-slot:default="{items}">
            <transition :name="lastNavigation === 'up' ? 'scroll-y-reverse-transition' : 'scroll-y-transition'" mode="out-in">
              <div class="pa-3" v-if="!loading">
                <div class="explorer-grid">
                  <library-node-view v-for="item in items" :node="item" :key="item.path" @click="navigateToItem(item)" />
                </div>
              </div>
            </transition>
          </template>
        </v-data-iterator>
      </v-scroll-x-transition>
    </v-card>
  </div>
</template>

<script>
  import {axios} from '@/lib/axios'
  import {formatsBytes} from '@/lib/mixins/formatsBytes'
  import LibraryNodeView from '@/components/library-node-view'

  export default {
    name: 'library-explorer-view',
    components: {LibraryNodeView},
    mixins: [formatsBytes],
    props: {
      library: Object,
    },
    data: vm => ({
      currentPath: vm.$route.query.path ?? '',
      items: [],
      parentPath: null,
      loading: false,
      throttledLoading: false,
      throttledLoadingTimeoutId: null,
      displayMode: 'grid',
      lastNavigation: 'down', // or up
      paginationOptions: {
        page: 1,
        itemsPerPage: -1,
      },
    }),
    watch: {
      '$route.query.path'(path) {
        this.currentPath = path ?? ''
        this.$nextTick(() => this.fetch())
      },
      currentPath(value, oldValue) {
        this.lastNavigation = oldValue.startsWith(value)
          ? 'down'
          : 'up'
      },
      loading(value) {
        if (!value && this.throttledLoadingTimeoutId !== null) {
          clearTimeout(this.throttledLoadingTimeoutId)
          this.throttledLoadingTimeoutId = null
          this.throttledLoading = false
        } else if (value) {
          this.throttledLoadingTimeoutId = setTimeout(() => {
            this.throttledLoading = true
          }, 750)
        }
      },
    },
    mounted() {
      this.fetch()
    },
    methods: {
      async fetch() {
        this.loading = true

        try {
          const data = await axios.$get(`/library/${this.library.id}/browse`, {
            params: {
              path: this.currentPath,
            }
          })
          this.items = data.items
          this.parentPath = data.parent_path
        } catch (e) {
          console.error(e)
        }

        this.loading = false
      },
      getItemIcon(item) {
        if (item.type === 'directory') {
          return 'mdi-folder-outline'
        } else {
          return 'mdi-file-outline'
        }
      },
      navigateToPath(path) {
        this.$router.push({query: {path}})
      },
      navigateToItem(item) {
        if (item.type === 'directory') {
          this.navigateToPath(item.path)
        }

        // TODO: Handle files
      },
    },
  }
</script>

<style lang="scss" scoped>
  .explorer-grid {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(164px, 100%), 1fr));
    grid-gap: 8px;
  }

  .full-width {
    width: 100%;
  }
</style>