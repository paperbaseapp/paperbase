import {default as BaseAxios} from 'axios'

const axios = BaseAxios.create({
  baseURL: '/api/v1',
})

for (const method of ['request', 'delete', 'get', 'head', 'options', 'post', 'put', 'patch']) {
  axios['$' + method] = function () { return this[method].apply(this, arguments).then(res => res && res.data) }
}

export {axios}
