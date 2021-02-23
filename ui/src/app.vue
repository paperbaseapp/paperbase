<template>
  <v-app>
    <v-app-bar :value="$store.getters.isLoggedIn && !$route.meta.hideNavigation" app>
      <v-btn
        v-if="$route.name !== 'browse'"
        icon
        @click.stop="$router.history.length > 1 ? $router.back() : $router.push({name: 'applications'})"
      >
        <v-icon>mdi-arrow-left</v-icon>
      </v-btn>
      <v-toolbar-title>{{ $route.meta.title || '' }}</v-toolbar-title>
      <v-spacer />
      <v-menu v-if="$store.getters.globalBatchProgress !== null" offset-y>
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
                    :buffer-value="(batch.processedJobs + batch.failedJobs) / batch.totalJobs * 100"
                    :value="batch.processedJobs / batch.totalJobs * 100"
                    background-color="black"
                    class="secondary lighten-4"
                    height="6"
                    rounded
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
      <v-menu v-if="$store.state.user !== null" bottom offset-y rounded>
        <template v-slot:activator="{ on }">
          <div class="pr-1">
            <v-btn v-on="on" class="ml-3 mr-1" icon x-large>
              <v-avatar color="brown" size="48">
                <span class="white--text headline">{{ $store.state.user.account[0] }}</span>
              </v-avatar>
            </v-btn>
          </div>
        </template>
        <v-card>
          <v-list class="pb-0">
            <v-list-item>
              <v-list-item-content>
                <div class="mx-auto text-center">
                  <h3 class="mb-1">{{ $store.state.user.display_name }}</h3>
                  <div v-if="$store.state.user.email !== null" class="caption mb-1">
                    {{ $store.state.user.email }}
                  </div>
                </div>
              </v-list-item-content>
            </v-list-item>
            <v-divider />
            <v-list-item @click="logout">
              <v-list-item-title>
                <v-icon left>mdi-logout</v-icon>
                Sign out
              </v-list-item-title>
            </v-list-item>
          </v-list>
        </v-card>
      </v-menu>
    </v-app-bar>
    <v-main>
      <transition mode="out-in" name="page-transition">
        <router-view />
      </transition>
    </v-main>
  </v-app>
</template>

<script>
  import {axios} from '@/lib/axios'

  export default {
    name: 'app',
    methods: {
      async logout() {
        await axios.$post('/auth/logout')
        this.$store.commit('setUser', null)
        this.$router.push({name: 'login'})
      },
    },
  }
</script>
