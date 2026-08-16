<script setup>
import { onMounted, ref } from 'vue'
import { api, apiError, responseData } from '../../api'
import AdminState from '../../admin/AdminState.vue'

const payments = ref([])
const loading = ref(false)
const error = ref('')
const status = ref('')
const from = ref('')
const to = ref('')
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
const dateTime = value => value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—'

const payMethod = value => {
  const map = {
    cash: 'Tiền mặt',
    pay_at_hotel: 'Tại khách sạn',
    paypal: 'PayPal',
    card_mock: 'Thẻ thanh toán',
    card: 'Thẻ thanh toán'
  }
  return map[value] ?? value
}

const payType = value => {
  const map = {
    full: 'Thanh toán đủ',
    deposit: 'Đặt cọc',
    refund: 'Hoàn tiền'
  }
  return map[value] ?? value
}

const payStatus = value => {
  const map = {
    created: 'Khởi tạo',
    succeeded: 'Thành công',
    failed: 'Thất bại',
    refunded: 'Đã hoàn tiền'
  }
  return map[value] ?? value
}

function selectPreset(preset) {
  const now = new Date()
  let fromDate, toDate = new Date()

  if (preset === '1') {
    fromDate = new Date()
  } else if (preset === '3') {
    fromDate = new Date()
    fromDate.setDate(now.getDate() - 2)
  } else if (preset === '7') {
    fromDate = new Date()
    fromDate.setDate(now.getDate() - 6)
  } else if (preset === '30') {
    fromDate = new Date()
    fromDate.setDate(now.getDate() - 29)
  } else if (preset === 'last_month') {
    fromDate = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    toDate = new Date(now.getFullYear(), now.getMonth(), 0)
  }

  const format = d => {
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  from.value = format(fromDate)
  to.value = format(toDate)
  load()
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const result = responseData(await api.get('/admin/payments', { params: { status: status.value || undefined, from: from.value || undefined, to: to.value || undefined } }))
    payments.value = Array.isArray(result) ? result : result?.items ?? []
  } catch (err) {
    error.value = apiError(err, 'Không thể tải báo cáo giao dịch.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  const now = new Date()
  const fromDate = new Date()
  fromDate.setDate(now.getDate() - 6)
  const format = d => {
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  from.value = format(fromDate)
  to.value = format(now)
  
  load()
})
</script>

<template>
  <section>
    <header class="admin-page-head"><div><h1>Giao dịch thanh toán</h1><p>Đối soát các khoản thu và hoàn tiền theo thời gian</p></div></header>
    <div class="admin-card">
      <div class="admin-toolbar">
        <label class="admin-field"><span>Từ ngày</span><input v-model="from" class="admin-input" type="date"></label>
        <label class="admin-field"><span>Đến ngày</span><input v-model="to" class="admin-input" type="date"></label>
        
        <div class="admin-field">
          <span>Chọn nhanh</span>
          <div class="preset-buttons">
            <button type="button" class="preset-btn" @click="selectPreset('1')">1 ngày</button>
            <button type="button" class="preset-btn" @click="selectPreset('3')">3 ngày</button>
            <button type="button" class="preset-btn" @click="selectPreset('7')">7 ngày</button>
            <button type="button" class="preset-btn" @click="selectPreset('30')">30 ngày</button>
            <button type="button" class="preset-btn" @click="selectPreset('last_month')">Tháng trước</button>
          </div>
        </div>

        <label class="admin-field">
          <span>Trạng thái</span>
          <select v-model="status" class="admin-select">
            <option value="">Trạng thái: Tất cả</option>
            <option value="created">Khởi tạo</option>
            <option value="succeeded">Thành công</option>
            <option value="failed">Thất bại</option>
            <option value="refunded">Đã hoàn tiền</option>
          </select>
        </label>
        
        <div class="admin-field">
          <span style="opacity: 0;">&nbsp;</span>
          <button class="admin-button" :disabled="loading" @click="load">Lọc báo cáo</button>
        </div>
      </div>
      <AdminState :loading="loading" :error="error" :empty="!loading && !error && !payments.length" empty-text="Không có giao dịch trong khoảng đã chọn." @retry="load" />
      <div v-if="!loading && payments.length" class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Tham chiếu</th><th>Đặt phòng</th><th>Phương thức</th><th>Loại</th><th>Số tiền</th><th>Trạng thái</th></tr></thead><tbody><tr v-for="item in payments" :key="item.id"><td>{{ dateTime(item.processed_at ?? item.created_at) }}</td><td><strong>{{ item.reference }}</strong></td><td>{{ item.booking?.code ?? item.booking_id }}</td><td>{{ payMethod(item.method) }}</td><td>{{ payType(item.type) }}</td><td><strong>{{ money(item.amount) }}</strong></td><td><span class="admin-badge" :class="item.status">{{ payStatus(item.status) }}</span></td></tr></tbody></table></div>
    </div>
  </section>
</template>

<style scoped>
.preset-buttons{display:flex;gap:6px;align-items:center}.preset-btn{padding:7px 11px;border:1px solid var(--admin-line);border-radius:7px;background:#fff;color:#475569;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s ease}.preset-btn:hover{background:#f1f5f9;border-color:#cbd5e1}
</style>
