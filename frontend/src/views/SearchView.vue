<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import SearchForm from '../components/SearchForm.vue'
import { api, apiError, responseList } from '../api'
import { addDays, localImage, money, today } from '../utils'

const route = useRoute()
const router = useRouter()
const hotels = ref([])
const catalog = ref([])
const loading = ref(true)
const error = ref('')
const sort = ref('recommended')
const filters = ref({ min_price: '', max_price: '', stars: [], amenities: [], room_type: [], refundable: false })
const currentPage = ref(1)
const perPage = ref(5)
const starOptions = [5, 4, 3, 2, 1]
const roomTypeOptions = computed(() => uniqueOptions(catalog.value.flatMap(hotel => hotel.room_types ?? []), 'slug', 'name'))
const amenityOptions = computed(() => uniqueOptions(catalog.value.flatMap(hotel => (hotel.room_types ?? []).flatMap(room => room.amenities ?? [])), 'slug', 'name'))

const sortedHotels = computed(() => {
  const result = hotels.value.filter(roomType => {
    let stars = Number(roomType.hotel?.stars ?? roomType.hotel?.star_rating ?? 5)
    if (stars <= 0) stars = 5
    const slug = String(roomType.slug ?? roomType.room_type ?? roomType.name ?? '').toLowerCase()
    const matchesStars = !filters.value.stars.length || filters.value.stars.includes(stars)
    const matchesType = !filters.value.room_type.length || filters.value.room_type.some(type => slug.includes(type))
    const matchesRefundable = !filters.value.refundable || roomType.refundable === true || roomType.is_refundable === true
    return matchesStars && matchesType && matchesRefundable
  })
  if (sort.value === 'price_asc') result.sort((a, b) => hotelPrice(a) - hotelPrice(b))
  if (sort.value === 'rating_desc') result.sort((a, b) => reviewScore(b) - reviewScore(a))
  return result
})

function hotelPrice(roomType) { return roomType.price_per_night ?? roomType.price ?? 0 }
function amenities(roomType) { return roomType.amenities?.slice?.(0, 4) ?? [] }
function image(roomType, index) { return localImage(roomType.images?.[0]?.url, (roomType.id ?? index + 1) % 4 || 1) }
function reviewScore(roomType) { return Number(roomType.hotel?.approved_reviews_avg_rating ?? 0) }
function reviewLabel(score) { return score >= 4.5 ? 'Tuyệt vời' : score >= 4 ? 'Rất tốt' : score >= 3 ? 'Tốt' : 'Đánh giá của khách' }
function uniqueOptions(items, valueKey, labelKey) {
  return [...new Map(items.filter(item => item?.[valueKey]).map(item => [item[valueKey], { value: item[valueKey], label: item[labelKey] }])).values()]
}
function searchParams() {
  return {
    location: route.query.location || '',
    checkin: route.query.checkin || addDays(today(), 1),
    checkout: route.query.checkout || addDays(today(), 2),
    rooms: Number(route.query.rooms || 1),
    adults: Number(route.query.adults || 2),
    children: Number(route.query.children || 0),
    keyword: route.query.keyword || '',
    arrival_time: route.query.arrival_time || '',
    checkout_time: route.query.checkout_time || '',
  }
}

async function fetchCatalog() {
  try { catalog.value = responseList(await api.get('/hotels', { params: { location: route.query.location || undefined } })) }
  catch { catalog.value = [] }
}

async function fetchHotels() {
  loading.value = true
  error.value = ''
  try {
    const params = {
      ...searchParams(),
      min_price: filters.value.min_price || undefined,
      max_price: filters.value.max_price || undefined,
      stars: filters.value.stars.length ? filters.value.stars.join(',') : undefined,
      amenities: filters.value.amenities.length ? filters.value.amenities.join(',') : undefined,
      room_type: filters.value.room_type.length ? filters.value.room_type.join(',') : undefined,
      refundable: filters.value.refundable ? 1 : undefined,
      keyword: route.query.keyword || undefined,
      arrival_time: route.query.arrival_time || undefined,
      checkout_time: route.query.checkout_time || undefined,
    }
    hotels.value = responseList(await api.get('/search', { params }))
  } catch (err) {
    error.value = apiError(err, 'Không thể tìm khách sạn lúc này. Vui lòng kiểm tra kết nối và thử lại.')
  } finally { loading.value = false }
}

function hotelLink(roomType) {
  return { path: `/hotel/${roomType.hotel?.slug}`, query: route.query }
}

function chooseRoom(roomType) {
  const params = searchParams()
  const quote = { hotel: roomType.hotel, room: roomType, ...params }
  sessionStorage.setItem('staygo_booking_quote', JSON.stringify(quote))
  router.push({ path: '/hotel/booking', query: { ...params, room_type_id: roomType.id, hotel_slug: roomType.hotel?.slug } })
}

function clearFilters() {
  filters.value = { min_price: '', max_price: '', stars: [], amenities: [], room_type: [], refundable: false }
}

const paginatedHotels = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  const end = start + perPage.value
  return sortedHotels.value.slice(start, end)
})
const totalPages = computed(() => Math.ceil(sortedHotels.value.length / perPage.value))

let filterTimer
watch(() => route.query, fetchHotels, { deep: true })
watch(() => route.query.location, () => { clearFilters(); fetchCatalog() })
watch(filters, () => { clearTimeout(filterTimer); filterTimer = setTimeout(fetchHotels, 350) }, { deep: true })
watch([filters, sort, () => route.query], () => { currentPage.value = 1 }, { deep: true })
onMounted(() => { fetchCatalog(); fetchHotels() })
</script>

<template>
  <div class="compact-search"><div class="container"><SearchForm :initial="route.query" compact /></div></div>
  <div class="search-page container">
    <aside class="filters" aria-label="Bộ lọc tìm kiếm">
      <h2>Bộ lọc</h2>
      <fieldset><legend>Khoảng giá mỗi đêm</legend><div class="price-fields"><input v-model.number="filters.min_price" type="number" min="0" placeholder="Từ" aria-label="Giá thấp nhất" /><input v-model.number="filters.max_price" type="number" min="0" placeholder="Đến" aria-label="Giá cao nhất" /></div></fieldset>
      <fieldset v-if="starOptions.length"><legend>Hạng khách sạn</legend><label v-for="star in starOptions" :key="star"><input v-model="filters.stars" type="checkbox" :value="star" /> <span class="stars">{{ '★'.repeat(star) }}</span></label></fieldset>
      <fieldset v-if="roomTypeOptions.length"><legend>Loại phòng</legend><label v-for="item in roomTypeOptions" :key="item.value"><input v-model="filters.room_type" type="checkbox" :value="item.value" /> {{ item.label }}</label></fieldset>
      <fieldset v-if="amenityOptions.length"><legend>Tiện ích</legend><label v-for="item in amenityOptions" :key="item.value"><input v-model="filters.amenities" type="checkbox" :value="item.value" /> {{ item.label }}</label></fieldset>
      <fieldset><legend>Chính sách</legend><label><input v-model="filters.refundable" type="checkbox" /> Có thể hoàn hủy</label></fieldset>
    </aside>
    <section class="results" aria-live="polite">
      <div class="results-head"><div><p>Tìm thấy nơi nghỉ tại</p><h1>{{ route.query.location || 'điểm đến của bạn' }}</h1></div><label>Sắp xếp <select v-model="sort"><option value="recommended">Đề xuất</option><option value="price_asc">Giá thấp nhất</option><option value="rating_desc">Đánh giá cao</option></select></label></div>
      <div v-if="loading" class="state-card"><span class="spinner"></span><h2>Đang tìm giá tốt nhất...</h2></div>
      <div v-else-if="error" class="state-card error-state"><h2>Chưa thể tải kết quả</h2><p>{{ error }}</p><button class="primary" @click="fetchHotels">Thử lại</button></div>
      <div v-else-if="!sortedHotels.length" class="state-card"><h2>Không tìm thấy nơi nghỉ phù hợp</h2><p>{{ catalog.length ? 'Hãy thử đổi ngày, khoảng giá hoặc bỏ bớt bộ lọc.' : `Hiện chưa có khách sạn đang hoạt động tại ${route.query.location || 'điểm đến này'}.` }}</p><button v-if="catalog.length" class="primary" type="button" @click="clearFilters">Xóa bộ lọc</button></div>
      <article v-for="(roomType, index) in paginatedHotels" v-else :key="roomType.id" class="hotel-card">
        <RouterLink :to="hotelLink(roomType)" class="hotel-image"><img :src="image(roomType, index)" :alt="roomType.hotel.name" /><span v-if="roomType.images?.length" class="photo-count">▣ {{ roomType.images.length }} ảnh</span></RouterLink>
        <div class="hotel-info">
          <span v-if="roomType.hotel.star_rating" class="stars">{{ '★'.repeat(Number(roomType.hotel.star_rating)) }}</span>
          <RouterLink :to="hotelLink(roomType)"><h2>{{ roomType.hotel.name }}</h2></RouterLink>
          <p class="location">⌖ {{ roomType.hotel.address }}</p>
          <h3>{{ roomType.name }}</h3>
          <div class="room-specs">
            <span>👤 Tối đa {{ roomType.max_adults }} khách</span>
            <span v-if="roomType.bed_description"> · 🛏️ {{ roomType.bed_description }}</span>
            <span v-if="roomType.size_m2"> · 📐 {{ Math.round(roomType.size_m2) }} m²</span>
          </div>
          <p class="room-refund">{{ roomType.refundable === false ? 'Không hoàn tiền' : 'Có thể hoàn hủy' }}</p>
          <div class="amenities"><span v-for="item in amenities(roomType)" :key="item.id">✓ {{ item.name }}</span></div>
          <p class="availability">Chỉ còn {{ roomType.available_rooms }} phòng ở mức giá này</p>
        </div>
        <div class="hotel-price"><div v-if="roomType.hotel.approved_reviews_count" class="score"><span><b>{{ reviewScore(roomType).toFixed(1) }}</b></span><p><strong>{{ reviewLabel(reviewScore(roomType)) }}</strong><small>{{ roomType.hotel.approved_reviews_count }} đánh giá</small></p></div><p v-else class="muted">Chưa có đánh giá</p><p>Giá mỗi đêm từ</p><strong>{{ money(hotelPrice(roomType)) }}</strong><small>Tổng {{ money(roomType.total_price) }} cho kỳ nghỉ</small><button class="primary" type="button" @click="chooseRoom(roomType)">Chọn phòng</button></div>
      </article>

      <!-- Pagination Controls -->
      <div v-if="totalPages > 1" class="pagination">
        <button 
          type="button" 
          class="pagination-btn" 
          :disabled="currentPage === 1" 
          @click="currentPage--; window.scrollTo({ top: 0, behavior: 'smooth' })"
          aria-label="Trang trước"
        >
          &lsaquo; Trước
        </button>
        
        <button 
          v-for="page in totalPages" 
          :key="page" 
          type="button" 
          class="pagination-btn page-num" 
          :class="{ active: currentPage === page }"
          @click="currentPage = page; window.scrollTo({ top: 0, behavior: 'smooth' })"
        >
          {{ page }}
        </button>
        
        <button 
          type="button" 
          class="pagination-btn" 
          :disabled="currentPage === totalPages" 
          @click="currentPage++; window.scrollTo({ top: 0, behavior: 'smooth' })"
          aria-label="Trang sau"
        >
          Sau &rsaquo;
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.room-refund{display:inline-block;margin:3px 0 0;padding:3px 7px;border-radius:12px;background:#eaf7f0;color:#168a52;font-size:10px;font-weight:700}
.room-specs { display:flex; align-items:center; gap:8px; color:#4b5563; font-size:12px; margin:6px 0; font-weight:500; }

/* Pagination Styling */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  margin-top: 30px;
  margin-bottom: 20px;
}
.pagination-btn {
  background: #ffffff;
  border: 1px solid #dce3ea;
  color: #13243a;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}
.pagination-btn:hover:not(:disabled) {
  border-color: #ff5a5f;
  color: #ff5a5f;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(255, 90, 95, 0.1);
}
.pagination-btn.page-num {
  padding: 8px 14px;
}
.pagination-btn.active {
  background: #ff5a5f;
  border-color: #ff5a5f;
  color: #ffffff;
  box-shadow: 0 4px 10px rgba(255, 90, 95, 0.2);
}
.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
