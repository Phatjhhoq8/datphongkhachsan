<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, apiError, responseData } from '../../api'
import AdminState from '../../admin/AdminState.vue'

const transitions = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['checked_in', 'cancelled'],
  checked_in: ['checked_out'],
  checked_out: [],
  cancelled: [],
  expired: [],
}
const route = useRoute()
const booking = ref(null)
const loading = ref(false)
const working = ref(false)
const error = ref('')
const status = ref('')
const paymentMethod = ref('cash')
const paymentAmount = ref('')
const paymentError = ref('')
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
const paid = computed(() => Number(booking.value?.paid_amount ?? 0))
const balance = computed(() => Math.max(0, Number(booking.value?.total_amount ?? booking.value?.total ?? 0) - paid.value))
const nextStatuses = computed(() => transitions[booking.value?.status] ?? [])
const paymentLocked = computed(() => balance.value <= 0 || booking.value?.payment_status === 'paid' || ['cancelled', 'expired'].includes(booking.value?.status))

async function load() {
  loading.value = true
  error.value = ''
  try {
    booking.value = responseData(await api.get(`/admin/bookings/${route.params.id}`))
    status.value = nextStatuses.value[0] ?? ''
    paymentAmount.value = balance.value
  } catch (err) {
    error.value = apiError(err)
  } finally {
    loading.value = false
  }
}

async function updateStatus() {
  if (!nextStatuses.value.includes(status.value)) return
  working.value = true
  error.value = ''
  try {
    await api.patch(`/admin/bookings/${route.params.id}/status`, { status: status.value })
    await load()
  } catch (err) {
    error.value = apiError(err, 'Không thể cập nhật trạng thái.')
  } finally {
    working.value = false
  }
}

async function recordPayment() {
  paymentError.value = ''
  const amount = Number(paymentAmount.value)
  if (!Number.isFinite(amount) || amount < 1) {
    paymentError.value = 'Số tiền phải lớn hơn 0.'
    return
  }
  if (amount > balance.value) {
    paymentError.value = 'Số tiền không được vượt quá số dư còn lại.'
    return
  }

  working.value = true
  try {
    await api.post(`/admin/bookings/${route.params.id}/payments`, { method: paymentMethod.value, amount })
    await load()
  } catch (err) {
    paymentError.value = apiError(err, 'Không thể ghi nhận thanh toán.')
  } finally {
    working.value = false
  }
}

async function invoice() {
  working.value = true
  try {
    const response = await api.get(`/admin/bookings/${route.params.id}/invoice`)
    const blob = new Blob([JSON.stringify(responseData(response), null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (err) {
    error.value = apiError(err, 'Không thể xuất hóa đơn.')
  } finally {
    working.value = false
  }
}

onMounted(load)
</script>

<template>
  <section>
    <header class="admin-page-head">
      <div><router-link to="/admin/bookings">← Danh sách đặt phòng</router-link><h1>Chi tiết {{ booking?.code ?? booking?.booking_code ?? route.params.id }}</h1><p>Trạng thái lưu trú, thanh toán và hóa đơn</p></div>
      <button class="admin-button secondary" :disabled="working" @click="invoice">Xuất hóa đơn</button>
    </header>
    <p v-if="error && booking" class="admin-alert">{{ error }}</p>
    <AdminState :loading="loading" :error="error && !booking ? error : ''" :empty="!loading && !error && !booking" @retry="load" />
    <div v-if="booking && !loading" class="admin-detail-grid">
      <article class="admin-card">
        <header class="admin-panel-head"><h2>Thông tin đặt phòng</h2><span class="admin-badge" :class="booking.status">{{ booking.status }}</span></header>
        <div class="admin-panel-body">
          <dl class="admin-definition"><dt>Khách hàng</dt><dd>{{ booking.guest_name }}</dd></dl>
          <dl class="admin-definition"><dt>Liên hệ</dt><dd>{{ booking.guest_phone }} · {{ booking.guest_email }}</dd></dl>
          <dl class="admin-definition"><dt>Khách sạn</dt><dd>{{ booking.hotel?.name ?? booking.rooms?.[0]?.room_type?.hotel?.name ?? booking.hotel_name }}</dd></dl>
          <dl class="admin-definition"><dt>Phòng</dt><dd><template v-if="booking.rooms?.length"><div v-for="room in booking.rooms" :key="room.id">{{ room.room_number ?? room.number ?? 'Chưa xếp' }} · {{ room.room_type?.name ?? room.roomType?.name ?? room.room_type_name ?? 'Chưa phân loại' }}</div></template><span v-else>Chưa xếp</span></dd></dl>
          <dl class="admin-definition"><dt>Lưu trú</dt><dd>{{ booking.check_in ?? booking.checkin }} → {{ booking.check_out ?? booking.checkout }}</dd></dl>
          <dl class="admin-definition"><dt>Số khách</dt><dd>{{ booking.adults ?? 0 }} người lớn, {{ booking.children ?? 0 }} trẻ em</dd></dl>
          <dl class="admin-definition"><dt>Ghi chú</dt><dd>{{ booking.note ?? booking.special_requests ?? 'Không có' }}</dd></dl>
        </div>
      </article>
      <aside>
        <article class="admin-card" style="margin-bottom:18px">
          <header class="admin-panel-head"><h2>Nghiệp vụ lễ tân</h2></header>
          <form v-if="nextStatuses.length" class="admin-panel-body" @submit.prevent="updateStatus">
            <label class="admin-field"><span>Chuyển trạng thái</span><select v-model="status" class="admin-select"><option v-for="item in nextStatuses" :key="item" :value="item">{{ item }}</option></select></label>
            <button class="admin-button" style="width:100%;margin-top:10px" :disabled="working || !status">Cập nhật trạng thái</button>
          </form>
          <div v-else class="admin-panel-body"><p>Không có hành động trạng thái phù hợp.</p></div>
        </article>
        <article class="admin-card">
          <header class="admin-panel-head"><h2>Thanh toán</h2><span class="admin-badge" :class="booking.payment_status">{{ booking.payment_status ?? 'unpaid' }}</span></header>
          <div class="admin-panel-body admin-stat-list">
            <div class="admin-stat-line"><span>Tổng tiền</span><b>{{ money(booking.total_amount ?? booking.total) }}</b></div>
            <div class="admin-stat-line"><span>Đã trả</span><b>{{ money(booking.paid_amount) }}</b></div>
            <div class="admin-stat-line"><span>Còn lại</span><b>{{ money(balance) }}</b></div>
            <form v-if="!paymentLocked" @submit.prevent="recordPayment">
              <label class="admin-field"><span>Phương thức</span><select v-model="paymentMethod" class="admin-select"><option v-for="method in ['cash', 'pay_at_hotel']" :key="method" :value="method">{{ method }}</option></select></label>
              <label class="admin-field"><span>Số tiền</span><input v-model="paymentAmount" class="admin-input" type="number" min="1" :max="balance" step="1" required /></label>
              <p v-if="paymentError" class="admin-alert">{{ paymentError }}</p>
              <button class="admin-button" :disabled="working">{{ working ? 'Đang ghi nhận...' : 'Ghi nhận thanh toán mock' }}</button>
            </form>
            <p v-else-if="['cancelled', 'expired'].includes(booking.status)">Không thể ghi nhận thanh toán cho booking {{ booking.status }}.</p>
            <p v-else>Booking đã được thanh toán đủ.</p>
          </div>
        </article>
      </aside>
    </div>
  </section>
</template>
