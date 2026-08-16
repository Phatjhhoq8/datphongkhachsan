<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import SearchForm from '../components/SearchForm.vue'
import { api, responseList } from '../api'
import { localImage, money } from '../utils'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const hotels = ref([])
const vouchers = ref([])
const destinations = ref([])
const featuredRooms = computed(() => hotels.value.flatMap(hotel =>
  (hotel.room_types ?? []).filter(room => Number(room.available_rooms) > 0).map(room => ({ ...room, hotel })),
).slice(0, 3))
const defaultLocation = computed(() => destinations.value[0]?.name || '')

const claimedCodes = ref([])
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
  const [hotelResult, voucherResult, destinationResult] = await Promise.allSettled([api.get('/hotels'), api.get('/vouchers'), api.get('/destinations')])
  hotels.value = hotelResult.status === 'fulfilled' ? responseList(hotelResult.value) : []
  vouchers.value = voucherResult.status === 'fulfilled' ? responseList(voucherResult.value) : []
  destinations.value = destinationResult.status === 'fulfilled' ? responseList(destinationResult.value) : []

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

  <section v-if="vouchers.length" id="offers" class="section container">
    <div class="section-heading"><div><p class="eyebrow">Ưu đãi riêng cho bạn</p><h2>Đi nhiều hơn, chi ít hơn</h2></div><RouterLink to="/hotel/vouchers">Xem tất cả</RouterLink></div>
    <div class="offer-grid">
      <article v-for="(voucher, index) in vouchers.slice(0, 4)" :key="voucher.id" class="offer-card" :class="[index % 2 ? 'blue' : 'coral', { 'claimed-card': isClaimed(voucher.code) }]">
        <div>
          <span class="offer-label">{{ voucher.code }}</span>
          <h3>Giảm {{ voucherValue(voucher) }}</h3>
          <p>
            <template v-if="voucher.min_order">Cho đơn từ {{ money(voucher.min_order) }}.</template>
            <template v-if="voucher.hotel"> Áp dụng tại {{ voucher.hotel.name }}.</template>
          </p>
          <div class="offer-actions mt-2">
            <button v-if="!isClaimed(voucher.code)" class="btn-claim-home" @click="claimVoucher(voucher.code)">Lưu mã</button>
            <RouterLink v-else :to="{ path: '/hotel/search', query: voucherLocation(voucher) ? { location: voucherLocation(voucher) } : {} }" class="btn-use-home">Dùng ngay</RouterLink>
          </div>
        </div>
        <div v-if="voucher.type === 'percent'" class="offer-art">{{ Number(voucher.value) }}<small>%</small></div>
        <div v-else class="ticket">{{ voucher.code }}</div>
      </article>
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

<style scoped>
/* Custom styled buttons for Homepage vouchers */
.offer-actions {
  display: flex;
  margin-top: 12px;
}

.btn-claim-home {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 20px;
  font-weight: 700;
  font-size: 0.8rem;
  padding: 6px 18px;
  transition: all 0.2s ease;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.06);
}

.coral .btn-claim-home {
  background-color: #80310b;
  color: #ffffff;
}
.coral .btn-claim-home:hover {
  background-color: #5f2407;
  transform: translateY(-1px);
  box-shadow: 0 6px 12px rgba(128, 49, 11, 0.2);
}

.blue .btn-claim-home {
  background-color: #07568d;
  color: #ffffff;
}
.blue .btn-claim-home:hover {
  background-color: #053f67;
  transform: translateY(-1px);
  box-shadow: 0 6px 12px rgba(7, 86, 141, 0.2);
}

.btn-use-home {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 20px;
  font-weight: 700;
  font-size: 0.8rem;
  padding: 5px 16px;
  transition: all 0.2s ease;
  text-decoration: none !important;
  cursor: pointer;
}

.coral .btn-use-home {
  background-color: transparent;
  color: #80310b;
  border: 2px solid #80310b;
}
.coral .btn-use-home:hover {
  background-color: #80310b;
  color: #ffffff;
  transform: translateY(-1px);
}

.blue .btn-use-home {
  background-color: transparent;
  color: #07568d;
  border: 2px solid #07568d;
}
.blue .btn-use-home:hover {
  background-color: #07568d;
  color: #ffffff;
  transform: translateY(-1px);
}

.offer-card {
  transition: transform 0.2s, box-shadow 0.2s;
}
.offer-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
}

/* Gray styling for claimed cards on homepage if wanted */
.claimed-card {
  filter: grayscale(0.2);
}
</style>
