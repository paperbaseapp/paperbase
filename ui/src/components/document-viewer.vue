<template>
  <v-sheet tile>
    <div class="embed" ref="embed"></div>
  </v-sheet>
</template>

<script>
  import PDFObject from 'pdfobject'
  import {axios} from '@/lib/axios'

  export default {
    name: 'document-viewer',
    props: {
      libraryId: String,
      path: String,
    },
    watch: {
      path() {
        this.update()
      },
      libraryId() {
        this.update()
      },
    },
    mounted() {
      this.update()
    },
    methods: {
      update() {
        this.$refs.embed.innerHTML = ''
        PDFObject.embed(`${axios.defaults.baseURL}/library/${this.libraryId}/download/${this.path}`, this.$refs.embed)
      }
    }
  }
</script>

<style lang="scss" scoped>
  .embed {
    height: 100%;
  }
</style>
