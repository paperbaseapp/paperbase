import {formatDistanceToNow, format as formatDate, parseISO, differenceInDays, startOfToday, startOfDay} from 'date-fns'
import {de} from 'date-fns/locale'

export const formatsDates = {
  methods: {
    formatDateRelative(date) {
      return formatDistanceToNow(new Date(date), {
        addSuffix: true,
        locale: de,
      })
    },
    formatDate(dateOrIsoString, format = 'dd.MM.yyyy') {
      return formatDate(dateOrIsoString instanceof Date ? dateOrIsoString : parseISO(dateOrIsoString), format, {
        locale: de,
      })
    },
    formatDateRelativeDays(dateString) {
      const today = startOfToday()
      const days = differenceInDays(startOfDay(new Date(dateString)), today)

      switch (days) {
        case 2: return 'übermorgen'
        case 1: return 'morgen'
        case 0: return 'heute'
        case -1: return 'gestern'
        case -2: return 'vorgestern'
        default: return `${days > 0 ? 'in' : 'vor'} ${Math.abs(days)} Tagen`
      }
    },
  },
}
