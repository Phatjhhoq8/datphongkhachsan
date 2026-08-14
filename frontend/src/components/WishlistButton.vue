<script setup>
import { onMounted, ref, watch } from 'vue'
import { api, apiError, responseData } from '../api'

const props = defineProps({ roomTypeId: { type: [String, Number], default: null }, authenticated: Boolean })
const saved = ref(false)
const loading = ref(false)
const message = ref('')

async function loadState() {
  if (!props.authenticated || !props.roomTypeId) return
  try {
    const data = responseData(await api.get('/wishlist'))
    const items = Array.isArray(data) ? data : data.data ?? data.wishlist ?? []
    saved.value = items.some(item => String(item.room_type_id ?? item.room_type?.id) === String(props.roomTypeId))
  } catch { saved.value = false }
}
async function toggle() {
  if (!props.authenticated) { message.value = 'Đăng nhập để lưu khách sạn yêu thích.'; return }
  if (!props.roomTypeId) { message.value = 'Khách sạn chưa có phòng trống để lưu.'; return }
  loading.value = true; message.value = ''
  try {
    if (saved.value) await api.delete(`/wishlist/${props.roomTypeId}`)
    else await api.post('/wishlist', { room_type_id: props.roomTypeId })
    saved.value = !saved.value
    message.value = saved.value ? 'Đã lưu vào yêu thích.' : 'Đã bỏ khỏi yêu thích.'
  } catch (err) { message.value = apiError(err, 'Không thể cập nhật yêu thích.') }
  finally { loading.value = false }
}
onMounted(loadState)
watch(() => [props.roomTypeId, props.authenticated], loadState)
</script>

<template>
  <div class="wishlist-wrap"><button type="button" class="wishlist" :class="{ saved }" :disabled="loading" :aria-pressed="saved" @click="toggle"><span>{{ saved ? '♥' : '♡' }}</span>{{ saved ? 'Đã lưu' : 'Yêu thích' }}</button><small v-if="message">{{ message }}</small></div>
</template>

<style scoped>
.wishlist-wrap{text-align:right}.wishlist{display:inline-flex;align-items:center;gap:7px;border:1px solid #dce3ea;border-radius:22px;background:#fff;padding:8px 13px;color:#46576b;font-weight:700;cursor:pointer}.wishlist span{font-size:20px;line-height:1;color:#e05260}.wishlist.saved{background:#fff2f4;border-color:#efb7bf;color:#a72d42}.wishlist-wrap small{display:block;margin-top:5px;color:#637083;max-width:220px}
</style>
