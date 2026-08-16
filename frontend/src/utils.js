export const today = () => new Date().toISOString().slice(0, 10)

export function addDays(date, days) {
  const value = new Date(`${date}T00:00:00`)
  value.setDate(value.getDate() + days)
  return value.toISOString().slice(0, 10)
}

export function money(value) {
  return `${new Intl.NumberFormat('vi-VN').format(Number(value) || 0)} VND`
}

export function nights(checkin, checkout) {
  const count = (new Date(checkout) - new Date(checkin)) / 86400000
  return Number.isFinite(count) && count > 0 ? count : 1
}

export function localImage(value, fallbackId = 1) {
  if (typeof value === 'string' && value.startsWith('/')) return value
  if (typeof value === 'string' && value.includes('/images/')) return value.slice(value.indexOf('/images/'))
  let safeId = Number(fallbackId) || 1
  if (safeId < 1 || safeId > 10) {
    safeId = (safeId % 10) || 1
  }
  return `/images/rooms/${safeId}/1.jpg`
}
