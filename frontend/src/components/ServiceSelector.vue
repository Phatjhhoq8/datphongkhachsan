<script setup>
import { money } from '../utils'

defineProps({ services: { type: Array, default: () => [] }, modelValue: { type: Array, default: () => [] }, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])

function toggle(id, checked, current) {
  emit('update:modelValue', checked ? [...current, id] : current.filter(value => String(value) !== String(id)))
}
</script>

<template>
  <div class="service-list">
    <p v-if="!services.length" class="empty">Khách sạn chưa có dịch vụ đặt trước.</p>
    <label v-for="service in services" :key="service.id" class="service-item">
      <input :checked="modelValue.some(id => String(id) === String(service.id))" :disabled="disabled" type="checkbox" @change="toggle(service.id, $event.target.checked, modelValue)" />
      <span class="service-icon">{{ service.icon ?? '＋' }}</span>
      <span class="service-copy"><strong>{{ service.name }}</strong><small>{{ service.description ?? service.unit_label ?? 'Thêm vào kỳ nghỉ' }}</small></span>
      <strong class="service-price">{{ money(service.price ?? service.unit_price) }}</strong>
    </label>
  </div>
</template>

<style scoped>
.service-list{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}.service-item{display:grid!important;grid-template-columns:auto 34px 1fr auto;align-items:center;gap:9px;border:1px solid #dce3ea;border-radius:9px;padding:12px;cursor:pointer}.service-item:has(input:checked){border-color:#0877cc;background:#f2f9fe}.service-item input{width:auto;accent-color:#0877cc}.service-icon{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;background:#e8f4fc;color:#0877cc;font-size:18px}.service-copy strong,.service-copy small{display:block}.service-copy small{color:#637083;font-size:10px;margin-top:2px}.service-price{font-size:11px;white-space:nowrap}.empty{grid-column:1/-1;color:#637083}@media(max-width:620px){.service-list{grid-template-columns:1fr}}
</style>
