<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiError } from '../api'
import { useAuthStore } from '../stores/auth'
import AuthShell from '../components/AuthShell.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const error = ref('')

onMounted(async () => {
  const code = typeof route.query.code === 'string' ? route.query.code : ''
  if (!code) {
    error.value = route.query.error === 'unverified_email'
      ? 'Nhà cung cấp chưa xác minh email. Không thể dùng email này để đăng nhập.'
      : 'Không thể hoàn tất đăng nhập mạng xã hội.'
    return
  }

  try {
    window.history.replaceState({}, '', route.path)
    await auth.exchangeOAuth(code)
    await router.replace(auth.isStaff ? '/admin' : '/account')
  } catch (err) {
    error.value = apiError(err, 'Mã đăng nhập đã hết hạn hoặc đã được sử dụng.')
  }
})
</script>

<template>
  <AuthShell title="Đang xác thực" subtitle="StayGo đang hoàn tất phiên đăng nhập an toàn.">
    <p v-if="error" class="auth-message error" role="alert">{{ error }}</p>
    <p v-else class="auth-message">Vui lòng chờ trong giây lát...</p>
    <RouterLink v-if="error" class="primary callback-link" to="/login">Quay lại đăng nhập</RouterLink>
  </AuthShell>
</template>

<style scoped>
.callback-link { display: block; margin-top: 18px; text-align: center; }
</style>
