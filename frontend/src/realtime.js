import { io } from 'socket.io-client'

const TOKEN_KEY = 'staygo_auth_token'

export function createAdminRoomSocket({ hotelId, token, socketUrl } = {}) {
  const resolvedToken = token || localStorage.getItem(TOKEN_KEY)
  const socket = io(socketUrl || import.meta.env.VITE_SOCKET_URL || 'http://localhost:3001', {
    auth: { token: resolvedToken ? `Bearer ${resolvedToken}` : '' },
  })

  socket.on('connect', () => {
    socket.emit('hotel:join', hotelId)
  })

  return socket
}
