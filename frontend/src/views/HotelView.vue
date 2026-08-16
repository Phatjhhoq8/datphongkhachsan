<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SearchForm from '../components/SearchForm.vue'
import ReviewSection from '../components/ReviewSection.vue'
import WishlistButton from '../components/WishlistButton.vue'
import { api, apiError, responseData, responseList } from '../api'
import { useAuthStore } from '../stores/auth'
import { trackActivity } from '../analytics'
import { addDays, localImage, money, today } from '../utils'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const hotel = ref(null)
const availableRooms = ref([])
const services = ref([])
const loading = ref(true)
const availabilityLoading = ref(false)
const error = ref('')
const availabilityError = ref('')
const dates = computed(() => ({ checkin: route.query.checkin || addDays(today(), 1), checkout: route.query.checkout || addDays(today(), 2), adults: Number(route.query.adults || 2), children: Number(route.query.children || 0), rooms: Number(route.query.rooms || 1), location: route.query.location || '', arrival_time: route.query.arrival_time || '', checkout_time: route.query.checkout_time || '' }))
const authenticated = computed(() => auth.isAuthenticated)
const gallery = computed(() => {
  const values = (hotel.value?.room_types ?? []).flatMap(room => room.images ?? []).map(item => localImage(item.url ?? item))
  return [...new Set([hotel.value?.hero_image, ...values].filter(Boolean).map(item => localImage(item)))].slice(0, 5)
})
const hotelAmenities = computed(() => [...new Map((hotel.value?.room_types ?? []).flatMap(room => room.amenities ?? []).map(item => [item.id ?? item.slug, item])).values()])
const reviewCount = computed(() => Number(hotel.value?.approved_reviews_count ?? 0))
const reviewScore = computed(() => Number(hotel.value?.approved_reviews_avg_rating ?? 0))
const reviewLabel = computed(() => reviewScore.value >= 4.5 ? 'Tuyệt vời' : reviewScore.value >= 4 ? 'Rất tốt' : reviewScore.value >= 3 ? 'Tốt' : 'Đánh giá của khách')

function matchesHotel(room) { return String(room.hotel?.id ?? room.hotel_id) === String(hotel.value?.id) || room.hotel?.slug === (hotel.value?.slug ?? route.params.slug) }
async function fetchAvailability() {
  if (!hotel.value) return
  availabilityLoading.value = true; availabilityError.value = ''
  try {
    const results = responseList(await api.get('/search', { params: { ...dates.value, location: hotel.value.name } }))
    availableRooms.value = results.filter(matchesHotel)
  } catch (err) { availableRooms.value = []; availabilityError.value = apiError(err, 'Không thể kiểm tra phòng trống cho ngày đã chọn.') }
  finally { availabilityLoading.value = false }
}
async function fetchServices() {
  const embedded = hotel.value?.services ?? hotel.value?.available_services
  if (embedded?.length) { services.value = embedded; return }
  try { const data = responseData(await api.get(`/hotels/${hotel.value.slug ?? route.params.slug}/services`)); services.value = Array.isArray(data) ? data : data.services ?? data.items ?? [] } catch { services.value = [] }
}
async function fetchHotel() {
  loading.value = true; error.value = ''
  try {
    hotel.value = responseData(await api.get(`/hotels/${route.params.slug}`, { params: dates.value }))
    trackActivity('room_view', { hotel_id: hotel.value.id })
    await Promise.all([fetchAvailability(), fetchServices()])
  } catch (err) { error.value = apiError(err, 'Không thể tải thông tin khách sạn này.') }
  finally { loading.value = false }
}
function chooseRoom(room) {
  const quote = { hotel: { id: hotel.value.id, slug: hotel.value.slug ?? route.params.slug, name: hotel.value.name, address: hotel.value.address, star_rating: hotel.value.star_rating }, room, ...dates.value }
  sessionStorage.setItem('staygo_booking_quote', JSON.stringify(quote))
  router.push({ path: '/hotel/booking', query: { ...dates.value, room_type_id: room.id, hotel_slug: hotel.value.slug ?? route.params.slug } })
}
function scrollToReviews(e) {
  e.preventDefault()
  const el = document.getElementById('reviews')
  if (el) el.scrollIntoView({ behavior: 'smooth' })
}
watch([() => route.params.slug, () => route.query], fetchHotel, { deep: true })
onMounted(fetchHotel)
</script>

<template>
  <div class="detail-search"><div class="container"><SearchForm :initial="dates" compact /></div></div>
  <div v-if="loading" class="container state-card page-state"><span class="spinner"></span><h2>Đang tải khách sạn...</h2></div>
  <div v-else-if="error" class="container state-card error-state page-state"><h2>Chưa thể tải khách sạn</h2><p>{{ error }}</p><button class="primary" @click="fetchHotel">Thử lại</button></div>
  <template v-else-if="hotel"><div class="container detail-page">
    <nav class="breadcrumbs" aria-label="Đường dẫn"><a href="/hotel">Trang chủ</a><span>/</span><a href="/hotel/search">Khách sạn</a><span>/</span><span>{{ hotel.name }}</span></nav>
    <section class="hotel-title"><div><span v-if="hotel.star_rating" class="stars">{{ '★'.repeat(Number(hotel.star_rating)) }}</span><h1>{{ hotel.name }}</h1><p>⌖ {{ hotel.address ?? hotel.location }}</p></div><div class="hotel-title-actions"><WishlistButton v-if="authenticated" :room-type-id="availableRooms[0]?.id" :authenticated="authenticated" /><a href="#reviews" class="detail-score" @click="scrollToReviews"><span>{{ reviewCount ? reviewScore.toFixed(1) : '—' }}</span><div><strong>{{ reviewCount ? reviewLabel : 'Chưa có đánh giá' }}</strong><small>{{ reviewCount }} đánh giá</small></div></a></div></section>
    <section class="gallery" aria-label="Ảnh khách sạn"><img v-for="(image, index) in gallery" :key="image" :class="`gallery-${index}`" :src="image" :alt="`${hotel.name}, ảnh ${index + 1}`" /></section>
    <div class="detail-columns"><section class="detail-main"><template v-if="hotel.description"><h2>Thông tin nơi nghỉ</h2><p class="description">{{ hotel.description }}</p></template><template v-if="hotelAmenities.length"><h2>Tiện nghi nổi bật</h2><div class="amenity-grid"><span v-for="item in hotelAmenities" :key="item.id ?? item.slug">✓ {{ item.name }}</span></div></template></section><aside class="detail-aside"><span>Giá phòng trống từ</span><strong>{{ availableRooms.length ? money(Math.min(...availableRooms.map(room => Number(room.price ?? room.price_per_night)))) : 'Không có phòng phù hợp' }}</strong><small>theo ngày và số khách đã chọn</small><a class="primary" href="#rooms">Xem phòng trống</a></aside></div>
    <ReviewSection id="reviews" :hotel-slug="hotel.slug ?? route.params.slug" :room-types="hotel.room_types ?? hotel.rooms ?? []" :initial-reviews="hotel.reviews ?? []" :authenticated="authenticated" />
    <section v-if="services.length" class="service-preview"><div class="section-heading"><div><p class="eyebrow">Nâng cấp kỳ nghỉ</p><h2>Dịch vụ có thể đặt thêm</h2></div><small>Chọn ở bước thanh toán</small></div><div class="service-preview-grid"><article v-for="service in services.slice(0, 4)" :key="service.id"><i>{{ service.icon ?? '＋' }}</i><div><strong>{{ service.name }}</strong><p>{{ service.description ?? service.unit_label }}</p></div><b>{{ money(service.price ?? service.unit_price) }}</b></article></div></section>
    <section id="rooms" class="rooms-section"><div class="section-heading"><div><p class="eyebrow">Availability trực tiếp</p><h2>Phòng trống cho ngày của bạn</h2><p>{{ dates.checkin }} đến {{ dates.checkout }} · {{ dates.adults }} khách · {{ dates.rooms }} phòng</p></div><button class="refresh-rooms" :disabled="availabilityLoading" @click="fetchAvailability">↻ Kiểm tra lại</button></div>
      <div v-if="availabilityLoading" class="empty-inline availability-state"><span class="spinner"></span> Đang kiểm tra tồn phòng thực tế...</div><div v-else-if="availabilityError" class="empty-inline availability-error">{{ availabilityError }} <button @click="fetchAvailability">Thử lại</button></div><div v-else-if="!availableRooms.length" class="empty-inline">Không còn loại phòng phù hợp trong khoảng ngày này. Hãy đổi ngày hoặc số lượng phòng.</div>
      <article v-for="(room, index) in availableRooms" v-else :key="room.id" class="room-card"><img :src="localImage(room.image ?? room.images?.[0]?.url ?? room.images?.[0], index + 1)" :alt="room.name" /><div class="room-details"><h3>{{ room.name }}</h3><p>♙ Tối đa {{ Number(room.max_adults || 0) + Number(room.max_children || 0) }} khách/phòng</p><p v-if="room.size_m2 || room.bed_description">▣ <template v-if="room.size_m2">{{ room.size_m2 }} m²</template><template v-if="room.size_m2 && room.bed_description"> · </template>{{ room.bed_description }}</p><div class="amenities"><span v-for="item in room.amenities" :key="item.id">✓ {{ item.name }}</span></div></div><div class="room-policy"><strong>{{ room.refundable === false ? 'Không hoàn tiền' : 'Có thể hoàn hủy theo chính sách' }}</strong><span>{{ room.breakfast_included ? 'Bao gồm bữa sáng' : 'Không gồm bữa sáng' }}</span><span class="availability">Còn {{ room.available_rooms }} phòng theo ngày đã chọn</span></div><div class="room-price"><small>Giá mỗi phòng/đêm</small><strong>{{ money(room.price ?? room.price_per_night) }}</strong><small>Tổng kỳ nghỉ {{ money(room.total_price) }}</small><button class="primary" @click="chooseRoom(room)">Chọn phòng</button></div></article>
    </section>
  </div></template>
</template>

<style scoped>
.hotel-title-actions{display:flex;align-items:end;gap:22px}
.detail-score{display:flex;flex-direction:row-reverse;align-items:center;gap:10px;text-decoration:none;color:inherit;cursor:pointer}
.detail-score:hover small{text-decoration:underline;color:#0877cc}.service-preview{padding:38px 0;border-bottom:1px solid #dce3ea}.service-preview .section-heading small{color:#637083}.service-preview-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.service-preview article{display:grid;grid-template-columns:38px 1fr;gap:10px;border:1px solid #dce3ea;border-radius:10px;padding:14px}.service-preview i{display:grid;place-items:center;width:36px;height:36px;border-radius:9px;background:#e9f5fd;color:#0877cc;font-style:normal;font-size:20px}.service-preview p{font-size:10px;color:#637083;margin:3px 0}.service-preview b{grid-column:2;color:#e05220;font-size:11px}.refresh-rooms{border:1px solid #0877cc;background:#fff;color:#0877cc;border-radius:7px;padding:8px 12px;font-weight:700;cursor:pointer}.availability-state{display:flex;align-items:center;justify-content:center;gap:12px}.availability-state .spinner{width:22px;height:22px}.availability-error{color:#a72d2d}.availability-error button{border:0;background:none;color:#0877cc;text-decoration:underline;cursor:pointer}@media(max-width:900px){.service-preview-grid{grid-template-columns:1fr 1fr}}@media(max-width:620px){.hotel-title-actions{display:block}.hotel-title-actions :deep(.wishlist-wrap){margin-bottom:10px}.service-preview-grid{grid-template-columns:1fr}.rooms-section .section-heading{align-items:start;gap:10px}.refresh-rooms{white-space:nowrap}}
</style>
