const EVENT_NAMES = new Set(['room.updated', 'booking.updated', 'chat.message'])

export function parseDomainEvent(message) {
  let event

  try {
    event = JSON.parse(message)
  } catch {
    return null
  }

  if (!event || Array.isArray(event) || typeof event !== 'object') return null

  const type = event.type ?? event.event_type ?? event.event
  const data = event.data ?? event.payload ?? {}
  const hotelId = event.hotel_id ?? data?.hotel_id
  const conversationId = event.conversation_id ?? data?.conversation_id

  if (!EVENT_NAMES.has(type) || typeof hotelId !== 'string' || hotelId.length === 0) return null
  if (!data || Array.isArray(data) || typeof data !== 'object') return null
  if (type === 'chat.message' && (typeof conversationId !== 'string' || conversationId.length === 0)) return null

  return {
    type,
    hotelId,
    ...(type === 'chat.message' ? { conversationId } : {}),
    payload: {
      ...data,
      hotel_id: hotelId,
      ...(type === 'chat.message' ? { conversation_id: conversationId } : {}),
      ...(event.id !== undefined ? { event_id: event.id } : {}),
      ...(event.occurred_at !== undefined ? { occurred_at: event.occurred_at } : {}),
    },
  }
}
