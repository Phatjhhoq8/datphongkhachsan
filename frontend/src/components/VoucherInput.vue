<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import { api, responseList } from '../api'
import { money } from '../utils'
import { useAuthStore } from '../stores/auth'

const props = defineProps({
  modelValue: { type: String, default: '' },
  loading: Boolean,
  message: { type: String, default: '' },
  valid: Boolean,
  hotelId: { type: String, default: null },
  totalAmount: { type: Number, default: 0 }
})

const emit = defineEmits(['update:modelValue', 'apply'])
const code = ref(props.modelValue)

const showList = ref(false)
const availableVouchers = ref([])
const claimedVouchers = ref([])
const voucherContainer = ref(null)

const auth = useAuthStore()
const storageKey = computed(() => {
  const userId = auth.user?.id || auth.user?._id
  return userId ? `claimed_vouchers_${userId}` : 'claimed_vouchers_guest'
})

watch(() => props.modelValue, value => { code.value = value })

const applicableVouchers = computed(() => {
  return claimedVouchers.value.filter(voucher => {
    // 1. Kiểm tra khách sạn
    if (voucher.hotel_id && props.hotelId && String(voucher.hotel_id) !== String(props.hotelId)) {
      return false
    }
    // 2. Kiểm tra giá trị tối thiểu của đơn hàng
    if (voucher.min_order && props.totalAmount && Number(props.totalAmount) < Number(voucher.min_order)) {
      return false
    }
    return true
  })
})

onMounted(async () => {
  try {
    const res = await api.get('/vouchers')
    availableVouchers.value = responseList(res)
    loadClaimedVouchers()
    document.addEventListener('click', handleClickOutside)
  } catch (error) {
    console.error('Lỗi lấy voucher:', error)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

function handleClickOutside(e) {
  if (voucherContainer.value && !voucherContainer.value.contains(e.target)) {
    showList.value = false
  }
}

function loadClaimedVouchers() {
  const saved = localStorage.getItem(storageKey.value)
  if (saved) {
    try {
      const claimedCodes = JSON.parse(saved)
      claimedVouchers.value = availableVouchers.value.filter(v => claimedCodes.includes(v.code))
    } catch (e) {
      claimedVouchers.value = []
    }
  } else {
    claimedVouchers.value = []
  }
}

watch(storageKey, loadClaimedVouchers)

function selectVoucher(vCode) {
  code.value = vCode
  showList.value = false
  apply()
}

function apply() { 
  const value = code.value.trim().toUpperCase()
  emit('update:modelValue', value)
  emit('apply', value) 
}

function voucherValueText(voucher) {
  return voucher.type === 'percent' ? `${Number(voucher.value)}%` : money(voucher.value)
}
</script>

<template>
  <div ref="voucherContainer" class="voucher">
    <div class="input-group-wrapper">
      <input v-model="code" :disabled="loading" autocomplete="off" placeholder="Nhập mã ưu đãi" @keyup.enter.prevent="apply" />
      <button type="button" :disabled="loading || !code.trim()" @click="apply">
        {{ loading ? 'Đang kiểm tra' : 'Áp dụng' }}
      </button>
    </div>
    
    <div class="claimed-vouchers-trigger-wrapper" v-if="applicableVouchers.length">
      <button type="button" class="btn-trigger" @click="showList = !showList">
        🎟️ Chọn từ ví Voucher của bạn ({{ applicableVouchers.length }} mã có sẵn)
      </button>
    </div>

    <!-- Dropdown list of claimed vouchers -->
    <div v-if="showList && applicableVouchers.length" class="claimed-vouchers-dropdown">
      <div v-for="voucher in applicableVouchers" :key="voucher.id" class="dropdown-voucher-item">
        <div class="item-left">
          <span class="discount-value">{{ voucherValueText(voucher) }}</span>
          <span class="discount-type">GIẢM</span>
        </div>
        <div class="item-right">
          <div class="item-details">
            <span class="item-code">{{ voucher.code }}</span>
            <p class="item-desc">
              Giảm {{ voucherValueText(voucher) }} cho đơn từ {{ money(voucher.min_order || 0) }}
            </p>
          </div>
          <button type="button" class="btn-use-now" @click="selectVoucher(voucher.code)">Dùng</button>
        </div>
      </div>
    </div>

    <small v-if="message" :class="{ valid }">{{ message }}</small>
  </div>
</template>

<style scoped>
.voucher {
  position: relative;
}
.input-group-wrapper {
  display: flex;
  gap: 8px;
}
.voucher input {
  text-transform: uppercase;
  flex: 1;
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 6px;
}
.voucher button {
  border: 0;
  border-radius: 6px;
  padding: 0 15px;
  background: #13243a;
  color: #fff;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
}
.voucher button:disabled {
  opacity: .55;
}
.voucher small {
  display: block;
  color: #a72d2d;
  margin-top: 7px;
}
.voucher small.valid {
  color: #168a52;
}

/* Trigger button */
.claimed-vouchers-trigger-wrapper {
  margin-top: 8px;
}
.btn-trigger {
  background: none !important;
  border: none !important;
  color: #ff5a5f !important;
  font-size: 0.85rem !important;
  font-weight: 600 !important;
  padding: 0 !important;
  cursor: pointer;
  text-decoration: underline;
  display: flex;
  align-items: center;
  gap: 4px;
}
.btn-trigger:hover {
  color: #e04f53 !important;
}

/* Dropdown styling */
.claimed-vouchers-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #eef1f5;
  border-radius: 8px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
  z-index: 99;
  max-height: 250px;
  overflow-y: auto;
  margin-top: 5px;
  padding: 8px;
}

.dropdown-voucher-item {
  display: flex;
  border: 1px solid #f0f2f5;
  border-radius: 6px;
  overflow: hidden;
  height: 65px;
  margin-bottom: 8px;
}
.dropdown-voucher-item:last-child {
  margin-bottom: 0;
}

.item-left {
  width: 75px;
  background: #ff5a5f;
  color: white;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  flex-shrink: 0;
  text-align: center;
}
.item-left .discount-value {
  font-size: 1rem;
  font-weight: 800;
  line-height: 1;
}
.item-left .discount-type {
  font-size: 0.6rem;
  font-weight: 600;
}

.item-right {
  display: flex;
  flex: 1;
  padding: 8px 12px;
  align-items: center;
  justify-content: space-between;
  background: #ffffff;
}
.item-details {
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: left;
}
.item-code {
  font-size: 0.8rem;
  font-weight: 700;
  color: #ff5a5f;
  background: #ffebeb;
  padding: 1px 6px;
  border-radius: 4px;
  width: fit-content;
  border: 1px dashed #ff5a5f;
}
.item-desc {
  font-size: 0.75rem;
  color: #5c7080;
  margin: 4px 0 0 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 170px;
}

.btn-use-now {
  background: #ff5a5f !important;
  color: white !important;
  border: none !important;
  border-radius: 4px !important;
  padding: 4px 10px !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  cursor: pointer;
  height: 28px !important;
  line-height: 20px !important;
}
.btn-use-now:hover {
  background: #e04f53 !important;
}
</style>
