import test from 'node:test'
import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { createApp } from '../src/app.js'

test('GET /health reports process and Redis state', async (context) => {
  const server = createServer(createApp(() => 'ready'))
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve))
  context.after(() => server.close())

  const { port } = server.address()
  const response = await fetch(`http://127.0.0.1:${port}/health`)

  assert.equal(response.status, 200)
  assert.deepEqual(await response.json(), { status: 'ok', redis: 'ready' })
})

test('GET /health is unavailable when Redis is disconnected', async (context) => {
  const server = createServer(createApp(() => 'disconnected'))
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve))
  context.after(() => server.close())

  const { port } = server.address()
  const response = await fetch(`http://127.0.0.1:${port}/health`)

  assert.equal(response.status, 503)
  assert.deepEqual(await response.json(), { status: 'degraded', redis: 'disconnected' })
})
