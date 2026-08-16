import AdminLayout from './AdminLayout.vue'

export const adminChildren = [
  { path: '', redirect: '/admin/analytics' },
  { path: 'hotels', name: 'admin-hotels', component: () => import('../views/admin/HotelsView.vue'), meta: { title: 'Khách sạn', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'room-types', name: 'admin-room-types', component: () => import('../views/admin/RoomTypesView.vue'), meta: { title: 'Loại phòng', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'room-images', name: 'admin-room-images', component: () => import('../views/admin/RoomImagesView.vue'), meta: { title: 'Ảnh loại phòng', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'amenities', name: 'admin-amenities', component: () => import('../views/admin/AmenitiesView.vue'), meta: { title: 'Tiện nghi', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'rooms', name: 'admin-rooms', component: () => import('../views/admin/RoomsView.vue'), meta: { title: 'Phòng', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'room-map', name: 'admin-room-map', component: () => import('../views/admin/RoomMapView.vue'), meta: { title: 'Sơ đồ phòng', roles: ['super_admin', 'hotel_manager', 'receptionist'] } },
  { path: 'bookings', name: 'admin-bookings', component: () => import('../views/admin/BookingsView.vue'), meta: { title: 'Đặt phòng', roles: ['super_admin', 'hotel_manager', 'receptionist'] } },
  { path: 'bookings/:id', name: 'admin-booking-detail', component: () => import('../views/admin/BookingAdminDetailView.vue'), meta: { title: 'Chi tiết đặt phòng', roles: ['super_admin', 'hotel_manager', 'receptionist'] } },
  { path: 'chat', name: 'admin-chat', component: () => import('../views/admin/ChatInboxView.vue'), meta: { title: 'Chat hỗ trợ', roles: ['super_admin', 'hotel_manager', 'receptionist'] } },
  { path: 'chat/:id', name: 'admin-chat-view', component: () => import('../views/admin/ChatView.vue'), meta: { title: 'Hội thoại', roles: ['super_admin', 'hotel_manager', 'receptionist'] } },
  { path: 'services', name: 'admin-services', component: () => import('../views/admin/ServicesView.vue'), meta: { title: 'Dịch vụ', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'vouchers', name: 'admin-vouchers', component: () => import('../views/admin/VouchersView.vue'), meta: { title: 'Voucher', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'users', name: 'admin-users', component: () => import('../views/admin/UsersView.vue'), meta: { title: 'Người dùng', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'reviews', name: 'admin-reviews', component: () => import('../views/admin/ReviewsView.vue'), meta: { title: 'Đánh giá', roles: ['super_admin', 'hotel_manager'] } },
  { path: 'analytics', name: 'admin-analytics', component: () => import('../views/admin/AnalyticsView.vue'), meta: { title: 'Phân tích', roles: ['super_admin', 'hotel_manager', 'accountant'] } },
  { path: 'payments', name: 'admin-payments', component: () => import('../views/admin/PaymentReportView.vue'), meta: { title: 'Giao dịch', roles: ['super_admin', 'hotel_manager', 'accountant'] } },
]

export const adminRoute = {
  path: '/admin',
  component: AdminLayout,
  meta: {
    requiresAuth: true,
    standalone: true,
    roles: ['super_admin', 'hotel_manager', 'receptionist', 'accountant', 'admin', 'staff'],
  },
  children: adminChildren,
}
export default adminChildren
