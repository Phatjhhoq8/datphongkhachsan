<script setup>
defineProps({ loading: Boolean, error: String, empty: Boolean, emptyText: { type: String, default: 'Chưa có dữ liệu.' } })
defineEmits(['retry'])
</script>

<template>
  <div v-if="loading || error || empty" class="admin-state">
    <div class="admin-state-inner">
      <span v-if="loading" class="admin-spinner"></span>
      <template v-else-if="error">
        <h3>Không thể tải dữ liệu</h3><p>{{ error }}</p>
        <button class="admin-button secondary" @click="$emit('retry')">Thử lại</button>
      </template>
      <template v-else><h3>Không có kết quả</h3><p>{{ emptyText }}</p></template>
    </div>
  </div>
</template>
