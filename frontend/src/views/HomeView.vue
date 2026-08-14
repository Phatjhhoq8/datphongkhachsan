<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import SearchForm from '../components/SearchForm.vue'
import { api, responseList } from '../api'
import { localImage, money } from '../utils'

const hotels = ref([])
const vouchers = ref([])
const destinations = computed(() => Object.values(hotels.value.reduce((result, hotel) => {
  const city = hotel.city?.trim()
  if (!city) return result
  result[city] ??= { name: city, count: 0, image: hotel.hero_image }
  result[city].count += 1
  return result
}, {})))
const featuredRooms = computed(() => hotels.value.flatMap(hotel =>
  (hotel.room_types ?? []).filter(room => Number(room.available_rooms) > 0).map(room => ({ ...room, hotel })),
).slice(0, 3))
const defaultLocation = computed(() => destinations.value[0]?.name || '')

onMounted(async () => {
  const [hotelResult, voucherResult] = await Promise.allSettled([api.get('/hotels'), api.get('/vouchers')])
  hotels.value = hotelResult.status === 'fulfilled' ? responseList(hotelResult.value) : []
  vouchers.value = voucherResult.status === 'fulfilled' ? responseList(voucherResult.value) : []
})

function voucherValue(voucher) { return voucher.type === 'percent' ? `${Number(voucher.value)}%` : money(voucher.value) }
function voucherLocation(voucher) { return voucher.hotel?.city || defaultLocation.value }
</script>

<template>
  <section class="home-hero">
    <div class="hero-shade"></div>
    <div class="container hero-content">
      <p class="eyebrow light">Kỳ nghỉ của bạn, lựa chọn của bạn</p>
      <h1>Tìm nơi nghỉ hoàn hảo<br />cho hành trình sắp tới</h1>
      <p>{{ hotels.length ? `${hotels.length} khách sạn đang hoạt động, giá minh bạch và xác nhận nhanh chóng.` : 'Giá minh bạch và xác nhận nhanh chóng.' }}</p>
    </div>
  </section>
  <div class="container hero-search"><SearchForm :initial="{ location: defaultLocation }" /></div>

  <section class="trust-strip">
    <div class="container trust-grid">
      <div><span class="trust-icon">✓</span><p><strong>Giá rõ ràng</strong><small>Không phí ẩn khi thanh toán</small></p></div>
      <div><span class="trust-icon">⌁</span><p><strong>Nhiều lựa chọn</strong><small>Phòng phù hợp mọi chuyến đi</small></p></div>
      <div><span class="trust-icon">24</span><p><strong>Hỗ trợ tận tâm</strong><small>Đồng hành khi bạn cần</small></p></div>
    </div>
  </section>

  <section v-if="vouchers.length" id="offers" class="section container">
    <div class="section-heading"><div><p class="eyebrow">Ưu đãi riêng cho bạn</p><h2>Đi nhiều hơn, chi ít hơn</h2></div><RouterLink to="/hotel/search">Xem tất cả</RouterLink></div>
    <div class="offer-grid">
      <article v-for="(voucher, index) in vouchers" :key="voucher.id" class="offer-card" :class="index % 2 ? 'blue' : 'coral'"><div><span class="offer-label">{{ voucher.code }}</span><h3>Giảm {{ voucherValue(voucher) }}</h3><p><template v-if="voucher.min_order">Cho đơn từ {{ money(voucher.min_order) }}.</template><template v-if="voucher.hotel"> Áp dụng tại {{ voucher.hotel.name }}.</template></p><RouterLink :to="{ path: '/hotel/search', query: voucherLocation(voucher) ? { location: voucherLocation(voucher) } : {} }">Xem phòng áp dụng</RouterLink></div><div v-if="voucher.type === 'percent'" class="offer-art">{{ Number(voucher.value) }}<small>%</small></div><div v-else class="ticket">{{ voucher.code }}</div></article>
    </div>
  </section>

  <section v-if="destinations.length" id="destinations" class="section muted-section">
    <div class="container">
      <div class="section-heading"><div><p class="eyebrow">Điểm đến được yêu thích</p><h2>Cảm hứng cho chuyến đi tiếp theo</h2></div></div>
      <div class="destination-grid">
        <RouterLink v-for="destination in destinations" :key="destination.name" class="destination-card" :to="`/hotel/search?location=${destination.name}`">
          <img :src="destination.image" :alt="`Khách sạn tại ${destination.name}`" />
          <div><h3>{{ destination.name }}</h3><p>{{ destination.count }} khách sạn</p></div>
        </RouterLink>
      </div>
    </div>
  </section>

  <section v-if="featuredRooms.length" class="section container hotel-picks">
    <div class="section-heading"><div><p class="eyebrow">Được khách StayGo lựa chọn</p><h2>Nơi nghỉ nổi bật tuần này</h2></div></div>
    <div class="pick-grid">
      <RouterLink v-for="(room, index) in featuredRooms" :key="room.id" class="pick-card" :to="`/hotel/${room.hotel.slug}`">
        <img :src="localImage(room.images?.[0]?.url, index + 1)" :alt="room.name" />
        <div class="pick-body"><span v-if="room.hotel.star_rating" class="stars">{{ '★'.repeat(room.hotel.star_rating) }}</span><h3>{{ room.name }}</h3><p>{{ room.hotel.name }}</p><span class="rating"><template v-if="room.hotel.approved_reviews_count"><b>{{ Number(room.hotel.approved_reviews_avg_rating).toFixed(1) }}</b> · {{ room.hotel.approved_reviews_count }} đánh giá</template><template v-else>Chưa có đánh giá</template></span></div>
      </RouterLink>
    </div>
  </section>

  <section id="how-it-works" class="steps-section section">
    <div class="container"><p class="eyebrow centered">Đặt phòng thật dễ dàng</p><h2 class="centered">Ba bước cho một kỳ nghỉ đáng nhớ</h2>
      <div class="steps-grid"><div><span>1</span><h3>Tìm nơi bạn muốn đến</h3><p>Chọn ngày đi, số khách và điểm đến.</p></div><div><span>2</span><h3>Chọn phòng phù hợp</h3><p>So sánh tiện ích và chính sách rõ ràng.</p></div><div><span>3</span><h3>Nhận xác nhận</h3><p>Điền thông tin và hoàn tất đặt phòng.</p></div></div>
    </div>
  </section>
</template>
