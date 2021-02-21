<template>
  <v-sheet class="d-flex" tile>
    <div ref="embed" class="embed"></div>
    <v-sheet class="sidebar">
      <v-list>
        <v-list-item class="justify-center">
          <v-icon class="mt-3" size="48">mdi-file</v-icon>
        </v-list-item>
        <v-list-item>
          <v-list-item-content>
            <v-list-item-title>{{ node.basename }}</v-list-item-title>
            <v-list-item-subtitle>{{ formatBytes(node.size) }}</v-list-item-subtitle>
          </v-list-item-content>
        </v-list-item>
        <v-divider />
        <v-list-item>
          <v-icon class="mr-3">mdi-text-recognition</v-icon>
          <v-list-item-content>
            <v-list-item-title>
              {{ ocrStatusText }}
              <v-icon v-if="['done', 'not_required'].includes(node.document.ocr_status)" class="ml-2" color="success">
                mdi-check-circle-outline
              </v-icon>
              <v-icon v-else-if="node.document.ocr_status === 'failed'" class="ml-2" color="error">
                mdi-alert-circle-outline
              </v-icon>
            </v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <v-list-item v-if="node.document.ocr_status === 'failed'">
          <v-btn
            v-if="!forceOcrJob"
            :disabled="loading"
            block
            color="warning"
            depressed
            @click="forceOcrDialogOpen = true"
          >
            <v-icon left>
              mdi-ocr
            </v-icon>
            Force OCR
          </v-btn>
          <div v-else class="flex-grow-1">
            <div>OCR:</div>
            <v-progress-linear :indeterminate="forceOcrProgress === 0" :value="forceOcrProgress" />
          </div>
          <v-dialog :persistent="forceOcrLoading" :value="forceOcrDialogOpen" max-width="300">
            <v-card>
              <v-card-title>Force OCR</v-card-title>
              <v-card-text>
                Forced OCR will re-render this PDF file before
                trying to do OCR again. This could lead to quality
                loss.
              </v-card-text>
              <v-card-actions>
                <v-spacer />
                <v-btn :disabled="forceOcrLoading" depressed @click="forceOcrDialogOpen = false">Cancel</v-btn>
                <v-btn :loading="forceOcrLoading" color="warning" depressed @click="forceOcr">Force OCR</v-btn>
              </v-card-actions>
            </v-card>
          </v-dialog>
        </v-list-item>
        <v-list-item>
          <v-list-item-content>
            <v-list-item-subtitle>Imported at</v-list-item-subtitle>
            <v-list-item-title>{{ formatDate(node.document.created_at) }}</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <v-list-item>
          <v-btn :disabled="loading" block depressed>
            <v-icon left>
              mdi-file-cog-outline
            </v-icon>
            Settings
          </v-btn>
        </v-list-item>
        <v-list-item>
          <v-btn
            :color="movingToTrash ? 'error' : null"
            :disabled="loading"
            block
            class="delete-button"
            depressed
            @click="onDeleteClick"
          >
            <v-icon v-if="!movingToTrash" left>
              mdi-delete-outline
            </v-icon>
            {{ movingToTrash ? 'Confirm deletion' : 'Delete' }}
          </v-btn>
        </v-list-item>
      </v-list>
    </v-sheet>
  </v-sheet>
</template>

<script>
  import PDFObject from 'pdfobject'
  import {axios} from '@/lib/axios'
  import {formatsBytes} from '@/lib/mixins/formatsBytes'
  import {formatsDates} from '@/lib/mixins/formatsDates'

  export default {
    name: 'document-viewer',
    mixins: [formatsBytes, formatsDates],
    props: {
      libraryId: String,
      node: Object,
      loading: Boolean,
      page: Number,
    },
    data: () => ({
      movingToTrash: false,
      forceOcrLoading: false,
      forceOcrDialogOpen: false,
      forceOcrJobId: null,
    }),
    computed: {
      ocrStatusText() {
        switch (this.node.document?.ocr_status) {
          case 'pending':
            return 'Document text pending'
          case 'done':
            return 'OCR done'
          case 'unavailable':
            return 'Document text unavailable'
          case 'failed':
            return 'OCR failed'
          case 'not_required':
            return 'Document has text'
          default:
            return this.node.document?.ocr_status ?? 'unknown'
        }
      },
      forceOcrJob() {
        return this.$store.state.jobs[this.forceOcrJobId] ?? null
      },
      forceOcrProgress() {
        if (this.forceOcrJobId === null) {
          return null
        }

        return (this.forceOcrJob.progress_now / (this.forceOcrJob.progress_max || 1)) * 100
      },
    },
    watch: {
      node() {
        this.update()
        this.movingToTrash = false
      },
      libraryId() {
        this.update()
      },
      page() {
        this.update()
      },
      movingToTrash(value) {
        if (value) {
          if (this.movingToTrashTimeoutId) {
            clearTimeout(this.movingToTrashTimeoutId)
          }
          this.movingToTrashTimeoutId = setTimeout(() => this.movingToTrash = false, 3000)
        }
      },
      forceOcrJob(job) {
        if (['finished', 'failed'].includes(job?.status)) {
          this.update()
          this.$emit('request-update')
          this.forceOcrJobId = null
        }
      },
    },
    mounted() {
      this.update()
    },
    methods: {
      update() {
        this.forceOcrJobId = null
        this.forceOcrLoading = false

        this.$refs.embed.innerHTML = ''
        let url = `${axios.defaults.baseURL}/library/${this.libraryId}/download/${this.node.path}`

        if (this.page) {
          url += `#page=${this.page}`
        }

        PDFObject.embed(url, this.$refs.embed)
      },
      async forceOcr() {
        this.forceOcrLoading = true
        const job = await this.$store.dispatch('startForceOcrJob', this.node.document.id)
        this.forceOcrJobId = job.id
        this.forceOcrLoading = false
        this.forceOcrDialogOpen = false
      },
      onDeleteClick() {
        if (!this.movingToTrash) {
          this.movingToTrash = true
        } else {
          this.$emit('delete')
        }
      },
    },
  }
</script>

<style lang="scss" scoped>
  .embed {
    flex-grow: 1;
    height: 100%;
  }

  .sidebar {
    flex-shrink: 0;
    max-width: 33%;
    min-width: 250px;
    width: min-content;
  }

  .delete-button {
    transition: background-color 200ms, color 200ms;
  }
</style>
