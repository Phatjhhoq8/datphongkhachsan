<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { api, apiError, responseData } from '../../api'
import AdminState from '../../admin/AdminState.vue'

const conversations = ref([]), loading = ref(true), error = ref(''), status = ref('open')
const filtered = computed(() => status.value ? conversations.value.filter(item => item.status === status.value) : conversations.value)
let pollTimer

async function load(silent = false) {
  if (!silent) loading.value = true
  try { conversations.value = responseData(await api.get('/admin/chat/conversations')) ?? []; error.value = '' }
  catch (requestError) { error.value = apiError(requestError) }
  finally { loading.value = false }
}

onMounted(() => { load(); pollTimer = setInterval(() => load(true), 10000) })
onBeforeUnmount(() => clearInterval(pollTimer))
</script>

<template><section><header class="admin-page-head"><div><h1>Chat hỗ trợ</h1><p>Hội thoại của khách theo phạm vi khách sạn</p></div><button class="admin-button secondary" @click="load()">Làm mới</button></header><div class="admin-card"><div class="admin-toolbar"><select v-model="status" class="admin-select"><option value="open">Đang mở</option><option value="closed">Đã đóng</option><option value="">Tất cả</option></select></div><AdminState :loading="loading" :error="error" :empty="!loading&&!filtered.length" empty-text="Chưa có hội thoại." @retry="load"/><div v-if="!loading&&filtered.length" class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Khách</th><th>Khách sạn</th><th>Cập nhật</th><th>Trạng thái</th><th></th></tr></thead><tbody><tr v-for="item in filtered" :key="item.id"><td>{{item.guest_name||'Khách'}}</td><td>{{item.hotel_id}}</td><td>{{new Date(item.last_message_at||item.created_at).toLocaleString('vi-VN')}}</td><td><span class="admin-badge" :class="item.status">{{item.status === 'open' ? 'Đang mở' : 'Đã đóng'}}</span></td><td><router-link class="admin-button secondary small" :to="`/admin/chat/${item.id}`">Mở</router-link></td></tr></tbody></table></div></div></section></template>
