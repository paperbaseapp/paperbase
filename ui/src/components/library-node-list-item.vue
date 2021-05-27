<template>
  <tr
    class="list-item"
    :class="{'drop-active': dropActive}"
    :draggable="isDraggable(node)"
    @dragover="event => !dropDisabled && nodeDragOver(event, node)"
    @dragstart="onNodeDragStart"
    @dragend="dropDisabled = false"
    @drop.stop="onNodeDrop"
    @dragenter="onNodeDragEnter"
    @dragleave="onNodeDragLeave"
  >
    <td>
      <v-icon class="inherit-color" v-text="node.icon" />
    </td>
    <td>{{ node.basename }}</td>
    <td>
      <template v-if="node.type === 'file'">
        {{ formatBytes(node.size) }}
      </template>
    </td>
  </tr>
</template>
<script>
  import {nodeDragAndDrop} from '@/lib/mixins/nodeDragAndDrop'
  import {formatsBytes} from '@/lib/mixins/formatsBytes'

  export default {
    name: 'library-node-list-item',
    mixins: [formatsBytes, nodeDragAndDrop],
    props: {
      node: Object,
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
</style>
