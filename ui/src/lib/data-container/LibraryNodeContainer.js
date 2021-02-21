import {Container} from './Container'

export class LibraryNodeContainer extends Container {
  get icon() {
    if (this.type === 'directory') {
      for (const flag of this.flags) {
        switch (flag) {
          case 'trash': return 'mdi-delete-empty'
          case 'inbox': return 'mdi-inbox-arrow-down'
        }
      }

      return 'mdi-folder-outline'
    } else {
      return 'mdi-file-outline'
    }
  }
}
