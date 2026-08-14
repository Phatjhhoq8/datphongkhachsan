const EVENT_NAMES = new Set(['room.updated', 'booking.updated'])

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
  const hotelId = Number(event.hotel_id ?? data?.hotel_id)

  if (!EVENT_NAMES.has(type) || !Number.isSafeInteger(hotelId) || hotelId <= 0) return null
  if (!data || Array.isArray(data) || typeof data !== 'object') return null

  return {
    type,
    hotelId,
    payload: {
      ...data,
      hotel_id: hotelId,
      ...(event.id !== undefined ? { event_id: event.id } : {}),
      ...(event.occurred_at !== undefined ? { occurred_at: event.occurred_at } : {}),
    },
  }
}
