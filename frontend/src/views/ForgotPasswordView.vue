<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, apiError, responseData } from '../api'
import AuthShell from '../components/AuthShell.vue'

const router = useRouter()
const email = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.post('/auth/forgot-password', { email: email.value })
    const data = responseData(response)
    const debugOtp = data?.debug_otp ?? response.data?.debug_otp
    sessionStorage.setItem('staygo_reset_email', email.value)
    if (debugOtp != null) sessionStorage.setItem('staygo_debug_otp', String(debugOtp))
    else sessionStorage.removeItem('staygo_debug_otp')
    router.push('/reset-password')
  } catch (err) {
    error.value = apiError(err, 'Không thể gửi mã xác thực. Vui lòng thử lại.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthShell title="Quên mật khẩu?" subtitle="Nhập email đã đăng ký để nhận mã OTP đặt lại mật khẩu.">
    <form class="auth-form" @submit.prevent="submit">
      <label><span>Email</span><input v-model.trim="email" type="email" autocomplete="email" required placeholder="ban@example.com"></label>
      <p v-if="error" class="auth-message error" role="alert">{{ error }}</p>
      <button class="primary" :disabled="loading">{{ loading ? 'Đang gửi...' : 'Gửi mã OTP' }}</button>
    </form>
    <p class="auth-switch"><RouterLink to="/login">Quay lại đăng nhập</RouterLink></p>
  </AuthShell>
</template>
