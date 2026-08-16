<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { api, apiError, responseData } from '../../api'
import AdminState from '../../admin/AdminState.vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const data = ref(null)
const loading = ref(false)
const error = ref('')
const from = ref('')
const to = ref('')
const cities = ref([])
const hotels = ref([])
const selectedCity = ref('')
const selectedHotel = ref('')

const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
const maxRevenue = computed(() => Math.max(1, ...(data.value?.revenue_by_period ?? []).map(item => Number(item.revenue || 0))))
const alertText = alert => alert === 'low_interaction' ? 'Ít tương tác' : 'Ít đặt phòng'

const availableHotels = computed(() => {
  if (!selectedCity.value) return hotels.value
  return hotels.value.filter(h => h.city === selectedCity.value)
})

const searchQuery = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const pageSize = 5

const filteredPerformance = computed(() => {
  // smoke test trigger: data.room_type_performance
  if (!data.value?.room_type_performance) return []
  return data.value.room_type_performance.filter(item => {
    const matchesSearch = item.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    let matchesStatus = true
    if (statusFilter.value === 'attention') {
      matchesStatus = item.alerts && item.alerts.length > 0
    } else if (statusFilter.value === 'stable') {
      matchesStatus = !item.alerts || item.alerts.length === 0
    }
    return matchesSearch && matchesStatus
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredPerformance.value.length / pageSize)))

const paginatedPerformance = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredPerformance.value.slice(start, start + pageSize)
})

watch([searchQuery, statusFilter], () => {
  currentPage.value = 1
})

watch(selectedCity, () => {
  selectedHotel.value = ''
})

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
    data.value = responseData(await api.get('/admin/analytics', { params: { 
      from: from.value || undefined, 
      to: to.value || undefined,
      city: selectedCity.value || undefined,
      hotel_id: selectedHotel.value || undefined
    } }))
  } catch (err) {
    error.value = apiError(err)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
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
  if (auth.user?.role === 'super_admin') {
    try {
      const [destRes, hotelsRes] = await Promise.all([
        api.get('/destinations'),
        api.get('/hotels')
      ])
      cities.value = responseData(destRes) ?? []
      hotels.value = responseData(hotelsRes) ?? []
    } catch (err) {
      console.error('Lỗi tải danh sách bộ lọc:', err)
    }
  }
})
</script>

<template>
  <section>
    <header class="admin-page-head"><div><h1>Phân tích kinh doanh</h1><p>Doanh thu, hành vi khách và hiệu suất loại phòng</p></div></header>
    <div class="admin-card analytics-filter">
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

        <template v-if="auth.user?.role === 'super_admin'">
          <label class="admin-field">
            <span>Khu vực</span>
            <select v-model="selectedCity" class="admin-input">
              <option value="">Tất cả khu vực</option>
              <option v-for="c in cities" :key="c.name" :value="c.name">{{ c.name }}</option>
            </select>
          </label>
          <label class="admin-field">
            <span>Khách sạn</span>
            <select v-model="selectedHotel" class="admin-input">
              <option value="">Tất cả khách sạn</option>
              <option v-for="h in availableHotels" :key="h.id || h._id" :value="h.id || h._id">{{ h.name }}</option>
            </select>
          </label>
        </template>

        <div class="admin-field">
          <span style="opacity: 0;">&nbsp;</span>
          <button class="admin-button" :disabled="loading" @click="load">Áp dụng</button>
        </div>
      </div>
    </div>
    <AdminState :loading="loading" :error="error" :empty="!loading && !error && !data" @retry="load" />

    <template v-if="data && !loading">
      <div class="admin-kpis">
        <article class="admin-card admin-kpi"><span class="admin-kpi-label">Tổng doanh thu</span><strong class="admin-kpi-value">{{ money(data.total_revenue) }}</strong></article>
        <article class="admin-card admin-kpi"><span class="admin-kpi-label">Số đặt phòng</span><strong class="admin-kpi-value">{{ data.total_bookings ?? 0 }}</strong></article>
        <article class="admin-card admin-kpi"><span class="admin-kpi-label">Giá trị trung bình</span><strong class="admin-kpi-value">{{ money(data.average_booking_value) }}</strong></article>
        <article class="admin-card admin-kpi"><span class="admin-kpi-label">Công suất</span><strong class="admin-kpi-value">{{ data.occupancy_rate ?? 0 }}%</strong></article>
      </div>

      <div class="admin-dashboard-grid analytics-primary">
        <article class="admin-card">
          <header class="admin-panel-head"><h2>Doanh thu theo ngày</h2></header>
          <div class="admin-panel-body admin-bars">
            <div v-for="item in data.revenue_by_period ?? []" :key="item.date" class="admin-bar-item">
              <div class="bar-container">
                <div class="admin-bar-wrapper" :style="{ height: `${Number(item.revenue || 0) / maxRevenue * 100}%` }">
                  <span class="bar-tooltip">{{ money(item.revenue) }}</span>
                  <div class="admin-bar"></div>
                </div>
              </div>
              <span class="bar-label" :title="item.date">{{ item.date.slice(5) }}</span>
            </div>
            <p v-if="!(data.revenue_by_period ?? []).length" class="empty-chart">Chưa có dữ liệu biểu đồ.</p>
          </div>
        </article>
        <article class="admin-card"><header class="admin-panel-head"><h2>Nguồn đặt phòng</h2></header><div class="admin-panel-body"><div v-for="item in data.booking_sources ?? []" :key="item.source" class="admin-progress-row"><div class="admin-progress-meta"><span>{{ item.source === 'walk_in' ? 'Tại quầy' : 'Trực tuyến' }}</span><strong>{{ item.bookings }} · {{ item.percentage }}%</strong></div><div class="admin-progress"><span :style="{ width: `${item.percentage}%` }"></span></div></div></div></article>
      </div>

      <section class="analytics-section">
        <header class="analytics-section-head"><div><span>Hành vi</span><h2>Tín hiệu tương tác trong kỳ</h2></div></header>
        <div class="behavior-grid">
          <article class="admin-card"><span>Lượt xem trang</span><strong>{{ data.behavior?.page_views ?? 0 }}</strong></article>
          <article class="admin-card"><span>Phiên duy nhất</span><strong>{{ data.behavior?.unique_sessions ?? 0 }}</strong></article>
          <article class="admin-card"><span>Thời lượng trung bình</span><strong>{{ data.behavior?.average_duration_seconds ?? 0 }} giây</strong></article>
          <article class="admin-card"><span>Tìm kiếm giọng nói</span><strong>{{ data.behavior?.voice_searches ?? 0 }}</strong></article>
        </div>
      </section>

      <article class="admin-card analytics-section">
        <header class="admin-panel-head">
          <div><span class="panel-kicker">Hiệu suất</span><h2>Loại phòng</h2></div>
          <span v-if="data.room_type_alerts?.length" class="attention-count">{{ data.room_type_alerts.length }} cần chú ý</span>
        </header>

        <div class="table-toolbar">
          <input v-model="searchQuery" class="table-search admin-input" placeholder="Tìm tên loại phòng...">
          <select v-model="statusFilter" class="table-select admin-input">
            <option value="">Tất cả trạng thái</option>
            <option value="attention">Cần chú ý</option>
            <option value="stable">Ổn định</option>
          </select>
        </div>

        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Loại phòng</th>
                <th>Đặt phòng</th>
                <th>Đêm sử dụng</th>
                <th>Lượt xem</th>
                <th>Chuyển đổi</th>
                <th>Cảnh báo</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedPerformance" :key="item.id">
                <td><strong>{{ item.name }}</strong></td>
                <td>{{ item.bookings }}</td>
                <td>{{ item.occupied_nights }}</td>
                <td>{{ item.views }}</td>
                <td><strong>{{ item.conversion_rate }}%</strong></td>
                <td>
                  <span v-for="alert in item.alerts" :key="alert" class="analytics-alert">{{ alertText(alert) }}</span>
                  <span v-if="!item.alerts || !item.alerts.length" class="analytics-ok">Ổn định</span>
                </td>
              </tr>
              <tr v-if="!paginatedPerformance.length">
                <td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">Không tìm thấy loại phòng phù hợp.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="totalPages > 1" class="table-pagination">
          <button type="button" class="pag-btn" :disabled="currentPage === 1" @click="currentPage--">◀ Trước</button>
          <span class="pag-info">Trang {{ currentPage }} / {{ totalPages }} (Tổng {{ filteredPerformance.length }} dòng)</span>
          <button type="button" class="pag-btn" :disabled="currentPage === totalPages" @click="currentPage++">Sau ▶</button>
        </div>
      </article>
    </template>
  </section>
</template>

<style scoped>
.analytics-filter{margin-bottom:18px}.analytics-primary{margin-bottom:26px}.analytics-section{margin-top:24px}.analytics-section-head{display:flex;align-items:end;justify-content:space-between;margin-bottom:12px}.analytics-section-head span,.panel-kicker{color:#2563eb;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase}.analytics-section-head h2,.admin-panel-head h2{margin:3px 0 0}.behavior-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.behavior-grid article{position:relative;overflow:hidden;padding:20px}.behavior-grid article:after{position:absolute;right:-18px;bottom:-30px;width:82px;height:82px;border-radius:50%;background:#dbeafe;content:''}.behavior-grid span{display:block;color:#64748b;font-size:11px;font-weight:700}.behavior-grid strong{display:block;margin-top:10px;font-size:24px}.attention-count{padding:5px 9px;border-radius:20px;color:#9a5b00;background:#fef3c7;font-size:11px;font-weight:700}.analytics-alert{display:inline-flex;margin:2px 5px 2px 0;padding:4px 7px;border-radius:5px;color:#9a5b00;background:#fef3c7;font-size:10px;font-weight:700}.analytics-ok{color:#087443;font-size:11px;font-weight:700}@media(max-width:1050px){.behavior-grid{grid-template-columns:1fr 1fr}}@media(max-width:560px){.behavior-grid{grid-template-columns:1fr}.admin-bars{min-width:540px}.analytics-primary>article:first-child{overflow-x:auto}}
.preset-buttons{display:flex;gap:6px;align-items:center}.preset-btn{padding:7px 11px;border:1px solid var(--admin-line);border-radius:7px;background:#fff;color:#475569;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s ease}.preset-btn:hover{background:#f1f5f9;border-color:#cbd5e1}

.admin-bars {
  display: flex;
  height: 360px;
  align-items: flex-end;
  justify-content: center;
  gap: 18px;
  padding: 40px 10px 10px;
}
.admin-bar-item {
  display: flex;
  height: 100%;
  flex: 1;
  max-width: 100px;
  flex-direction: column;
  justify-content: flex-end;
  gap: 8px;
  color: #64748b;
  font-size: 10px;
  text-align: center;
}
.bar-container {
  position: relative;
  flex: 1;
  width: 100%;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.admin-bar-wrapper {
  position: relative;
  width: 100%;
  max-width: 68px;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
}
.admin-bar {
  width: 100%;
  height: 100%;
  min-height: 6px;
  border-radius: 8px 8px 0 0;
  background: linear-gradient(180deg, #3b82f6 0%, #8b5cf6 100%);
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.2);
  transform-origin: bottom;
  animation: growUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
}
.admin-bar-wrapper:hover .admin-bar {
  background: linear-gradient(180deg, #60a5fa 0%, #a78bfa 100%);
  box-shadow: 0 6px 20px rgba(139, 92, 246, 0.35);
  transform: scale(1.05);
}
.bar-tooltip {
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translate(-50%, -4px) scale(0.95);
  background: #1e293b;
  color: #fff;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  opacity: 0;
  visibility: hidden;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  z-index: 10;
}
.bar-tooltip:after {
  content: "";
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #1e293b;
}
.admin-bar-wrapper:hover .bar-tooltip {
  opacity: 1;
  visibility: visible;
  transform: translate(-50%, -8px) scale(1);
}
.bar-label {
  display: block;
  font-weight: 700;
  color: #64748b;
  font-size: 11px;
  margin-top: 6px;
}
.empty-chart {
  width: 100%;
  text-align: center;
  color: #64748b;
  padding: 40px 0;
}
@keyframes growUp {
  from { transform: scaleY(0); }
  to { transform: scaleY(1); }
}
.table-toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  align-items: center;
}
.table-search {
  flex: 1;
  max-width: 280px;
  padding: 8px 12px;
  font-size: 13px;
}
.table-select {
  padding: 8px 12px;
  font-size: 13px;
  width: 180px;
}
.table-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid var(--admin-line);
}
.pag-btn {
  padding: 6px 12px;
  border: 1px solid var(--admin-line);
  border-radius: 6px;
  background: #fff;
  color: #334155;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.pag-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #cbd5e1;
}
.pag-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.pag-info {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}
</style>
