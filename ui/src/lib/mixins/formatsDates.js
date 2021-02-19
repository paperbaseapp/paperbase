import {formatDistanceToNow, format as formatDate, parseISO} from 'date-fns'
import {de} from 'date-fns/locale'

export const formatsDates = {
  methods: {
    formatDateRelative(date) {
      return formatDistanceToNow(new Date(date), {
        addSuffix: true,
        locale: de,
      })
    },
    formatDate(dateOrIsoString, format = 'dd.MM.yyyy HH:mm') {
      return formatDate(dateOrIsoString instanceof Date ? dateOrIsoString : parseISO(dateOrIsoString), format, {
        locale: de,
      })
    },
  },
}
