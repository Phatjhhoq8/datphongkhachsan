<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const open = ref(false)
const accountOpen = ref(false)
const auth = useAuthStore()
const router = useRouter()
const isTeamMember = computed(() => auth.isStaff)

function closeMenus() {
  open.value = false
  accountOpen.value = false
}

async function logout() {
  closeMenus()
  await auth.logout()
  router.push('/hotel')
}
</script>

<template>
  <a class="skip-link" href="#main-content">Bỏ qua đến nội dung</a>
  <header class="site-header">
    <div class="header-top container">
      <RouterLink class="brand" to="/hotel" aria-label="StayGo trang chủ">
        <span class="brand-mark">S</span><span>StayGo</span>
      </RouterLink>
      <button class="menu-button" aria-label="Mở menu" :aria-expanded="open" @click="open = !open">☰</button>
      <div class="header-actions" :class="{ open }" @click="closeMenus">
        <button class="text-action">VND | VI</button>
        <RouterLink to="/hotel#offers">Khuyến mãi</RouterLink>
        <a href="mailto:support@staygo.vn">Hỗ trợ</a>
        <template v-if="!auth.isAuthenticated">
          <RouterLink class="login-button" to="/login">Đăng nhập</RouterLink>
          <RouterLink class="primary small" to="/register">Đăng ký</RouterLink>
        </template>
        <div v-else class="account-menu" @click.stop>
          <button class="account-trigger" :aria-expanded="accountOpen" @click="accountOpen = !accountOpen">
            <span class="account-avatar">{{ auth.displayName.charAt(0).toUpperCase() }}</span>
            <span>{{ auth.displayName }}</span>
            <span aria-hidden="true">⌄</span>
          </button>
          <div v-if="accountOpen" class="account-dropdown" @click="closeMenus">
            <RouterLink to="/account/bookings">Lịch sử đặt phòng</RouterLink>
            <RouterLink to="/account/wishlist">Danh sách yêu thích</RouterLink>
            <RouterLink to="/account">Hồ sơ tài khoản</RouterLink>
            <RouterLink v-if="isTeamMember" to="/admin">Trang quản trị</RouterLink>
            <button type="button" @click="logout">Đăng xuất</button>
          </div>
        </div>
      </div>
    </div>
    <nav class="main-nav" aria-label="Điều hướng chính">
      <div class="container nav-inner">
        <RouterLink class="active-nav" to="/hotel">Khách sạn</RouterLink>
        <RouterLink to="/hotel#offers">Ưu đãi hôm nay</RouterLink>
        <RouterLink to="/hotel#destinations">Điểm đến nổi bật</RouterLink>
        <RouterLink to="/hotel#how-it-works">Cách đặt phòng</RouterLink>
      </div>
    </nav>
  </header>
</template>

<style scoped>
.account-menu { position:relative; }
.account-trigger { display:flex; align-items:center; gap:8px; border:0; background:none; color:var(--ink); font-weight:700; cursor:pointer; }
.account-avatar { display:grid; place-items:center; width:32px; height:32px; border-radius:50%; background:#e7f4fd; color:var(--blue); }
.account-dropdown { position:absolute; right:0; top:44px; display:flex; min-width:210px; padding:8px; flex-direction:column; background:#fff; border:1px solid var(--line); border-radius:9px; box-shadow:0 10px 30px #13243a26; }
.account-dropdown a,.account-dropdown button { padding:10px 12px; border:0; border-radius:6px; background:none; color:var(--ink); text-align:left; white-space:nowrap; cursor:pointer; }
.account-dropdown a:hover,.account-dropdown button:hover { color:var(--blue); background:var(--soft); }
@media (max-width:900px) {
  .header-actions { min-width:250px; }
  .account-trigger { width:100%; }
  .account-dropdown { position:static; margin-top:8px; box-shadow:none; }
}
</style>
