import { createHash } from 'node:crypto'

const STAFF_ROLES = new Set(['super_admin', 'hotel_manager', 'receptionist', 'accountant'])

function tokenFromHandshake(socket) {
  const authToken = socket.handshake.auth?.token
  const authorization = socket.handshake.headers.authorization
  const value = authToken || authorization

  if (typeof value !== 'string') return null
  return value.startsWith('Bearer ') ? value.slice(7).trim() : value.trim()
}

export function createAuthenticator({ authUrl, cacheTtlMs, timeoutMs = 5000 }) {
  const cache = new Map()

  return async function authenticate(socket, next) {
    const token = tokenFromHandshake(socket)
    if (!token) return next(new Error('unauthorized'))

    const cacheKey = createHash('sha256').update(token).digest('hex')
    const cached = cache.get(cacheKey)
    if (cached && cached.expiresAt > Date.now()) {
      socket.data.user = cached.user
      return next()
    }

    try {
      const response = await fetch(authUrl, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        signal: AbortSignal.timeout(timeoutMs),
      })

      if (!response.ok) return next(new Error('unauthorized'))

      const body = await response.json()
      const user = body?.data?.user ?? body?.data ?? body?.user
      if (!user || user.status !== 'active' || !STAFF_ROLES.has(user.role)) {
        return next(new Error('forbidden'))
      }

      cache.set(cacheKey, { user, expiresAt: Date.now() + cacheTtlMs })
      socket.data.user = user
      next()
    } catch {
      next(new Error('authentication unavailable'))
    }
  }
}

export function mayJoinHotel(user, hotelId) {
  return user.role === 'super_admin' || Number(user.hotel_id) === hotelId
}
