<template>
  <v-autocomplete
    v-model="selected"
    :items="results"
    :search-input.sync="query"
    return-object
    no-filter
    auto-select-first
    item-text="document.basename"
    prepend-inner-icon="mdi-magnify"
    placeholder="Search in library"
    solo
  >
    <template v-slot:item="{item}">
      <v-avatar tile class="mr-2">
        <v-icon v-if="item.document.thumbnail_url === null">mdi-file</v-icon>
        <v-img v-else max-width="48" max-height="48" :src="item.document.thumbnail_url" />
      </v-avatar>
      <v-list-item-content>
        <v-list-item-title class="search-result" v-html="getDocumentTitleHtml(item)" />
        <v-list-item-subtitle class="search-result" v-html="item.search_metadata.formatted.text_content" />
      </v-list-item-content>
    </template>
  </v-autocomplete>
</template>

<script>
  import {CancelToken} from 'axios'
  import {axios} from '@/lib/axios'

  export default {
    name: 'library-search-bar',
    props: {
      libraryId: String,
    },
    data: () => ({
      results: [],
      query: '',
      selected: null,
    }),
    watch: {
      libraryId() {
        this.search()
      },
      query() {
        this.search()
      },
      selected(item) {
        if (!item) {
          return
        }

        this.$router.push({
          name: 'browse',
          params: {
            libraryId: item.document.library_id,
          },
          query: {
            path: item.document.path,
            page: item.page,
          },
        })

        this.$nextTick(() => this.selected = null)
      },
    },
    created() {
      this.cancelTokenSource = null
    },
    methods: {
      async search() {
        if (!this.libraryId) {
          this.results = []
        }

        if (this.cancelTokenSource) {
          this.cancelTokenSource.cancel()
        }

        this.cancelTokenSource = CancelToken.source()

        try {
          this.results = await axios.$get(`/library/${this.libraryId}/search`, {
            params: {
              query: this.query,
            },
          })
        } catch (e) {
          console.error(e)
        }
      },
      getDocumentTitleHtml(item) {
        return `${item.search_metadata.formatted.document_filename} <small class="grey--text">Seite ${item.page}</small>`
      },
    }
  }
</script>

<style lang="scss">
  .search-result {
    em {
      font-style: normal;
      display: inline-block;
      background-color: #ffffaa;
      padding: 0.1em 0.2em;
      margin: 0 -0.2em;
    }
  }
</style>
