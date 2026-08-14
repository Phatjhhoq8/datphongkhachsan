<script setup>
import { ref, watch } from 'vue'

const props = defineProps({ modelValue: { type: String, default: '' }, loading: Boolean, message: { type: String, default: '' }, valid: Boolean })
const emit = defineEmits(['update:modelValue', 'apply'])
const code = ref(props.modelValue)
watch(() => props.modelValue, value => { code.value = value })
function apply() { const value = code.value.trim().toUpperCase(); emit('update:modelValue', value); emit('apply', value) }
</script>

<template>
  <div class="voucher">
    <div><input v-model="code" :disabled="loading" autocomplete="off" placeholder="Nhập mã ưu đãi" @keyup.enter.prevent="apply" /><button type="button" :disabled="loading || !code.trim()" @click="apply">{{ loading ? 'Đang kiểm tra' : 'Áp dụng' }}</button></div>
    <small v-if="message" :class="{ valid }">{{ message }}</small>
  </div>
</template>

<style scoped>
.voucher>div{display:flex;gap:8px}.voucher input{text-transform:uppercase}.voucher button{border:0;border-radius:6px;padding:0 15px;background:#13243a;color:#fff;font-weight:700;white-space:nowrap;cursor:pointer}.voucher button:disabled{opacity:.55}.voucher small{display:block;color:#a72d2d;margin-top:7px}.voucher small.valid{color:#168a52}
</style>
