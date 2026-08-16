import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api, responseData, setUnauthorizedHandler, TOKEN_KEY } from '../api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(TOKEN_KEY))
  const user = ref(null)
  const bootstrapped = ref(false)
  let bootstrapPromise
  const isAuthenticated = computed(() => Boolean(token.value && user.value))
  const displayName = computed(() => user.value?.name || user.value?.full_name || user.value?.email || 'Tài khoản')
  const roles = computed(() => {
    const values = Array.isArray(user.value?.roles) ? user.value.roles : [user.value?.role]
    return values.filter(Boolean).map(role => String(role?.name || role).toLowerCase())
  })
  const isStaff = computed(() => roles.value.some(role => ['super_admin', 'hotel_manager', 'receptionist', 'accountant', 'admin', 'staff'].includes(role)))

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem(TOKEN_KEY)
  }

  function saveSession(payload) {
    const data = responseData(payload)
    const nextToken = data?.token || data?.access_token
    if (!nextToken) throw new Error('Phản hồi đăng nhập không có token.')
    token.value = nextToken
    user.value = data?.user || null
    localStorage.setItem(TOKEN_KEY, nextToken)
  }

  async function fetchMe() {
    const response = await api.get('/auth/me')
    const data = responseData(response)
    user.value = data?.user || data
    return user.value
  }

  async function bootstrap() {
    if (bootstrapped.value) return
    if (bootstrapPromise) return bootstrapPromise
    setUnauthorizedHandler(clearSession)
    bootstrapPromise = (async () => {
      if (token.value) {
        try {
          await fetchMe()
        } catch (error) {
          if (error.response?.status !== 401) console.error('Không thể tải tài khoản StayGo.', error)
        }
      }
      bootstrapped.value = true
    })()
    return bootstrapPromise
  }

  async function login(credentials) {
    const response = await api.post('/auth/login', credentials)
    saveSession(response)
    if (!user.value) await fetchMe()
    return user.value
  }

  async function register(details) {
    const response = await api.post('/auth/register', details)
    saveSession(response)
    if (!user.value) await fetchMe()
    return user.value
  }

  async function exchangeOAuth(code) {
    const response = await api.post('/auth/oauth/exchange', { code })
    saveSession(response)
    if (!user.value) await fetchMe()
    return user.value
  }

  async function logout() {
    try {
      if (token.value) await api.post('/auth/logout')
    } catch (error) {
      if (error.response?.status !== 401) console.error('Không thể kết thúc phiên trên máy chủ.', error)
    } finally {
      clearSession()
    }
  }

  return { token, user, roles, bootstrapped, isAuthenticated, isStaff, displayName, bootstrap, fetchMe, login, register, exchangeOAuth, logout }
})
