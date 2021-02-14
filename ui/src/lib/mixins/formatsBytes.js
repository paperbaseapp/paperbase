import prettyBytes from 'pretty-bytes'

export const formatsBytes = {
  methods: {
    formatBytes(bytes) {
      return prettyBytes(bytes, {
        locale: true,
      })
    },
  },
}
