import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const source = async path => readFile(new URL(`../${path}`, import.meta.url), 'utf8')
const [router, adminRoutes, auth, booking, payment] = await Promise.all([
  source('src/router.js'),
  source('src/admin/routes.js'),
  source('src/stores/auth.js'),
  source('src/views/BookingView.vue'),
  source('src/components/PaymentMockModal.vue'),
])

for (const path of ['/login', '/register', '/account']) {
  assert.ok(router.includes(`path: '${path}'`), `Missing SPA route ${path}`)
}
assert.ok(adminRoutes.includes("path: '/admin'"), 'Missing SPA route /admin')
assert.match(router, /beforeEach/)
assert.match(router, /requiresAuth/)
assert.match(router, /roles/)
assert.ok(auth.includes("api.get('/auth/me')"), 'Auth profile endpoint is not /auth/me')
assert.ok(booking.includes("api.post('/quotes'"), 'Booking quote step is missing')
assert.ok(booking.includes("api.post('/bookings'"), 'Booking create step is missing')
assert.ok(booking.includes('/payments/mock/intents'), 'Payment intent step is missing')
assert.ok(booking.includes('/payments/mock/${intentReference}/confirm'), 'Payment confirm step is missing')
assert.ok(payment.includes('{ card_last_four: digits.value.slice(-4) }'), 'Card modal must emit last four only')
assert.ok(!payment.includes("emit('confirm', card)"), 'Card modal must not emit sensitive card data')

console.log('Frontend route, auth, booking and payment smoke checks passed.')
