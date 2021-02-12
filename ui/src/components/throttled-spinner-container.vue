<template>
  <v-container class="fill-height align-center justify-center" key="throttled-spinner">
    <throttled-spinner />
  </v-container>
</template>

<script>
  import ThrottledSpinner from './throttled-spinner'
  export default {
    name: 'throttled-spinner-container',
    components: {ThrottledSpinner},
  }
</script>

<style scoped>

</style>
