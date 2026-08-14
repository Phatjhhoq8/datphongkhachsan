<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiError } from '../api'
import { useAuthStore } from '../stores/auth'
import AuthShell from '../components/AuthShell.vue'
import SocialButtons from '../components/SocialButtons.vue'

const auth = useAuthStore()
const router = useRouter()
const form = reactive({ name: '', email: '', phone: '', password: '', password_confirmation: '' })
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
    await auth.register(form)
    router.push('/account')
  } catch (err) {
    error.value = apiError(err, err.message || 'Không thể tạo tài khoản.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthShell title="Tạo tài khoản" subtitle="Gia nhập StayGo và quản lý mọi chuyến đi tại một nơi.">
    <form class="auth-form" @submit.prevent="submit">
      <label><span>Họ và tên</span><input v-model.trim="form.name" autocomplete="name" required placeholder="Nguyễn Văn An"></label>
      <label><span>Email</span><input v-model.trim="form.email" type="email" autocomplete="email" required placeholder="ban@example.com"></label>
      <label><span>Số điện thoại</span><input v-model.trim="form.phone" type="tel" autocomplete="tel" placeholder="0901 234 567"></label>
      <label><span>Mật khẩu</span><input v-model="form.password" type="password" autocomplete="new-password" required minlength="8" placeholder="Tối thiểu 8 ký tự"></label>
      <label><span>Xác nhận mật khẩu</span><input v-model="form.password_confirmation" type="password" autocomplete="new-password" required minlength="8" placeholder="Nhập lại mật khẩu"></label>
      <p v-if="error" class="auth-message error" role="alert">{{ error }}</p>
      <button class="primary" :disabled="loading">{{ loading ? 'Đang tạo tài khoản...' : 'Đăng ký' }}</button>
    </form>
    <SocialButtons />
    <p class="auth-switch">Đã có tài khoản? <RouterLink to="/login">Đăng nhập</RouterLink></p>
  </AuthShell>
</template>
