import test from 'node:test'
import assert from 'node:assert/strict'
import { parseDomainEvent } from '../src/events.js'

test('normalizes a supported domain event', () => {
  assert.deepEqual(
    parseDomainEvent(JSON.stringify({
      event_type: 'room.updated',
      id: 'evt-1',
      payload: { hotel_id: 4, room_id: 12 },
    })),
    {
      type: 'room.updated',
      hotelId: 4,
      payload: { hotel_id: 4, room_id: 12, event_id: 'evt-1' },
    },
  )
})

test('rejects malformed, unsupported, and unscoped events', () => {
  assert.equal(parseDomainEvent('{bad json'), null)
  assert.equal(parseDomainEvent(JSON.stringify({ type: 'user.updated', hotel_id: 1 })), null)
  assert.equal(parseDomainEvent(JSON.stringify({ type: 'booking.updated', data: {} })), null)
})
