<script setup>
import { onMounted, ref } from 'vue'
import { api, apiError, responseList } from '../api'

const bookings = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    bookings.value = responseList(await api.get('/me/bookings'))
  } catch (err) {
    error.value = apiError(err, 'Không thể tải lịch sử đặt phòng.')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <article class="account-panel">
    <header class="account-panel-head"><div><h1>Lịch sử đặt phòng</h1><p>Theo dõi những chuyến đi đã và sắp diễn ra.</p></div></header>
    <div v-if="loading" class="booking-skeleton" aria-label="Đang tải lịch sử đặt phòng" aria-busy="true"><i v-for="item in 3" :key="item"></i></div>
    <div v-else-if="error" class="account-state"><p class="auth-message error" role="alert">{{ error }}</p></div>
    <div v-else-if="!bookings.length" class="account-state"><div><h2>Chưa có chuyến đi nào</h2><p>Khách sạn bạn đặt sẽ xuất hiện tại đây.</p><RouterLink class="primary" to="/hotel">Khám phá khách sạn</RouterLink></div></div>
    <div v-else class="account-list">
      <RouterLink v-for="booking in bookings" :key="booking.id || booking.code" class="account-item" :to="`/hotel/booking/${booking.code}`">
        <div><small>Mã đặt phòng</small><strong>{{ booking.code }}</strong></div>
        <div><small>Khách sạn</small><strong>{{ booking.hotel?.name || booking.hotel_name || 'StayGo' }}</strong></div>
        <span>{{ booking.status_label || booking.status }}</span>
      </RouterLink>
    </div>
  </article>
</template>

<style scoped>
.booking-skeleton { display:flex; flex-direction:column; gap:12px; }.booking-skeleton i { height:82px; border-radius:8px; background:linear-gradient(90deg,#edf1f4 25%,#f8fafb 50%,#edf1f4 75%); background-size:200% 100%; animation:shimmer 1.3s infinite; }@keyframes shimmer { to { background-position:-200% 0; } }
.account-list { display:flex; flex-direction:column; gap:11px; }.account-item { display:grid; grid-template-columns:150px 1fr auto; align-items:center; gap:20px; padding:16px; border:1px solid var(--line); border-radius:8px; }.account-item div { display:flex; flex-direction:column; }.account-item small { color:var(--muted); }.account-item>span { color:var(--green); font-size:11px; font-weight:800; text-transform:uppercase; }
@media (max-width:560px) { .account-item { grid-template-columns:1fr auto; }.account-item div:nth-child(2) { grid-row:2; } }
</style>
