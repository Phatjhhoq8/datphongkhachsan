<script setup>
import { reactive, watch, ref } from 'vue'
import { api, responseData } from '../api'

const props = defineProps({ open: Boolean, title: String, fields: { type: Array, default: () => [] }, value: { type: Object, default: () => ({}) }, saving: Boolean, error: String })
const emit = defineEmits(['close', 'save', 'add-option'])
const form = reactive({})
const newAmenityText = ref('')

const slugify = str => {
  return str
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[đĐ]/g, 'd')
    .replace(/([^0-9a-z-\s])/g, '')
    .replace(/(\s+)/g, '-')
    .replace(/-+/g, '-')
    .trim()
}

async function addNewAmenity(field) {
  const text = newAmenityText.value.trim()
  if (!text) return

  try {
    const slug = slugify(text)
    const res = await api.post('/admin/amenities', { name: text, slug })
    const created = responseData(res)
    if (created) {
      const option = { value: String(created.id ?? created._id), label: created.name }
      emit('add-option', { endpoint: field.optionsEndpoint, option })
      
      if (!form[field.key]) {
        form[field.key] = []
      }
      form[field.key].push(option.value)
      newAmenityText.value = ''
    }
  } catch (err) {
    alert(err?.response?.data?.message || 'Không thể tạo tiện nghi mới.')
  }
}

function submit() {
  const payload = { ...form }
  if (payload.password === '') delete payload.password
  emit('save', payload)
}

watch(() => [props.open, props.value], () => {
  Object.keys(form).forEach((key) => delete form[key])
  props.fields.forEach((field) => { form[field.key] = props.value?.[field.key] ?? field.default ?? (field.type === 'multiselect' ? [] : '') })
  newAmenityText.value = ''
}, { immediate: true, deep: true })
</script>

<template>
  <div v-if="open" class="admin-modal-backdrop" @click.self="emit('close')">
    <form class="admin-modal" @submit.prevent="submit">
      <header class="admin-modal-head"><h2>{{ title }}</h2><button class="admin-modal-close" type="button" aria-label="Đóng" @click="emit('close')">×</button></header>
      <div class="admin-modal-body">
        <p v-if="error" class="admin-alert">{{ error }}</p>
        <div class="admin-form-grid">
          <label v-for="field in fields" :key="field.key" class="admin-field" :class="{ full: field.full }">
            <span>{{ field.label }}</span>
            <textarea v-if="field.type === 'textarea'" v-model="form[field.key]" class="admin-textarea" :required="field.required" rows="3"></textarea>
            <div v-else-if="field.type === 'multiselect'" style="display: flex; flex-direction: column; gap: 8px;">
              <select v-model="form[field.key]" class="admin-select" :required="field.required" multiple style="height: 120px;">
                <option v-for="option in field.options || []" :key="option.value ?? option" :value="option.value ?? option">{{ option.label ?? option }}</option>
              </select>
              <div style="display: flex; gap: 8px; align-items: center;">
                <input v-model="newAmenityText" type="text" placeholder="Nhập tiện nghi mới..." class="admin-input" style="flex: 1; min-width: 0;" @keyup.enter.prevent="addNewAmenity(field)" />
                <button class="admin-button secondary small" type="button" @click="addNewAmenity(field)" style="height: 38px; white-space: nowrap;">Thêm tiện nghi</button>
              </div>
            </div>
            <select v-else-if="field.type === 'select'" v-model="form[field.key]" class="admin-select" :required="field.required">
              <option value="">-- Chọn --</option><option v-for="option in field.options || []" :key="option.value ?? option" :value="option.value ?? option">{{ option.label ?? option }}</option>
            </select>
            <input v-else v-model="form[field.key]" class="admin-input" :type="field.type || 'text'" :required="field.required" :min="field.min" :placeholder="field.placeholder" />
          </label>
        </div>
      </div>
      <footer class="admin-modal-foot"><button class="admin-button secondary" type="button" @click="emit('close')">Hủy</button><button class="admin-button" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu' }}</button></footer>
    </form>
  </div>
</template>
