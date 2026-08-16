<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, responseList } from '../api'
import { money } from '../utils'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const vouchers = ref([])
const claimedCodes = ref([])
const loading = ref(true)

const storageKey = computed(() => {
  const userId = auth.user?.id || auth.user?._id
  return userId ? `claimed_vouchers_${userId}` : 'claimed_vouchers_guest'
})

function loadClaimedCodes() {
  const saved = localStorage.getItem(storageKey.value)
  if (saved) {
    try {
      claimedCodes.value = JSON.parse(saved)
    } catch (e) {
      claimedCodes.value = []
    }
  } else {
    claimedCodes.value = []
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/vouchers')
    vouchers.value = responseList(res)
  } catch (error) {
    console.error('Lỗi khi lấy danh sách voucher:', error)
  } finally {
    loading.value = false
  }

  loadClaimedCodes()
})

watch(storageKey, loadClaimedCodes)

function isClaimed(code) {
  return claimedCodes.value.includes(code)
}

function claimVoucher(code) {
  if (!claimedCodes.value.includes(code)) {
    claimedCodes.value.push(code)
    localStorage.setItem(storageKey.value, JSON.stringify(claimedCodes.value))
  }
}

function voucherValueText(voucher) {
  return voucher.type === 'percent' ? `${Number(voucher.value)}%` : money(voucher.value)
}

function formatDate(dateStr) {
  if (!dateStr) return 'Không thời hạn'
  const date = new Date(dateStr)
  return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <div class="vouchers-page container py-5">
    <div class="back-navigation mb-4">
      <RouterLink to="/hotel" class="btn-back">
        <span class="back-arrow">←</span> Quay lại Trang chủ
      </RouterLink>
    </div>
    <div class="page-header text-center mb-5">
      <p class="eyebrow text-uppercase">Khuyến mãi cực hot</p>
      <h1 class="display-5 fw-bold text-navy">Kho Voucher Ưu Đãi</h1>
      <p class="lead text-muted">Thu thập mã giảm giá để nhận thêm nhiều ưu đãi khi đặt phòng tại StayGo</p>
    </div>


    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Đang tải...</span>
      </div>
    </div>

    <div v-else-if="!vouchers.length" class="text-center py-5">
      <div class="empty-state">
        <span class="empty-icon">🎫</span>
        <h3>Hiện không có mã giảm giá nào</h3>
        <p class="text-muted">Vui lòng quay lại sau để săn mã ưu đãi mới nhé!</p>
        <RouterLink to="/hotel" class="btn btn-navy mt-3">Quay lại Trang chủ</RouterLink>
      </div>
    </div>

    <div v-else class="voucher-grid row g-4">
      <div v-for="voucher in vouchers" :key="voucher.id" class="col-md-6 col-lg-6">
        <div class="shopee-voucher-card" :class="{ 'claimed-style': isClaimed(voucher.code) }">
          <!-- Left portion: Percentage or amount icon -->
          <div class="voucher-left">
            <div class="voucher-badge">
              <span class="value">{{ voucherValueText(voucher) }}</span>
              <span class="label">GIẢM</span>
            </div>
            <div class="sawtooth-border"></div>
          </div>

          <!-- Right portion: Info and Action button -->
          <div class="voucher-right">
            <div class="voucher-info">
              <div class="voucher-code-title">
                <span class="badge-code">{{ voucher.code }}</span>
                <span v-if="voucher.hotel" class="badge-hotel">Chỉ áp dụng tại {{ voucher.hotel.name }}</span>
              </div>
              <h3 class="voucher-discount-text">
                Giảm {{ voucherValueText(voucher) }} <span v-if="voucher.max_discount"> (tối đa {{ money(voucher.max_discount) }})</span>
              </h3>
              <p class="voucher-min-spend mb-1">
                Áp dụng đơn tối thiểu: <strong>{{ money(voucher.min_order || 0) }}</strong>
              </p>
              <div class="voucher-expiry text-muted">
                Hạn dùng: {{ formatDate(voucher.ends_at) }}
              </div>
            </div>
            <div class="voucher-action">
              <button 
                v-if="!isClaimed(voucher.code)" 
                class="btn-claim" 
                @click="claimVoucher(voucher.code)"
              >
                Lưu mã
              </button>
              <RouterLink 
                v-else 
                to="/hotel/search" 
                class="btn-use"
              >
                Dùng ngay
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.vouchers-page {
  max-width: 1000px;
  padding-top: 40px !important;
  padding-bottom: 80px !important;
}
.text-navy {
  color: #13243a;
}
.btn-navy {
  background-color: #13243a;
  color: white;
}
.btn-navy:hover {
  background-color: #1b3252;
  color: white;
}
.eyebrow {
  font-size: 0.85rem;
  letter-spacing: 2px;
  color: #ff5a5f;
  font-weight: 700;
}

/* Back Navigation Button */
.back-navigation {
  text-align: left;
  margin-top: 15px;
}
.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #5c7080;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9rem;
  transition: all 0.2s ease;
  padding: 8px 16px;
  border-radius: 20px;
  background: #f8fafc;
  border: 1px solid #eef1f5;
  box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.btn-back:hover {
  color: #13243a;
  background: #f0f4f8;
  border-color: #dbe3ec;
  transform: translateX(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}
.back-arrow {
  font-size: 1.1rem;
  line-height: 1;
  transition: transform 0.2s ease;
}
.btn-back:hover .back-arrow {
  transform: translateX(-3px);
}

/* Shopee-style Voucher Card */
.shopee-voucher-card {
  display: flex;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  height: 140px;
  border: 1px solid #eef1f5;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  position: relative;
}
.shopee-voucher-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.claimed-style {
  background-color: #fafbfc;
  border-color: #e2e8f0;
}

.voucher-left {
  width: 130px;
  background: linear-gradient(135deg, #ff5a5f 0%, #ff7e82 100%);
  color: #ffffff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  position: relative;
  text-align: center;
  padding: 10px;
  flex-shrink: 0;
}

.claimed-style .voucher-left {
  background: linear-gradient(135deg, #94a3b8 0%, #cbd5e1 100%);
}

.voucher-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  z-index: 2;
}
.voucher-badge .value {
  font-size: 1.6rem;
  font-weight: 900;
  line-height: 1.1;
  text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.voucher-badge .label {
  font-size: 0.7rem;
  font-weight: 700;
  opacity: 0.95;
  letter-spacing: 1.5px;
  margin-top: 6px;
  background: rgba(255, 255, 255, 0.2);
  padding: 2px 8px;
  border-radius: 10px;
}

/* Sawtooth border effect */
.sawtooth-border {
  position: absolute;
  right: -5px;
  top: 0;
  bottom: 0;
  width: 10px;
  background-image: radial-gradient(circle, transparent 4px, #ffffff 4px);
  background-size: 10px 10px;
  background-position: -5px center;
  z-index: 10;
}
.claimed-style .sawtooth-border {
  background-image: radial-gradient(circle, transparent 4px, #fafbfc 4px);
}

.voucher-right {
  display: flex;
  flex: 1;
  padding: 16px 20px;
  align-items: center;
  justify-content: space-between;
  background: transparent;
}

.voucher-info {
  flex: 1;
  padding-right: 15px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: left;
}

.voucher-code-title {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
  align-items: center;
}

.badge-code {
  background-color: #ffebeb;
  color: #ff5a5f;
  font-weight: 700;
  font-size: 0.75rem;
  padding: 3px 8px;
  border-radius: 4px;
  border: 1px dashed #ff5a5f;
  letter-spacing: 0.5px;
}

.claimed-style .badge-code {
  background-color: #f1f5f9;
  color: #64748b;
  border-color: #cbd5e1;
}

.badge-hotel {
  font-size: 0.7rem;
  color: #475569;
  background-color: #f1f5f9;
  padding: 3px 8px;
  border-radius: 4px;
  max-width: 190px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
}

.voucher-discount-text {
  font-size: 1.15rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 6px 0;
}
.claimed-style .voucher-discount-text {
  color: #475569;
}

.voucher-min-spend {
  font-size: 0.8rem;
  color: #64748b;
}

.voucher-expiry {
  font-size: 0.75rem;
  font-weight: 500;
}

.voucher-action {
  flex-shrink: 0;
}

.btn-claim {
  background-color: #ff5a5f;
  color: white;
  border: none;
  border-radius: 20px;
  padding: 8px 20px;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 6px rgba(255, 90, 95, 0.2);
}
.btn-claim:hover {
  background-color: #e04f53;
  transform: scale(1.03);
  box-shadow: 0 6px 12px rgba(255, 90, 95, 0.3);
}
.btn-claim:active {
  transform: scale(0.98);
}

.btn-use {
  display: inline-block;
  background-color: #13243a;
  color: white;
  border: 1px solid #13243a;
  border-radius: 20px;
  padding: 8px 20px;
  font-weight: 700;
  font-size: 0.85rem;
  text-decoration: none;
  text-align: center;
  transition: all 0.2s ease;
  box-shadow: 0 4px 6px rgba(19, 36, 58, 0.15);
}
.btn-use:hover {
  background-color: #1d3554;
  border-color: #1d3554;
  transform: scale(1.03);
  box-shadow: 0 6px 12px rgba(19, 36, 58, 0.25);
}
.btn-use:active {
  transform: scale(0.98);
}

/* Empty State */
.empty-state {
  padding: 40px;
}
.empty-icon {
  font-size: 4rem;
  display: block;
  margin-bottom: 20px;
}
</style>
