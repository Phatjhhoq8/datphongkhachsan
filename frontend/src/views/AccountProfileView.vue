<script setup>
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>

<template>
  <article class="account-panel">
    <header class="account-panel-head"><div><h1>Hồ sơ của tôi</h1><p>Thông tin tài khoản StayGo của bạn.</p></div></header>
    <dl class="profile-details">
      <div><dt>Họ và tên</dt><dd>{{ auth.displayName }}</dd></div>
      <div><dt>Email</dt><dd>{{ auth.user?.email || 'Chưa cập nhật' }}</dd></div>
      <div><dt>Số điện thoại</dt><dd>{{ auth.user?.phone || auth.user?.phone_number || 'Chưa cập nhật' }}</dd></div>
    </dl>
  </article>
</template>

<style scoped>
.profile-details { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin:0; }.profile-details div { padding:17px; border:1px solid var(--line); border-radius:8px; }.profile-details dt { color:var(--muted); font-size:11px; }.profile-details dd { margin:4px 0 0; font-weight:700; }
@media (max-width:560px) { .profile-details { grid-template-columns:1fr; } }
</style>
