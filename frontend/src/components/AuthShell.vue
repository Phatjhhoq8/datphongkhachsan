<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <section class="auth-page">
    <div class="auth-aside">
      <div>
        <span class="auth-logo">S</span>
        <p class="eyebrow light">StayGo member</p>
        <h2>Mỗi hành trình bắt đầu bằng một nơi ở thật vừa ý.</h2>
        <p>Đăng nhập để quản lý chuyến đi, lưu khách sạn yêu thích và nhận ưu đãi dành riêng cho bạn.</p>
      </div>
    </div>
    <div class="auth-card">
      <p class="eyebrow">Tài khoản StayGo</p>
      <h1>{{ title }}</h1>
      <p class="auth-subtitle">{{ subtitle }}</p>
      <slot />
    </div>
  </section>
</template>

<style>
.auth-page { width:min(1040px,calc(100% - 32px)); min-height:610px; margin:48px auto 70px; display:grid; grid-template-columns:.9fr 1.1fr; overflow:hidden; border:1px solid var(--line); border-radius:16px; background:#fff; box-shadow:0 16px 45px #13243a16; }
.auth-aside { display:flex; align-items:flex-end; padding:48px; color:#fff; background:linear-gradient(145deg,#075c9fe8,#0877cce8),url('/images/hotel-hero.jpg') center/cover; }
.auth-aside h2 { font-size:28px; margin:12px 0; line-height:1.35; text-wrap:balance; }
.auth-aside p:last-child { color:#d8efff; }
.auth-logo { display:grid; place-items:center; width:46px; height:46px; border-radius:14px 14px 14px 3px; background:#fff; color:var(--blue); font-size:25px; font-weight:900; }
.auth-card { align-self:center; padding:48px 64px; }
.auth-card h1 { margin-bottom:8px; }
.auth-subtitle { color:var(--muted); margin-bottom:26px; }
.auth-form { display:flex; flex-direction:column; gap:15px; }
.auth-form label>span { display:block; margin-bottom:5px; color:#3c4c60; font-size:12px; font-weight:700; }
.auth-form .primary { width:100%; margin-top:4px; }
.auth-row { display:flex; justify-content:space-between; gap:15px; }
.auth-row>a,.auth-switch a { color:var(--blue); font-weight:700; }
.auth-message { padding:11px 12px; margin:0; border-radius:7px; font-size:12px; }
.auth-message.error { color:#a72d2d; background:#fff0f0; border:1px solid #ebcaca; }
.auth-message.success { color:#126a40; background:#ebf8f1; border:1px solid #bfe4d0; }
.social-divider { display:flex; align-items:center; gap:12px; margin:23px 0 15px; color:var(--muted); font-size:11px; }
.social-divider::before,.social-divider::after { content:''; height:1px; flex:1; background:var(--line); }
.social-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.social-button { padding:10px; border:1px solid var(--line); border-radius:7px; background:#f6f8fa; color:#7a8795; cursor:not-allowed; }
.social-button small { display:block; font-size:9px; }
.auth-switch { margin:22px 0 0; text-align:center; color:var(--muted); }
@media (max-width:760px) { .auth-page { display:block; margin:24px auto 45px; }.auth-aside { display:none; }.auth-card { padding:38px 26px; }.auth-card h1 { font-size:28px; } }
@media (max-width:420px) { .social-grid { grid-template-columns:1fr; }.auth-row { flex-direction:column; } }
</style>
