<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api, apiError, responseData } from '../api'

const props = defineProps({ hotelSlug: { type: String, required: true }, roomTypes: { type: Array, default: () => [] }, initialReviews: { type: Array, default: () => [] }, authenticated: Boolean })
const reviews = ref([...props.initialReviews])
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const form = reactive({ booking_code: '', room_type_id: '', rating_overall: 5, rating_room: 5, rating_service: 5, title: '', content: '' })
const canReview = computed(() => props.authenticated && props.roomTypes.length > 0)
const average = computed(() => reviews.value.length ? (reviews.value.reduce((sum, item) => sum + Number(item.rating_overall ?? item.rating ?? 0), 0) / reviews.value.length).toFixed(1) : '—')

const userBookings = ref([])
const showBookingDropdown = ref(false)

async function loadReviews() {
  loading.value = true
  try {
    const data = responseData(await api.get(`/hotels/${props.hotelSlug}/reviews`))
    reviews.value = Array.isArray(data) ? data : data.data ?? data.reviews ?? data.items ?? []
  } catch (err) { if (!reviews.value.length) error.value = apiError(err, 'Chưa thể tải đánh giá.') }
  finally { loading.value = false }
}

async function fetchUserBookings() {
  if (!props.authenticated) return
  try {
    const res = await api.get('/me/bookings')
    const list = res.data?.data ?? res.data?.items ?? res.data ?? []
    userBookings.value = Array.isArray(list) ? list : []
  } catch (err) {
    console.error('Không thể lấy danh sách đặt phòng:', err)
  }
}

const myHotelBookings = computed(() => {
  return userBookings.value.filter(b => b.hotel?.slug === props.hotelSlug)
})

const suggestedBookings = computed(() => {
  const query = String(form.booking_code).trim().toLowerCase()
  if (!query) return myHotelBookings.value
  return myHotelBookings.value.filter(b => String(b.code).toLowerCase().includes(query))
})

function selectBooking(b) {
  form.booking_code = b.code
  const found = props.roomTypes.find(r => r.id === b.room_type?.id || r.id === b.room_type_id || r.id === b.rooms?.[0]?.room_type_id)
  if (found) {
    form.room_type_id = found.id
  } else if (b.room_type?.id || b.room_type_id) {
    form.room_type_id = b.room_type?.id || b.room_type_id
  }
  showBookingDropdown.value = false
}

function handleBookingBlur() {
  setTimeout(() => { showBookingDropdown.value = false }, 200)
}

async function submitReview() {
  submitting.value = true; error.value = ''
  try {
    const review = responseData(await api.post('/reviews', { ...form, room_type_id: form.room_type_id }))
    reviews.value.unshift(review); form.booking_code = ''; form.title = ''; form.content = ''
  } catch (err) { error.value = apiError(err, 'API chỉ nhận đánh giá từ chủ booking đã trả phòng.') }
  finally { submitting.value = false }
}

watch(() => props.hotelSlug, () => { loadReviews(); fetchUserBookings() })
watch(() => props.authenticated, val => { if (val) fetchUserBookings() })
watch(() => props.roomTypes, rooms => { if (!form.room_type_id && rooms.length) form.room_type_id = rooms[0].id }, { immediate: true })

onMounted(() => {
  loadReviews()
  fetchUserBookings()
})
</script>

<template>
  <section class="reviews-section">
    <div class="review-heading"><div><p class="eyebrow">Trải nghiệm thực tế</p><h2>Đánh giá của khách</h2></div><div class="review-average"><strong>{{ average }}</strong><span>{{ reviews.length }} đánh giá</span></div></div>
    
    <form v-if="canReview" class="review-form" @submit.prevent="submitReview">
      <h3>Chia sẻ kỳ nghỉ đã hoàn tất</h3>
      
      <div class="review-form-grid">
        <label>
          <span>Mã booking đã trả phòng</span>
          <div class="autocomplete-wrapper">
            <input 
              v-model.trim="form.booking_code" 
              required 
              placeholder="DP-..." 
              autocomplete="off"
              @focus="showBookingDropdown = true"
              @blur="handleBookingBlur"
            />
            <transition name="fade">
              <ul v-if="showBookingDropdown && suggestedBookings.length" class="bookings-dropdown">
                <li 
                  v-for="b in suggestedBookings" 
                  :key="b.code"
                  class="dropdown-item"
                  @mousedown="selectBooking(b)"
                >
                  <div class="booking-item-info">
                    <strong>{{ b.code }}</strong>
                    <small>{{ b.room_type?.name || b.room_name || 'Phòng đã đặt' }} · {{ b.checkin }}</small>
                  </div>
                </li>
              </ul>
            </transition>
          </div>
        </label>
        
        <label>
          <span>Loại phòng</span>
          <select v-model="form.room_type_id" required>
            <option v-for="room in roomTypes" :key="room.id" :value="room.id">{{ room.name }}</option>
          </select>
        </label>
      </div>
      
      <div class="review-ratings">
        <label v-for="field in [{key:'rating_overall',name:'Tổng thể'},{key:'rating_room',name:'Phòng'},{key:'rating_service',name:'Dịch vụ'}]" :key="field.key">
          <span>{{ field.name }}</span>
          <select v-model.number="form[field.key]">
            <option v-for="star in [5,4,3,2,1]" :key="star" :value="star">{{ star }} sao</option>
          </select>
        </label>
      </div>
      
      <label><span>Tiêu đề</span><input v-model.trim="form.title" maxlength="255" /></label>
      <label><span>Nội dung</span><textarea v-model.trim="form.content" rows="3" maxlength="5000"></textarea></label>
      
      <button class="primary" :disabled="submitting" type="submit">
        {{ submitting ? 'Đang gửi...' : 'Gửi đánh giá' }}
      </button>
    </form>
    
    <p v-else-if="authenticated && !loading" class="review-note">Bạn có thể đánh giá khi khách sạn có loại phòng hợp lệ.</p>
    <p v-else-if="!authenticated" class="review-note">Đăng nhập để đánh giá kỳ nghỉ đã hoàn tất.</p>
    
    <p v-if="error" class="review-error">{{ error }}</p>
    <div v-if="loading" class="review-note">Đang tải đánh giá...</div>
    
    <div v-else-if="reviews.length" class="review-grid">
      <article v-for="review in reviews" :key="review.id">
        <div>
          <strong>{{ review.guest_name ?? review.user?.name ?? 'Khách StayGo' }}</strong>
          <span>{{ '★'.repeat(Number(review.rating_overall ?? review.rating ?? 0)) }}</span>
        </div>
        <h3>{{ review.title }}</h3>
        <p>{{ review.content ?? review.comment }}</p>
        <small>{{ review.stay_date ?? review.created_at?.slice?.(0, 10) }}</small>
      </article>
    </div>
    <div v-else class="review-note">Chưa có đánh giá. Hãy là vị khách đầu tiên chia sẻ trải nghiệm.</div>
  </section>
</template>

<style scoped>
.reviews-section{padding:45px 0 70px;border-top:1px solid #dce3ea}.review-heading{display:flex;justify-content:space-between;align-items:end;margin-bottom:22px}.review-heading h2{margin:0}.review-average{display:flex;align-items:center;gap:10px}.review-average strong{background:#0877cc;color:#fff;border-radius:8px 8px 8px 0;padding:9px;font-size:18px}.review-average span{color:#637083}.review-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.review-grid article{border:1px solid #dce3ea;border-radius:10px;padding:18px}.review-grid article>div{display:flex;justify-content:space-between}.review-grid article>div span{color:#f5a623}.review-grid h3{margin:12px 0 5px}.review-grid p,.review-grid small,.review-note{color:#637083}.review-form{background:#f4f7fa;border-radius:10px;padding:20px;margin-bottom:20px}.review-form-grid,.review-ratings{display:grid;grid-template-columns:1fr 1fr;gap:12px}.review-ratings{grid-template-columns:repeat(3,1fr)}.review-form label>span{display:block;color:#637083;font-size:11px;font-weight:700;margin:8px 0 4px}.review-form textarea{resize:vertical}.review-form .primary{margin-top:10px}.review-error{color:#a72d2d}

/* Autocomplete styling for booking codes */
.autocomplete-wrapper {
  position: relative;
  width: 100%;
}
.bookings-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  width: 100%;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(19, 36, 58, 0.15);
  border: 1px solid #dce3ea;
  list-style: none;
  margin: 4px 0 0;
  padding: 6px 0;
  z-index: 99;
  max-height: 180px;
  overflow-y: auto;
  box-sizing: border-box;
}
.dropdown-item {
  padding: 8px 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
}
.dropdown-item:hover {
  background: #edf5fd;
}
.booking-item-info {
  display: flex;
  flex-direction: column;
  text-align: left;
}
.booking-item-info strong {
  font-size: 13px;
  color: #13243a;
}
.booking-item-info small {
  font-size: 11px;
  color: #637083;
  margin-top: 1.5px;
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media(max-width:620px){.review-grid,.review-form-grid,.review-ratings{grid-template-columns:1fr}}
</style>
