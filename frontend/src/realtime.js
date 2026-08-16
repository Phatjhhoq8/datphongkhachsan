import { io } from 'socket.io-client'

const TOKEN_KEY = 'staygo_auth_token'

export function createAdminRoomSocket({ hotelId, token, socketUrl } = {}) {
  if (typeof hotelId !== 'string' || hotelId.length === 0) {
    throw new TypeError('hotelId must be a non-empty string')
  }

  const resolvedToken = token || localStorage.getItem(TOKEN_KEY)
  const socket = io(socketUrl || import.meta.env.VITE_SOCKET_URL || 'http://localhost:3001', {
    auth: { token: resolvedToken ? `Bearer ${resolvedToken}` : '' },
  })

  socket.on('connect', () => {
    socket.emit('hotel:join', hotelId)
  })

  return socket
}

export function createConversationSocket({ conversationId, conversationToken, token, socketUrl, onJoin } = {}) {
  if (typeof conversationId !== 'string' || conversationId.length === 0) {
    throw new TypeError('conversationId must be a non-empty string')
  }

  const staffToken = token || localStorage.getItem(TOKEN_KEY)
  const socket = io(socketUrl || import.meta.env.VITE_SOCKET_URL || 'http://localhost:3001', {
    auth: conversationToken
      ? { conversationId, conversationToken }
      : { token: staffToken ? `Bearer ${staffToken}` : '' },
  })
  socket.on('connect', () => {
    socket.emit('conversation:join', conversationId, (ack) => {
      if (typeof onJoin === 'function') onJoin(ack)
    })
  })
  return socket
}
