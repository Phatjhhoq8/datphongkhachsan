<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api, apiError, responseData } from '../../api'
import AdminState from '../../admin/AdminState.vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const bookings = ref([]), hotels = ref([]), roomTypes = ref([]), rooms = ref([])
const loading = ref(false), error = ref(''), search = ref(''), status = ref('')
const open = ref(false), saving = ref(false), formError = ref('')
const form = reactive({ guest_name: '', guest_phone: '', guest_email: '', hotel_id: '', room_type_id: '', allocation: 'quantity', room_id: '', rooms: 1, checkin: '', checkout: '', adults: 1, children: 0, arrival_time: '14:00', checkout_time: '12:00' })
const isSuperAdmin = computed(() => auth.roles.includes('super_admin'))
const selectedHotel = computed(() => hotels.value.find(item => String(item.id) === String(form.hotel_id)))
const availableRooms = computed(() => rooms.value.filter(room => String(room.room_type_id ?? room.room_type?.id) === String(form.room_type_id) && room.active !== false && ['available', 'cleaning'].includes(room.operational_status)))
const filtered = computed(() => bookings.value.filter(item => (!status.value || item.status === status.value) && (!search.value || [item.code, item.booking_code, item.guest_name, item.guest_phone].some(value => String(value ?? '').toLowerCase().includes(search.value.toLowerCase())))))
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))

const payStatusMap = value => {
  const map = {
    paid: 'Đã thanh toán',
    unpaid: 'Chưa thanh toán',
    partial: 'Thanh toán một phần'
  }
  return map[value] ?? value ?? 'Chưa thanh toán'
}

const bookingStatusMap = value => {
  const map = {
    pending: 'Chờ xử lý',
    confirmed: 'Đã xác nhận',
    checked_in: 'Đã nhận phòng',
    checked_out: 'Đã trả phòng',
    cancelled: 'Đã hủy',
    expired: 'Đã hết hạn'
  }
  return map[value] ?? value
}
const dummyStatuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'expired']

function list(response) {
  const data = responseData(response)
  return Array.isArray(data) ? data : data?.bookings ?? data?.items ?? data?.data ?? []
}
async function load() {
  loading.value = true; error.value = ''
  try { bookings.value = list(await api.get('/admin/bookings')) }
  catch (err) { error.value = apiError(err) }
  finally { loading.value = false }
}
async function loadHotels() {
  if (isSuperAdmin.value || auth.roles.includes('hotel_manager')) hotels.value = list(await api.get('/admin/hotels'))
  else if (auth.user?.hotel_id) hotels.value = [{ id: auth.user.hotel_id, name: auth.user.hotel?.name ?? 'Khách sạn được phân quyền' }]
  if (!form.hotel_id && hotels.value.length) form.hotel_id = String(hotels.value[0].id)
}
async function loadInventory() {
  if (!form.hotel_id) return
  const params = { hotel_id: form.hotel_id }
  const [typesResponse, roomsResponse] = await Promise.all([
    api.get('/admin/room-types', { params }),
    api.get('/admin/rooms', { params }),
  ])
  roomTypes.value = list(typesResponse)
  rooms.value = list(roomsResponse)
  if (!roomTypes.value.some(item => String(item.id) === String(form.room_type_id))) form.room_type_id = ''
}
async function openCounterBooking() {
  open.value = true; formError.value = ''
  try { await loadHotels(); await loadInventory() }
  catch (err) { formError.value = apiError(err, 'Không thể tải danh mục phòng.') }
}
async function createBooking() {
  saving.value = true; formError.value = ''
  const specificRoom = form.allocation === 'room'
  try {
    await api.post('/admin/bookings/counter', {
      room_type_id: form.room_type_id,
      room_ids: specificRoom ? [form.room_id] : undefined,
      rooms: specificRoom ? undefined : Number(form.rooms),
      guest_name: form.guest_name,
      guest_email: form.guest_email,
      guest_phone: form.guest_phone,
      checkin: form.checkin,
      checkout: form.checkout,
      adults: Number(form.adults),
      children: Number(form.children),
      arrival_time: form.arrival_time || undefined,
      checkout_time: form.checkout_time || undefined,
    })
    open.value = false
    await load()
  } catch (err) { formError.value = apiError(err, 'Không thể tạo đặt phòng tại quầy.') }
  finally { saving.value = false }
}

watch(() => form.hotel_id, () => { form.room_type_id = ''; form.room_id = ''; loadInventory().catch(err => { formError.value = apiError(err) }) })
watch(() => form.room_type_id, () => { form.room_id = '' })
onMounted(load)

const showSearchDropdown = ref(false)

const searchSuggestions = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return []
  return bookings.value.filter(item => {
    return [item.code, item.booking_code, item.guest_name, item.guest_phone].some(value => 
      String(value ?? '').toLowerCase().includes(query)
    )
  }).slice(0, 10)
})

function selectSuggestion(item) {
  search.value = item.guest_name
  showSearchDropdown.value = false
}

function handleSearchBlur() {
  setTimeout(() => {
    showSearchDropdown.value = false
  }, 200)
}
</script>

<template>
  <section>
    <header class="admin-page-head"><div><h1>Đặt phòng</h1><p>Danh sách đặt chỗ và nghiệp vụ lễ tân</p></div><button class="admin-button" @click="openCounterBooking">+ Đặt tại quầy</button></header>
    <div class="admin-card">
      <div class="admin-toolbar">
        <div class="autocomplete-wrapper admin-search-wrapper">
          <input 
            v-model="search" 
            class="admin-input admin-search" 
            type="search" 
            placeholder="Mã đặt phòng, tên hoặc số điện thoại..." 
            @focus="showSearchDropdown = true"
            @blur="handleSearchBlur"
          />
          <transition name="fade">
            <ul v-if="showSearchDropdown && searchSuggestions.length" class="destinations-dropdown admin-search-dropdown">
              <li 
                v-for="item in searchSuggestions" 
                :key="item.id"
                class="dropdown-item"
                @mousedown="selectSuggestion(item)"
              >
                <div class="dest-info">
                  <span class="dest-name">{{ item.guest_name }}</span>
                  <small class="dest-count">Mã: {{ item.code || item.booking_code }} - SĐT: {{ item.guest_phone }}</small>
                </div>
              </li>
            </ul>
          </transition>
        </div>
        <select v-model="status" class="admin-select">
          <option value="">Trạng thái: Tất cả</option>
          <option value="pending">Chờ xử lý</option>
          <option value="confirmed">Đã xác nhận</option>
          <option value="checked_in">Đã nhận phòng</option>
          <option value="checked_out">Đã trả phòng</option>
          <option value="cancelled">Đã hủy</option>
          <option value="expired">Đã hết hạn</option>
        </select>
      </div>
      <AdminState :loading="loading" :error="error" :empty="!loading&&!error&&!filtered.length" empty-text="Không tìm thấy đặt phòng." @retry="load" />
      <div v-if="!loading&&filtered.length" class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Mã</th><th>Khách hàng</th><th>Nhận / Trả</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái</th><th></th></tr></thead><tbody><tr v-for="item in filtered" :key="item.id"><td><strong>{{ item.code??item.booking_code }}</strong></td><td>{{ item.guest_name }}<br /><small>{{ item.guest_phone }}</small></td><td>{{ item.checkin??item.check_in }}<br />{{ item.checkout??item.check_out }}</td><td>{{ money(item.total??item.total_amount) }}</td><td><span class="admin-badge" :class="item.payment_status">{{ payStatusMap(item.payment_status) }}</span></td><td><span class="admin-badge" :class="item.status">{{ bookingStatusMap(item.status) }}</span></td><td><router-link class="admin-button secondary small" :to="`/admin/bookings/${item.id}`">Chi tiết</router-link></td></tr></tbody></table></div>
    </div>
    <div v-if="open" class="admin-modal-backdrop" @click.self="open=false"><form class="admin-modal" @submit.prevent="createBooking"><header class="admin-modal-head"><h2>Đặt phòng tại quầy</h2><button class="admin-modal-close" type="button" @click="open=false">×</button></header><div class="admin-modal-body"><p v-if="formError" class="admin-alert">{{ formError }}</p><div class="admin-form-grid">
      <label class="admin-field full"><span>Khách sạn</span><select v-model="form.hotel_id" class="admin-select" required :disabled="!isSuperAdmin"><option value="">-- Chọn khách sạn --</option><option v-for="hotel in hotels" :key="hotel.id" :value="String(hotel.id)">{{ hotel.name }}</option></select><small v-if="selectedHotel">{{ selectedHotel.address }}</small></label>
      <label class="admin-field full"><span>Loại phòng</span><select v-model="form.room_type_id" class="admin-select" required><option value="">-- Chọn loại phòng --</option><option v-for="type in roomTypes" :key="type.id" :value="String(type.id)">{{ type.name }}</option></select></label>
      <label class="admin-field"><span>Cách chọn</span><select v-model="form.allocation" class="admin-select"><option value="quantity">Theo số lượng</option><option value="room">Chọn phòng cụ thể</option></select></label>
      <label v-if="form.allocation==='quantity'" class="admin-field"><span>Số lượng phòng</span><input v-model.number="form.rooms" class="admin-input" type="number" min="1" required /></label>
      <label v-else class="admin-field"><span>Phòng</span><select v-model="form.room_id" class="admin-select" required><option value="">-- Chọn phòng --</option><option v-for="room in availableRooms" :key="room.id" :value="String(room.id)">Phòng {{ room.room_number }} ({{ room.operational_status }})</option></select></label>
      <label class="admin-field"><span>Ngày nhận phòng</span><input v-model="form.checkin" class="admin-input" type="date" required /></label><label class="admin-field"><span>Giờ nhận dự kiến</span><input v-model="form.arrival_time" class="admin-input" type="time" required /></label>
      <label class="admin-field"><span>Ngày trả phòng</span><input v-model="form.checkout" class="admin-input" type="date" required /></label><label class="admin-field"><span>Giờ trả dự kiến</span><input v-model="form.checkout_time" class="admin-input" type="time" required /></label>
      <label class="admin-field full"><span>Họ tên khách</span><input v-model="form.guest_name" class="admin-input" required /></label><label class="admin-field"><span>Email</span><input v-model="form.guest_email" class="admin-input" type="email" required /></label><label class="admin-field"><span>Điện thoại</span><input v-model="form.guest_phone" class="admin-input" required /></label><label class="admin-field"><span>Người lớn</span><input v-model.number="form.adults" class="admin-input" type="number" min="1" required /></label><label class="admin-field"><span>Trẻ em</span><input v-model.number="form.children" class="admin-input" type="number" min="0" /></label>
    </div></div><footer class="admin-modal-foot"><button class="admin-button secondary" type="button" @click="open=false">Hủy</button><button class="admin-button" :disabled="saving">{{ saving?'Đang lưu...':'Tạo đặt phòng' }}</button></footer></form></div>
  </section>
</template>

<style scoped>
.admin-search-wrapper {
  position: relative;
  width: 320px;
}
.admin-search-wrapper input {
  width: 100% !important;
}
@media (max-width: 760px) {
  .admin-search-wrapper {
    width: 100% !important;
  }
}
.admin-search-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  width: 100%;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--admin-line);
  list-style: none;
  margin: 0;
  padding: 6px 0;
  z-index: 999;
  max-height: 250px;
  overflow-y: auto;
}
.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 14px;
  cursor: pointer;
  transition: background 0.15s ease;
}
.dropdown-item:hover {
  background: #f1f5f9;
}
.dest-info {
  display: flex;
  flex-direction: column;
  text-align: left;
}
.dest-name {
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
}
.dest-count {
  font-size: 11px;
  color: #64748b;
  margin-top: 2px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
