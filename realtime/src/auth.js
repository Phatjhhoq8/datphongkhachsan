import { createHash } from 'node:crypto'

const STAFF_ROLES = new Set(['super_admin', 'hotel_manager', 'receptionist', 'accountant'])

function tokenFromHandshake(socket) {
  const authToken = socket.handshake.auth?.token
  const authorization = socket.handshake.headers.authorization
  const value = authToken || authorization

  if (typeof value !== 'string') return null
  return value.startsWith('Bearer ') ? value.slice(7).trim() : value.trim()
}

function guestCredentials(socket) {
  const token = socket.handshake.auth?.conversationToken
  const conversationId = socket.handshake.auth?.conversationId
  return typeof token === 'string' && token.length > 0 && typeof conversationId === 'string' && conversationId.length > 0
    ? { token, conversationId }
    : null
}

export function createAuthenticator({ authUrl, chatAuthUrl, cacheTtlMs, timeoutMs = 5000 }) {
  const cache = new Map()

  return async function authenticate(socket, next) {
    const guest = guestCredentials(socket)
    if (guest) {
      try {
        const response = await fetch(chatAuthUrl, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Conversation-Token': guest.token,
          },
          body: JSON.stringify({ conversation_id: guest.conversationId }),
          signal: AbortSignal.timeout(timeoutMs),
        })
        if (!response.ok) return next(new Error('unauthorized'))

        const principal = (await response.json())?.data
        if (principal?.kind !== 'guest' || principal.conversation_id !== guest.conversationId) {
          return next(new Error('forbidden'))
        }
        socket.data.principal = principal
        return next()
      } catch {
        return next(new Error('authentication unavailable'))
      }
    }

    const token = tokenFromHandshake(socket)
    if (!token) return next(new Error('unauthorized'))

    const cacheKey = createHash('sha256').update(token).digest('hex')
    const cached = cache.get(cacheKey)
    if (cached && cached.expiresAt > Date.now()) {
      socket.data.user = cached.user
      socket.data.principal = { kind: 'staff', user: cached.user }
      socket.data.bearerToken = token
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
      socket.data.principal = { kind: 'staff', user }
      socket.data.bearerToken = token
      next()
    } catch {
      next(new Error('authentication unavailable'))
    }
  }
}

export function createConversationAuthorizer({ chatAuthUrl, timeoutMs = 5000 }) {
  return async function authorize(socket, conversationId) {
    if (typeof conversationId !== 'string' || conversationId.length === 0) return null
    if (socket.data.principal?.kind === 'guest') {
      return mayJoinConversation(socket.data.principal, conversationId) ? socket.data.principal : null
    }
    if (!socket.data.bearerToken) return null

    try {
      const response = await fetch(chatAuthUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          Authorization: `Bearer ${socket.data.bearerToken}`,
        },
        body: JSON.stringify({ conversation_id: conversationId }),
        signal: AbortSignal.timeout(timeoutMs),
      })
      if (!response.ok) return null
      const principal = (await response.json())?.data
      return principal?.kind === 'staff' && principal.conversation_id === conversationId ? principal : null
    } catch {
      return null
    }
  }
}

export function mayJoinConversation(principal, conversationId) {
  if (typeof conversationId !== 'string' || conversationId.length === 0) return false
  if (principal?.conversation_id === conversationId) return true
  return principal?.kind === 'staff' && Array.isArray(principal.conversation_ids) && principal.conversation_ids.includes(conversationId)
}

export function mayJoinHotel(user, hotelId) {
  if (typeof hotelId !== 'string' || hotelId.length === 0) return false
  return user.role === 'super_admin' || user.hotel_id === hotelId
}
