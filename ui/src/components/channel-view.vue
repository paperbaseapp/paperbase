<template>
  <v-card :to="{name: 'channel', params: {id: channel.id}}" class="pa-3">
    <div class="d-flex">
      <div>
        <h3>{{ channel.name }}</h3>
        <span>{{ channel.description }}</span>
      </div>
      <v-spacer />
      <div class="align-self-end">
        <span class="caption grey--text">
          <v-icon size="16" color="grey">mdi-cellphone-nfc</v-icon>
          {{ channel.subscriptions_count }}
        </span>
      </div>
    </div>
  </v-card>
</template>

<script>
  export default {
    name: 'channel-view',
    props: {
      channel: Object,
    },
  }
</script>

<style scoped>

</style>
