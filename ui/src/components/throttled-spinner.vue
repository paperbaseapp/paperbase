<template>
  <v-fade-transition>
    <v-progress-circular v-if="show" indeterminate v-bind="$attrs" />
  </v-fade-transition>
</template>

<script>
  export default {
    name: 'throttled-spinner',
    data: () => ({
      show: false,
    }),
    mounted() {
      setTimeout(() => this.show = true, 750)
    },
  }
</script>
