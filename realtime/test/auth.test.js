import test from 'node:test'
import assert from 'node:assert/strict'
import { createAuthenticator, mayJoinConversation, mayJoinHotel } from '../src/auth.js'

test('authorizes staff against an opaque string hotel ObjectId', () => {
  const hotelId = '64f1c2a3b4d5e6f7890abcde'

  assert.equal(mayJoinHotel({ role: 'hotel_manager', hotel_id: hotelId }, hotelId), true)
  assert.equal(mayJoinHotel({ role: 'hotel_manager', hotel_id: hotelId }, '64f1c2a3b4d5e6f7890abcdf'), false)
  assert.equal(mayJoinHotel({ role: 'super_admin', hotel_id: null }, hotelId), true)
  assert.equal(mayJoinHotel({ role: 'super_admin', hotel_id: null }, 123), false)
})

test('guest authentication delegates the conversation token to Laravel chat auth', async () => {
  const originalFetch = global.fetch
  global.fetch = async (_url, options) => {
    assert.equal(options.headers['X-Conversation-Token'], 'guest-secret')
    assert.equal(JSON.parse(options.body).conversation_id, 'conversation-1')
    return { ok: true, json: async () => ({ data: { kind: 'guest', conversation_id: 'conversation-1', hotel_id: 'hotel-1' } }) }
  }

  try {
    const authenticate = createAuthenticator({
      authUrl: 'http://laravel/auth/me',
      chatAuthUrl: 'http://laravel/chat/socket-auth',
      cacheTtlMs: 1000,
    })
    const socket = { handshake: { auth: { conversationToken: 'guest-secret', conversationId: 'conversation-1' }, headers: {} }, data: {} }
    const error = await new Promise(resolve => authenticate(socket, resolve))

    assert.equal(error, undefined)
    assert.equal(socket.data.principal.kind, 'guest')
    assert.equal(mayJoinConversation(socket.data.principal, 'conversation-1'), true)
    assert.equal(mayJoinConversation(socket.data.principal, 'conversation-2'), false)
  } finally {
    global.fetch = originalFetch
  }
})

test('staff may join only a conversation authorized by Laravel', () => {
  assert.equal(mayJoinConversation({ kind: 'staff', conversation_ids: ['conversation-1'] }, 'conversation-1'), true)
  assert.equal(mayJoinConversation({ kind: 'staff', conversation_ids: ['conversation-1'] }, 'conversation-2'), false)
  assert.equal(mayJoinConversation({ kind: 'guest', conversation_id: 'conversation-1' }, 123), false)
})
