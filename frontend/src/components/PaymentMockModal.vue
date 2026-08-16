<script setup>
import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import { money } from '../utils'

const props = defineProps({
  open: Boolean,
  method: { type: String, default: 'card' },
  amount: { type: Number, default: 0 },
  bookingCode: { type: String, default: '' },
  processing: Boolean,
  error: { type: String, default: '' },
})
const emit = defineEmits(['close', 'confirm'])

const card = reactive({ number: '', name: '', expiry: '', cvc: '' })
const paypal = reactive({ email: 'demo-buyer@staygo.test', password: 'staygo-demo' })
const touched = ref(false)

const methodLabel = computed(() => ({ card: 'Thẻ ngân hàng', vietqr: 'VietQR', paypal: 'PayPal' })[props.method] ?? 'Thanh toán')
const digits = computed(() => card.number.replace(/\D/g, ''))
const cardBrand = computed(() => digits.value.startsWith('5') ? 'MASTERCARD' : 'VISA')
const cardValid = computed(() => /^\d{16}$/.test(digits.value) && card.name.trim().length >= 3 && /^(0[1-9]|1[0-2])\/\d{2}$/.test(card.expiry) && /^\d{3,4}$/.test(card.cvc))
const qrContent = computed(() => `STAYGO ${props.bookingCode || 'BOOKING'} ${Math.round(props.amount)}`)
const qrUrl = computed(() => `https://img.vietqr.io/image/MB-9704221900123456789-compact2.png?amount=${Math.round(props.amount)}&addInfo=${encodeURIComponent(qrContent.value)}&accountName=STAYGO%20DEMO`)
const canConfirm = computed(() => props.method !== 'card' || cardValid.value)

function formatCard(event) {
  card.number = event.target.value.replace(/\D/g, '').slice(0, 16).replace(/(.{4})/g, '$1 ').trim()
}

function formatExpiry(event) {
  const value = event.target.value.replace(/\D/g, '').slice(0, 4)
  card.expiry = value.length > 2 ? `${value.slice(0, 2)}/${value.slice(2)}` : value
}

function formatCvc(event) {
  card.cvc = event.target.value.replace(/\D/g, '').slice(0, 3)
}

function clearSensitive() {
  card.number = ''
  card.name = ''
  card.expiry = ''
  card.cvc = ''
  touched.value = false
}

function close() {
  if (props.processing) return
  clearSensitive()
  emit('close')
}

function confirmPayment() {
  touched.value = true
  if (!canConfirm.value) return
  emit('confirm', props.method === 'card' ? { card_last_four: digits.value.slice(-4) } : {})
}

watch(() => props.open, value => {
  document.body.style.overflow = value ? 'hidden' : ''
  if (!value) clearSensitive()
})
onUnmounted(() => {
  document.body.style.overflow = ''
  clearSensitive()
})
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="mock-overlay" role="dialog" aria-modal="true" aria-labelledby="payment-title" @click.self="close">
      <div class="mock-modal">
        <button class="mock-close" :disabled="processing" aria-label="Đóng" @click="close">×</button>

        <header class="mock-header">
          <span class="mock-brand">S</span>
          <div>
            <p>CỔNG THANH TOÁN STAYGO</p>
            <h2 id="payment-title">Thanh toán {{ methodLabel }}</h2>
          </div>
        </header>

        <div class="mock-columns">
          <section class="mock-stage">
            <div v-if="method === 'card'" class="card-layout">
              <div class="visual-card">
                <div class="card-glow"></div>
                <div class="card-top"><span>STAYGO PLATINUM CARD</span><b>{{ cardBrand }}</b></div>
                <div class="chip-row"><i class="chip"></i><span class="tap-wave">)))</span></div>
                <strong>{{ card.number || '•••• •••• •••• ••••' }}</strong>
                <div class="card-bottom"><span>{{ card.name || 'TEN CHU THE' }}</span><span>{{ card.expiry || 'MM/YY' }}</span></div>
              </div>

              <div class="card-fields">
                <h3>Thông tin thẻ thanh toán</h3>
                <label>Số thẻ<input :value="card.number" inputmode="numeric" autocomplete="cc-number" placeholder="4111 1111 1111 1111" @input="formatCard" /></label>
                <label>Tên chủ thẻ<input v-model.trim="card.name" autocomplete="cc-name" placeholder="NGUYEN VAN A" /></label>
                <div class="field-row">
                  <label>Hạn dùng<input :value="card.expiry" inputmode="numeric" maxlength="5" autocomplete="cc-exp" placeholder="12/30" @input="formatExpiry" /></label>
                  <label>CVC<input :value="card.cvc" type="password" inputmode="numeric" maxlength="3" autocomplete="cc-csc" placeholder="123" @input="formatCvc" /></label>
                </div>
                <small v-if="touched && !cardValid" class="field-error">Kiểm tra số thẻ 16 số, tên, hạn dùng MM/YY và CVC.</small>
                <p>Giao dịch được bảo mật mã hóa theo tiêu chuẩn PCI DSS toàn cầu.</p>
              </div>
            </div>

            <div v-else-if="method === 'vietqr'" class="qr-layout">
              <div class="qr-card">
                <span>VIETQR CHUYỂN KHOẢN</span>
                <img :src="qrUrl" alt="Mã VietQR chuyển khoản nhanh" />
                <strong>{{ money(amount) }}</strong>
                <small>{{ qrContent }}</small>
              </div>

              <div class="phone">
                <div class="phone-notch"><i></i></div>
                <div class="phone-screen">
                  <div class="phone-status"><span>9:41</span><span>5G ▰</span></div>
                  <div class="phone-camera"><span class="scan-line"></span><b>QUÉT MÃ QR</b><small>Đang tự động nhận diện giao dịch</small></div>
                  <button type="button" :disabled="processing" @click="confirmPayment">{{ processing ? 'Đang xác nhận...' : 'Xác nhận đã chuyển khoản' }}</button>
                </div>
              </div>
            </div>

            <div v-else class="paypal-checkout">
              <div class="paypal-top"><b>Pay<span>Pal</span></b><em>Secure Checkout</em></div>
              <div class="paypal-card">
                <p>Đăng nhập tài khoản PayPal</p>
                <label>Email<input v-model="paypal.email" readonly /></label>
                <label>Mật khẩu<input v-model="paypal.password" type="password" readonly /></label>
                <div class="paypal-buyer"><span>Tài khoản khách hàng</span><strong>{{ paypal.email }}</strong></div>
              </div>
              <div class="paypal-summary"><span>Thanh toán cho StayGo</span><strong>{{ money(amount) }}</strong></div>
            </div>
          </section>

          <aside class="mock-summary">
            <p class="eyebrow">Tóm tắt thanh toán</p>
            <h3>Mã đặt phòng</h3>
            <strong>{{ bookingCode }}</strong>
            <hr />
            <div><span>Thanh toán</span><b>An toàn</b></div>
            <div><span>Số tiền</span><strong>{{ money(amount) }}</strong></div>
            <div><span>Môi trường</span><b>Bảo mật</b></div>
            <p>Giao dịch được bảo vệ bằng SSL mã hóa 256-bit.</p>
            <p v-if="error" class="mock-error">{{ error }}</p>
            <button v-if="method !== 'vietqr'" class="mock-confirm" :disabled="processing || !canConfirm" @click="confirmPayment">
              {{ processing ? 'Đang xác nhận...' : method === 'paypal' ? 'Đăng nhập và thanh toán' : 'Xác nhận thanh toán' }}
            </button>
            <p v-else-if="processing" class="processing">Đang xác nhận thanh toán...</p>
          </aside>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.mock-overlay{position:fixed;inset:0;z-index:100;background:rgba(8,21,37,.75);backdrop-filter:blur(6px);display:grid;place-items:center;padding:18px}.mock-modal{position:relative;width:min(1080px,100%);max-height:94vh;overflow:auto;background:#fff;border-radius:24px;box-shadow:0 30px 90px rgba(0,0,0,.35)}.mock-close{position:absolute;right:18px;top:13px;border:0;background:transparent;font-size:30px;color:#64748b;cursor:pointer}.mock-header{display:flex;align-items:center;gap:14px;padding:22px 28px;border-bottom:1px solid #e2e8f0}.mock-brand{display:grid;place-items:center;width:42px;height:42px;border-radius:13px 13px 13px 4px;background:#0877cc;color:#fff;font-size:24px;font-weight:900}.mock-header p{margin:0;color:#0877cc;font-size:10px;font-weight:900;letter-spacing:1.6px}.mock-header h2{margin:2px 0 0;font-size:22px}.mock-columns{display:grid;grid-template-columns:1.65fr .85fr}.mock-stage{min-height:500px;padding:30px;background:linear-gradient(135deg,#f8fafc,#eef6ff);display:grid;place-items:center}.mock-summary{padding:30px;display:flex;flex-direction:column}.mock-summary h3{font-size:12px;color:#64748b;margin:14px 0 4px}.mock-summary>strong{font-size:20px;letter-spacing:.8px}.mock-summary hr{width:100%;border:0;border-top:1px solid #e2e8f0;margin:22px 0}.mock-summary>div{display:flex;justify-content:space-between;gap:14px;margin:7px 0}.mock-summary b{color:#0877cc}.mock-summary p:not(.eyebrow):not(.mock-error){color:#64748b;font-size:12px;line-height:1.5;margin-top:18px}.mock-confirm{margin-top:auto;border:0;border-radius:12px;padding:13px 16px;background:#0877cc;color:#fff;font-weight:900;cursor:pointer}.mock-confirm:disabled{opacity:.55;cursor:not-allowed}.mock-error{padding:10px 12px;border-radius:10px;background:#fef2f2;color:#b91c1c;font-size:12px}.processing{color:#0877cc;font-weight:800}.card-layout{width:100%;display:grid;grid-template-columns:330px 1fr;gap:28px;align-items:center}.visual-card{position:relative;overflow:hidden;height:205px;border-radius:22px;padding:24px;color:#fff;background:linear-gradient(135deg,#111827,#1e1b4b 58%,#0369a1);box-shadow:0 24px 50px rgba(15,23,42,.28);display:flex;flex-direction:column;justify-content:space-between}.card-glow{position:absolute;right:-50px;top:-50px;width:160px;height:160px;border-radius:50%;background:rgba(56,189,248,.25);filter:blur(8px)}.card-top,.card-bottom,.chip-row{position:relative;display:flex;justify-content:space-between;align-items:center}.card-top span{font-size:10px;font-weight:900;color:#cbd5e1;letter-spacing:1.5px}.card-top b{font-style:italic}.chip{width:42px;height:31px;border-radius:7px;background:linear-gradient(135deg,#facc15,#b7791f);box-shadow:inset 0 0 0 1px rgba(255,255,255,.3)}.tap-wave{color:#cbd5e1;letter-spacing:-3px;transform:rotate(90deg)}.visual-card strong{position:relative;text-align:center;font-size:20px;letter-spacing:3px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.card-bottom span{font-size:11px;font-weight:800;max-width:190px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.card-fields{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:22px;box-shadow:0 14px 32px rgba(15,23,42,.08)}.card-fields h3{margin:0 0 14px;font-size:16px}.card-fields label,.paypal-card label{display:block;color:#475569;font-size:11px;font-weight:900;margin-top:10px}.card-fields input,.paypal-card input{width:100%;box-sizing:border-box;margin-top:6px;border:1px solid #dbe3ee;border-radius:11px;padding:12px;background:#f8fafc;color:#0f172a;font-weight:800}.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field-error{display:block;color:#b91c1c;margin-top:8px;font-weight:800}.card-fields p{color:#64748b;font-size:11px;line-height:1.45}.qr-layout{width:100%;display:grid;grid-template-columns:.9fr 1.1fr;gap:30px;align-items:center}.qr-card{justify-self:end;width:min(320px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:22px;text-align:center;box-shadow:0 18px 40px rgba(15,23,42,.12)}.qr-card span{font-size:10px;font-weight:900;color:#0877cc;letter-spacing:1.7px}.qr-card img{width:220px;height:220px;object-fit:contain;margin:12px auto;background:#fff;border-radius:14px;border:1px solid #edf2f7}.qr-card strong{display:block;font-size:22px}.qr-card small{display:block;color:#64748b;font-weight:800;margin-top:5px}.phone{width:245px;height:430px;border-radius:42px;background:#020617;padding:10px;box-shadow:0 26px 60px rgba(2,6,23,.35);border:2px solid #111827}.phone-notch{height:18px;display:grid;place-items:center}.phone-notch i{display:block;width:64px;height:10px;background:#000;border-radius:999px}.phone-screen{height:392px;border-radius:30px;background:#0f172a;color:#fff;display:flex;flex-direction:column;padding:16px}.phone-status{display:flex;justify-content:space-between;color:#94a3b8;font-size:10px;font-weight:800}.phone-camera{position:relative;overflow:hidden;flex:1;margin:14px 0;border:1px solid #1f2937;border-radius:20px;background:radial-gradient(circle at center,#064e3b,#020617 72%);display:grid;place-items:center;text-align:center}.phone-camera b{color:#4ade80;font-size:12px;letter-spacing:1px}.phone-camera small{display:block;color:#94a3b8}.scan-line{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#4ade80,transparent);box-shadow:0 0 12px #4ade80;animation:scan 1.6s linear infinite}.phone button{border:0;border-radius:15px;padding:12px;background:#22c55e;color:#052e16;font-weight:900;cursor:pointer}.paypal-checkout{width:min(430px,100%);display:grid;gap:14px}.paypal-top{display:flex;justify-content:space-between;align-items:center}.paypal-top b{color:#003087;font-size:34px;font-weight:900}.paypal-top b span{color:#179bd7}.paypal-top em{padding:5px 10px;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:900;font-style:normal}.paypal-card,.paypal-summary,.demo-banner{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:20px;box-shadow:0 12px 30px rgba(15,23,42,.08)}.paypal-card p{margin:0 0 10px;text-align:center;color:#64748b}.paypal-buyer{margin-top:14px;border-radius:13px;background:#f8fafc;padding:12px;display:flex;justify-content:space-between;gap:12px;font-size:12px}.paypal-summary{display:flex;justify-content:space-between;align-items:center;font-weight:900}.paypal-summary strong{font-size:20px;color:#003087}.demo-banner{background:#fff7ed;color:#9a3412;font-size:12px;font-weight:800;line-height:1.5}.eyebrow{font-size:10px;font-weight:900;color:#0877cc;letter-spacing:1.6px;text-transform:uppercase}@keyframes scan{0%{top:8%}100%{top:90%}}@media(max-width:860px){.mock-columns,.card-layout,.qr-layout{grid-template-columns:1fr}.mock-stage{min-height:auto}.visual-card,.qr-card,.phone{justify-self:center}.mock-summary{padding-top:22px}.mock-confirm{margin-top:20px}}@media(max-width:520px){.mock-overlay{padding:0}.mock-modal{max-height:100vh;border-radius:0}.mock-header{padding:18px}.mock-stage,.mock-summary{padding:18px}.field-row{grid-template-columns:1fr}.visual-card{height:190px}.visual-card strong{font-size:16px;letter-spacing:2px}}
</style>
