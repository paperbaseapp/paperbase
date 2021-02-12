<template>
  <v-card :to="{name: 'application', params: {id: application.id}}" class="pa-3">
    <h2>{{ application.name }}</h2>

    <div class="align-self-end">
        <span class="caption grey--text">
          <v-icon size="16" color="grey">mdi-broadcast</v-icon>
          {{ application.channels.length }}
        </span>
    </div>
  </v-card>
</template>

<script>
  export default {
    name: 'application-view',
    props: {
      application: Object,
    }
  }
</script>

<style scoped>

</style>
