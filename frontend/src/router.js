import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { adminRoute } from './admin/routes'
import './admin/admin.css'
import HomeView from './views/HomeView.vue'
import SearchView from './views/SearchView.vue'
import HotelView from './views/HotelView.vue'
import BookingView from './views/BookingView.vue'
import BookingDetailView from './views/BookingDetailView.vue'

const routes = [
  { path: '/', redirect: '/hotel' },
  { path: '/hotel', name: 'home', component: HomeView },
  { path: '/hotel/vouchers', name: 'vouchers', component: () => import('./views/VouchersView.vue') },
  { path: '/hotel/search', name: 'search', component: SearchView },
  { path: '/hotel/booking', name: 'booking', component: BookingView },
  { path: '/hotel/booking/:code', name: 'booking-detail', component: BookingDetailView },
  { path: '/hotel/:slug', name: 'hotel-detail', component: HotelView },
  { path: '/login', name: 'login', component: () => import('./views/LoginView.vue'), meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: () => import('./views/RegisterView.vue'), meta: { guestOnly: true } },
  { path: '/forgot-password', name: 'forgot-password', component: () => import('./views/ForgotPasswordView.vue'), meta: { guestOnly: true } },
  { path: '/reset-password', name: 'reset-password', component: () => import('./views/ResetPasswordView.vue'), meta: { guestOnly: true } },
  { path: '/auth/oauth/callback', name: 'oauth-callback', component: () => import('./views/OAuthCallbackView.vue') },
  {
    path: '/account',
    component: () => import('./layouts/AccountLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'account-profile', component: () => import('./views/AccountProfileView.vue') },
      { path: 'bookings', name: 'account-bookings', component: () => import('./views/AccountBookingsView.vue') },
      { path: 'wishlist', name: 'account-wishlist', component: () => import('./views/AccountWishlistView.vue') },
    ],
  },
  adminRoute,
  { path: '/:pathMatch(.*)*', redirect: '/hotel' },
]

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: (to, from, savedPosition) => savedPosition || (to.hash ? { el: to.hash, behavior: 'smooth' } : { top: 0 }),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.bootstrap()

  if (to.meta.guestOnly && auth.isAuthenticated) return { path: auth.isStaff ? '/admin' : '/account' }
  if (to.meta.requiresAuth && !auth.isAuthenticated) return { name: 'login', query: { redirect: to.fullPath } }

  const allowedRoles = to.meta.roles
  if (allowedRoles?.length && !auth.roles.some(role => allowedRoles.includes(role))) {
    return { path: auth.isStaff ? '/admin' : '/account' }
  }
})

export default router
