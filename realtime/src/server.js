import { createServer } from 'node:http'
import { createClient } from 'redis'
import { Server } from 'socket.io'
import { createApp } from './app.js'
import { createAuthenticator, createConversationAuthorizer, mayJoinHotel } from './auth.js'
import { parseDomainEvent } from './events.js'

const port = Number(process.env.PORT || 3001)
const frontendUrl = process.env.FRONTEND_URL || 'http://localhost:3000'
const redisUrl = process.env.REDIS_URL || 'redis://redis:6379'
const channel = process.env.REDIS_CHANNEL || 'staygo.events'
const authUrl = process.env.LARAVEL_AUTH_URL || 'http://backend:8000/api/v1/auth/me'
const chatAuthUrl = process.env.LARAVEL_CHAT_AUTH_URL || 'http://backend:8000/api/v1/chat/socket-auth'
const authCacheTtlMs = Number(process.env.AUTH_CACHE_TTL_MS || 15000)

const subscriber = createClient({ url: redisUrl })
subscriber.on('error', (error) => console.error('Redis subscriber error:', error.message))

const app = createApp(() => (subscriber.isReady ? 'ready' : 'disconnected'))
const httpServer = createServer(app)
const io = new Server(httpServer, {
  cors: { origin: frontendUrl, methods: ['GET', 'POST'] },
})

const authorizeConversation = createConversationAuthorizer({ chatAuthUrl })
io.use(createAuthenticator({ authUrl, chatAuthUrl, cacheTtlMs: authCacheTtlMs }))
io.on('connection', (socket) => {
  socket.on('hotel:join', (requestedHotelId, callback) => {
    const acknowledge = typeof callback === 'function' ? callback : () => {}
    if (!mayJoinHotel(socket.data.user, requestedHotelId)) {
      acknowledge({ ok: false, error: 'forbidden' })
      return
    }

    socket.join(`hotel:${requestedHotelId}`)
    acknowledge({ ok: true })
  })
  socket.on('conversation:join', async (conversationId, callback) => {
    const acknowledge = typeof callback === 'function' ? callback : () => {}
    const principal = await authorizeConversation(socket, conversationId)
    if (!principal) {
      acknowledge({ ok: false, error: 'forbidden' })
      return
    }

    socket.join(`conversation:${conversationId}`)
    acknowledge({ ok: true })
  })
})

await subscriber.connect()
await subscriber.subscribe(channel, (message) => {
  const event = parseDomainEvent(message)
  if (!event) {
    console.warn(`Ignored invalid event on ${channel}`)
    return
  }

  if (event.type === 'chat.message') {
    io.to(`conversation:${event.conversationId}`).emit(event.type, event.payload)
    io.to(`hotel:${event.hotelId}`).except(`conversation:${event.conversationId}`).emit(event.type, event.payload)
  } else {
    io.to(`hotel:${event.hotelId}`).emit(event.type, event.payload)
  }
})

httpServer.listen(port, '0.0.0.0', () => {
  console.log(`Realtime service listening on port ${port}`)
})

async function shutdown() {
  io.close()
  httpServer.close()
  if (subscriber.isOpen) await subscriber.quit()
}

process.on('SIGTERM', shutdown)
process.on('SIGINT', shutdown)
