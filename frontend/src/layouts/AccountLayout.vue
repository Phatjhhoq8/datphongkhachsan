<script setup>
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>

<template>
  <section class="account-page">
    <div class="container account-grid">
      <aside class="account-sidebar">
        <div class="profile-summary">
          <span>{{ auth.displayName.charAt(0).toUpperCase() }}</span>
          <div><strong>{{ auth.displayName }}</strong><small>{{ auth.user?.email }}</small><span class="sidebar-badge">👑 Thành viên</span></div>
        </div>
        <nav aria-label="Tài khoản">
          <RouterLink to="/account">Hồ sơ</RouterLink>
          <RouterLink to="/account/bookings">Lịch sử đặt phòng</RouterLink>
          <RouterLink to="/account/wishlist">Danh sách yêu thích</RouterLink>
        </nav>
      </aside>
      <div class="account-content"><RouterView /></div>
    </div>
  </section>
</template>

<style>
.account-page { min-height:620px; padding:44px 0 70px; background:var(--soft); }
.account-grid { display:grid; grid-template-columns:260px 1fr; gap:28px; }
.account-sidebar,.account-panel { background:#fff; border:1px solid var(--line); border-radius:11px; }
.profile-summary { display:flex; align-items:center; gap:12px; padding:20px; border-bottom:1px solid var(--line); }
.profile-summary>span { display:grid; place-items:center; width:46px; height:46px; flex:none; border-radius:50%; background:#e5f3fd; color:var(--blue); font-size:19px; font-weight:800; }
.profile-summary div { min-width:0; display:flex; flex-direction:column; }.profile-summary strong,.profile-summary small { overflow:hidden; text-overflow:ellipsis; }.profile-summary small { color:var(--muted); }
.account-sidebar nav { display:flex; flex-direction:column; padding:10px; }.account-sidebar nav a { padding:12px; border-radius:7px; font-weight:600; }.account-sidebar nav a:hover,.account-sidebar nav a.router-link-exact-active { color:var(--blue); background:#edf7fd; }
.account-panel { padding:28px; }.account-panel-head { display:flex; justify-content:space-between; align-items:start; margin-bottom:22px; }.account-panel-head h1 { margin:0 0 4px; font-size:27px; }.account-panel-head p { margin:0; color:var(--muted); }
.account-state { min-height:260px; display:grid; place-items:center; text-align:center; color:var(--muted); }.account-state .spinner { margin:auto; }
@media (max-width:760px) { .account-page { padding-top:24px; }.account-grid { grid-template-columns:1fr; }.account-sidebar nav { flex-direction:row; overflow:auto; }.account-sidebar nav a { white-space:nowrap; }.account-panel { padding:21px 17px; } }
.sidebar-badge { align-self:flex-start; margin-top:5px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:2px 8px; border-radius:12px; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:0.3px; display:inline-flex; }
</style>
