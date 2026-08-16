import { api } from './api'

const SESSION_KEY = 'staygo_activity_session'

export function activitySessionId() {
  let id = sessionStorage.getItem(SESSION_KEY)
  if (!id) {
    id = globalThis.crypto?.randomUUID?.() || `session-${Date.now()}-${Math.random().toString(36).slice(2)}`
    sessionStorage.setItem(SESSION_KEY, id)
  }
  return id
}

export function trackActivity(event, details = {}) {
  const payload = {
    event,
    session_id: activitySessionId(),
    path: details.path || `${location.pathname}${location.search}`,
    ...details,
  }

  return api.post('/activity-events', payload).catch(() => undefined)
}

export function installPageTracking(router) {
  let enteredAt = performance.now()
  let currentPath = `${location.pathname}${location.search}`

  router.afterEach((to) => {
    const nextPath = to.fullPath
    if (currentPath) {
      trackActivity('page_view', {
        path: currentPath,
        duration_seconds: Math.min(86400, Math.max(0, Math.round((performance.now() - enteredAt) / 1000))),
        metadata: { phase: 'duration' },
      })
    }
    currentPath = nextPath
    enteredAt = performance.now()
    trackActivity('page_view', { path: nextPath, metadata: { phase: 'enter', route: String(to.name || '') } })
  })

  addEventListener('pagehide', () => {
    trackActivity('page_view', {
      path: currentPath,
      duration_seconds: Math.min(86400, Math.max(0, Math.round((performance.now() - enteredAt) / 1000))),
      metadata: { phase: 'duration' },
    })
  })
}
