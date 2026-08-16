<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { api, apiError, responseData } from '../../api'
import AdminState from '../../admin/AdminState.vue'
import { createAdminRoomSocket } from '../../realtime'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const rooms = ref([]), hotels = ref([]), loading = ref(false), error = ref(''), hotelId = ref('')
const lastUpdated = ref(null), timer = ref(null), socketCleanup = ref(null), completing = ref('')
const isSuperAdmin = computed(() => auth.roles.includes('super_admin'))
const statuses = [['available','Trống','#22c55e'],['occupied','Có khách','#3b82f6'],['cleaning','Đang dọn','#f59e0b'],['maintenance','Bảo trì','#ef4444'],['out_of_service','Ngừng phục vụ','#64748b']]
const floors = computed(() => Object.entries(rooms.value.reduce((groups, room) => { const floor=room.floor??'Khác'; (groups[floor]??=[]).push(room); return groups }, {})).sort(([a],[b]) => Number(a)-Number(b)))

function list(response) { const data=responseData(response); return Array.isArray(data)?data:data?.items??data?.data??[] }
function roomList(response) { const data=responseData(response); if(Array.isArray(data))return data; return data?.rooms??data?.items??Object.values(data??{}).flat() }
function availableAt(value) { if(!value)return ''; const date=new Date(value); return Number.isNaN(date.getTime())?value:new Intl.DateTimeFormat('vi-VN',{dateStyle:'short',timeStyle:'short'}).format(date) }
async function load(silent=false) {
  if(!hotelId.value)return
  if(!silent)loading.value=true
  error.value=''
  try { rooms.value=roomList(await api.get('/admin/room-map',{params:{hotel_id:hotelId.value}})); lastUpdated.value=new Date() }
  catch(err){error.value=apiError(err,'Không thể cập nhật sơ đồ phòng.')}
  finally{loading.value=false}
}
async function loadHotelScope() {
  if(isSuperAdmin.value) {
    hotels.value=list(await api.get('/admin/hotels'))
    if(!hotelId.value&&hotels.value.length)hotelId.value=String(hotels.value[0].id)
  } else {
    hotelId.value=String(auth.user?.hotel_id??'')
    hotels.value=hotelId.value?[{id:hotelId.value,name:auth.user?.hotel?.name??'Khách sạn được phân quyền'}]:[]
  }
}
function startPolling(){stopPolling();timer.value=setInterval(()=>load(true),10000)}
function stopPolling(){if(timer.value)clearInterval(timer.value);timer.value=null}
function prepareSocketHook(){
  if(!hotelId.value)return false
  socketCleanup.value?.()
  const socket=createAdminRoomSocket({hotelId:hotelId.value})
  const updateRoom=(event)=>{if(event?.room){const index=rooms.value.findIndex(room=>room.id===event.room.id);if(index>=0)rooms.value[index]=event.room;else rooms.value.push(event.room);lastUpdated.value=new Date()}}
  socket.on('room.updated',updateRoom);socket.on('booking.updated',()=>load(true));socket.on('connect',stopPolling);socket.on('connect_error',startPolling);socket.on('disconnect',startPolling)
  socketCleanup.value=()=>socket.close()
  return true
}
async function refresh(){await load();if(!prepareSocketHook())startPolling()}
async function cleaningComplete(room){
  completing.value=room.id;error.value=''
  try { await api.post(`/admin/rooms/${room.id}/cleaning-complete`); await load(true) }
  catch(err){error.value=apiError(err,'Không thể xác nhận dọn phòng.')}
  finally{completing.value=''}
}
watch(hotelId,()=>{if(hotelId.value)refresh()})
onMounted(async()=>{try{await loadHotelScope();if(hotelId.value)await refresh()}catch(err){error.value=apiError(err,'Không thể tải khách sạn.')}})
onBeforeUnmount(()=>{stopPolling();socketCleanup.value?.()})
</script>

<template>
  <section><header class="admin-page-head"><div><h1>Sơ đồ phòng</h1><p>Trạng thái phòng theo tầng, tự động cập nhật mỗi 10 giây</p></div><button class="admin-button secondary" @click="refresh">Làm mới</button></header><div class="admin-card"><div class="admin-toolbar"><select v-model="hotelId" class="admin-select" :disabled="!isSuperAdmin"><option value="">-- Chọn khách sạn --</option><option v-for="hotel in hotels" :key="hotel.id" :value="String(hotel.id)">{{hotel.name}}</option></select><div class="admin-legend"><span v-for="status in statuses" :key="status[0]"><i class="admin-dot" :style="{background:status[2]}"></i>{{status[1]}}</span></div><small v-if="lastUpdated" style="margin-left:auto;color:#64748b">Cập nhật {{lastUpdated.toLocaleTimeString('vi-VN')}}</small></div><p v-if="error&&rooms.length" class="admin-alert">{{error}}</p><AdminState :loading="loading" :error="error&&!rooms.length?error:''" :empty="!loading&&!error&&!rooms.length" empty-text="Chưa có phòng để hiển thị." @retry="refresh"/><div v-if="rooms.length" class="admin-panel-body admin-room-floors"><section v-for="floor in floors" :key="floor[0]"><header class="admin-floor-head"><h2>Tầng {{floor[0]}}</h2><span>{{floor[1].length}} phòng</span></header><div class="admin-room-grid"><article v-for="room in floor[1]" :key="room.id" class="admin-room-tile" :class="room.effective_status"><strong>Phòng {{room.room_number}}</strong><small>{{room.room_type?.name??room.room_type_name??'Chưa phân loại'}}</small><span class="admin-badge" :class="room.effective_status">{{statuses.find(s=>s[0]===room.effective_status)?.[1]??room.effective_status}}</span><small v-if="room.available_at">Sẵn sàng: {{availableAt(room.available_at)}}</small><button v-if="room.effective_status==='cleaning'" class="admin-button small" :disabled="completing===room.id" @click="cleaningComplete(room)">{{completing===room.id?'Đang xử lý...':'Dọn xong'}}</button></article></div></section></div></div></section>
</template>
