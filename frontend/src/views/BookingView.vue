<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, apiError, responseData } from '../api'
import { localImage, money, nights, addDays, today } from '../utils'
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
function defaultArrivalTime() {
  const now = new Date()
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes}`
}

const guest = reactive({ first_name: '', last_name: '', email: '', phone: '', special_requests: '', payment_method: 'pay_at_hotel', payment_option: 'full', arrival_time: route.query.arrival_time || defaultArrivalTime(), checkout_time: route.query.checkout_time || '12:00' })

const checkin = ref(route.query.checkin || addDays(today(), 1))
const checkout = ref(route.query.checkout || addDays(today(), 2))
watch(checkin, (newVal) => {
  if (checkout.value <= newVal) {
    checkout.value = addDays(newVal, 1)
  }
})
const roomTypeId = computed(() => seedQuote.value?.room?.id ?? route.query.room_type_id)
const roomCount = computed(() => Number(seedQuote.value?.rooms ?? route.query.rooms ?? 1))
const stayNights = computed(() => nights(checkin.value, checkout.value))
const pricing = computed(() => quote.value?.pricing ?? quote.value?.breakdown ?? quote.value ?? {})
const total = computed(() => Number(pricing.value.total ?? 0))
const amountDue = computed(() => guest.payment_option === 'deposit' ? Number(quote.value?.deposit_amount ?? total.value) : total.value)
const depositPercent = computed(() => {
  if (!quote.value || !total.value) return 0
  const dep = Number(quote.value.deposit_amount ?? 0)
  if (dep <= 0 || dep >= total.value) return 100
  return Math.round((dep / total.value) * 100)
})
const bookingCode = computed(() => createdBooking.value?.code ?? createdBooking.value?.booking_code ?? createdBooking.value?.reference ?? '')
const modalMethod = computed(() => ({ credit_card: 'card', vietqr: 'vietqr', paypal: 'paypal' })[guest.payment_method] ?? 'card')
const isOnline = computed(() => ['credit_card', 'vietqr', 'paypal'].includes(guest.payment_method))
const previewQrUrl = computed(() => `https://img.vietqr.io/image/MB-9704221900123456789-compact2.png?amount=${Math.round(amountDue.value)}&addInfo=${encodeURIComponent(`STAYGO DEMO ${bookingCode.value || 'BOOKING'}`)}&accountName=STAYGO%20DEMO`)
const cancellationDeadline = computed(() => {
  if (!checkin.value || !seedQuote.value?.hotel) return ''
  const time = String(seedQuote.value.hotel.checkin_time ?? '14:00').slice(0, 5)
  const checkinAt = new Date(`${checkin.value}T${time}:00`)
  if (isNaN(checkinAt.getTime())) return ''
  checkinAt.setHours(checkinAt.getHours() - Number(seedQuote.value.hotel.free_cancellation_hours ?? 24))
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(checkinAt)
})

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
    arrival_time: guest.arrival_time || null,
    checkout_time: guest.checkout_time || null,
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
  if (seedQuote.value) {
    checkin.value = seedQuote.value.checkin ?? route.query.checkin ?? checkin.value
    checkout.value = seedQuote.value.checkout ?? route.query.checkout ?? checkout.value
  }
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
      arrival_time: guest.arrival_time || null,
      checkout_time: guest.checkout_time || null,
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
watch([selectedServices, () => guest.payment_option, checkin, checkout, () => guest.arrival_time, () => guest.checkout_time], () => { if (!loading.value) { clearTimeout(quoteTimer); quoteTimer = setTimeout(() => requestQuote(), 250) } }, { deep: true })
function calculateLateTime(timeStr, graceMinutes) {
  if (!timeStr) return ''
  const [hours, minutes] = timeStr.split(':').map(Number)
  const totalMinutes = hours * 60 + minutes + Number(graceMinutes)
  const lateHours = Math.floor(totalMinutes / 60) % 24
  const lateMinutes = totalMinutes % 60
  return `${String(lateHours).padStart(2, '0')}:${String(lateMinutes).padStart(2, '0')}`
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('-')
  if (parts.length !== 3) return dateStr
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

onMounted(loadCheckout)
</script>

<template>
  <div class="booking-page container">
    <section class="booking-form-wrap"><nav class="breadcrumbs"><a href="/hotel">Trang chủ</a><span>/</span><span>Đặt phòng</span></nav><h1>Hoàn tất kỳ nghỉ</h1><p class="lead">Giá và phòng trống được xác nhận trực tiếp từ máy chủ.</p>
      <div v-if="error" class="alert-banner" role="alert">
        <span class="alert-icon">⚠️</span>
        <span class="alert-text">{{ error }}</span>
      </div>
      <div v-if="loading" class="state-card"><span class="spinner"></span><h2>Đang lấy báo giá...</h2></div>
      <form v-else class="booking-form" @submit.prevent="submitBooking">
        <div class="form-panel"><h2>Thông tin người liên hệ</h2><p>Xác nhận đặt phòng sẽ được gửi đến email này.</p><div class="two-columns"><label><span>Họ (không bắt buộc)</span><input v-model.trim="guest.last_name" autocomplete="family-name" /></label><label><span>Tên <span class="required-star">*</span></span><input v-model.trim="guest.first_name" autocomplete="given-name" required /></label><label><span>Email <span class="required-star">*</span></span><input v-model.trim="guest.email" type="email" autocomplete="email" required /></label><label><span>Số điện thoại <span class="required-star">*</span></span><input v-model.trim="guest.phone" type="tel" autocomplete="tel" required /></label><label><span>Giờ nhận phòng dự kiến</span><input v-model="guest.arrival_time" type="time" /></label><label><span>Giờ trả phòng dự kiến</span><input v-model="guest.checkout_time" type="time" /></label></div></div>
        <div class="form-panel"><h2>Dịch vụ thêm</h2><p>Chọn trước dịch vụ để máy chủ cập nhật báo giá.</p><ServiceSelector v-model="selectedServices" :services="services" :disabled="quoting" /></div>
        <div class="form-panel"><h2>Mã ưu đãi</h2><VoucherInput v-model="voucherCode" :loading="quoting" :message="voucherMessage" :valid="voucherValid" :hotel-id="seedQuote?.hotel?.id || seedQuote?.hotel?._id" :total-amount="total" @apply="requestQuote({ voucher: true })" /></div>
        <div class="form-panel">
          <h2>Chính sách nhận/trả phòng & Hủy phòng</h2>
          <p v-if="seedQuote?.room?.refundable === false"><strong>Không hoàn tiền:</strong> phí hủy bằng 100% tổng giá trị sau khi đặt phòng được xác nhận và thanh toán.</p>
          <p v-else>Hủy miễn phí đến <strong>{{ cancellationDeadline }}</strong>. Sau thời điểm này, phí hủy là <strong>{{ seedQuote?.hotel?.late_cancellation_fee_percent ?? 30 }}%</strong> tổng giá trị.</p>
          <small>Đặt phòng chờ xác nhận hoặc chưa thanh toán luôn được hủy miễn phí.</small>
          
          <hr style="margin: 15px 0; border: 0; border-top: 1px solid #edf2f7;" />
          
          <div class="late-checkout-policy">
            <h3 style="font-size: 13.5px; font-weight: 700; color: #13243a; margin-bottom: 8px;">Chính sách trả phòng trễ (Late Checkout)</h3>
            <p style="font-size: 12.5px; margin: 4px 0;">Giờ nhận phòng dự kiến của quý khách: từ <strong>{{ guest.arrival_time || '14:00' }}</strong> ngày <strong>{{ formatDate(checkin) }}</strong>.</p>
            <p style="font-size: 12.5px; margin: 4px 0;">Giờ trả phòng dự kiến của quý khách: trước <strong>{{ guest.checkout_time || '12:00' }}</strong> ngày <strong>{{ formatDate(checkout) }}</strong>.</p>
            <p style="font-size: 12.5px; margin: 4px 0;">Thời gian châm chước trả trễ miễn phí: <strong>{{ seedQuote?.hotel?.late_checkout_grace_minutes ?? 30 }} phút</strong>.</p>
            <p style="color: #991b1b; font-size: 12.5px; font-weight: 600; margin-top: 8px; line-height: 1.6;">
              ⚠️ Nếu thực tế quý khách trả phòng muộn hơn <strong>{{ calculateLateTime(guest.checkout_time || '12:00', seedQuote?.hotel?.late_checkout_grace_minutes ?? 30) }}</strong> ngày <strong>{{ formatDate(checkout) }}</strong> (tức là sau giờ đăng ký + {{ seedQuote?.hotel?.late_checkout_grace_minutes ?? 30 }} phút châm chước):
              <br />
              Phí phạt trả muộn sẽ được áp dụng tự động bằng:
              <span style="color: #b91c1c; background: #fee2e2; padding: 2px 6px; border-radius: 4px; font-family: monospace; display: inline-block; margin-top: 4px; font-size: 12px; border: 1px solid #fecaca;">(Số giờ trễ thực tế) x (10% giá phòng của 1 đêm)</span>
            </p>
            <small style="display: block; color: #637083; margin-top: 6px; font-size: 11px; line-height: 1.4;">
              * Ví dụ: Nếu giá phòng là 1.000.000 VND/đêm, phí phạt trễ sẽ là 100.000 VND cho mỗi giờ trễ (10% của 1.000.000 VND). Phí này chỉ phát sinh khi trả phòng thực tế bị trễ so với giờ đã đăng ký.
            </small>
          </div>
        </div>
        <div class="form-panel"><h2>Thanh toán bao nhiêu?</h2><div class="payment-split"><label><input v-model="guest.payment_option" type="radio" value="deposit" /><span><strong>Đặt cọc {{ depositPercent > 0 && depositPercent < 100 ? `${depositPercent}%` : '' }}</strong><small>Thanh toán khoản cọc tương đương {{ depositPercent > 0 && depositPercent < 100 ? `${depositPercent}%` : 'một phần' }} tổng tiền.</small></span></label><label><input v-model="guest.payment_option" type="radio" value="full" /><span><strong>Toàn bộ (100%)</strong><small>Thanh toán toàn bộ giá trị đặt phòng.</small></span></label></div></div>
        <div class="form-panel"><h2>Phương thức thanh toán</h2><div class="method-grid"><label v-for="method in [{id:'pay_at_hotel',icon:'⌂',name:'Tại khách sạn',note:'Thanh toán trực tiếp khi nhận phòng'},{id:'paypal',icon:'P',name:'PayPal',note:'Cổng thanh toán điện tử quốc tế'},{id:'credit_card',icon:'▰',name:'Thẻ tín dụng / Ghi nợ',note:'Visa / Mastercard / JCB'},{id:'vietqr',icon:'▦',name:'VietQR',note:'Quét mã QR thanh toán nhanh'}]" :key="method.id" :class="{ selected: guest.payment_method === method.id }"><input v-model="guest.payment_method" type="radio" :value="method.id" /><b>{{ method.icon }}</b><span><strong>{{ method.name }}</strong><small>{{ method.note }}</small></span></label></div></div>
        <div class="form-panel"><h2>Yêu cầu đặc biệt</h2><label><span>Ghi chú cho nơi nghỉ (không bắt buộc)</span><textarea v-model.trim="guest.special_requests" rows="3" placeholder="Ví dụ: nhận phòng muộn, phòng tầng cao"></textarea></label></div>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p><button class="primary submit-booking" :disabled="submitting || quoting || !quote || error" type="submit">{{ submitting ? 'Đang giữ phòng...' : isOnline ? `Đặt phòng và thanh toán ${money(amountDue)}` : 'Xác nhận đặt phòng' }}</button><p class="terms">Bằng việc xác nhận, bạn đồng ý với chính sách đặt và hủy phòng của nơi nghỉ.</p>
      </form>
    </section>
    <aside v-if="seedQuote" class="booking-summary"><img :src="localImage(seedQuote.room?.image ?? seedQuote.room?.images?.[0]?.url ?? seedQuote.hotel?.hero_image, seedQuote.room?.id)" :alt="seedQuote.hotel?.name" /><div class="summary-content"><span v-if="seedQuote.hotel?.star_rating" class="stars">{{ '★'.repeat(Number(seedQuote.hotel.star_rating)) }}</span><h2>{{ seedQuote.hotel?.name }}</h2><p>{{ seedQuote.hotel?.address }}</p><hr /><h3>{{ seedQuote.room?.name }}</h3><div class="stay-dates"><span><small>Nhận phòng</small><input type="date" v-model="checkin" :min="today()" class="inline-date-input" /></span><span><small>Trả phòng</small><input type="date" v-model="checkout" :min="addDays(checkin, 1)" class="inline-date-input" /></span></div><p>{{ stayNights }} đêm · {{ roomCount }} phòng · {{ seedQuote.adults ?? route.query.adults ?? 2 }} khách</p><hr /><PriceBreakdown v-if="quote" :quote="quote" :amount-due="amountDue" /><div v-if="quoting" class="recalculating"><span class="mini-spinner"></span> Đang cập nhật giá...</div><small>Giá cuối cùng do máy chủ tính, đã gồm các khoản được thể hiện trong báo giá.</small></div></aside>
  </div>
  <PaymentMockModal :open="paymentModal" :method="modalMethod" :amount="amountDue" :booking-code="bookingCode" :processing="paymentProcessing" :error="paymentError" @close="paymentModal = false" @confirm="confirmMockPayment" />
</template>

<style scoped>
.payment-split,.method-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}.payment-split label,.method-grid label{display:flex;align-items:center;gap:10px;border:1px solid #dce3ea;border-radius:9px;padding:13px;cursor:pointer}.payment-split label:has(input:checked),.method-grid label.selected{border-color:#0877cc;background:#f1f8fd}.payment-split input,.method-grid input{width:auto;accent-color:#0877cc}.payment-split strong,.payment-split small,.method-grid strong,.method-grid small{display:block}.payment-split small,.method-grid small{color:#637083;font-size:10px}.method-grid label>b{display:grid;place-items:center;width:31px;height:31px;border-radius:8px;background:#e9f5fd;color:#0877cc;font-size:17px}.payment-preview{margin-top:14px;border:1px solid #dce3ea;border-radius:14px;background:#f8fbfe;padding:15px}.preview-card{overflow:hidden;position:relative;border-radius:16px;padding:18px;color:#fff;background:linear-gradient(135deg,#111827,#1e1b4b 60%,#0877cc);box-shadow:0 12px 28px #13243a18}.preview-card div{display:flex;justify-content:space-between;font-size:10px;font-weight:900;letter-spacing:1px}.preview-card strong{display:block;margin:28px 0 10px;font-size:18px;letter-spacing:2px}.preview-card small{color:#dbeafe}.preview-qr,.preview-paypal{display:flex;align-items:center;gap:15px}.preview-qr img{width:118px;height:118px;object-fit:contain;background:#fff;border:1px solid #e1e7ed;border-radius:12px;padding:6px}.preview-qr strong,.preview-qr span,.preview-paypal strong{display:block}.preview-qr span{color:#0877cc;font-size:18px;font-weight:900}.preview-qr small,.preview-paypal small{display:block;color:#637083;margin-top:5px}.preview-paypal>b{flex:0 0 auto;color:#003087;font-size:32px;font-weight:900}.preview-paypal>b span{color:#179bd7}.recalculating{display:flex;align-items:center;gap:7px;color:#0877cc;font-size:11px;margin:12px 0}.mini-spinner{width:14px;height:14px;border:2px solid #d8eaf7;border-top-color:#0877cc;border-radius:50%;animation:spin .7s linear infinite}
.alert-banner { display: flex; align-items: center; gap: 12px; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; color: #991b1b; font-size: 13.5px; font-weight: 600; width: 100%; text-align: left; }
.alert-icon { font-size: 18px; }
.inline-date-input { border: 1px dashed #0877cc; border-radius: 6px; padding: 4px 8px; font-family: inherit; font-size: 13.5px; font-weight: 700; color: #0877cc; background: #f1f8fd; outline: none; cursor: pointer; transition: all 0.2s ease; margin-top: 2px; }
.inline-date-input:focus { border-style: solid; border-color: #003087; background: #fff; }
.two-columns label.full-width { grid-column: span 2; }
.two-columns label.full-width input { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 6px; background-color: #fff; font-size: 13.5px; color: #13243a; font-weight: 500; cursor: pointer; outline: none; transition: border-color 0.2s ease; }
.two-columns label.full-width input:focus { border-color: #0877cc; }
@media(max-width:620px){.payment-split,.method-grid{grid-template-columns:1fr}.booking-bar ol{font-size:9px}.preview-qr,.preview-paypal{align-items:flex-start}.preview-qr img{width:96px;height:96px}.two-columns label.full-width { grid-column: span 1; }}
</style>
