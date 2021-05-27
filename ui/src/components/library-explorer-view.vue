<template>
  <div>
    <v-breadcrumbs :items="locationBreadcrumbs" class="px-0" />

    <v-card
      class="overflow-hidden"
      @dragover.native="event => nodeDragOver(event, currentNode)"
      @drop.native="event => onNodeDrop(event, currentNode)"
      @dragenter.native="event => onNodeDrop(event, currentNode)"
      @dragleave.native="onNodeDragLeave"
    >
      <div class="d-flex px-3 pt-3">
        <v-btn
          :disabled="parentPath === null"
          depressed
          @click="navigateToPath(parentPath)"
          @dragenter="onNavigateToParentButtonDragEnter"
          @dragleave="onNavigateToParentButtonDragLeave"
        >
          <v-icon>mdi-arrow-up</v-icon>
        </v-btn>

        <v-spacer />

        <input ref="fileUploadInput" class="d-none" type="file" @input="uploadFile" />
        <v-btn class="mr-1" depressed @click="$refs.fileUploadInput.click()">
          <v-icon>mdi-file-upload-outline</v-icon>
        </v-btn>

        <v-menu v-model="createDirectoryDialogOpen" :close-on-content-click="false" attach offset-y>
          <template v-slot:activator="{ on }">
            <v-btn v-on="on" class="mr-1" depressed>
              <v-icon>mdi-folder-plus-outline</v-icon>
            </v-btn>
          </template>
          <v-card>
            <h4 class="pa-3 my-0">Create directory</h4>
            <v-text-field
              v-model="newDirectoryName"
              :error-messages="createDirectoryErrorMessages"
              :hide-details="createDirectoryErrorMessages.length === 0"
              append-icon="mdi-check"
              autofocus
              flat
              label="Name"
              solo
              @click:append="createDirectory"
              @keydown.enter="createDirectory"
            />
          </v-card>
        </v-menu>
        <v-btn-toggle v-model="displayMode" class="ma-0" dense mandatory>
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
          :footer-props="{
            'items-per-page-options': [30,50,100,-1]
          }"
          :headers="[
            {value: 'type', width: 1, filterable: false, sortable: false},
            {text: 'Name', value: 'basename'},
            {text: 'Size', value: 'size'},
          ]"
          :item-class="() => 'hand-cursor'"
          :items="items"
          :loading="throttledLoading"
          :options="paginationOptions"
          class="full-width"
        >
          <template v-slot:item="{item}">
            <library-node-list-item :node="item" @click.native="navigateToItem(item)" @rename-node="renameNode" />
          </template>
        </v-data-table>
        <v-data-iterator
          v-else
          :footer-props="{
            'items-per-page-options': [30,50,100,-1]
          }"
          :items="items"
          :loading="throttledLoading"
          :options="paginationOptions"
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
              <div v-if="!loading" class="pa-3">
                <div class="explorer-grid">
                  <library-node-view
                    v-for="item in items"
                    :key="item.path"
                    :node="item"
                    @click="navigateToItem(item)"
                    @rename-node="renameNode"
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
        <v-toolbar class="flex-grow-0" dense>
          <v-toolbar-title>{{ documentViewerNodeDisplayName }}</v-toolbar-title>
          <v-spacer />
          <v-btn icon @click="documentViewerDialogOpen = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-toolbar>
        <document-viewer
          :library-id="library.id"
          :loading="loading"
          :node="documentViewerNode"
          :page="documentViewerPage"
          class="flex-grow-1"
          @delete="permanently => deleteNode(documentViewerNode, permanently)"
          @request-update="fetch"
        />
      </div>
    </v-dialog>

    <v-snackbar v-model="snackbarOpen" :timeout="5000" right top>
      {{ snackbarText }}
    </v-snackbar>
  </div>
</template>

<script>
  import {axios} from '@/lib/axios'
  import {formatsBytes} from '@/lib/mixins/formatsBytes'
  import LibraryNodeView from '@/components/library-node-view'
  import DocumentViewer from '@/components/document-viewer'
  import {LibraryNodeContainer} from '@/lib/data-container/LibraryNodeContainer'
  import {CONFLICT, LOCKED} from '@/lib/statuses'
  import {nodeDragAndDrop, nodeDragEventIsDroppable} from '@/lib/mixins/nodeDragAndDrop'
  import LibraryNodeListItem from '@/components/library-node-list-item'

  export default {
    name: 'library-explorer-view',
    components: {LibraryNodeListItem, DocumentViewer, LibraryNodeView},
    mixins: [formatsBytes, nodeDragAndDrop],
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
      snackbarOpen: false,
      snackbarText: '',
      newDirectoryName: '',
      createDirectoryErrorMessages: [],
      createDirectoryDialogOpen: '',
      navigateToParentDragRefCount: 0,
      navigateToParentIntervalId: null,
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

      this.$on('rename-node', this.renameNode)
    },
    methods: {
      async fetch() {
        const node = LibraryNodeContainer.wrap(await axios.$get(`/library/${this.library.id}/node`, {
          params: {
            path: this.currentPath,
          },
        }))

        this.currentNode = node

        if (node.type === 'directory') {
          await this.browse()
        } else {
          this.currentPath = node.path
          this.documentViewerNode = node
          this.documentViewerDialogOpen = true
          await this.browse()
        }
      },
      async browse() {
        this.loading = true

        try {
          const data = await axios.$get(`/library/${this.library.id}/browse`, {
            params: {
              path: this.currentDirectoryPath,
            },
          })
          this.items = LibraryNodeContainer.wrap(data.items)
          this.parentPath = data.parent_path
        } catch (e) {
          console.error(e)
        }

        this.loading = false
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
      async deleteNode(node, permanently) {
        this.loading = true

        try {
          await axios.$delete(`/library/${this.library.id}/node`, {
            data: {
              delete_permanently: permanently,
              path: this.currentPath,
            },
          })
          this.documentViewerDialogOpen = false
          this.snackbarText = node.basename + ' deleted.'
          this.snackbarOpen = true
          await this.browse()
        } catch (e) {
          console.error(e)

          if (e.response?.status === LOCKED) {
            this.snackbarText = `Could not delete ${node.basename}. The file is currently locked by another process (e.g. OCR)`
            this.snackbarOpen = true
          }
        }

        this.loading = false
      },
      async renameNode(node, targetPath) {
        this.loading = true

        console.log({node, targetPath})

        try {
          await axios.$post(`/library/${this.library.id}/node/rename`, {
            source_path: node.path,
            target_path: targetPath,
            move_inside_directories: true,
          })
          this.snackbarText = node.basename + ' moved.'
          this.snackbarOpen = true
          await this.browse()
        } catch (e) {
          console.error(e)

          if (e.response?.status === LOCKED) {
            this.snackbarText = `Could not move ${node.basename}. The file is currently locked by another process (e.g. OCR)`
            this.snackbarOpen = true
          }
        }

        this.loading = false
      },
      async createDirectory() {
        try {
          await axios.$post(`/library/${this.library.id}/directory`, {
            path: this.currentNode.path + '/' + this.newDirectoryName,
          })
          this.createDirectoryDialogOpen = false
          this.newDirectoryName = ''
          this.createDirectoryErrorMessages = ''
          await this.browse()
        } catch (e) {
          console.error(e)

          this.createDirectoryErrorMessages = e.response?.status === CONFLICT
            ? ['Already exists']
            : ['An error occurred.']
        }
      },
      async uploadFile() {
        const data = new FormData()

        for (const file of this.$refs.fileUploadInput.files) {
          data.append('files[]', file)
        }

        data.append('path', this.currentDirectoryPath)

        try {
          await axios.$post(`/library/${this.library.id}/file`, data, {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
          })

          await this.browse()
        } catch (e) {
          console.error(e)
        }
      },
      onNavigateToParentButtonDragEnter(event) {
        if (this.navigateToParentDragRefCount === 0) {
          if (nodeDragEventIsDroppable(event)) {
            const intervalId = setInterval(() => {
              if (this.navigateToParentIntervalId === intervalId) {
                this.navigateToPath(this.parentPath)
              }
            }, 750)
            this.navigateToParentIntervalId = intervalId
          }
        }

        this.navigateToParentDragRefCount++
      },
      onNavigateToParentButtonDragLeave(event) {
        this.navigateToParentDragRefCount--

        if (this.navigateToParentDragRefCount === 0) {
          if (nodeDragEventIsDroppable(event) && this.navigateToParentIntervalId) {
            clearTimeout(this.navigateToParentIntervalId)
            this.navigateToParentIntervalId = null
          }
        }
      },
    },
  }
</script>

<style lang="scss" scoped>
  .explorer-grid {
    display: grid;
    grid-gap: 8px;
    grid-template-columns: repeat(auto-fill, minmax(min(164px, 100%), 1fr));
    width: 100%;
  }

  .full-width {
    width: 100%;
  }
</style>
