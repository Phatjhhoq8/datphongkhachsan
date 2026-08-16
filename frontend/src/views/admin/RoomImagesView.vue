<script setup>
import { onMounted, ref, watch } from 'vue'
import { api, apiError, responseData } from '../../api'
import AdminState from '../../admin/AdminState.vue'

const roomTypes = ref([])
const roomTypeId = ref('')
const images = ref([])
const file = ref(null)
const loading = ref(false)
const uploading = ref(false)
const error = ref('')

async function loadRoomTypes() {
  try {
    const data = responseData(await api.get('/admin/room-types'))
    roomTypes.value = Array.isArray(data) ? data : data?.data ?? []
    roomTypeId.value ||= String(roomTypes.value[0]?.id ?? '')
  } catch (err) {
    error.value = apiError(err, 'Không thể tải danh sách loại phòng.')
  }
}

async function loadImages() {
  images.value = []
  if (!roomTypeId.value) return
  loading.value = true
  error.value = ''
  try {
    images.value = responseData(await api.get(`/admin/room-types/${roomTypeId.value}/images`)) ?? []
  } catch (err) {
    error.value = apiError(err, 'Không thể tải ảnh loại phòng.')
  } finally {
    loading.value = false
  }
}

async function upload(event) {
  const selected = event.target.files?.[0]
  file.value = selected ?? null
  if (!file.value || !roomTypeId.value) return
  uploading.value = true
  error.value = ''
  const form = new FormData()
  form.append('image', file.value)
  try {
    await api.post(`/admin/room-types/${roomTypeId.value}/images`, form)
    event.target.value = ''
    file.value = null
    await loadImages()
  } catch (err) {
    error.value = apiError(err, 'Không thể tải ảnh lên.')
  } finally {
    uploading.value = false
  }
}

async function updateOrder(image) {
  try {
    await api.patch(`/admin/room-images/${image.id}`, { sort_order: Number(image.sort_order) || 0 })
    await loadImages()
  } catch (err) {
    error.value = apiError(err, 'Không thể cập nhật thứ tự ảnh.')
  }
}

async function remove(image) {
  if (!confirm('Xóa ảnh này?')) return
  try {
    await api.delete(`/admin/room-images/${image.id}`)
    await loadImages()
  } catch (err) {
    error.value = apiError(err, 'Không thể xóa ảnh.')
  }
}

watch(roomTypeId, loadImages)
onMounted(loadRoomTypes)
</script>

<template>
  <section>
    <header class="admin-page-head"><div><h1>Ảnh loại phòng</h1><p>Ảnh JPG, PNG hoặc WebP, tối đa 5MB mỗi tệp</p></div></header>
    <div class="admin-card room-image-controls">
      <label class="admin-field"><span>Loại phòng</span><select v-model="roomTypeId" class="admin-select"><option value="">-- Chọn loại phòng --</option><option v-for="item in roomTypes" :key="item.id" :value="String(item.id)">{{ item.name }}<template v-if="item.hotel?.name"> - {{ item.hotel.name }}</template></option></select></label>
      <label class="admin-button upload-button" :class="{ disabled: !roomTypeId || uploading }"><input type="file" accept="image/jpeg,image/png,image/webp" :disabled="!roomTypeId || uploading" @change="upload" />{{ uploading ? 'Đang tải...' : 'Tải ảnh lên' }}</label>
    </div>
    <p v-if="error" class="admin-alert">{{ error }}</p>
    <AdminState :loading="loading" :empty="!loading && !error && !!roomTypeId && !images.length" empty-text="Loại phòng này chưa có ảnh." @retry="loadImages" />
    <div v-if="!loading && images.length" class="room-image-grid">
      <article v-for="image in images" :key="image.id" class="admin-card room-image-card">
        <img :src="image.url" :alt="`Ảnh loại phòng thứ tự ${image.sort_order}`" />
        <div class="room-image-actions"><label class="admin-field"><span>Thứ tự</span><input v-model.number="image.sort_order" class="admin-input" type="number" min="0" /></label><button class="admin-button secondary small" type="button" @click="updateOrder(image)">Lưu</button><button class="admin-button danger small" type="button" @click="remove(image)">Xóa</button></div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.room-image-controls { display: flex; align-items: end; gap: 16px; margin-bottom: 18px; }
.room-image-controls .admin-field { min-width: min(100%, 360px); }
.upload-button { cursor: pointer; }
.upload-button input { display: none; }
.upload-button.disabled { cursor: not-allowed; opacity: .55; }
.room-image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
.room-image-card { overflow: hidden; padding: 0; }
.room-image-card img { display: block; width: 100%; height: 190px; object-fit: cover; background: #eef1f4; }
.room-image-actions { display: flex; align-items: end; gap: 8px; padding: 14px; }
.room-image-actions .admin-field { flex: 1; }
@media (max-width: 600px) { .room-image-controls, .room-image-actions { align-items: stretch; flex-direction: column; } }
</style>
