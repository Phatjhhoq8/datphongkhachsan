<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import SiteHeader from './components/SiteHeader.vue'
import SiteFooter from './components/SiteFooter.vue'
import ChatWidget from './components/ChatWidget.vue'

const route = useRoute()
const standalone = computed(() => route.matched.some(record => record.meta.standalone))

const showScrollTop = ref(false)

function handleScroll() {
  showScrollTop.value = window.scrollY > 300
}

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <div class="app-shell">
    <SiteHeader v-if="!standalone" />
    <main id="main-content"><RouterView /></main>
    <SiteFooter v-if="!standalone" />
    <ChatWidget v-if="!standalone" />
    <button
      v-if="!standalone"
      class="scroll-to-top"
      :class="{ show: showScrollTop }"
      type="button"
      aria-label="Quay lại đầu trang"
      @click="scrollToTop"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
    </button>
  </div>
</template>
