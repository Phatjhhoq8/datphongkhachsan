<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, apiError } from '../api'
import AuthShell from '../components/AuthShell.vue'

const router = useRouter()
const debugOtp = sessionStorage.getItem('staygo_debug_otp') || ''
const form = reactive({ email: sessionStorage.getItem('staygo_reset_email') || '', otp: '', password: '', password_confirmation: '' })
const otpPlaceholder = computed(() => debugOtp ? `Mã OTP demo: ${debugOtp}` : 'Nhập mã OTP')
const loading = ref(false)
const error = ref('')

async function submit() {
  if (form.password !== form.password_confirmation) {
    error.value = 'Mật khẩu xác nhận chưa khớp.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await api.post('/auth/reset-password', form)
    sessionStorage.removeItem('staygo_reset_email')
    sessionStorage.removeItem('staygo_debug_otp')
    router.push({ path: '/login', query: { reset: 'success' } })
  } catch (err) {
    error.value = apiError(err, 'Mã OTP không hợp lệ hoặc đã hết hạn.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthShell title="Đặt lại mật khẩu" subtitle="Nhập mã OTP và chọn mật khẩu mới cho tài khoản.">
    <form class="auth-form" @submit.prevent="submit">
      <label><span>Email</span><input v-model.trim="form.email" type="email" autocomplete="email" required></label>
      <label><span>Mã OTP</span><input v-model.trim="form.otp" inputmode="numeric" autocomplete="one-time-code" required :placeholder="otpPlaceholder"></label>
      <label><span>Mật khẩu mới</span><input v-model="form.password" type="password" autocomplete="new-password" required minlength="8" placeholder="Tối thiểu 8 ký tự"></label>
      <label><span>Xác nhận mật khẩu</span><input v-model="form.password_confirmation" type="password" autocomplete="new-password" required minlength="8" placeholder="Nhập lại mật khẩu"></label>
      <p v-if="error" class="auth-message error" role="alert">{{ error }}</p>
      <button class="primary" :disabled="loading">{{ loading ? 'Đang cập nhật...' : 'Đặt lại mật khẩu' }}</button>
    </form>
  </AuthShell>
</template>
