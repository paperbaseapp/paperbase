<template>
  <v-sheet class="d-flex" tile>
    <div class="embed" ref="embed"></div>
    <v-sheet class="sidebar">
      <v-list>
        <v-list-item class="justify-center">
          <v-icon size="48" class="mt-3">mdi-file</v-icon>
        </v-list-item>
        <v-list-item>
          <v-list-item-content>
            <v-list-item-title>{{ nodeDisplayName }}</v-list-item-title>
            <v-list-item-subtitle>{{ formatBytes(node.size) }}</v-list-item-subtitle>
          </v-list-item-content>
        </v-list-item>
        <v-divider />
        <v-list-item>
          <v-icon class="mr-3">mdi-text-recognition</v-icon>
          <v-list-item-content>
            <v-list-item-title>
              {{ ocrStatusText }}
              <v-icon class="ml-2" color="success" v-if="['done', 'not_required'].includes(node.document.ocr_status)">
                mdi-check-circle-outline
              </v-icon>
            </v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <v-list-item>
          <v-list-item-content>
            <v-list-item-subtitle>Imported at</v-list-item-subtitle>
            <v-list-item-title>{{ formatDate(node.document.created_at) }}</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
        <v-list-item>
          <v-btn :disabled="loading" depressed block>
            <v-icon left>
              mdi-file-cog-outline
            </v-icon>
            Settings
          </v-btn>
        </v-list-item>
        <v-list-item>
          <v-btn :disabled="loading" class="delete-button" @click="onDeleteClick" depressed block :color="movingToTrash ? 'error' : null">
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
    },
    data: () => ({
      movingToTrash: false,
    }),
    watch: {
      node() {
        this.update()
      },
      libraryId() {
        this.update()
      },
      movingToTrash(value) {
        if (value) {
          setTimeout(() => this.movingToTrash = false, 3000)
        }
      },
    },
    computed: {
      nodeDisplayName() {
        return this.node.document?.title ?? this.node.basename
      },
      ocrStatusText() {
        switch (this.node.document?.ocr_status) {
          case 'pending': return 'Document text pending'
          case 'done': return 'OCR done'
          case 'unavailable': return 'Document text unavailable'
          case 'failed': return 'OCR failed'
          case 'not_required': return 'Document has text'
          default: return this.node.document?.ocr_status ?? 'unknown'
        }
      },
    },
    mounted() {
      this.update()
    },
    methods: {
      update() {
        this.$refs.embed.innerHTML = ''
        PDFObject.embed(`${axios.defaults.baseURL}/library/${this.libraryId}/download/${this.node.path}`, this.$refs.embed)
      },
      onDeleteClick() {
        if (!this.movingToTrash) {
          this.movingToTrash = true
        } else {
          this.$emit('delete')
        }
      },
    }
  }
</script>

<style lang="scss" scoped>
  .embed {
    height: 100%;
    flex-grow: 1;
  }

  .sidebar {
    flex-shrink: 0;
    width: min-content;
    max-width: 33%;
  }

  .delete-button {
    transition: background-color 200ms, color 200ms;
  }
</style>
