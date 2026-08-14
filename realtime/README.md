# StayGo realtime service

This service subscribes to Redis channel `staygo.events` and forwards validated
`room.updated` and `booking.updated` events to the Socket.io room `hotel:{id}`.

## Event contract

Laravel or its outbox worker must publish JSON in this shape:

```json
{
  "id": "optional-outbox-id",
  "type": "room.updated",
  "hotel_id": 1,
  "occurred_at": "2026-08-15T12:00:00Z",
  "data": { "room_id": 42 }
}
```

`event_type` can replace `type`, and `payload` can replace `data`. `hotel_id`
may be on the envelope or payload. Other event names and invalid hotel scopes
are discarded.

The current Laravel application does not publish these Redis events yet. Its
future mutation/outbox worker must publish after commit to `staygo.events`.
Until then, the existing room-map HTTP polling remains unchanged; Socket.io
also keeps its HTTP long-polling transport fallback enabled.

## Authentication and client integration

During development, the client sends its Laravel Bearer token in the Socket.io
auth payload. The service verifies it against `GET /api/v1/auth/me` and caches
successful responses for 15 seconds. Only active staff roles are accepted.
Hotel staff can only join their assigned hotel; `super_admin` can join any.

The frontend adapter requires the integration agent to add `socket.io-client`
to `frontend/package.json` before importing `src/realtime.js`.

Run locally with `npm test` or `npm start`. Health is exposed at
`http://localhost:3001/health`.
