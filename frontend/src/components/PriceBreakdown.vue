<script setup>
import { computed } from 'vue'
import { money } from '../utils'

const props = defineProps({ quote: { type: Object, default: () => ({}) }, amountDue: { type: Number, default: null } })

const pricing = computed(() => props.quote.pricing ?? props.quote.breakdown ?? props.quote)
const room = computed(() => Number(pricing.value.room ?? pricing.value.room_total ?? pricing.value.subtotal ?? 0))
const services = computed(() => Number(pricing.value.service_total ?? pricing.value.services_total ?? (Array.isArray(pricing.value.services) ? pricing.value.services.reduce((sum, item) => sum + Number(item.total ?? 0), 0) : pricing.value.services) ?? 0))
const discount = computed(() => Number(pricing.value.discount ?? pricing.value.discount_total ?? 0))
const total = computed(() => Number(pricing.value.total ?? room.value + services.value - discount.value))
const due = computed(() => Number(props.amountDue ?? pricing.value.due ?? pricing.value.amount_due ?? props.quote.amount_due ?? total.value))
</script>

<template>
  <div class="price-breakdown">
    <div><span>Tiền phòng</span><strong>{{ money(room) }}</strong></div>
    <div><span>Dịch vụ</span><strong>{{ money(services) }}</strong></div>
    <div v-if="discount"><span>Ưu đãi</span><strong class="discount">-{{ money(discount) }}</strong></div>
    <div class="total"><span>Tổng cộng</span><strong>{{ money(total) }}</strong></div>
    <div class="due"><span>Thanh toán ngay</span><strong>{{ money(due) }}</strong></div>
    <small v-if="due < total">Phần còn lại {{ money(total - due) }} thanh toán theo chính sách khách sạn.</small>
  </div>
</template>

<style scoped>
.price-breakdown{display:grid;gap:9px}.price-breakdown>div{display:flex;justify-content:space-between;gap:20px}.price-breakdown span{color:#637083}.discount{color:#168a52}.total{border-top:1px solid #dce3ea;padding-top:12px;margin-top:3px;font-size:15px}.due{background:#eef7fd;margin:2px -10px -2px;padding:11px 10px;border-radius:7px}.due strong{color:#e05220;font-size:17px}.price-breakdown small{color:#637083;line-height:1.5}
</style>
