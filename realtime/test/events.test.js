import test from 'node:test'
import assert from 'node:assert/strict'
import { parseDomainEvent } from '../src/events.js'

test('normalizes a supported domain event', () => {
  const hotelId = '64f1c2a3b4d5e6f7890abcde'
  const roomId = '64f1c2a3b4d5e6f7890abcdf'

  assert.deepEqual(
    parseDomainEvent(JSON.stringify({
      event_type: 'room.updated',
      id: 'evt-1',
      payload: { hotel_id: hotelId, room_id: roomId },
    })),
    {
      type: 'room.updated',
      hotelId,
      payload: { hotel_id: hotelId, room_id: roomId, event_id: 'evt-1' },
    },
  )
})

test('rejects malformed, unsupported, and unscoped events', () => {
  assert.equal(parseDomainEvent('{bad json'), null)
  assert.equal(parseDomainEvent(JSON.stringify({ type: 'user.updated', hotel_id: 'hotel-1' })), null)
  assert.equal(parseDomainEvent(JSON.stringify({ type: 'booking.updated', data: {} })), null)
  assert.equal(parseDomainEvent(JSON.stringify({ type: 'booking.updated', hotel_id: 1 })), null)
})

test('parses chat message with opaque room identifiers', () => {
  assert.deepEqual(parseDomainEvent(JSON.stringify({
    id: 'evt-chat-1',
    type: 'chat.message',
    hotel_id: 'hotel-1',
    conversation_id: 'conversation-1',
    data: { message_id: 'message-1', text: 'Hello', sender_type: 'guest' },
  })), {
    type: 'chat.message',
    hotelId: 'hotel-1',
    conversationId: 'conversation-1',
    payload: {
      message_id: 'message-1', text: 'Hello', sender_type: 'guest',
      hotel_id: 'hotel-1', conversation_id: 'conversation-1', event_id: 'evt-chat-1',
    },
  })
})
