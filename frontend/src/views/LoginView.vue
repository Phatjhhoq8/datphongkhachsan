<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiError } from '../api'
import { useAuthStore } from '../stores/auth'
import AuthShell from '../components/AuthShell.vue'
import SocialButtons from '../components/SocialButtons.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const form = reactive({ email: '', password: '' })
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(form)
    const destination = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/')
      ? route.query.redirect
      : auth.isStaff ? '/admin' : '/account'
    router.push(destination)
  } catch (err) {
    error.value = apiError(err, err.message || 'Đăng nhập không thành công.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthShell title="Chào mừng trở lại" subtitle="Đăng nhập để tiếp tục hành trình của bạn.">
    <form class="auth-form" @submit.prevent="submit">
      <label><span>Email</span><input v-model.trim="form.email" type="email" autocomplete="email" required placeholder="ban@example.com"></label>
      <label><span>Mật khẩu</span><input v-model="form.password" type="password" autocomplete="current-password" required minlength="6" placeholder="Nhập mật khẩu"></label>
      <div class="auth-row"><span></span><RouterLink to="/forgot-password">Quên mật khẩu?</RouterLink></div>
      <p v-if="error" class="auth-message error" role="alert">{{ error }}</p>
      <button class="primary" :disabled="loading">{{ loading ? 'Đang đăng nhập...' : 'Đăng nhập' }}</button>
    </form>
    <SocialButtons />
    <p class="auth-switch">Chưa có tài khoản? <RouterLink to="/register">Đăng ký ngay</RouterLink></p>
  </AuthShell>
</template>
