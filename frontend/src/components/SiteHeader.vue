<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const open = ref(false)
const accountOpen = ref(false)
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const isTeamMember = computed(() => auth.isStaff)

const activeSection = ref('top')
const isScrolled = ref(false)

function closeMenus() {
  open.value = false
  accountOpen.value = false
}

async function logout() {
  closeMenus()
  await auth.logout()
  router.push('/hotel')
}

function handleNavClick(e, id) {
  const path = window.location.pathname
  if (path === '/hotel' || path === '/') {
    e.preventDefault()
    if (id === 'top') {
      window.scrollTo({ top: 0, behavior: 'smooth' })
      history.pushState(null, null, '/hotel')
    } else {
      const el = document.getElementById(id)
      if (el) {
        const y = el.getBoundingClientRect().top + window.pageYOffset - 15
        window.scrollTo({ top: y, behavior: 'smooth' })
        history.pushState(null, null, `#${id}`)
      }
    }
  }
}

function handleScroll() {
  isScrolled.value = window.scrollY > 20

  const path = window.location.pathname
  if (path !== '/hotel' && path !== '/') {
    activeSection.value = ''
    return
  }

  const scrollPosition = window.scrollY
  if (scrollPosition < 200) {
    activeSection.value = 'top'
    return
  }

  const sections = ['offers', 'destinations', 'how-it-works']
  let currentActive = 'top'

  for (const id of sections) {
    const el = document.getElementById(id)
    if (el) {
      const top = el.offsetTop - 150
      if (scrollPosition >= top) {
        currentActive = id
      }
    }
  }

  activeSection.value = currentActive
}

function handleClickOutside(e) {
  const menu = document.querySelector('.account-menu')
  if (menu && !menu.contains(e.target)) {
    accountOpen.value = false
  }
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true })
  window.addEventListener('click', handleClickOutside)
  handleScroll()
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
  window.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <a class="skip-link" href="#main-content">Bỏ qua đến nội dung</a>
  <header class="site-header" :class="{ 'is-scrolled': isScrolled }">
    <div class="header-top container">
      <RouterLink class="brand" to="/hotel" aria-label="StayGo trang chủ">
        <span class="brand-mark">S</span><span>StayGo</span>
      </RouterLink>
      <button class="menu-button" aria-label="Mở menu" :aria-expanded="open" @click="open = !open">☰</button>
      <div class="header-actions" :class="{ open }" @click="closeMenus">
        <template v-if="!auth.isAuthenticated">
          <RouterLink class="login-button" to="/login">Đăng nhập</RouterLink>
          <RouterLink class="primary small" to="/register">Đăng ký</RouterLink>
        </template>
        <div v-else class="account-menu" @click.stop>
          <button class="account-trigger" :aria-expanded="accountOpen" @click="accountOpen = !accountOpen">
            <span class="account-avatar">{{ auth.displayName.charAt(0).toUpperCase() }}</span>
            <span>{{ auth.displayName }}</span>
            <svg class="chevron-icon" :class="{ 'is-active': accountOpen }" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="m6 9 6 6 6-6"/>
            </svg>
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
        <a :class="{ 'active-nav': activeSection === 'top' }" href="/hotel" @click="handleNavClick($event, 'top')">Khách sạn</a>
        <a :class="{ 'active-nav': activeSection === 'offers' }" href="/hotel#offers" @click="handleNavClick($event, 'offers')">Ưu đãi hôm nay</a>
        <a :class="{ 'active-nav': activeSection === 'destinations' }" href="/hotel#destinations" @click="handleNavClick($event, 'destinations')">Điểm đến nổi bật</a>
        <a :class="{ 'active-nav': activeSection === 'how-it-works' }" href="/hotel#how-it-works" @click="handleNavClick($event, 'how-it-works')">Cách đặt phòng</a>
        <RouterLink :class="{ 'active-nav': route.path === '/hotel/booking/tra-cuu' }" to="/hotel/booking/tra-cuu">Tra cứu đặt phòng</RouterLink>
      </div>
    </nav>
  </header>
</template>

<style scoped>
.account-menu { position:relative; }
.account-trigger { display:flex; align-items:center; gap:8px; border:0; background:none; color:var(--ink); font-weight:700; cursor:pointer; }
.chevron-icon { margin-left:1px; flex:none; opacity:0.85; transition:transform 0.2s ease; }
.chevron-icon.is-active { transform:rotate(180deg); }
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
