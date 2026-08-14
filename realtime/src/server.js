import { createServer } from 'node:http'
import { createClient } from 'redis'
import { Server } from 'socket.io'
import { createApp } from './app.js'
import { createAuthenticator, mayJoinHotel } from './auth.js'
import { parseDomainEvent } from './events.js'

const port = Number(process.env.PORT || 3001)
const frontendUrl = process.env.FRONTEND_URL || 'http://localhost:3000'
const redisUrl = process.env.REDIS_URL || 'redis://redis:6379'
const channel = process.env.REDIS_CHANNEL || 'staygo.events'
const authUrl = process.env.LARAVEL_AUTH_URL || 'http://backend:8000/api/v1/auth/me'
const authCacheTtlMs = Number(process.env.AUTH_CACHE_TTL_MS || 15000)

const subscriber = createClient({ url: redisUrl })
subscriber.on('error', (error) => console.error('Redis subscriber error:', error.message))

const app = createApp(() => (subscriber.isReady ? 'ready' : 'disconnected'))
const httpServer = createServer(app)
const io = new Server(httpServer, {
  cors: { origin: frontendUrl, methods: ['GET', 'POST'] },
})

io.use(createAuthenticator({ authUrl, cacheTtlMs: authCacheTtlMs }))
io.on('connection', (socket) => {
  socket.on('hotel:join', (requestedHotelId, callback) => {
    const acknowledge = typeof callback === 'function' ? callback : () => {}
    const hotelId = Number(requestedHotelId)
    if (!Number.isSafeInteger(hotelId) || hotelId <= 0 || !mayJoinHotel(socket.data.user, hotelId)) {
      acknowledge({ ok: false, error: 'forbidden' })
      return
    }

    socket.join(`hotel:${hotelId}`)
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

  io.to(`hotel:${event.hotelId}`).emit(event.type, event.payload)
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
