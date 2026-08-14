<script setup>
import { reactive, watch } from 'vue'

const props = defineProps({ open: Boolean, title: String, fields: { type: Array, default: () => [] }, value: { type: Object, default: () => ({}) }, saving: Boolean, error: String })
const emit = defineEmits(['close', 'save'])
const form = reactive({})

watch(() => [props.open, props.value], () => {
  Object.keys(form).forEach((key) => delete form[key])
  props.fields.forEach((field) => { form[field.key] = props.value?.[field.key] ?? field.default ?? '' })
}, { immediate: true, deep: true })
</script>

<template>
  <div v-if="open" class="admin-modal-backdrop" @click.self="emit('close')">
    <form class="admin-modal" @submit.prevent="emit('save', { ...form })">
      <header class="admin-modal-head"><h2>{{ title }}</h2><button class="admin-modal-close" type="button" aria-label="Đóng" @click="emit('close')">×</button></header>
      <div class="admin-modal-body">
        <p v-if="error" class="admin-alert">{{ error }}</p>
        <div class="admin-form-grid">
          <label v-for="field in fields" :key="field.key" class="admin-field" :class="{ full: field.full }">
            <span>{{ field.label }}</span>
            <textarea v-if="field.type === 'textarea'" v-model="form[field.key]" class="admin-textarea" :required="field.required" rows="3"></textarea>
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
