<script setup>
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import SiteHeader from './components/SiteHeader.vue'
import SiteFooter from './components/SiteFooter.vue'

const route = useRoute()
const standalone = computed(() => route.matched.some(record => record.meta.standalone))
</script>

<template>
  <div class="app-shell">
    <SiteHeader v-if="!standalone" />
    <main id="main-content"><RouterView /></main>
    <SiteFooter v-if="!standalone" />
  </div>
</template>
