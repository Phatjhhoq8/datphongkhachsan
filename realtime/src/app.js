import express from 'express'

export function createApp(redisStatus = () => 'disconnected') {
  const app = express()

  app.disable('x-powered-by')
  app.get('/health', (_request, response) => {
    const redis = redisStatus()
    response.status(redis === 'ready' ? 200 : 503).json({
      status: redis === 'ready' ? 'ok' : 'degraded',
      redis,
    })
  })

  return app
}
