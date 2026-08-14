<script setup>
import { reactive, watch } from 'vue'
import { useRouter } from 'vue-router'
import { addDays, today } from '../utils'

const props = defineProps({ initial: { type: Object, default: () => ({}) }, compact: Boolean })
const router = useRouter()
const form = reactive({
  location: props.initial.location || '',
  checkin: props.initial.checkin || addDays(today(), 1),
  checkout: props.initial.checkout || addDays(today(), 2),
  adults: Number(props.initial.adults) || 2,
  children: Number(props.initial.children) || 0,
  rooms: Number(props.initial.rooms) || 1,
})

watch(() => props.initial, (value) => Object.assign(form, value), { deep: true })

function search() {
  if (form.checkout <= form.checkin) form.checkout = addDays(form.checkin, 1)
  router.push({ path: '/hotel/search', query: { ...form } })
}
</script>

<template>
  <form class="search-form" :class="{ compact }" aria-label="Tìm khách sạn" @submit.prevent="search">
    <label class="field location-field"><span>Thành phố, khách sạn</span><input v-model.trim="form.location" placeholder="Tất cả điểm đến" /></label>
    <label class="field"><span>Nhận phòng</span><input v-model="form.checkin" type="date" :min="today()" required /></label>
    <label class="field"><span>Trả phòng</span><input v-model="form.checkout" type="date" :min="addDays(form.checkin, 1)" required /></label>
    <label class="field guest-field"><span>Khách và phòng</span><span class="guest-input"><input v-model.number="form.adults" type="number" min="1" aria-label="Số người lớn" /> khách, <input v-model.number="form.rooms" type="number" min="1" aria-label="Số phòng" /> phòng</span></label>
    <button class="primary search-button" type="submit">Tìm khách sạn</button>
  </form>
</template>
