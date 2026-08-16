<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, apiError, responseData } from '../api'
import { localImage, money } from '../utils'
import PriceBreakdown from '../components/PriceBreakdown.vue'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const booking = ref(null)
const email = ref(sessionStorage.getItem('staygo_booking_email') || localStorage.getItem('staygo_booking_email') || '')
const loading = ref(false)
const cancelling = ref(false)
const error = ref('')
const cancelled = computed(() => ['cancelled', 'canceled'].includes(String(booking.value?.status).toLowerCase()))
const expired = computed(() => String(booking.value?.status).toLowerCase() === 'expired')
const canCancel = computed(() => booking.value && ['pending', 'confirmed'].includes(String(booking.value.status).toLowerCase()))
const allocatedRoom = computed(() => booking.value?.rooms?.[0])
const roomType = computed(() => booking.value?.room_type ?? allocatedRoom.value?.room_type)
const bookedHotel = computed(() => booking.value?.hotel ?? roomType.value?.hotel)
const services = computed(() => booking.value?.services ?? booking.value?.booking_services ?? [])
const payments = computed(() => booking.value?.payment_transactions ?? booking.value?.payments ?? booking.value?.transactions ?? [])
const invoice = computed(() => booking.value?.invoice ?? booking.value?.invoices?.[0])
const pricing = computed(() => booking.value?.pricing ?? booking.value?.breakdown ?? { room: booking.value?.subtotal, service_total: booking.value?.service_total ?? booking.value?.services_total, discount: booking.value?.discount_total, total: booking.value?.total, due: booking.value?.amount_due ?? booking.value?.deposit_amount ?? booking.value?.total })
const amountDue = computed(() => Number(invoice.value?.balance ?? Math.max(0, Number(booking.value?.total ?? 0) - Number(booking.value?.paid_amount ?? 0))))
const cancellationPolicy = computed(() => {
  if (!booking.value) return ''
  if (booking.value.refundable === false) return 'Không hoàn tiền sau khi đặt phòng được xác nhận và thanh toán.'
  return `Hủy miễn phí đến ${dateTime(booking.value.free_cancellation_until)}; sau đó phí hủy là ${booking.value.late_cancellation_fee_percent ?? 30}% tổng giá trị.`
})
const timeline = computed(() => {
  const history = booking.value?.timeline ?? booking.value?.status_histories ?? booking.value?.status_history
  if (history?.length) return history.map(item => ({ ...item, status: item.status ?? item.to_status, at: item.at ?? item.created_at }))
  const current = String(booking.value?.status ?? 'pending').toLowerCase()
  const steps = [{ status: 'pending', label: 'Đã tiếp nhận', at: booking.value?.created_at }, { status: 'confirmed', label: 'Đã xác nhận', at: booking.value?.confirmed_at }, { status: 'checked_in', label: 'Đã nhận phòng', at: booking.value?.checked_in_at }, { status: 'completed', label: 'Hoàn tất kỳ nghỉ', at: booking.value?.completed_at }]
  if (cancelled.value) return [steps[0], { status: 'cancelled', label: 'Đã hủy', at: booking.value?.cancelled_at ?? booking.value?.updated_at }]
  if (expired.value) return [steps[0], { status: 'expired', label: 'Đã hết hạn giữ phòng', at: booking.value?.updated_at }]
  const order = ['pending', 'confirmed', 'checked_in', 'checked_out', 'completed']
  return steps.map(step => ({ ...step, done: order.indexOf(step.status) <= order.indexOf(current) }))
})

function statusText(status) { return ({ pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', cancelled: 'Đã hủy', canceled: 'Đã hủy', expired: 'Đã hết hạn giữ phòng', checked_in: 'Đã nhận phòng', checked_out: 'Đã trả phòng', completed: 'Đã hoàn tất', paid: 'Đã thanh toán', succeeded: 'Thành công', failed: 'Thất bại', refunded: 'Đã hoàn tiền' })[String(status).toLowerCase()] ?? status ?? 'Đã tiếp nhận' }
function dateTime(value) { if (!value) return 'Đang chờ'; const date = new Date(value); return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(date) }
function stayTime(value, fallback) {
  if (!value) return fallback
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return fallback
  return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: bookedHotel.value?.timezone ?? 'Asia/Ho_Chi_Minh' }).format(date)
}
async function fetchBooking() {
  if (!auth.isAuthenticated && !email.value) return
  loading.value = true; error.value = ''
  try {
    const bookingEndpoint = auth.isAuthenticated ? `/me/bookings/${route.params.code}` : `/bookings/${route.params.code}`
    const guestParams = auth.isAuthenticated ? undefined : { email: email.value }
    booking.value = responseData(await api.get(bookingEndpoint, { params: guestParams }))
    const [paymentResult, invoiceResult] = await Promise.allSettled([
      api.get(`/booking/${route.params.code}/payments`, { params: guestParams }),
      api.get(`/booking/${route.params.code}/invoice`, { params: guestParams }),
    ])
    if (paymentResult.status === 'fulfilled') booking.value.payment_transactions = responseData(paymentResult.value)
    if (invoiceResult.status === 'fulfilled') booking.value.invoice = invoiceResult.value.data?.data ?? null
    if (email.value) { sessionStorage.setItem('staygo_booking_email', email.value); localStorage.setItem('staygo_booking_email', email.value) }
    sessionStorage.setItem(`staygo_booking_${route.params.code}`, JSON.stringify(booking.value))
  } catch (err) {
    try { booking.value = JSON.parse(sessionStorage.getItem(`staygo_booking_${route.params.code}`)) } catch { booking.value = null }
    if (!booking.value) error.value = apiError(err, 'Không tìm thấy đặt phòng với mã và email này.')
  } finally { loading.value = false }
}
async function cancelBooking() {
  if (!confirm('Bạn chắc chắn muốn hủy đặt phòng này? Khoản hoàn tiền phụ thuộc chính sách đã đặt.')) return
  cancelling.value = true; error.value = ''
  try { booking.value = responseData(await api.post(`/bookings/${route.params.code}/cancel`, { email: email.value })) }
  catch (err) { error.value = apiError(err, 'Không thể hủy đặt phòng. Vui lòng liên hệ hỗ trợ.') }
  finally { cancelling.value = false }
}
function lookupBooking(code) { sessionStorage.setItem('staygo_booking_email', email.value); localStorage.setItem('staygo_booking_email', email.value); router.push(`/hotel/booking/${code.trim()}`) }
onMounted(() => { if (route.params.code !== 'tra-cuu') fetchBooking() })
watch(() => route.params.code, code => { booking.value = null; error.value = ''; if (code !== 'tra-cuu') fetchBooking() })
</script>

<template>
  <div class="booking-detail-page"><div class="container booking-detail-container">
    <div v-if="route.params.code === 'tra-cuu' || (!auth.isAuthenticated && !email && !booking)" class="lookup-card"><span class="success-icon lookup-icon">⌕</span><h1>Tra cứu đặt phòng</h1><p>Nhập mã đặt phòng và email đã dùng khi đặt.</p><form v-if="route.params.code !== 'tra-cuu'" @submit.prevent="fetchBooking"><label><span>Email đặt phòng</span><input v-model.trim="email" type="email" required /></label><button class="primary" type="submit">Tra cứu</button></form><form v-else @submit.prevent="lookupBooking($event.target.code.value)"><label><span>Mã đặt phòng</span><input name="code" required /></label><label><span>Email đặt phòng</span><input v-model.trim="email" type="email" required /></label><button class="primary" type="submit">Tra cứu</button></form></div>
    <div v-else-if="loading" class="state-card page-state"><span class="spinner"></span><h2>Đang tải hành trình...</h2></div>
    <div v-else-if="error && !booking" class="state-card error-state page-state"><h2>Không tìm thấy đặt phòng</h2><p>{{ error }}</p><button class="primary" @click="fetchBooking">Thử lại</button></div>
    <template v-else-if="booking">
      <section class="detail-hero"><div><p class="eyebrow">{{ route.query.payment === 'mock_success' ? 'Thanh toán demo thành công' : 'Hành trình StayGo' }}</p><h1>{{ cancelled ? 'Đặt phòng đã được hủy' : 'Thông tin đặt phòng' }}</h1><p>Quản lý lịch trình, dịch vụ và thanh toán tại một nơi.</p><div class="hero-actions"><a class="btn-discover-hero" href="/hotel">Tiếp tục khám phá</a></div></div><div class="code-block"><small>Mã đặt phòng</small><strong>{{ booking.code ?? booking.booking_code ?? route.params.code }}</strong><span class="status-pill" :class="{ cancelled }">{{ statusText(booking.status) }}</span></div></section>
      <div class="detail-layout"><main>
        <section class="detail-card hotel-booked"><img :src="localImage(roomType?.images?.[0]?.url ?? roomType?.image, roomType?.id)" :alt="bookedHotel?.name" /><div><span v-if="bookedHotel?.star_rating" class="stars">{{ '★'.repeat(Number(bookedHotel.star_rating)) }}</span><h2>{{ bookedHotel?.name ?? 'Khách sạn của bạn' }}</h2><p>{{ bookedHotel?.address }}</p><strong>{{ roomType?.name }}</strong></div></section>
        <section class="detail-card"><h2>Lịch trình trạng thái</h2><ol class="status-timeline"><li v-for="(item, index) in timeline" :key="`${item.status}-${index}`" :class="{ done: item.done !== false || item.at, cancelled: ['cancelled','canceled'].includes(item.status) }"><i>{{ item.done !== false || item.at ? '✓' : index + 1 }}</i><div><strong>{{ item.label ?? statusText(item.status) }}</strong><small>{{ dateTime(item.at ?? item.created_at) }}</small></div></li></ol></section>
        <section class="detail-card"><h2>Chi tiết lưu trú</h2><div class="stay-grid"><div><small>Nhận phòng</small><strong>{{ booking.checkin ?? booking.check_in }}</strong><span>Từ {{ stayTime(booking.scheduled_checkin_at, bookedHotel?.checkin_time ?? '15:00') }}</span></div><div><small>Trả phòng</small><strong>{{ booking.checkout ?? booking.check_out }}</strong><span>Trước {{ stayTime(booking.scheduled_checkout_at, bookedHotel?.checkout_time ?? '12:00') }}</span></div><div><small>Khách</small><strong>{{ booking.guest_name }}</strong><span>{{ booking.adults ?? 2 }} người lớn · {{ booking.children ?? 0 }} trẻ em</span></div><div><small>Số phòng</small><strong>{{ booking.rooms_count ?? booking.rooms_quantity ?? 1 }}</strong><span>{{ booking.nights }} đêm</span></div></div></section>
        <section v-if="services.length" class="detail-card"><h2>Dịch vụ đã đặt</h2><div class="booked-services"><div v-for="service in services" :key="service.id"><span><strong>{{ service.name ?? service.service?.name }}</strong><small>{{ service.quantity ?? 1 }} × {{ money(service.unit_price ?? service.price ?? service.service?.price) }}</small></span><strong>{{ money(service.total ?? Number(service.unit_price ?? service.price ?? service.service?.price) * Number(service.quantity ?? 1)) }}</strong></div></div></section>
        <section class="detail-card"><h2>Giao dịch thanh toán</h2><div v-if="payments.length" class="transactions"><div v-for="payment in payments" :key="payment.id"><i>{{ String(payment.provider ?? payment.method).includes('vietqr') ? '▦' : String(payment.provider ?? payment.method).includes('paypal') ? 'P' : '▰' }}</i><span><strong>{{ payment.provider ?? payment.method }}</strong><small>{{ dateTime(payment.created_at) }}<template v-if="payment.card_last_four"> · •••• {{ payment.card_last_four }}</template></small></span><strong>{{ money(payment.amount) }}</strong><em :class="String(payment.status).toLowerCase()">{{ statusText(payment.status) }}</em></div></div><p v-else class="muted">Chưa có giao dịch thanh toán được ghi nhận.</p></section>
      </main><aside>
        <section class="detail-card price-card"><h2>Hóa đơn</h2><PriceBreakdown :quote="pricing" :amount-due="amountDue" /><div v-if="invoice" class="invoice-meta"><span><small>Số hóa đơn</small><strong>{{ invoice.number }}</strong></span><span><small>Đã thanh toán</small><strong>{{ money(invoice.paid) }}</strong></span><span><small>Còn lại</small><strong>{{ money(invoice.balance) }}</strong></span></div><a v-if="invoice?.url ?? invoice?.download_url" class="invoice-link" :href="invoice.url ?? invoice.download_url" target="_blank" rel="noopener">Tải hóa đơn</a><p v-else-if="!invoice" class="muted">Hóa đơn sẽ xuất hiện khi khoản thanh toán đủ điều kiện.</p></section>
        <section class="detail-card help-card"><h3>Chính sách hủy</h3><p>{{ cancellationPolicy }}</p><dl v-if="cancelled" class="cancellation-result"><dt>Phí hủy</dt><dd>{{ money(booking.cancellation_fee) }}</dd><dt>Hoàn tiền mock</dt><dd>{{ money(booking.refund_amount) }}</dd></dl><button v-if="canCancel" class="danger-button" :disabled="cancelling" @click="cancelBooking">{{ cancelling ? 'Đang hủy...' : 'Hủy đặt phòng' }}</button><a class="secondary-button" href="mailto:support@staygo.vn">Liên hệ hỗ trợ</a></section>
      </aside></div>
      <p v-if="error" class="form-error">{{ error }}</p>
    </template>
  </div></div>
</template>

<style scoped>
.booking-detail-container{max-width:1080px}.detail-hero{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.detail-hero h1{margin:2px 0}.detail-hero>div>p:last-child{color:#637083}.code-block{min-width:245px;background:#fff;border-radius:10px;padding:15px 18px;box-shadow:0 4px 16px #13243a12;display:grid;grid-template-columns:1fr auto;align-items:center}.code-block small{grid-column:1/-1;color:#637083}.code-block strong{font-size:19px;color:#168a52;letter-spacing:.7px}.detail-layout{display:grid;grid-template-columns:1fr 315px;gap:18px;align-items:start}.detail-layout main{display:grid;gap:16px}.detail-layout aside{display:grid;gap:16px;position:sticky;top:18px}.detail-card{background:#fff;border:1px solid #dce3ea;border-radius:11px;padding:22px;box-shadow:0 3px 15px #13243a09}.detail-card h2{font-size:18px;margin-bottom:18px}.hotel-booked{display:flex;gap:18px}.hotel-booked img{width:170px;height:112px;object-fit:cover;border-radius:8px}.hotel-booked h2{margin:3px 0}.hotel-booked p,.muted{color:#637083}.status-timeline{display:flex;list-style:none;margin:0;padding:0}.status-timeline li{position:relative;flex:1;display:flex;gap:8px;color:#93a0ae}.status-timeline li:not(:last-child):after{content:'';position:absolute;left:28px;right:5px;top:13px;height:2px;background:#dce3ea}.status-timeline li.done:not(:last-child):after{background:#3dae75}.status-timeline i{position:relative;z-index:1;display:grid;place-items:center;flex:0 0 27px;height:27px;border-radius:50%;background:#e7ebef;font-style:normal;font-size:10px}.status-timeline .done i{background:#168a52;color:#fff}.status-timeline .cancelled i{background:#bd3c3c}.status-timeline li div{display:flex;flex-direction:column;padding-top:33px;margin-left:-35px}.status-timeline small{font-size:9px}.stay-grid{display:grid;grid-template-columns:repeat(4,1fr)}.stay-grid>div{display:flex;flex-direction:column;border-right:1px solid #dce3ea;padding:0 13px}.stay-grid>div:first-child{padding-left:0}.stay-grid>div:last-child{border:0}.stay-grid small,.stay-grid span{color:#637083}.booked-services,.transactions{display:grid;gap:10px}.booked-services>div{display:flex;justify-content:space-between;background:#f4f7fa;padding:12px;border-radius:7px}.booked-services span{display:flex;flex-direction:column}.booked-services small{color:#637083}.transactions>div{display:grid;grid-template-columns:34px 1fr auto auto;align-items:center;gap:10px;border-bottom:1px solid #e7ebef;padding:10px 0}.transactions i{display:grid;place-items:center;width:32px;height:32px;background:#e9f5fd;color:#0877cc;border-radius:8px;font-style:normal;font-weight:800}.transactions span{display:flex;flex-direction:column;text-transform:capitalize}.transactions small{color:#637083}.transactions em{font-style:normal;font-size:9px;border-radius:12px;padding:4px 7px;background:#fff5d9;color:#7a5413}.transactions em.succeeded,.transactions em.paid{background:#def4e8;color:#168a52}.transactions em.failed{background:#f8dede;color:#9c3232}.invoice-link{display:flex;justify-content:center;border:1px solid #0877cc;color:#0877cc;border-radius:7px;padding:10px;margin-top:18px;font-weight:700}.help-card p{color:#637083}.help-card .danger-button,.help-card .secondary-button{width:100%;margin-top:8px}.hero-actions{margin-top:14px}.btn-discover-hero{display:inline-flex;align-items:center;background:#f97316;color:#fff;padding:8px 20px;border-radius:20px;font-weight:700;font-size:12.5px;text-decoration:none;box-shadow:0 4px 10px rgba(249,115,22,0.2);transition:all 0.2s ease}.btn-discover-hero:hover{background:#ea580c;transform:translateY(-1px);box-shadow:0 6px 15px rgba(249,115,22,0.3)}@media(max-width:850px){.detail-layout{grid-template-columns:1fr}.detail-layout aside{position:static}.status-timeline{display:grid;gap:8px}.status-timeline li:not(:last-child):after{display:none}.status-timeline li div{padding:2px 0;margin:0}.stay-grid{grid-template-columns:1fr 1fr;gap:18px}.stay-grid>div{border:0;padding:0}}@media(max-width:620px){.detail-hero{display:block}.code-block{margin-top:15px}.hotel-booked{display:block}.hotel-booked img{width:100%;height:170px;margin-bottom:14px}.stay-grid{grid-template-columns:1fr}.transactions>div{grid-template-columns:34px 1fr auto}.transactions em{grid-column:2}}
.invoice-meta{display:grid;gap:8px;border-top:1px solid #dce3ea;margin-top:18px;padding-top:14px}.invoice-meta span{display:flex;justify-content:space-between}.invoice-meta small{color:#637083}
.cancellation-result{display:grid;grid-template-columns:1fr auto;gap:8px;border-top:1px solid #dce3ea;padding-top:12px}.cancellation-result dt{color:#637083}.cancellation-result dd{margin:0;font-weight:700}
</style>
