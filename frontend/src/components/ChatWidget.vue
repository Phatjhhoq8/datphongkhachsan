<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { api, apiError, responseData, responseList } from '../api'
import { createConversationSocket } from '../realtime'
import { useAuthStore } from '../stores/auth'

const STORAGE_KEY = 'staygo_chat_conversation'
const authStore = useAuthStore()
const isAuthenticated = computed(() => authStore.isAuthenticated)
const currentUser = computed(() => authStore.user)

const open = ref(false), loading = ref(false), sending = ref(false), error = ref('')
const hotels = ref([]), hotelId = ref(''), hotelSearch = ref(''), guestName = ref(''), text = ref(''), messages = ref([])
const saved = ref(loadSaved())
const showDropdown = ref(false)
const hotelInput = ref(null)
let socket = null
let pollTimer = null

const hasConversation = computed(() => Boolean(saved.value?.id && saved.value?.token))

const filteredHotels = computed(() => {
  const query = String(hotelSearch.value).trim().toLowerCase()
  if (!query) return hotels.value
  return hotels.value.filter(h => 
    String(h.name).toLowerCase().includes(query) || 
    String(h.city).toLowerCase().includes(query)
  )
})

function selectHotel(hotel) {
  hotelId.value = hotel.id
  hotelSearch.value = hotel.name
  hotelInput.value?.setCustomValidity('')
  showDropdown.value = false
}

function handleBlur() {
  setTimeout(() => {
    showDropdown.value = false
  }, 200)
}

watch(hotelSearch, (newVal) => {
  hotelInput.value?.setCustomValidity('')
  const matched = hotels.value.find(h => String(h.name).toLowerCase() === String(newVal).toLowerCase())
  if (matched) {
    hotelId.value = matched.id
  } else {
    hotelId.value = ''
  }
})

function loadSaved() {
  try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null') } catch { return null }
}

function messageList(response) {
  const data = responseData(response)
  return Array.isArray(data) ? data : data?.messages ?? []
}

function mergeMessage(message) {
  if (!message?.id && message?.message_id) message = { ...message, id: message.message_id }
  if (!message?.id || messages.value.some(item => item.id === message.id)) return
  messages.value.push(message)
}

async function loadHistory(silent = false) {
  if (!hasConversation.value) return
  if (!silent) loading.value = true
  try {
    const response = await api.get(`/chat/conversations/${saved.value.id}/messages`, {
      headers: { 'X-Conversation-Token': saved.value.token },
    })
    messages.value = messageList(response)
    error.value = ''
  } catch (requestError) {
    if (requestError.response?.status === 401 || requestError.response?.status === 403 || requestError.response?.status === 404) resetConversation()
    error.value = apiError(requestError, 'Không thể tải hội thoại.')
  } finally { loading.value = false }
}

function connect() {
  socket?.disconnect()
  socket = createConversationSocket({
    conversationId: saved.value.id,
    conversationToken: saved.value.token,
    onJoin: () => startPolling(),
  })
  socket.on('chat.message', mergeMessage)
  socket.on('disconnect', startPolling)
  socket.on('connect_error', startPolling)
  startPolling()
}

function startPolling() {
  if (!pollTimer) pollTimer = setInterval(() => loadHistory(true), 3000)
}

function stopPolling() {
  if (pollTimer) clearInterval(pollTimer)
  pollTimer = null
}

async function fetchHotelsIfNeeded() {
  if (hotels.value.length) return
  try {
    hotels.value = responseList(await api.get('/hotels'))
  } catch (requestError) {
    error.value = apiError(requestError)
  }
}

async function show() {
  open.value = !open.value
  if (!open.value) return
  if (hasConversation.value) {
    await loadHistory()
    connect()
  } else {
    await fetchHotelsIfNeeded()
  }
}

async function createConversation() {
  if (!hotelId.value) {
    hotelInput.value?.setCustomValidity('Vui lòng chọn một khách sạn hợp lệ từ danh sách gợi ý.')
    hotelInput.value?.reportValidity()
    return
  } else {
    hotelInput.value?.setCustomValidity('')
  }
  loading.value = true
  error.value = ''
  try {
    const nameToSubmit = isAuthenticated.value 
      ? (currentUser.value?.name || currentUser.value?.full_name || currentUser.value?.email)
      : guestName.value
    const data = responseData(await api.post('/chat/conversations', { hotel_id: hotelId.value, guest_name: nameToSubmit || undefined }))
    saved.value = { id: data.conversation.id, token: data.access_token, hotelId: data.conversation.hotel_id }
    localStorage.setItem(STORAGE_KEY, JSON.stringify(saved.value))
    messages.value = []
    connect()
  } catch (requestError) { error.value = apiError(requestError, 'Không thể bắt đầu trò chuyện.') } finally { loading.value = false }
}

async function send() {
  const value = text.value.trim()
  if (!value || sending.value) return
  sending.value = true
  try {
    const message = responseData(await api.post(`/chat/conversations/${saved.value.id}/messages`, { text: value }, {
      headers: { 'X-Conversation-Token': saved.value.token },
    }))
    mergeMessage(message)
    text.value = ''
  } catch (requestError) { error.value = apiError(requestError, 'Không thể gửi tin nhắn.') } finally { sending.value = false }
}

async function resetConversation() {
  socket?.disconnect()
  stopPolling()
  localStorage.removeItem(STORAGE_KEY)
  saved.value = null
  messages.value = []
  error.value = ''
  hotelId.value = ''
  hotelSearch.value = ''
  await fetchHotelsIfNeeded()
}

onBeforeUnmount(() => { socket?.disconnect(); stopPolling() })
</script>

<template>
  <aside class="chat-widget">
    <section v-if="open" class="chat-panel" aria-label="Hỗ trợ trực tuyến">
      <header>
        <div><strong>StayGo hỗ trợ</strong><small>Trao đổi trực tiếp với khách sạn</small></div>
        <div class="header-buttons">
          <button v-if="hasConversation" type="button" class="btn-new-chat" title="Tạo cuộc trò chuyện mới" @click="resetConversation">🔄 Trò chuyện mới</button>
          <button type="button" class="chat-close-btn" aria-label="Đóng chat" @click="open=false">×</button>
        </div>
      </header>
      <form v-if="!hasConversation" class="chat-start" @submit.prevent="createConversation">
        <label class="chat-hotel-field">
          <span>Khách sạn</span>
          <div class="autocomplete-wrapper">
            <input 
              ref="hotelInput"
              v-model="hotelSearch" 
              placeholder="Chọn hoặc nhập tên khách sạn..." 
              autocomplete="off"
              required
              @focus="showDropdown = true"
              @blur="handleBlur"
            />
            <transition name="fade">
              <ul v-if="showDropdown && filteredHotels.length" class="hotels-dropdown">
                <li 
                  v-for="hotel in filteredHotels" 
                  :key="hotel.id"
                  class="dropdown-item"
                  @mousedown="selectHotel(hotel)"
                >
                  <div class="hotel-info-item">
                    <span class="hotel-name-item">{{ hotel.name }}</span>
                    <small class="hotel-city-item">{{ hotel.city }}</small>
                  </div>
                </li>
              </ul>
            </transition>
          </div>
        </label>
        <label v-if="!isAuthenticated">Tên của bạn<input v-model.trim="guestName" maxlength="100" placeholder="Nhập tên của bạn để tiếp tục..." required /></label>
        <button type="submit" :disabled="loading">Bắt đầu trò chuyện</button>
      </form>
      <template v-else>
        <div class="chat-messages" aria-live="polite">
          <p v-if="loading">Đang tải...</p>
          <p v-else-if="!messages.length" class="chat-empty">Hãy gửi câu hỏi đầu tiên.</p>
          <div v-for="message in messages" :key="message.id" class="chat-bubble" :class="message.sender_type">{{ message.text }}</div>
        </div>
        <form class="chat-compose" @submit.prevent="send"><input v-model="text" maxlength="2000" placeholder="Nhập tin nhắn..." aria-label="Tin nhắn" /><button :disabled="sending || !text.trim()">Gửi</button></form>
      </template>
      <p v-if="error" class="chat-error">
        {{ error }}
        <button v-if="hasConversation" type="button" class="btn-error-reset" @click="resetConversation">Quay lại</button>
      </p>
    </section>
    <button class="chat-toggle" type="button" :aria-expanded="open" @click="show">{{ open ? 'Đóng' : '💬 Hỗ trợ' }}</button>
  </aside>
</template>

<style scoped>
.chat-widget{position:fixed;right:20px;bottom:20px;z-index:80;font-family:inherit}.chat-toggle{float:right;border:0;border-radius:999px;background:#123c69;color:#fff;padding:13px 20px;font-weight:700;box-shadow:0 8px 25px #123c6940}.chat-panel{width:min(360px,calc(100vw - 28px));height:480px;margin-bottom:10px;display:flex;flex-direction:column;background:#fff;border:1px solid #dbe4ec;border-radius:16px;overflow:hidden;box-shadow:0 18px 55px #18344f33}.chat-panel header{display:flex;align-items:center;justify-content:space-between;padding:15px 17px;background:#123c69;color:#fff}.chat-panel header small{display:block;margin-top:3px;color:#dce9f4}.chat-close-btn{border:0;background:transparent;color:#fff;font-size:24px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}.chat-start{display:grid;gap:14px;padding:22px}.chat-start label{display:grid;gap:6px;font-size:13px;font-weight:700}.chat-start input,.chat-start select,.chat-compose input{min-width:0;border:1px solid #cbd8e3;border-radius:8px;padding:10px;background:#fff}.chat-start button,.chat-compose button{border:0;border-radius:8px;background:#e58b2a;color:#fff;padding:10px 14px;font-weight:700}.chat-messages{flex:1;overflow:auto;padding:15px;background:#f6f8fa}.chat-bubble{width:max-content;max-width:82%;margin:8px 0;padding:9px 12px;border-radius:12px;background:#fff;border:1px solid #dde5eb;white-space:pre-wrap}.chat-bubble.guest{margin-left:auto;background:#123c69;color:#fff;border-color:#123c69}.chat-compose{display:flex;gap:8px;padding:12px;border-top:1px solid #e4e9ed}.chat-compose input{flex:1}.chat-error{margin:0;padding:7px 12px;color:#a12828;background:#fff0f0;font-size:12px;display:flex;justify-content:space-between;align-items:center}.chat-empty{text-align:center;color:#6b7b88;margin-top:80px}@media(max-width:520px){.chat-widget{right:14px;bottom:14px}.chat-panel{height:min(520px,calc(100vh - 90px))}}
.header-buttons{display:flex;align-items:center;gap:8px}
.btn-new-chat{border:1px solid rgba(255,255,255,0.3);border-radius:20px;background:rgba(255,255,255,0.1);color:#fff;font-size:11px;font-weight:600;padding:5px 12px;cursor:pointer;transition:all 0.2s ease;display:inline-flex;align-items:center;gap:4px;line-height:1.2}
.btn-new-chat:hover{background:rgba(255,255,255,0.25);border-color:rgba(255,255,255,0.6)}
.btn-error-reset{background:#cbd5e1;color:#1e293b;border:0;border-radius:4px;padding:3px 8px;font-size:10px;font-weight:700;cursor:pointer;transition:all 0.2s}
.btn-error-reset:hover{background:#94a3b8}
.chat-start button:disabled,.chat-compose button:disabled{opacity:0.65;cursor:not-allowed;background-color:#cbd5e1!important;color:#64748b!important;box-shadow:none!important}

.autocomplete-wrapper {
  position: relative;
  width: 100%;
}
.chat-hotel-field {
  display: grid;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  position: relative;
}
.chat-hotel-field input {
  width: 100%;
  min-width: 0;
  border: 1px solid #cbd8e3;
  border-radius: 8px;
  padding: 10px;
  background: #fff;
  font-weight: 600;
  font-family: inherit;
}
.hotels-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  width: 100%;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 6px 20px rgba(24, 52, 79, 0.15);
  border: 1px solid #cbd8e3;
  list-style: none;
  margin: 0;
  padding: 6px 0;
  z-index: 99;
  max-height: 180px;
  overflow-y: auto;
}
.dropdown-item {
  padding: 8px 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.dropdown-item:hover {
  background: #f4f7fa;
}
.hotel-info-item {
  display: flex;
  flex-direction: column;
  text-align: left;
}
.hotel-name-item {
  font-size: 12px;
  font-weight: 600;
  color: #123c69;
  line-height: 1.2;
}
.hotel-city-item {
  font-size: 10px;
  color: #6b7b88;
  margin-top: 2px;
}
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
