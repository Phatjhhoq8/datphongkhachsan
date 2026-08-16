<script setup>
import ResourcePage from '../../admin/ResourcePage.vue'

const columns = [{ key: 'name', label: 'Tiện nghi' }, { key: 'slug', label: 'Slug' }]
const fields = [{ key: 'name', label: 'Tên tiện nghi', required: true }, { key: 'slug', label: 'Slug', required: true }]
</script>

<template>
  <ResourcePage title="Tiện nghi" subtitle="Danh mục tiện nghi dùng cho các loại phòng" endpoint="/admin/amenities" item-key="amenities" :columns="columns" :fields="fields" create-label="Thêm tiện nghi" />
</template>
