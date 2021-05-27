export const nodeDragEventIsDroppable = event => {
  return event.dataTransfer.types.includes('application/x.paperbase.node+json')
}

export const nodeDragAndDrop = {
  data: () => ({
    dropActive: false,
    dropDisabled: false,
    dragRefCount: 0,
  }),
  methods: {
    onNodeDragStart(event, node = this.node) {
      this.dropDisabled = true
      this.nodeDragStart(event, node)
    },
    onNodeDrop(event, node = this.node) {
      if (this.dropDisabled) {
        return
      }

      this.nodeDrop(event, node)
      this.dropActive = false
      this.dragRefCount = 0
    },
    onNodeDragEnter(event, node = this.node) {
      if (this.dropDisabled) {
        return
      }

      this.dragRefCount++

      if (
        this.nodeDragEventIsDroppable(event) &&
        node.type === 'directory'
      ) {
        this.dropActive = true
      }
    },
    onNodeDragLeave() {
      this.dragRefCount--

      if (this.dragRefCount <= 0) {
        this.dropActive = false
        this.dragRefCount = 0
      }
    },
    isDraggable(node) {
      return !node.flags.includes('trash') && !node.flags.includes('inbox')
    },
    nodeDragEventIsDroppable,
    nodeDragStart(event, node) {
      event.dataTransfer.setData('application/x.paperbase.node+json', JSON.stringify(node))
      event.dataTransfer.dropEffect = 'move'
    },
    nodeDragOver(event, node) {
      if (node.type === 'directory') {
        if (this.nodeDragEventIsDroppable(event)) {
          event.preventDefault()
          event.dataTransfer.dropEffect = 'move'
          return true
        }
      }

      return false
    },
    async nodeDrop(event, node) {
      if (node.type === 'directory') {
        const data = event.dataTransfer.getData('application/x.paperbase.node+json')
        if (data) {
          event.preventDefault()
          event.stopPropagation()
          const sourceNode = JSON.parse(data)

          if (sourceNode.parent_path === node.path) {
            return
          }

          this.$emit('rename-node', sourceNode, node.path)
        }
      }
    },
  },
}
