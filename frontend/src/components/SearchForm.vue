<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { addDays, today } from '../utils'
import { trackActivity } from '../analytics'
import { parseVoiceSearch } from '../voiceSearchParser'
import { api, responseList } from '../api'

const props = defineProps({ initial: { type: Object, default: () => ({}) }, compact: Boolean })
const router = useRouter()
function defaultArrivalTime() {
  const now = new Date()
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes}`
}

const form = reactive({
  location: props.initial.location || '',
  checkin: props.initial.checkin || addDays(today(), 1),
  checkout: props.initial.checkout || addDays(today(), 2),
  adults: Number(props.initial.adults) || 2,
  children: Number(props.initial.children) || 0,
  rooms: Number(props.initial.rooms) || 1,
  keyword: props.initial.keyword || '',
  arrival_time: props.initial.arrival_time || defaultArrivalTime(),
  checkout_time: props.initial.checkout_time || '12:00',
})

const destinations = ref([])
const hotels = ref([])
const showDropdown = ref(false)
const showKeywordDropdown = ref(false)

const roomTypes = ['Deluxe', 'Suite', 'Standard', 'Family', 'Superior', 'Penthouse', 'Villa']

onMounted(async () => {
  try {
    const [destRes, hotelsRes] = await Promise.all([
      api.get('/destinations'),
      api.get('/hotels')
    ])
    destinations.value = responseList(destRes)
    hotels.value = responseList(hotelsRes)
  } catch (error) {
    console.error('Lỗi lấy danh sách gợi ý:', error)
  }
})

const filteredSuggestions = computed(() => {
  const query = String(form.location).trim().toLowerCase()
  if (!query) {
    // Nếu rỗng, hiển thị tất cả các thành phố và khách sạn có sẵn
    const destItems = destinations.value.map(d => ({ type: 'destination', name: d.name, sub: `${d.count} khách sạn` }))
    const hotelItems = hotels.value.map(h => ({ type: 'hotel', name: h.name, sub: h.city }))
    return [...destItems, ...hotelItems]
  }

  const destItems = destinations.value
    .filter(d => String(d.name).toLowerCase().includes(query))
    .map(d => ({ type: 'destination', name: d.name, sub: `${d.count} khách sạn` }))

  const hotelItems = hotels.value
    .filter(h => String(h.name).toLowerCase().includes(query) || String(h.city).toLowerCase().includes(query))
    .map(h => ({ type: 'hotel', name: h.name, sub: h.city }))

  return [...destItems, ...hotelItems]
})

const filteredRoomTypes = computed(() => {
  const query = String(form.keyword).trim().toLowerCase()
  if (!query) return roomTypes
  return roomTypes.filter(r => r.toLowerCase().includes(query))
})

function selectSuggestion(name) {
  form.location = name
  showDropdown.value = false
}

function handleBlur() {
  setTimeout(() => {
    showDropdown.value = false
  }, 200)
}

function selectRoomType(room) {
  form.keyword = room
  showKeywordDropdown.value = false
}

function handleKeywordBlur() {
  setTimeout(() => {
    showKeywordDropdown.value = false
  }, 200)
}

const voiceStatus = ref('')
const listening = ref(false)
const SpeechRecognition = globalThis.SpeechRecognition || globalThis.webkitSpeechRecognition
const voiceSupported = computed(() => Boolean(SpeechRecognition))
let recognition

watch(() => props.initial, (value) => Object.assign(form, value), { deep: true })

const formError = ref('')

function search() {
  formError.value = ''
  if (form.checkout <= form.checkin) {
    formError.value = 'Ngày trả phòng phải sau ngày nhận phòng.'
    return
  }
  if (form.checkout_time) {
    const [hours, minutes] = form.checkout_time.split(':').map(Number)
    const checkoutMinutes = hours * 60 + minutes
    if (checkoutMinutes > 720) {
      formError.value = 'Giờ trả phòng đăng ký muộn nhất là 12:00 để đảm bảo thời gian dọn dẹp chuẩn bị phòng cho khách tiếp theo.'
      return
    }
  }
  trackActivity('search', { metadata: { location: form.location, keyword: form.keyword, adults: form.adults } })
  router.push({ path: '/hotel/search', query: { ...form } })
}

function startVoiceSearch() {
  if (!SpeechRecognition) {
    voiceStatus.value = 'Trình duyệt chưa hỗ trợ tìm kiếm bằng giọng nói.'
    return
  }

  recognition = new SpeechRecognition()
  recognition.lang = 'vi-VN'
  recognition.interimResults = false
  recognition.maxAlternatives = 1
  recognition.onstart = () => { listening.value = true; voiceStatus.value = 'Đang nghe...' }
  recognition.onerror = () => { listening.value = false; voiceStatus.value = 'Không nhận được giọng nói. Vui lòng thử lại.' }
  recognition.onend = () => { listening.value = false }
  recognition.onresult = (event) => {
    const transcript = String(event.results?.[0]?.[0]?.transcript || '').slice(0, 300)
    if (!transcript) return
    Object.assign(form, parseVoiceSearch(transcript))
    voiceStatus.value = `Đã nhận: ${transcript}`
    trackActivity('voice_search', { metadata: { transcript, source: 'web_speech' } })
    search()
  }
  recognition.start()
}

onBeforeUnmount(() => recognition?.abort())
</script>

<template>
  <form class="search-form" :class="{ compact }" aria-label="Tìm khách sạn" @submit.prevent="search">
    <label class="field location-field">
      <span>Thành phố, khách sạn</span>
      <div class="autocomplete-wrapper">
        <input 
          v-model.trim="form.location" 
          placeholder="Tất cả điểm đến" 
          autocomplete="off"
          @focus="showDropdown = true"
          @blur="handleBlur"
        />
        <transition name="fade">
          <ul v-if="showDropdown && filteredSuggestions.length" class="destinations-dropdown">
            <li 
              v-for="item in filteredSuggestions" 
              :key="item.name"
              class="dropdown-item"
              @mousedown="selectSuggestion(item.name)"
            >
              <div class="dest-info">
                <span class="dest-name">{{ item.name }}</span>
                <small class="dest-count">{{ item.type === 'destination' ? item.sub : `Khách sạn tại ${item.sub}` }}</small>
              </div>
            </li>
          </ul>
        </transition>
      </div>
    </label>
    
    <label class="field keyword-field">
      <span>Loại phòng</span>
      <div class="autocomplete-wrapper">
        <input 
          v-model.trim="form.keyword" 
          placeholder="Deluxe, Suite..." 
          autocomplete="off"
          @focus="showKeywordDropdown = true"
          @blur="handleKeywordBlur"
        />
        <transition name="fade">
          <ul v-if="showKeywordDropdown && filteredRoomTypes.length" class="destinations-dropdown">
            <li 
              v-for="room in filteredRoomTypes" 
              :key="room"
              class="dropdown-item"
              @mousedown="selectRoomType(room)"
            >
              <div class="dest-info">
                <span class="dest-name">{{ room }}</span>
              </div>
            </li>
          </ul>
        </transition>
      </div>
    </label>

    <label class="field"><span>Nhận phòng</span><input v-model="form.checkin" type="date" :min="today()" required /></label>
    <label class="field">
      <span>Giờ nhận</span>
      <input v-model="form.arrival_time" type="time" />
    </label>
    <label class="field"><span>Trả phòng</span><input v-model="form.checkout" type="date" :min="addDays(form.checkin, 1)" required /></label>
    <label class="field">
      <span>Giờ trả</span>
      <input v-model="form.checkout_time" type="time" />
    </label>
    <label class="field guest-field"><span>Khách</span><span class="guest-input"><input v-model.number="form.adults" type="number" min="1" aria-label="Số người lớn" /> khách</span></label>
    <button class="voice-button" type="button" :aria-pressed="listening" :disabled="listening" @click="startVoiceSearch">{{ listening ? 'Đang nghe...' : 'Tìm bằng giọng nói' }}</button>
    <button class="primary search-button" type="submit">Tìm khách sạn</button>
    <div v-if="formError" class="form-validation-error" role="alert">
      ⚠️ {{ formError }}
    </div>
    <p v-if="voiceStatus" class="voice-status" role="status">{{ voiceStatus }}</p>
    <p v-else-if="!voiceSupported" class="voice-status">Trình duyệt chưa hỗ trợ tìm kiếm bằng giọng nói.</p>
  </form>
</template>

<style scoped>
.autocomplete-wrapper {
  position: relative;
  width: 100%;
}

.destinations-dropdown {
  position: absolute;
  top: calc(100% + 12px);
  left: 0;
  width: 100%;
  min-width: 260px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(19, 36, 58, 0.15);
  border: 1px solid #edf1f6;
  list-style: none;
  margin: 0;
  padding: 8px 0;
  z-index: 999;
  max-height: 280px;
  overflow-y: auto;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.dropdown-item:hover {
  background: #f4f7fa;
}

.dest-info {
  display: flex;
  flex-direction: column;
  text-align: left;
}

.dest-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: #13243a;
  line-height: 1.2;
}

.dest-count {
  font-size: 0.75rem;
  color: #637083;
  margin-top: 2px;
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
.form-validation-error {
  grid-column: 1 / -1;
  background: #fef2f2;
  border: 1px solid #fee2e2;
  border-radius: 8px;
  padding: 10px 14px;
  color: #991b1b;
  font-size: 13px;
  font-weight: 600;
  margin-top: 10px;
  text-align: left;
  width: 100%;
}
</style>
