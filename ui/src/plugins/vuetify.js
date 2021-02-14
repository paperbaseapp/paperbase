import Vue from 'vue';
import Vuetify from 'vuetify/lib'
import en from 'vuetify/es5/locale/en'

Vue.use(Vuetify);

export default new Vuetify({
  theme: {
    dark: false,
    options: {
      customProperties: true,
    },
    themes: {
      light: {
        primary: '#2196F3',
        secondary: '#1f7ac3',
        accent: '#2196F3',
        error: '#FF5252',
        info: '#2196F3',
        success: '#4CAF50',
        warning: '#fb8c00',
      },
    },
  },
  lang: {
    locales: {en},
    current: 'en',
  },
  icons: {
    iconfont: 'mdi',
  },
})
