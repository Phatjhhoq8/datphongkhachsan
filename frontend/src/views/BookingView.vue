<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, apiError, responseData } from '../api'
import { localImage, money, nights } from '../utils'
import PaymentMockModal from '../components/PaymentMockModal.vue'
import PriceBreakdown from '../components/PriceBreakdown.vue'
import ServiceSelector from '../components/ServiceSelector.vue'
import VoucherInput from '../components/VoucherInput.vue'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const quote = ref(null)
const seedQuote = ref(null)
const services = ref([])
const selectedServices = ref([])
const voucherCode = ref('')
const voucherMessage = ref('')
const voucherValid = ref(false)
const loading = ref(true)
const quoting = ref(false)
const submitting = ref(false)
const paymentProcessing = ref(false)
const error = ref('')
const paymentError = ref('')
const paymentModal = ref(false)
const createdBooking = ref(null)
const guest = reactive({ first_name: '', last_name: '', email: '', phone: '', special_requests: '', payment_method: 'pay_at_hotel', payment_option: 'full' })

const checkin = computed(() => seedQuote.value?.checkin ?? route.query.checkin)
const checkout = computed(() => seedQuote.value?.checkout ?? route.query.checkout)
const roomTypeId = computed(() => seedQuote.value?.room?.id ?? route.query.room_type_id)
const roomCount = computed(() => Number(seedQuote.value?.rooms ?? route.query.rooms ?? 1))
const stayNights = computed(() => nights(checkin.value, checkout.value))
const pricing = computed(() => quote.value?.pricing ?? quote.value?.breakdown ?? quote.value ?? {})
const total = computed(() => Number(pricing.value.total ?? 0))
const amountDue = computed(() => guest.payment_option === 'deposit' ? Number(quote.value?.deposit_amount ?? total.value) : total.value)
const bookingCode = computed(() => createdBooking.value?.code ?? createdBooking.value?.booking_code ?? createdBooking.value?.reference ?? '')
const modalMethod = computed(() => ({ credit_card: 'card', vietqr: 'vietqr', paypal: 'paypal' })[guest.payment_method] ?? 'card')
const isOnline = computed(() => ['credit_card', 'vietqr', 'paypal'].includes(guest.payment_method))

function loadStoredQuote() {
  try { seedQuote.value = JSON.parse(sessionStorage.getItem('staygo_booking_quote')) } catch { seedQuote.value = null }
}
function quotePayload(code = voucherCode.value) {
  return {
    room_type_id: roomTypeId.value,
    checkin: checkin.value,
    checkout: checkout.value,
    rooms: roomCount.value,
    adults: Number(seedQuote.value?.adults ?? route.query.adults ?? 2),
    children: Number(seedQuote.value?.children ?? route.query.children ?? 0),
    service_ids: selectedServices.value,
    voucher_code: code || null,
    guest_email: guest.email || undefined,
    payment_option: guest.payment_option,
  }
}
async function requestQuote({ voucher = false } = {}) {
  if (!roomTypeId.value || !checkin.value || !checkout.value) { loading.value = false; error.value = 'Thiếu thông tin phòng hoặc ngày lưu trú. Vui lòng chọn phòng lại.'; return }
  quoting.value = true; if (!voucher) error.value = ''
  try {
    const data = responseData(await api.post('/quotes', quotePayload()))
    quote.value = data.quote ?? data
    if (voucher) {
      voucherValid.value = Number(pricing.value.discount ?? pricing.value.discount_total) > 0
      voucherMessage.value = voucherValid.value ? (data.voucher?.message ?? 'Mã ưu đãi đã được áp dụng.') : 'Mã không hợp lệ hoặc không áp dụng cho kỳ nghỉ này.'
    }
  } catch (err) {
    if (voucher) { voucherValid.value = false; voucherMessage.value = apiError(err, 'Mã ưu đãi không hợp lệ.') }
    else error.value = apiError(err, 'Không thể lấy báo giá từ máy chủ.')
  } finally { quoting.value = false; loading.value = false }
}
async function loadCheckout() {
  if (auth.user) {
    const parts = String(auth.user.name ?? auth.user.full_name ?? '').trim().split(/\s+/)
    guest.first_name = parts.pop() ?? ''
    guest.last_name = parts.join(' ')
    guest.email = auth.user.email ?? ''
    guest.phone = auth.user.phone ?? auth.user.phone_number ?? ''
  }
  loadStoredQuote()
  if ((!seedQuote.value?.room || String(seedQuote.value.room.id) !== String(route.query.room_type_id)) && route.query.hotel_slug) {
    try {
      const hotel = responseData(await api.get(`/hotels/${route.query.hotel_slug}`, { params: route.query }))
      const room = (hotel.room_types ?? hotel.rooms ?? []).find(item => String(item.id) === String(route.query.room_type_id))
      if (room) seedQuote.value = { hotel, room, ...route.query }
    } catch { /* POST /quotes remains the source of truth for price and inventory. */ }
  }
  if (route.query.hotel_slug) {
    try {
      const data = responseData(await api.get(`/hotels/${route.query.hotel_slug}/services`))
      services.value = Array.isArray(data) ? data : data.services ?? data.items ?? []
    } catch { services.value = [] }
  }
  await requestQuote()
}
function newKey() { return globalThis.crypto?.randomUUID?.() ?? `staygo-${Date.now()}-${Math.random().toString(16).slice(2)}` }
function idempotencyKey() { let key = sessionStorage.getItem('staygo_idempotency_key'); if (!key) { key = newKey(); sessionStorage.setItem('staygo_idempotency_key', key) } return key }
function finish(code, booking = createdBooking.value) {
  sessionStorage.setItem('staygo_booking_email', guest.email)
  localStorage.setItem('staygo_booking_email', guest.email)
  if (booking) sessionStorage.setItem(`staygo_booking_${code}`, JSON.stringify(booking))
  sessionStorage.removeItem('staygo_idempotency_key')
  router.push({ path: `/hotel/booking/${code}`, query: isOnline.value ? { payment: 'mock_success' } : {} })
}
async function submitBooking() {
  if (!quote.value) { error.value = 'Báo giá chưa sẵn sàng. Vui lòng tải lại trước khi đặt phòng.'; return }
  submitting.value = true; error.value = ''
  try {
    const payload = {
      ...quotePayload(),
      guest_name: `${guest.first_name} ${guest.last_name}`.trim(),
      guest_email: guest.email,
      guest_phone: guest.phone,
      special_requests: guest.special_requests || null,
      payment_method: ({ paypal: 'paypal_mock', credit_card: 'card_mock', vietqr: 'vietqr_mock' })[guest.payment_method] ?? guest.payment_method,
    }
    const data = responseData(await api.post('/bookings', payload, { headers: { 'Idempotency-Key': idempotencyKey() } }))
    createdBooking.value = data.booking ?? data
    if (!bookingCode.value) throw new Error('missing_booking_code')
    sessionStorage.setItem('staygo_booking_email', guest.email)
    sessionStorage.setItem(`staygo_booking_${bookingCode.value}`, JSON.stringify(createdBooking.value))
    if (isOnline.value) paymentModal.value = true
    else finish(bookingCode.value)
  } catch (err) { error.value = err.message === 'missing_booking_code' ? 'Máy chủ đã nhận yêu cầu nhưng không trả về mã đặt phòng.' : apiError(err, 'Không thể tạo đặt phòng. Vui lòng thử lại.') }
  finally { submitting.value = false }
}
async function confirmMockPayment(cardData) {
  paymentProcessing.value = true; paymentError.value = ''
  try {
    const paymentKey = `${idempotencyKey()}-payment`
    const intentData = responseData(await api.post(`/booking/${bookingCode.value}/payments/mock/intents`, {
      method: ({ paypal: 'paypal_mock', credit_card: 'card_mock', vietqr: 'vietqr_mock' })[guest.payment_method],
      type: guest.payment_option,
      amount: amountDue.value,
      email: guest.email,
      idempotency_key: paymentKey,
      ...(cardData.card_last_four ? { card_last_four: cardData.card_last_four } : {}),
    }, { headers: { 'Idempotency-Key': paymentKey } }))
    const intent = intentData.payment_intent ?? intentData
    const intentReference = intent.reference ?? intent.intent_id
    if (!intentReference) throw new Error('missing_payment_intent')
    await api.post(`/payments/mock/${intentReference}/confirm`, { outcome: 'success', email: guest.email })
    paymentModal.value = false
    finish(bookingCode.value)
  } catch (err) { paymentError.value = err.message === 'missing_payment_intent' ? 'Máy chủ không trả về mã payment intent.' : apiError(err, 'Không thể xác nhận thanh toán mô phỏng.') }
  finally { paymentProcessing.value = false }
}

let quoteTimer
watch([selectedServices, () => guest.payment_option], () => { if (!loading.value) { clearTimeout(quoteTimer); quoteTimer = setTimeout(() => requestQuote(), 250) } }, { deep: true })
onMounted(loadCheckout)
</script>

<template>
  <div class="booking-bar"><div class="container"><span class="brand-mini">StayGo</span><ol><li class="current">1. Tùy chọn</li><li>2. Thanh toán</li><li>3. Xác nhận</li></ol></div></div>
  <div class="booking-page container">
    <section class="booking-form-wrap"><nav class="breadcrumbs"><a href="/hotel">Trang chủ</a><span>/</span><span>Đặt phòng</span></nav><h1>Hoàn tất kỳ nghỉ</h1><p class="lead">Giá và phòng trống được xác nhận trực tiếp từ máy chủ.</p>
      <div v-if="loading" class="state-card"><span class="spinner"></span><h2>Đang lấy báo giá...</h2></div>
      <form v-else class="booking-form" @submit.prevent="submitBooking">
        <div class="form-panel"><h2>Thông tin người liên hệ</h2><p>Xác nhận đặt phòng sẽ được gửi đến email này.</p><div class="two-columns"><label><span>Họ</span><input v-model.trim="guest.last_name" autocomplete="family-name" required /></label><label><span>Tên</span><input v-model.trim="guest.first_name" autocomplete="given-name" required /></label><label><span>Email</span><input v-model.trim="guest.email" type="email" autocomplete="email" required /></label><label><span>Số điện thoại</span><input v-model.trim="guest.phone" type="tel" autocomplete="tel" required /></label></div></div>
        <div class="form-panel"><h2>Dịch vụ thêm</h2><p>Chọn trước dịch vụ để máy chủ cập nhật báo giá.</p><ServiceSelector v-model="selectedServices" :services="services" :disabled="quoting" /></div>
        <div class="form-panel"><h2>Mã ưu đãi</h2><VoucherInput v-model="voucherCode" :loading="quoting" :message="voucherMessage" :valid="voucherValid" @apply="requestQuote({ voucher: true })" /></div>
        <div class="form-panel"><h2>Thanh toán bao nhiêu?</h2><div class="payment-split"><label><input v-model="guest.payment_option" type="radio" value="deposit" /><span><strong>Đặt cọc</strong><small>Thanh toán khoản cọc do khách sạn quy định.</small></span></label><label><input v-model="guest.payment_option" type="radio" value="full" /><span><strong>Toàn bộ</strong><small>Thanh toán toàn bộ giá trị đặt phòng.</small></span></label></div></div>
        <div class="form-panel"><h2>Phương thức thanh toán</h2><div class="method-grid"><label v-for="method in [{id:'pay_at_hotel',icon:'⌂',name:'Tại khách sạn',note:'Không thanh toán online'},{id:'paypal',icon:'P',name:'PayPal',note:'Sandbox mô phỏng'},{id:'credit_card',icon:'▰',name:'Thẻ',note:'Visa / Mastercard demo'},{id:'vietqr',icon:'▦',name:'VietQR',note:'QR và điện thoại giả lập'}]" :key="method.id" :class="{ selected: guest.payment_method === method.id }"><input v-model="guest.payment_method" type="radio" :value="method.id" /><b>{{ method.icon }}</b><span><strong>{{ method.name }}</strong><small>{{ method.note }}</small></span></label></div><p v-if="isOnline" class="notice">Chế độ demo: không kết nối cổng thanh toán và không phát sinh giao dịch thật.</p></div>
        <div class="form-panel"><h2>Yêu cầu đặc biệt</h2><label><span>Ghi chú cho nơi nghỉ (không bắt buộc)</span><textarea v-model.trim="guest.special_requests" rows="3" placeholder="Ví dụ: nhận phòng muộn, phòng tầng cao"></textarea></label></div>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p><button class="primary submit-booking" :disabled="submitting || quoting || !quote" type="submit">{{ submitting ? 'Đang giữ phòng...' : isOnline ? `Đặt phòng và thanh toán ${money(amountDue)}` : 'Xác nhận đặt phòng' }}</button><p class="terms">Bằng việc xác nhận, bạn đồng ý với chính sách đặt và hủy phòng của nơi nghỉ.</p>
      </form>
    </section>
    <aside v-if="seedQuote" class="booking-summary"><img :src="localImage(seedQuote.room?.image ?? seedQuote.room?.images?.[0]?.url ?? seedQuote.hotel?.hero_image, seedQuote.room?.id)" :alt="seedQuote.hotel?.name" /><div class="summary-content"><span v-if="seedQuote.hotel?.star_rating" class="stars">{{ '★'.repeat(Number(seedQuote.hotel.star_rating)) }}</span><h2>{{ seedQuote.hotel?.name }}</h2><p>{{ seedQuote.hotel?.address }}</p><hr /><h3>{{ seedQuote.room?.name }}</h3><div class="stay-dates"><span><small>Nhận phòng</small><strong>{{ checkin }}</strong></span><span><small>Trả phòng</small><strong>{{ checkout }}</strong></span></div><p>{{ stayNights }} đêm · {{ roomCount }} phòng · {{ seedQuote.adults ?? route.query.adults ?? 2 }} khách</p><hr /><PriceBreakdown v-if="quote" :quote="quote" :amount-due="amountDue" /><div v-if="quoting" class="recalculating"><span class="mini-spinner"></span> Đang cập nhật giá...</div><small>Giá cuối cùng do máy chủ tính, đã gồm các khoản được thể hiện trong báo giá.</small></div></aside>
  </div>
  <PaymentMockModal :open="paymentModal" :method="modalMethod" :amount="amountDue" :booking-code="bookingCode" :processing="paymentProcessing" :error="paymentError" @close="paymentModal = false" @confirm="confirmMockPayment" />
</template>

<style scoped>
.payment-split,.method-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}.payment-split label,.method-grid label{display:flex;align-items:center;gap:10px;border:1px solid #dce3ea;border-radius:9px;padding:13px;cursor:pointer}.payment-split label:has(input:checked),.method-grid label.selected{border-color:#0877cc;background:#f1f8fd}.payment-split input,.method-grid input{width:auto;accent-color:#0877cc}.payment-split strong,.payment-split small,.method-grid strong,.method-grid small{display:block}.payment-split small,.method-grid small{color:#637083;font-size:10px}.method-grid label>b{display:grid;place-items:center;width:31px;height:31px;border-radius:8px;background:#e9f5fd;color:#0877cc;font-size:17px}.recalculating{display:flex;align-items:center;gap:7px;color:#0877cc;font-size:11px;margin:12px 0}.mini-spinner{width:14px;height:14px;border:2px solid #d8eaf7;border-top-color:#0877cc;border-radius:50%;animation:spin .7s linear infinite}@media(max-width:620px){.payment-split,.method-grid{grid-template-columns:1fr}.booking-bar ol{font-size:9px}}
</style>
