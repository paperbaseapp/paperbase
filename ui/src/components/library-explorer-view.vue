<template>
  <div>
    <v-breadcrumbs class="px-0" :items="locationBreadcrumbs" />

    <v-card class="overflow-hidden">
      <div class="d-flex px-3 pt-3">
        <v-btn :disabled="parentPath === null" @click="navigateToPath(parentPath)" depressed>
          <v-icon>mdi-arrow-up</v-icon>
        </v-btn>
        <v-spacer/>

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
          :footer-props="{
            'items-per-page-options': [30,50,100,-1]
          }"
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
          :footer-props="{
            'items-per-page-options': [30,50,100,-1]
          }"
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
            <transition
              :name="lastNavigation === 'up' ? 'scroll-y-reverse-transition' : 'scroll-y-transition'"
              mode="out-in"
            >
              <div class="pa-3" v-if="!loading">
                <div class="explorer-grid">
                  <library-node-view
                    v-for="item in items"
                    :node="item"
                    :key="item.path"
                    @click="navigateToItem(item)"
                  />
                </div>
              </div>
            </transition>
          </template>
        </v-data-iterator>
      </v-scroll-x-transition>
    </v-card>

    <v-dialog v-model="documentViewerDialogOpen" fullscreen transition="dialog-bottom-transition">
      <div v-if="!!documentViewerNode" class="fill-height d-flex flex-column">
        <v-toolbar dense class="flex-grow-0">
          <v-toolbar-title>{{ documentViewerNodeDisplayName }}</v-toolbar-title>
          <v-spacer />
          <v-btn icon @click="documentViewerDialogOpen = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-toolbar>
        <document-viewer
          class="flex-grow-1"
          :library-id="library.id"
          :node="documentViewerNode"
          :loading="loading"
          :page="documentViewerPage"
          @delete="deleteNode(documentViewerNode)"
        />
      </div>
    </v-dialog>

    <v-snackbar top right v-model="nodeDeletedSnackbarOpen" :timeout="5000">
      {{ deletedNodeName }} deleted.
    </v-snackbar>
  </div>
</template>

<script>
  import {axios} from '@/lib/axios'
  import {formatsBytes} from '@/lib/mixins/formatsBytes'
  import LibraryNodeView from '@/components/library-node-view'
  import DocumentViewer from '@/components/document-viewer'

  export default {
    name: 'library-explorer-view',
    components: {DocumentViewer, LibraryNodeView},
    mixins: [formatsBytes],
    props: {
      library: Object,
    },
    data: vm => ({
      currentPath: vm.$route.query.path ?? '',
      currentNode: null,
      parentPath: '',
      previousNode: null,
      items: [],
      loading: false,
      throttledLoading: false,
      throttledLoadingTimeoutId: null,
      displayMode: 'grid',
      lastNavigation: 'down', // or up
      paginationOptions: {
        page: 1,
        itemsPerPage: 50,
      },
      documentViewerDialogOpen: false,
      documentViewerNode: null,
      documentViewerParentNode: null,
      documentViewerPage: null,
      nodeDeletedSnackbarOpen: false,
      deletedNodeName: '',
    }),
    watch: {
      library() {
        this.fetch()
      },
      '$route.query.path'(path) {
        const newPath = path ?? ''

        if (this.currentPath !== newPath) {
          this.currentPath = newPath
          this.$nextTick(() => this.fetch())
        }
      },
      '$route.query.page': {
        immediate: true,
        handler(value) {
          this.documentViewerPage = value ?? null
        },
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
      documentViewerDialogOpen(value) {
        if (!value) {
          this.currentPath = this.documentViewerNode?.parent_path
          this.currentNode = this.documentViewerParentNode
          this.navigateToPath(this.documentViewerNode?.parent_path)
        }
      },
    },
    computed: {
      currentDirectoryPath() {
        return this.currentNode?.type === 'directory'
          ? this.currentNode?.path
          : this.currentNode?.parent_path ?? this.currentPath
      },
      locationBreadcrumbs() {
        const segments = this.currentDirectoryPath.split('/')

        const items = [{
          text: 'Library',
          exact: true,
          to: {
            query: {
              path: '',
            },
          },
        }]
        const processedSegments = []

        for (const segment of segments) {
          processedSegments.push(segment)
          items.push({
            text: segment,
            exact: true,
            to: {
              query: {
                path: processedSegments.join('/'),
              },
            },
          })
        }

        return items
      },
      documentViewerNodeDisplayName() {
        return this.documentViewerNode.document?.title ?? this.documentViewerNode.basename
      },
    },
    async mounted() {
      this.fetch()
    },
    methods: {
      async fetch() {
        const node = await axios.$get(`/library/${this.library.id}/node/${this.currentPath}`)

        this.currentNode = node

        if (node.type === 'directory') {
          await this.browse()
        } else {
          this.currentPath = node.parent_path
          this.documentViewerNode = node
          this.documentViewerDialogOpen = true
          await this.browse()
        }
      },
      async browse() {
        this.loading = true

        try {
          const data = await axios.$get(`/library/${this.library.id}/browse/${this.currentDirectoryPath}`)
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
        } else if (item.document !== null) {
          this.documentViewerParentNode = this.currentNode
          this.documentViewerNode = item
          this.currentNode = item
          this.currentPath = item.path
          this.documentViewerDialogOpen = true
          this.navigateToPath(item.path)
        }
      },
      async deleteNode(node) {
        this.loading = true

        try {
          await axios.$delete(`/library/${this.library.id}/node/${node.path}`)
          this.documentViewerDialogOpen = false
          this.deletedNodeName = node.basename
          this.nodeDeletedSnackbarOpen = true
          await this.browse()
        } catch (e) {
          console.error(e)
        }

        this.loading = false
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
