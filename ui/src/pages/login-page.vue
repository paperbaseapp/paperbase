<template>
  <div class="fill-height">
    <v-fade-transition mode="out-in">
      <v-container class="fill-height login-page" v-if="!$store.state.loggingIn">
        <div class="flex-grow-1 d-flex justify-center align-end">
          <login-form />
        </div>
        <div class="flex-grow-1 pt-5 teaser">
          <img src="../assets/logo-text-dark.svg">
        </div>
      </v-container>
    </v-fade-transition>
  </div>
</template>

<script>
  import LoginForm from '../components/login-form'
  import {mapGetters} from 'vuex'

  export default {
    name: 'login-page',
    components: {LoginForm},
    computed: {
      ...mapGetters(['isLoggedIn'])
    },
    watch: {
      isLoggedIn: {
        immediate: true,
        handler(isLoggedIn) {
          if (isLoggedIn) {
            this.$router.replace(this.$store.state.redirectRoute || {name: 'browse'})
          }
        }
      }
    },
  }
</script>

<style lang="scss" scoped>
  .login-page {
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .teaser {
    img {
      height: 2em;
      width: auto;
      opacity: 0.25;
      transition: opacity 200ms;

      &:hover {
        opacity: 1;
      }
    }
  }
</style>
