<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, apiError, responseData } from '../../api'
import { createConversationSocket } from '../../realtime'
import AdminState from '../../admin/AdminState.vue'

const route = useRoute(), conversation = ref(null), messages = ref([]), text = ref(''), loading = ref(true), sending = ref(false), error = ref(''), messageBox = ref(null)
let socket, pollTimer

function merge(message) {
  if (!message?.id && message?.message_id) message = { ...message, id: message.message_id }
  if (!message?.id || messages.value.some(item => item.id === message.id)) return
  messages.value.push(message)
  nextTick(() => { if (messageBox.value) messageBox.value.scrollTop = messageBox.value.scrollHeight })
}

async function load(silent = false) {
  if (!silent) loading.value = true
  try { const data = responseData(await api.get(`/admin/chat/conversations/${route.params.id}`)); conversation.value = data.conversation; messages.value = data.messages ?? []; error.value = '' }
  catch (requestError) { error.value = apiError(requestError) }
  finally { loading.value = false }
}

async function send() {
  const value = text.value.trim(); if (!value) return
  sending.value = true
  try { merge(responseData(await api.post(`/admin/chat/conversations/${route.params.id}/messages`, { text: value }))); text.value = '' }
  catch (requestError) { error.value = apiError(requestError, 'Không thể gửi tin nhắn.') }
  finally { sending.value = false }
}

async function closeConversation() {
  try { conversation.value = responseData(await api.post(`/admin/chat/conversations/${route.params.id}/close`)) }
  catch (requestError) { error.value = apiError(requestError, 'Không thể đóng hội thoại.') }
}

onMounted(async () => {
  await load()
  socket = createConversationSocket({
    conversationId: String(route.params.id),
    onJoin: () => { if (!pollTimer) pollTimer = setInterval(() => load(true), 3000) },
  })
  socket.on('chat.message', merge)
  socket.on('disconnect', () => { if (!pollTimer) pollTimer = setInterval(() => load(true), 3000) })
  socket.on('connect_error', () => { if (!pollTimer) pollTimer = setInterval(() => load(true), 3000) })
  pollTimer = setInterval(() => load(true), 3000)
})
onBeforeUnmount(() => { socket?.disconnect(); clearInterval(pollTimer) })
</script>

<template><section><header class="admin-page-head"><div><router-link to="/admin/chat">← Hộp thư chat</router-link><h1>{{conversation?.guest_name||'Hội thoại khách'}}</h1><p>{{conversation?.hotel_id}}</p></div><button v-if="conversation?.status==='open'" class="admin-button secondary" @click="closeConversation">Đóng hội thoại</button></header><AdminState :loading="loading" :error="error&&!conversation?error:''" :empty="!loading&&!conversation" @retry="load"/><div v-if="conversation" class="admin-card chat-admin"><div ref="messageBox" class="chat-admin-messages"><p v-if="!messages.length">Chưa có tin nhắn.</p><div v-for="message in messages" :key="message.id" class="chat-admin-bubble" :class="message.sender_type"><small>{{message.sender_type==='staff'?'Nhân viên':(conversation?.guest_name||'Khách')}}</small>{{message.text}}</div></div><form class="chat-admin-compose" @submit.prevent="send"><input v-model="text" class="admin-input" maxlength="2000" placeholder="Nhập phản hồi..." :disabled="conversation.status==='closed'"/><button class="admin-button" :disabled="sending||!text.trim()||conversation.status==='closed'">Gửi</button></form></div></section></template>

<style scoped>
.chat-admin{max-width:860px}.chat-admin-messages{height:520px;overflow:auto;padding:20px;background:#f5f7f9}.chat-admin-bubble{display:grid;gap:4px;width:max-content;max-width:75%;margin:10px 0;padding:10px 13px;border-radius:10px;background:#fff;border:1px solid #dce3e8;white-space:pre-wrap}.chat-admin-bubble.staff{margin-left:auto;background:#123c69;color:#fff;border-color:#123c69}.chat-admin-bubble small{font-size:10px;opacity:.7}.chat-admin-compose{display:flex;gap:10px;padding:15px}.chat-admin-compose input{flex:1}
</style>
