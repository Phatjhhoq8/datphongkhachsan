<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { api, apiError, responseData } from '../../api'
import AdminState from '../../admin/AdminState.vue'
import { createAdminRoomSocket } from '../../realtime'

const rooms=ref([]),loading=ref(false),error=ref(''),hotelId=ref(''),lastUpdated=ref(null),timer=ref(null),socketCleanup=ref(null)
const statuses=[['available','Trống','#22c55e'],['occupied','Có khách','#3b82f6'],['cleaning','Đang dọn','#f59e0b'],['maintenance','Bảo trì','#ef4444'],['out_of_service','Ngừng phục vụ','#64748b']]
const floors=computed(()=>Object.entries(rooms.value.reduce((groups,room)=>{const floor=room.floor??'Khác';(groups[floor]??=[]).push(room);return groups},{})).sort(([a],[b])=>Number(a)-Number(b)))
function roomList(response){const data=responseData(response);if(Array.isArray(data))return data;return data?.rooms??data?.items??Object.values(data??{}).flat()}
async function load(silent=false){if(!silent)loading.value=true;error.value='';try{rooms.value=roomList(await api.get('/admin/room-map',{params:{hotel_id:hotelId.value||undefined}}));lastUpdated.value=new Date()}catch(err){error.value=apiError(err,'Không thể cập nhật sơ đồ phòng.')}finally{loading.value=false}}
function startPolling(){stopPolling();timer.value=setInterval(()=>load(true),10000)}
function stopPolling(){if(timer.value)clearInterval(timer.value);timer.value=null}
function prepareSocketHook(){
  if(!hotelId.value)return false
  socketCleanup.value?.()
  const socket=createAdminRoomSocket({hotelId:Number(hotelId.value)})
  const updateRoom=(event)=>{if(event?.room){const index=rooms.value.findIndex(room=>room.id===event.room.id);if(index>=0)rooms.value[index]=event.room;else rooms.value.push(event.room);lastUpdated.value=new Date()}}
  socket.on('room.updated',updateRoom)
  socket.on('booking.updated',()=>load(true))
  socket.on('connect',stopPolling)
  socket.on('connect_error',startPolling)
  socket.on('disconnect',startPolling)
  socketCleanup.value=()=>socket.close()
  return true
}
function refresh(){load().then(()=>{if(!prepareSocketHook())startPolling()})}
onMounted(refresh)
onBeforeUnmount(()=>{stopPolling();socketCleanup.value?.()})
</script>
<template><section><header class="admin-page-head"><div><h1>Sơ đồ phòng</h1><p>Trạng thái phòng theo tầng, tự động cập nhật mỗi 10 giây</p></div><button class="admin-button secondary" @click="refresh">Làm mới</button></header><div class="admin-card"><div class="admin-toolbar"><input v-model="hotelId" class="admin-input" type="number" placeholder="ID khách sạn" @change="refresh"/><div class="admin-legend"><span v-for="status in statuses" :key="status[0]"><i class="admin-dot" :style="{background:status[2]}"></i>{{status[1]}}</span></div><small v-if="lastUpdated" style="margin-left:auto;color:#64748b">Cập nhật {{lastUpdated.toLocaleTimeString('vi-VN')}}</small></div><AdminState :loading="loading" :error="error&&!rooms.length?error:''" :empty="!loading&&!error&&!rooms.length" empty-text="Chưa có phòng để hiển thị." @retry="refresh"/><div v-if="rooms.length" class="admin-panel-body admin-room-floors"><section v-for="floor in floors" :key="floor[0]"><header class="admin-floor-head"><h2>Tầng {{floor[0]}}</h2><span>{{floor[1].length}} phòng</span></header><div class="admin-room-grid"><article v-for="room in floor[1]" :key="room.id" class="admin-room-tile" :class="room.effective_status"><strong>Phòng {{room.room_number}}</strong><small>{{room.room_type?.name??room.room_type_name??'Chưa phân loại'}}</small><span class="admin-badge" :class="room.effective_status">{{statuses.find(s=>s[0]===room.effective_status)?.[1]??room.effective_status}}</span></article></div></section></div></div></section></template>
