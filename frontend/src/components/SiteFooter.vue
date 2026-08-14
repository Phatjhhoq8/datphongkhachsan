<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, responseList } from '../api'

const hotels = ref([])
const cities = computed(() => [...new Set(hotels.value.map(hotel => hotel.city).filter(Boolean))].sort())

onMounted(async () => {
  try { hotels.value = responseList(await api.get('/hotels')) } catch { hotels.value = [] }
})
</script>

<template>
  <footer class="site-footer">
    <div class="container footer-grid">
      <div><div class="brand footer-brand"><span class="brand-mark">S</span><span>StayGo</span></div><p>Đặt nơi nghỉ phù hợp, hành trình thêm trọn vẹn.</p></div>
      <div><strong>Về StayGo</strong><a href="/hotel#how-it-works">Cách đặt phòng</a><a href="mailto:support@staygo.vn">Liên hệ</a></div>
      <div><strong>Hỗ trợ</strong><a href="mailto:support@staygo.vn">Trung tâm trợ giúp</a><a href="/hotel/booking/tra-cuu">Quản lý đặt phòng</a></div>
      <div v-if="cities.length"><strong>Điểm đến</strong><RouterLink v-for="city in cities" :key="city" :to="{ path: '/hotel/search', query: { location: city } }">{{ city }}</RouterLink></div>
    </div>
    <div class="container copyright">© 2026 StayGo. Giá hiển thị bằng VND.</div>
  </footer>
</template>
