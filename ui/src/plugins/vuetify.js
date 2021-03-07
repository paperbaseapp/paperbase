import Vue from 'vue'
import Vuetify from 'vuetify/lib/framework'

Vue.use(Vuetify)

export default new Vuetify({
  theme: {
    options: {
      customProperties: true,
    },
    themes: {
      light: {
        primary: '#3B4252',
        secondary: '#2E3440',
        accent: '#5E81AC',
        error: '#BF616A',
        info: '#5E81AC',
        success: '#A3BE8C',
        warning: '#EBCB8B',
      },
    },
  },
})
