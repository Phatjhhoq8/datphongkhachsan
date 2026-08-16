<script setup>
import { reactive, ref, watchEffect } from 'vue'
import { api, apiError, responseData } from '../api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const profile = reactive({ name: '', phone: '' })
const password = reactive({ current_password: '', password: '', password_confirmation: '' })
const profileMessage = ref('')
const passwordMessage = ref('')
const error = ref('')
const saving = ref(false)

watchEffect(() => {
  profile.name = auth.user?.name || ''
  profile.phone = auth.user?.phone || ''
})

async function updateProfile() {
  saving.value = true
  error.value = ''
  profileMessage.value = ''
  try {
    const response = await api.patch('/auth/profile', profile)
    auth.user = responseData(response)
    profileMessage.value = 'Đã cập nhật hồ sơ thành công.'
  } catch (err) {
    error.value = apiError(err, 'Không thể cập nhật hồ sơ.')
  } finally {
    saving.value = false
  }
}

async function changePassword() {
  saving.value = true
  error.value = ''
  passwordMessage.value = ''
  try {
    await api.put('/auth/password', password)
    Object.assign(password, { current_password: '', password: '', password_confirmation: '' })
    passwordMessage.value = 'Đã đổi mật khẩu thành công. Vui lòng đăng nhập lại.'
    setTimeout(() => auth.logout(), 1200)
  } catch (err) {
    error.value = apiError(err, 'Không thể đổi mật khẩu.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <article class="account-profile-view">
    <!-- Tiêu đề trang nhỏ gọn, thanh lịch -->
    <div class="profile-header-simple">
      <h1>Hồ sơ & Bảo mật</h1>
      <p>Cập nhật thông tin cá nhân và thiết lập mật khẩu của bạn.</p>
    </div>

    <!-- Thông báo lỗi chung -->
    <div v-if="error" class="alert-box error" role="alert">
      <span class="alert-icon">⚠️</span>
      <span>{{ error }}</span>
    </div>

    <!-- Grid Layout -->
    <div class="profile-grid">
      <!-- Card Thông tin cá nhân -->
      <form class="profile-card" @submit.prevent="updateProfile">
        <div class="card-title-group">
          <span class="card-icon">👤</span>
          <h2>Thông tin cá nhân</h2>
        </div>
        
        <div class="input-group">
          <span class="input-label">Họ và tên</span>
          <div class="input-wrapper">
            <span class="input-icon">👤</span>
            <input 
              v-model.trim="profile.name" 
              class="input-field" 
              required 
              maxlength="255" 
              autocomplete="name"
              placeholder="Nhập họ và tên..."
            />
          </div>
        </div>

        <div class="input-group">
          <span class="input-label">Email</span>
          <div class="input-wrapper">
            <span class="input-icon">✉️</span>
            <input 
              :value="auth.user?.email" 
              class="input-field" 
              disabled
            />
          </div>
        </div>

        <div class="input-group">
          <span class="input-label">Số điện thoại</span>
          <div class="input-wrapper">
            <span class="input-icon">📞</span>
            <input 
              v-model.trim="profile.phone" 
              class="input-field" 
              maxlength="30" 
              autocomplete="tel"
              placeholder="Nhập số điện thoại..."
            />
          </div>
        </div>

        <div v-if="profileMessage" class="alert-box success" role="status">
          <span class="alert-icon">✅</span>
          <span>{{ profileMessage }}</span>
        </div>

        <button type="submit" class="btn-save" :disabled="saving">
          {{ saving ? 'Đang lưu...' : 'Lưu hồ sơ' }}
        </button>
      </form>

      <!-- Card Đổi mật khẩu -->
      <form class="profile-card" @submit.prevent="changePassword">
        <div class="card-title-group">
          <span class="card-icon">🔒</span>
          <h2>Bảo mật & Mật khẩu</h2>
        </div>

        <div class="input-group">
          <span class="input-label">Mật khẩu hiện tại</span>
          <div class="input-wrapper">
            <span class="input-icon">🔑</span>
            <input 
              v-model="password.current_password" 
              type="password" 
              class="input-field" 
              required 
              autocomplete="current-password"
              placeholder="Nhập mật khẩu hiện tại..."
            />
          </div>
        </div>

        <div class="input-group">
          <span class="input-label">Mật khẩu mới</span>
          <div class="input-wrapper">
            <span class="input-icon">🔒</span>
            <input 
              v-model="password.password" 
              type="password" 
              class="input-field" 
              required 
              minlength="8" 
              autocomplete="new-password"
              placeholder="Mật khẩu mới (tối thiểu 8 ký tự)..."
            />
          </div>
        </div>

        <div class="input-group">
          <span class="input-label">Xác nhận mật khẩu</span>
          <div class="input-wrapper">
            <span class="input-icon">✔️</span>
            <input 
              v-model="password.password_confirmation" 
              type="password" 
              class="input-field" 
              required 
              minlength="8" 
              autocomplete="new-password"
              placeholder="Nhập lại mật khẩu mới..."
            />
          </div>
        </div>

        <div v-if="passwordMessage" class="alert-box success" role="status">
          <span class="alert-icon">✅</span>
          <span>{{ passwordMessage }}</span>
        </div>

        <button type="submit" class="btn-save" :disabled="saving">
          {{ saving ? 'Đang đổi mật khẩu...' : 'Đổi mật khẩu' }}
        </button>
      </form>
    </div>
  </article>
</template>

<style scoped>
/* Page Header Simple */
.profile-header-simple {
  margin-bottom: 24px;
  border-bottom: 1px solid rgba(19, 36, 58, 0.06);
  padding-bottom: 16px;
}
.profile-header-simple h1 {
  font-size: 22px;
  font-weight: 800;
  color: #13243a;
  margin: 0 0 6px;
}
.profile-header-simple p {
  font-size: 13px;
  color: #637083;
  margin: 0;
}

/* Grid Layout */
.profile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}
.profile-card {
  background: #ffffff;
  border: 1px solid rgba(19, 36, 58, 0.06);
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 4px 15px rgba(19, 36, 58, 0.02);
  transition: all 0.25s ease;
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.profile-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(19, 36, 58, 0.04);
  border-color: rgba(8, 119, 204, 0.12);
}
.card-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 10px;
  margin-bottom: 2px;
}
.card-icon {
  font-size: 18px;
}
.profile-card h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: #13243a;
}

/* Input Fields */
.input-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.input-label {
  color: #637083;
  font-size: 12px;
  font-weight: 700;
}
.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}
.input-icon {
  position: absolute;
  left: 12px;
  font-size: 14px;
  color: #94a3b8;
  pointer-events: none;
  user-select: none;
}
.input-field {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 12px 10px 38px !important;
  border: 1.5px solid #dbe4ec !important;
  border-radius: 8px !important;
  background: #fff;
  color: #172b4d;
  font-size: 13.5px;
  font-weight: 500;
  transition: all 0.2s ease;
}
.input-field:focus {
  border-color: #0877cc !important;
  box-shadow: 0 0 0 3px rgba(8, 119, 204, 0.1) !important;
}
.input-field:disabled {
  background: #f8fafc !important;
  border-color: #e2e8f0 !important;
  color: #94a3b8 !important;
  cursor: not-allowed;
}

/* Alert Messages */
.alert-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 7px;
  font-size: 12.5px;
  font-weight: 600;
  margin-top: 2px;
  line-height: 1.4;
}
.alert-box.success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}
.alert-box.error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

/* Action Buttons */
.btn-save {
  margin-top: 4px;
  background: linear-gradient(135deg, #0877cc, #055a9c) !important;
  color: #fff !important;
  border: none !important;
  border-radius: 8px !important;
  padding: 10px 20px !important;
  font-weight: 700 !important;
  font-size: 13.5px !important;
  cursor: pointer;
  box-shadow: 0 3px 10px rgba(8, 119, 204, 0.15) !important;
  transition: all 0.2s ease !important;
  width: 100%;
}
.btn-save:hover:not(:disabled) {
  background: linear-gradient(135deg, #0762a8, #044b82) !important;
  transform: translateY(-1px) !important;
  box-shadow: 0 5px 15px rgba(8, 119, 204, 0.25) !important;
}
.btn-save:active:not(:disabled) {
  transform: translateY(0px) !important;
}
.btn-save:disabled {
  background: #cbd5e1 !important;
  color: #94a3b8 !important;
  cursor: not-allowed;
  box-shadow: none !important;
  opacity: 0.8;
}

@media (max-width: 820px) {
  .profile-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
}
</style>
