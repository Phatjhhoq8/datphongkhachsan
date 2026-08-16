<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, responseList } from '../api'

const destinations = ref([])
const route = useRoute()

onMounted(async () => {
  try {
    destinations.value = responseList(await api.get('/destinations'))
  } catch {
    destinations.value = []
  }
})

function handleNavClick(e, id) {
  const path = window.location.pathname
  if (path === '/hotel' || path === '/') {
    e.preventDefault()
    const el = document.getElementById(id)
    if (el) {
      const y = el.getBoundingClientRect().top + window.pageYOffset - 15
      window.scrollTo({ top: y, behavior: 'smooth' })
      history.pushState(null, null, `#${id}`)
    }
  }
}
</script>

<template>
  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <div class="brand footer-brand">
          <span class="brand-mark">S</span><span>StayGo</span>
        </div>
        <p>Đặt nơi nghỉ phù hợp, hành trình thêm trọn vẹn.</p>
      </div>
      <div>
        <strong>Về StayGo</strong>
        <a href="/hotel#how-it-works" @click="handleNavClick($event, 'how-it-works')">Cách đặt phòng</a>
        <a href="tel:19006868">📞 Hotline: 1900 6868</a>
        <a href="mailto:support@staygo.vn">📧 Email: support@staygo.vn</a>
        <a href="https://facebook.com/staygo.vn" target="_blank" rel="noopener">🌐 Facebook: StayGo Việt Nam</a>
      </div>
      <div>
        <strong>Hỗ trợ</strong>
        <a href="mailto:support@staygo.vn">Trung tâm trợ giúp</a>
        <RouterLink to="/hotel/booking/tra-cuu">Quản lý đặt phòng</RouterLink>
      </div>
      <div v-if="destinations.length">
        <strong>Điểm đến</strong>
        <RouterLink
          v-for="dest in destinations"
          :key="dest.name"
          :to="{ path: '/hotel/search', query: { location: dest.name } }"
        >
          {{ dest.name }}
        </RouterLink>
      </div>
    </div>
    <div class="container copyright">© 2026 StayGo - Nền tảng đặt phòng khách sạn trực tuyến tin cậy, tiện lợi và trọn vẹn hành trình.</div>
  </footer>
</template>
