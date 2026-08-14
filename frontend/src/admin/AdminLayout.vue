<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import './admin.css'

const route = useRoute(), menuOpen = ref(false)
const auth = useAuthStore()
const nav = [
  ['Tổng quan','/admin','▦'],['Khách sạn','/admin/hotels','H'],['Loại phòng','/admin/room-types','▤'],['Phòng','/admin/rooms','▣'],['Sơ đồ phòng','/admin/room-map','⌂'],['Đặt phòng','/admin/bookings','B'],['Dịch vụ','/admin/services','S'],['Voucher','/admin/vouchers','%'],['Người dùng','/admin/users','U'],['Đánh giá','/admin/reviews','★'],['Phân tích','/admin/analytics','↗']
]
const user = computed(() => auth.user ?? {}), pageTitle = computed(() => route.meta?.title ?? 'Quản trị')
</script>

<template>
  <div class="admin-shell">
    <div class="admin-overlay" :class="{ open:menuOpen }" @click="menuOpen=false"></div>
    <aside class="admin-sidebar" :class="{ open:menuOpen }"><router-link class="admin-brand" to="/admin" @click="menuOpen=false"><span class="admin-brand-mark">S</span><span>StayGo Admin</span></router-link><nav class="admin-nav"><router-link v-for="item in nav" :key="item[1]" :to="item[1]" :exact-active-class="item[1] === '/admin' ? 'router-link-active' : ''" @click="menuOpen=false"><span class="admin-nav-icon">{{ item[2] }}</span>{{ item[0] }}</router-link></nav><div class="admin-sidebar-foot">Hệ thống quản lý lưu trú<br />Phiên bản quản trị 1.0</div></aside>
    <main class="admin-main"><header class="admin-topbar"><div class="admin-topbar-left"><button class="admin-menu-button" aria-label="Mở menu" @click="menuOpen=!menuOpen">☰</button><h2 class="admin-page-title">{{ pageTitle }}</h2></div><div class="admin-user"><div><strong>{{ user.name ?? 'Quản trị viên' }}</strong><br /><span>{{ user.role ?? 'admin' }}</span></div><span class="admin-avatar">{{ (user.name ?? 'A').charAt(0).toUpperCase() }}</span></div></header><div class="admin-content"><router-view /></div></main>
  </div>
</template>
