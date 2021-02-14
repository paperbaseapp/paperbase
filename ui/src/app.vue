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
