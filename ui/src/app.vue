<template>
  <v-app>
    <v-app-bar app :value="$store.getters.isLoggedIn && !$route.meta.hideNavigation">
      <v-btn
        v-if="$route.name !== 'browse'"
        icon
        @click.stop="$router.history.length > 1 ? $router.back() : $router.push({name: 'applications'})"
      >
        <v-icon>mdi-arrow-left</v-icon>
      </v-btn>
      <v-toolbar-title>{{ $route.meta.title || '' }}</v-toolbar-title>
      <v-spacer />
      <v-menu offset-y v-if="$store.getters.globalBatchProgress !== null">
        <template v-slot:activator="{on, attrs}">
          <v-btn v-bind="attrs" v-on="on" class="mr-3" icon>
            <v-progress-circular
              :color="$store.getters.globalBatchProgress === 100 ? 'success' : null"
              :indeterminate="$store.getters.globalBatchProgress === 0"
              :value="$store.getters.globalBatchProgress"
              rotate="-90"
            />
          </v-btn>
        </template>
        <v-card min-width="200px">
          <v-list>
            <v-list-item v-for="batch in Object.values($store.state.batches)" :key="batch.id">
              <v-list-item-content>
                <v-list-item-title>
                  {{ batch.name }}
                  ({{ batch.processedJobs }}/{{ batch.totalJobs }})
                </v-list-item-title>
                <v-list-item-subtitle>
                  <div v-if="batch.failedJobs > 0">
                    {{ batch.failedJobs }} failed
                  </div>
                  <v-progress-linear
                    :value="batch.processedJobs / batch.totalJobs * 100"
                    background-color="black"
                    class="secondary lighten-4"
                    height="6"
                    rounded
                    :buffer-value="(batch.processedJobs + batch.failedJobs) / batch.totalJobs * 100"
                  />
                </v-list-item-subtitle>
              </v-list-item-content>
            </v-list-item>
          </v-list>
        </v-card>
      </v-menu>
      <portal-target name="app-bar-end" />
      <template v-if="!!$route.meta.hasTabs" v-slot:extension>
        <portal-target name="tabs" slim />
      </template>
    </v-app-bar>
    <v-main>
      <transition name="page-transition" mode="out-in">
        <router-view />
      </transition>
    </v-main>
  </v-app>
</template>

<script>
  export default {
    name: 'app',
  }
</script>
