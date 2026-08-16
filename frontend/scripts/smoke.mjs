import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const source = async path => readFile(new URL(`../${path}`, import.meta.url), 'utf8')
const [router, adminRoutes, adminLayout, auth, booking, payment, resourcePage, reviewSection, rooms, roomTypes, services, vouchers, users, hotel, home, search, searchForm, main, analytics, analyticsView, paymentReport, app, chatWidget, chatInbox, chatView, login, adminBookings, roomMap, bookingDetail, crudModal, hotels, amenities, roomImages, socialButtons, oauthCallback, accountProfile, adminBookingDetail] = await Promise.all([
  source('src/router.js'),
  source('src/admin/routes.js'),
  source('src/admin/AdminLayout.vue'),
  source('src/stores/auth.js'),
  source('src/views/BookingView.vue'),
  source('src/components/PaymentMockModal.vue'),
  source('src/admin/ResourcePage.vue'),
  source('src/components/ReviewSection.vue'),
  source('src/views/admin/RoomsView.vue'),
  source('src/views/admin/RoomTypesView.vue'),
  source('src/views/admin/ServicesView.vue'),
  source('src/views/admin/VouchersView.vue'),
  source('src/views/admin/UsersView.vue'),
  source('src/views/HotelView.vue'),
  source('src/views/HomeView.vue'),
  source('src/views/SearchView.vue'),
  source('src/components/SearchForm.vue'),
  source('src/main.js'),
  source('src/analytics.js'),
  source('src/views/admin/AnalyticsView.vue'),
  source('src/views/admin/PaymentReportView.vue'),
  source('src/App.vue'),
  source('src/components/ChatWidget.vue'),
  source('src/views/admin/ChatInboxView.vue'),
  source('src/views/admin/ChatView.vue'),
  source('src/views/LoginView.vue'),
  source('src/views/admin/BookingsView.vue'),
  source('src/views/admin/RoomMapView.vue'),
  source('src/views/BookingDetailView.vue'),
  source('src/admin/CrudModal.vue'),
  source('src/views/admin/HotelsView.vue'),
  source('src/views/admin/AmenitiesView.vue'),
  source('src/views/admin/RoomImagesView.vue'),
  source('src/components/SocialButtons.vue'),
  source('src/views/OAuthCallbackView.vue'),
  source('src/views/AccountProfileView.vue'),
  source('src/views/admin/BookingAdminDetailView.vue'),
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
assert.ok(!reviewSection.includes('Number(form.room_type_id)'), 'Review room_type_id must preserve MongoDB ObjectId strings')
assert.ok(resourcePage.includes('optionsEndpoint'), 'ResourcePage must load dynamic select options')
for (const [name, view] of Object.entries({ rooms, roomTypes, services, vouchers, users })) {
  assert.doesNotMatch(view, /key:'(?:hotel_id|room_type_id)'[^}]*type:'number'/, `${name} must not render ObjectIds as number inputs`)
}
assert.match(rooms, /key:'room_type_id'[^}]*optionsEndpoint:/, 'Room form must select a room type from API data')
for (const [name, view] of Object.entries({ rooms, roomTypes, services, vouchers, users })) {
  assert.match(view, /key:'hotel_id'[^}]*optionsEndpoint:/, `${name} must select a hotel from API data`)
}
assert.ok(services.includes("'per_guest'") && services.includes("'per_unit'"), 'Service form must use canonical pricing types')
for (const [name, view] of Object.entries({ hotel, home, search })) {
  assert.ok(view.includes('approved_reviews_avg_rating'), `${name} must use the review aggregate returned by backend`)
  assert.ok(!view.includes('approved_reviews_avg_rating_overall'), `${name} must not use the obsolete review aggregate field`)
}
assert.match(searchForm, /SpeechRecognition|webkitSpeechRecognition/, 'SearchForm must use the Web Speech API')
assert.ok(searchForm.includes("lang = 'vi-VN'"), 'Voice recognition language must be vi-VN')
assert.match(searchForm, /Trình duyệt[^<]*(không|chưa) hỗ trợ/, 'SearchForm must show an unsupported-browser fallback')
assert.ok(searchForm.includes('parseVoiceSearch'), 'SearchForm must parse voice search text')
assert.ok(analytics.includes("api.post('/activity-events'"), 'Analytics must use the public activity endpoint')
for (const event of ['page_view', 'search', 'room_view', 'voice_search']) {
  assert.ok(`${main}\n${router}\n${searchForm}\n${search}\n${hotel}\n${analytics}`.includes(`'${event}'`), `Missing ${event} tracking`)
}
assert.ok(!analytics.includes('MediaRecorder'), 'Analytics must not record raw audio')
assert.ok(app.includes('ChatWidget'), 'App must mount the guest ChatWidget')
assert.ok(chatWidget.includes('staygo_chat_conversation'), 'ChatWidget must persist guest conversation access')
assert.ok(chatWidget.includes('chat.message'), 'ChatWidget must consume realtime chat messages')
assert.match(chatWidget, /setInterval|poll/, 'ChatWidget must fall back to polling')
assert.ok(adminRoutes.includes("path: 'chat'"), 'Missing admin chat inbox route')
assert.ok(adminRoutes.includes("path: 'chat/:id'"), 'Missing admin chat conversation route')
assert.ok(chatInbox.includes("api.get('/admin/chat/conversations'"), 'Admin chat inbox must load scoped conversations')
assert.ok(chatView.includes('chat.message'), 'Admin chat view must consume realtime messages')
assert.ok(analyticsView.includes('data.behavior'), 'Analytics view must render behavior analytics')
assert.ok(analyticsView.includes('data.room_type_performance'), 'Analytics view must render room type performance')
assert.match(analyticsView, /AdminState[\s\S]*loading[\s\S]*error/, 'Analytics view must expose loading and error states')
assert.ok(adminRoutes.includes("path: 'payments'"), 'Missing admin payment report route')
assert.ok(adminLayout.includes('/admin/payments'), 'Missing payment report menu item')
assert.ok(paymentReport.includes("api.get('/admin/payments'"), 'Payment report must load the existing admin payments endpoint')
assert.match(paymentReport, /AdminState[\s\S]*loading[\s\S]*error/, 'Payment report must expose loading and error states')
assert.match(login, /auth\.isStaff\s*\?\s*['"]\/admin['"]\s*:\s*['"]\/account['"]/, 'Staff login must default to /admin')
assert.ok(adminLayout.includes("to=\"/hotel\"") && adminLayout.includes('auth.logout()'), 'Admin layout must link to website and log out')
assert.match(adminLayout, /roles[\s\S]*filter|filter[\s\S]*roles/, 'Admin menu must be filtered by role')
assert.match(adminBookings, /api\.get\(['"]\/admin\/hotels['"]/, 'Counter booking must load hotel options')
assert.match(adminBookings, /api\.get\(['"]\/admin\/room-types['"]/, 'Counter booking must load room type options')
assert.match(adminBookings, /api\.get\(['"]\/admin\/rooms['"]/, 'Counter booking must load room options')
assert.doesNotMatch(adminBookings, /placeholder=['"](?:ID|ObjectId)/i, 'Counter booking must not ask for raw ObjectIds')
assert.ok(adminBookings.includes('checkin:') && adminBookings.includes('checkout:'), 'Counter booking must submit canonical checkin/checkout fields')
assert.ok(roomMap.includes("api.get('/admin/hotels')"), 'Room map must load hotel options for super admin')
assert.ok(roomMap.includes('/cleaning-complete'), 'Room map must support completing room cleaning')
assert.ok(roomMap.includes('available_at'), 'Room map must display available_at')
assert.match(crudModal, /password[\s\S]*(?:delete|filter)/, 'Crud modal must omit an empty password')
assert.match(users, /super_admin[\s\S]*(?:fields|role|hotel_id)/, 'Manager user form must hide role and hotel assignment')
assert.ok(bookingDetail.includes('`/me/bookings/${route.params.code}`'), 'Authenticated booking detail must use /me/bookings/{code}')
assert.match(bookingDetail, /scheduled_checkin_at/, 'Booking detail must render scheduled check-in data')
assert.doesNotMatch(bookingDetail, /Từ 14:00/, 'Booking detail must not hardcode a 14:00 check-in')
assert.ok(hotels.includes("default:'15:00'"), 'New hotels must default to the 15:00 check-in policy')
assert.ok(adminRoutes.includes("path: 'amenities'") && adminRoutes.includes("path: 'room-images'"), 'Missing admin amenities or room image route')
assert.ok(adminLayout.includes('/admin/amenities') && adminLayout.includes('/admin/room-images'), 'Missing role-filtered amenities or room image menu item')
assert.ok(amenities.includes("endpoint=\"/admin/amenities\""), 'Amenities admin must use the amenities CRUD endpoint')
assert.match(roomTypes, /key:'amenity_ids'[^}]*type:'multiselect'/, 'Room type form must support amenity multi-select')
assert.match(crudModal, /multiple/, 'Crud modal must render a multi-select control')
assert.ok(roomImages.includes("api.get('/admin/room-types'"), 'Room images must load scoped room types')
assert.ok(roomImages.includes('FormData') && roomImages.includes("form.append('image'"), 'Room images must upload multipart image data')
assert.ok(roomImages.includes('/images') && roomImages.includes("api.patch(`/admin/room-images/"), 'Room images must list and update sort order')
assert.ok(roomImages.includes("api.delete(`/admin/room-images/"), 'Room images must support deletion')
assert.ok(router.includes("path: '/auth/oauth/callback'"), 'Missing OAuth callback SPA route')
assert.ok(socialButtons.includes("api.get('/auth/oauth/providers')"), 'Social buttons must load provider capabilities')
assert.match(socialButtons, /providers\[provider\]/, 'Social buttons must only enable configured providers')
assert.ok(oauthCallback.includes('auth.exchangeOAuth') && auth.includes("api.post('/auth/oauth/exchange'"), 'OAuth callback must exchange its one-time code with POST')
assert.doesNotMatch(oauthCallback, /(?:token|access_token)\s*=\s*route\.query/, 'OAuth callback must not read a bearer token from the URL')
assert.ok(accountProfile.includes("api.patch('/auth/profile'"), 'Profile form must update the authenticated profile')
assert.ok(accountProfile.includes("api.put('/auth/password'"), 'Profile form must support authenticated password changes')
assert.match(adminBookingDetail, /v-for=["']room in booking\.rooms/, 'Admin booking detail must render every assigned room')
assert.match(adminBookingDetail, /room\.room_type\?\.name/, 'Admin booking detail must render each room type')
assert.match(adminBookingDetail, /api\.post\(`\/admin\/bookings\/\$\{route\.params\.id\}\/payments`/, 'Admin booking detail must record internal mock payments')
assert.ok(adminBookingDetail.includes("['cash', 'pay_at_hotel']"), 'Admin booking payment form must only offer internal methods')
assert.match(adminBookingDetail, /paid_amount[\s\S]*balance/, 'Admin booking detail must show paid amount and balance')
assert.match(adminBookingDetail, /cancelled[\s\S]*expired/, 'Admin booking detail must protect terminal bookings from payment')
assert.ok(adminBookings.includes("'expired'"), 'Admin booking status filter must include expired')

console.log('Frontend route, auth, voice analytics, booking, payment, ObjectId and pricing smoke checks passed.')
