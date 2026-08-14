import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  headers: { Accept: 'application/json' },
  timeout: 15000,
})

const TOKEN_KEY = 'staygo_auth_token'
let unauthorizedHandler = null
let handlingUnauthorized = false

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers ||= {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const requestHadToken = Boolean(error.config?.headers?.Authorization)
    const publicAuthPaths = ['/auth/login', '/auth/register', '/auth/forgot-password', '/auth/reset-password']
    const isPublicAuthRequest = publicAuthPaths.includes(String(error.config?.url || ''))
    if (error.response?.status === 401 && requestHadToken && !isPublicAuthRequest && !handlingUnauthorized) {
      handlingUnauthorized = true
      try { unauthorizedHandler?.() } finally { handlingUnauthorized = false }
    }
    return Promise.reject(error)
  },
)

export function setUnauthorizedHandler(handler) {
  unauthorizedHandler = handler
}

export { TOKEN_KEY }

export function responseData(response) {
  return response?.data?.data ?? response?.data
}

export function responseList(response) {
  const data = responseData(response)
  if (Array.isArray(data)) return data
  return data?.hotels ?? data?.bookings ?? data?.wishlist ?? data?.results ?? data?.items ?? []
}

export function apiError(error, fallback = 'Không thể tải dữ liệu. Vui lòng thử lại.') {
  const errors = error?.response?.data?.errors
  if (errors && typeof errors === 'object') return Object.values(errors).flat().join(' ')
  return error?.response?.data?.message || fallback
}
