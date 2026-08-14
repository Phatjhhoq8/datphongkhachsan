<script setup>
import { onMounted, ref } from 'vue'
import { api, apiError, responseList } from '../api'

const hotels = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    hotels.value = responseList(await api.get('/wishlist'))
  } catch (err) {
    error.value = apiError(err, 'Không thể tải danh sách yêu thích.')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <article class="account-panel">
    <header class="account-panel-head"><div><h1>Danh sách yêu thích</h1><p>Những nơi nghỉ bạn muốn quay lại xem.</p></div></header>
    <div v-if="loading" class="account-state"><div><div class="spinner"></div><p>Đang tải danh sách...</p></div></div>
    <div v-else-if="error" class="account-state"><p class="auth-message error" role="alert">{{ error }}</p></div>
    <div v-else-if="!hotels.length" class="account-state"><div><h2>Chưa lưu khách sạn nào</h2><p>Nhấn yêu thích tại trang khách sạn để lưu vào đây.</p><RouterLink class="primary" to="/hotel">Tìm nơi lưu trú</RouterLink></div></div>
    <div v-else class="wishlist-grid">
      <RouterLink v-for="item in hotels" :key="item.id" :to="`/hotel/${(item.hotel ?? item.room_type?.hotel)?.slug}`">
        <img :src="(item.hotel ?? item.room_type?.hotel)?.image || (item.hotel ?? item.room_type?.hotel)?.cover_image || '/images/hotel-hero.jpg'" :alt="(item.hotel ?? item.room_type?.hotel)?.name">
        <div><strong>{{ (item.hotel ?? item.room_type?.hotel)?.name }}</strong><small>{{ (item.hotel ?? item.room_type?.hotel)?.city || (item.hotel ?? item.room_type?.hotel)?.address }}</small></div>
      </RouterLink>
    </div>
  </article>
</template>

<style scoped>
.wishlist-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }.wishlist-grid>a { overflow:hidden; border:1px solid var(--line); border-radius:9px; }.wishlist-grid img { width:100%; height:150px; object-fit:cover; }.wishlist-grid div { display:flex; flex-direction:column; padding:14px; }.wishlist-grid small { color:var(--muted); }
@media (max-width:560px) { .wishlist-grid { grid-template-columns:1fr; } }
</style>
