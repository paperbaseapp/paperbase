<template>
  <v-card class="overflow-hidden">
    <v-window :value="challengeIndex">
      <v-window-item :value="0">
        <div class="px-6 pt-3">
          <v-text-field
            :autofocus="!$app.isTouch"
            prepend-icon="mdi-account"
            placeholder="Account"
            v-model="account"
            hide-details
            :error-messages="errors"
            @keyup.enter="runCurrentChallenge"
          />
          <v-text-field
            type="password"
            prepend-icon="mdi-key"
            placeholder="Password"
            v-model="password"
            :error-messages="errors"
            @keyup.enter="runCurrentChallenge"
          />
          <v-checkbox v-model="remember" label="Stay logged in" />
        </div>
      </v-window-item>
      <v-window-item :value="1">
        <div class="px-4 pt-4">
          <v-text-field
            prepend-icon="mdi-security"
            placeholder="2FA Code"
            ref="totpKeyInput"
            v-model="totpKey"
            :error-messages="errors"
            @keyup.enter="runCurrentChallenge"
          />
        </div>
      </v-window-item>
    </v-window>
    <v-btn color="primary" depressed block tile class="mt-3" @click="runCurrentChallenge" :loading="loading || $store.state.loggingIn">
      Login
    </v-btn>
  </v-card>
</template>

<script>
  import {axios} from '@/lib/axios'

  export default {
    name: 'login-form',
    data: () => ({
      account: '',
      password: '',
      totpKey: '',
      errors: [],
      challenge: 'login',
      loading: false,
      remember: false,
    }),
    computed: {
      challengeIndex() {
        switch (this.challenge) {
          case 'login': return 0
          case 'totp': return 1
          default: return 0
        }
      }
    },
    methods: {
      runCurrentChallenge() {
        switch (this.challenge) {
          case 'login': this.login(); break
          case 'totp': this.validateTotp(); break
        }
      },
      async login() {
        if (this.loading || !this.account || !this.password) return
        this.loading = true
        this.errors = []

        try {
          const {account, password, remember} = this
          const {data} = await axios.post('/auth/login', {
            account,
            password,
            remember,
          })

          // TOTP for later...
          if (data.totp_validation_required) {
            this.challenge = 'totp'

            if (!this.$app.isTouch) {
              setTimeout(() => this.$refs.totpKeyInput.focus(), 300)
            }
          } else {
            this.$store.commit('setUser', data.user)
          }

          this.errors = []
        } catch (e) {
          console.error(e)

          if (!!e.response && e.response.status === 403) {
            this.errors = ['Invalid credentials']
          } else {
            this.errors = ['An error occurred']
          }
        }

        this.loading = false
      },
      async validateTotp() {
        if (this.loading) return
        this.loading = true
        this.errors = []

        try {
          const {data} = await axios.post('/auth/challenge/totp', {
            key: this.totpKey
          })

          this.$store.commit('setUser', data.user)

          this.errors = []
        } catch (e) {
          console.error(e)

          if (!!e.response && e.response.status === 403) {
            this.errors = ['Invalid 2FA token']
          } else {
            this.errors = ['An error occurred']
          }
        }

        this.loading = false
      }
    }
  }
</script>

<style scoped>

</style>
