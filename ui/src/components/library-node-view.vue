<template>
  <v-card
    @click="$emit('click')"
    class="library-node-view"
    min-height="156px"
    outlined
    :class="{'drop-active': dropActive}"
    :draggable="isDraggable(node)"
    @dragover="event => !dropDisabled && nodeDragOver(event, node)"
    @dragstart="onNodeDragStart"
    @dragend="dropDisabled = false"
    @drop="onNodeDrop"
    @dragenter="onNodeDragEnter"
    @dragleave="onNodeDragLeave"
  >
    <v-tooltip v-if="node.needs_sync" top transition="slide-y-reverse-transition">
      <template v-slot:activator="{on}">
        <div class="sync-alert pa-1" v-on="on">
          <v-icon color="white">mdi-sync-alert</v-icon>
        </div>
      </template>
      <span>This file is not synced</span>
    </v-tooltip>

    <v-img
      v-if="hasThumbnail"
      :src="node.document.thumbnail_url"
      class="thumbnail"
      width="100%"
      height="100%"
      position="top"
      transition="slide-y-reverse-transition"
      gradient="to top, rgba(0, 15, 25, 0.8) 0%, rgba(0, 15, 25, 0) 100%"
    />
    <div v-else class="thumbnail pb-5 d-flex align-center justify-center">
      <v-icon size="64" :color="dropActive ? 'white' : 'primary'" v-text="node.icon" />
    </div>
    <v-spacer />
    <div class="pa-3 card-text text-caption font-weight-medium" :class="{'white--text': hasThumbnail}">
      {{ node.basename }}
    </div>
    <v-sheet class="file-type-border" tile height="4px" :color="fileTypeColor" />
  </v-card>
</template>

<script>
  import {nodeDragAndDrop} from '@/lib/mixins/nodeDragAndDrop'

  export default {
    name: 'library-node-view',
    mixins: [nodeDragAndDrop],
    props: {
      node: Object,
    },
    computed: {
      hasThumbnail() {
        return this.node.document && this.node.document.thumbnail_url
      },
      fileTypeColor() {
        if (this.node.type === 'directory') {
          return 'transparent'
        }

        switch (this.node.extension.toLowerCase()) {
          case 'pdf': return 'red'
          case 'docx': return 'red'
          default: return 'grey'
        }
      }
    },
  }
</script>

<style lang="scss" scoped>
  .drop-active {
    background-color: var(--v-primary-base);
    color: white;

    td > * {
      pointer-events: none !important;
    }
  }

  .inherit-color {
    color: inherit;
  }

  .library-node-view {
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .thumbnail {
    position: absolute;
    z-index: 0;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
  }

  .card-text {
    position: relative;
    z-index: 1;
    word-break: normal;
    line-height: 1;
  }

  .file-type-border {
    z-index: 1;
  }

  .sync-alert {
    position: absolute;
    z-index: 2;
    top: 0;
    right: 0;

    > * {
      z-index: 2;
    }

    &::after {
      content: '';
      z-index: 1;
      position: absolute;
      top: -100%;
      left: 0;
      right: -50%;
      bottom: -100%;
      background-color: var(--v-error-base);
      transform: rotate(-45deg);
    }
  }
</style>
