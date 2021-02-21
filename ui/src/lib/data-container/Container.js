export class Container {
  static wrap(data, forceArray = false) {
    if (!Array.isArray(data)) {
      if (forceArray) {
        data = [data]
      } else {
        return data instanceof this ? data : new this(data)
      }
    }

    return data.map(item => item instanceof this ? item : new this(item))
  }

  constructor(entity) {
    for (const [key, value] of Object.entries(entity)) {
      this[key] = value
    }

    if (typeof this._init === 'function') {
      this._init()
    }
  }
}
