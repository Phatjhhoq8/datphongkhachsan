<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, apiError, responseData } from '../api'
import AdminState from './AdminState.vue'
import CrudModal from './CrudModal.vue'
import { useAuthStore } from '../stores/auth'

const props = defineProps({ title: String, subtitle: String, endpoint: String, itemKey: String, columns: Array, fields: Array, filters: { type: Array, default: () => [] }, createLabel: { type: String, default: 'Thêm mới' }, canWrite: { type: Boolean, default: true }, allowCreate: { type: Boolean, default: true }, canDelete: { type: Boolean, default: true }, updateMethod: { type: String, default: 'put' }, createRoles: { type: Array, default: () => [] } })
const items = ref([]), loading = ref(false), error = ref(''), search = ref(''), filterValues = ref({}), modalOpen = ref(false), editing = ref(null), saving = ref(false), formError = ref('')
const auth = useAuthStore()
const writable = computed(() => props.canWrite)
const canCreate = computed(() => writable.value && props.allowCreate && (!props.createRoles.length || auth.roles.some(role => props.createRoles.includes(role))))
const filtered = computed(() => items.value.filter((item) => {
  const needle = search.value.toLowerCase().trim()
  const matchesSearch = !needle || props.columns.some((column) => String(valueAt(item, column.key) ?? '').toLowerCase().includes(needle))
  return matchesSearch && props.filters.every((filter) => filterValues.value[filter.key] === '' || filterValues.value[filter.key] == null || String(valueAt(item, filter.key)) === String(filterValues.value[filter.key]))
}))

function valueAt(item, path) { return String(path).split('.').reduce((value, key) => value?.[key], item) }
function listFrom(response) {
  const data = responseData(response)
  if (Array.isArray(data)) return data
  return data?.[props.itemKey] ?? data?.items ?? data?.results ?? data?.data ?? []
}
function display(item, column) {
  const value = valueAt(item, column.key)
  if (column.format === 'money') return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
  if (column.format === 'date' && value) return new Intl.DateTimeFormat('vi-VN').format(new Date(value))
  return value ?? '—'
}
async function load() { loading.value = true; error.value = ''; try { items.value = listFrom(await api.get(props.endpoint)) } catch (err) { error.value = apiError(err) } finally { loading.value = false } }
function openCreate() { editing.value = null; formError.value = ''; modalOpen.value = true }
function openEdit(item) { editing.value = item; formError.value = ''; modalOpen.value = true }
async function save(payload) {
  saving.value = true; formError.value = ''
  try { const id = editing.value?.id; if (id) await api.request({ method: props.updateMethod, url: `${props.endpoint}/${id}`, data: payload }); else await api.post(props.endpoint, payload); modalOpen.value = false; await load() }
  catch (err) { formError.value = apiError(err, 'Không thể lưu dữ liệu.') } finally { saving.value = false }
}
async function remove(item) {
  if (!confirm(`Xóa "${item.name ?? item.code ?? item.id}"?`)) return
  try { await api.delete(`${props.endpoint}/${item.id}`); await load() } catch (err) { error.value = apiError(err, 'Không thể xóa dữ liệu.') }
}
onMounted(load)
</script>

<template>
  <section>
    <header class="admin-page-head"><div><h1>{{ title }}</h1><p>{{ subtitle }}</p></div><button v-if="canCreate" class="admin-button" @click="openCreate">+ {{ createLabel }}</button></header>
    <p v-if="error && items.length" class="admin-alert">{{ error }}</p>
    <div class="admin-card">
      <div class="admin-toolbar"><input v-model="search" class="admin-input admin-search" type="search" placeholder="Tìm kiếm..." /><select v-for="filter in filters" :key="filter.key" v-model="filterValues[filter.key]" class="admin-select"><option value="">{{ filter.label }}: Tất cả</option><option v-for="option in filter.options" :key="option.value ?? option" :value="option.value ?? option">{{ option.label ?? option }}</option></select><button class="admin-button secondary" @click="load">Làm mới</button></div>
      <AdminState :loading="loading" :error="error && !items.length ? error : ''" :empty="!loading && !error && !filtered.length" empty-text="Không tìm thấy bản ghi phù hợp." @retry="load" />
      <div v-if="!loading && filtered.length" class="admin-table-wrap"><table class="admin-table"><thead><tr><th v-for="column in columns" :key="column.key">{{ column.label }}</th><th v-if="writable">Thao tác</th></tr></thead><tbody><tr v-for="item in filtered" :key="item.id"><td v-for="column in columns" :key="column.key"><span v-if="column.format === 'status'" class="admin-badge" :class="String(valueAt(item,column.key)).toLowerCase()">{{ display(item,column) }}</span><template v-else>{{ display(item,column) }}</template></td><td v-if="writable"><div class="admin-actions"><button class="admin-button secondary small" @click="openEdit(item)">Sửa</button><button v-if="canDelete" class="admin-button danger small" @click="remove(item)">Xóa</button></div></td></tr></tbody></table></div>
    </div>
    <CrudModal :open="modalOpen" :title="editing ? `Cập nhật ${title.toLowerCase()}` : createLabel" :fields="fields" :value="editing || {}" :saving="saving" :error="formError" @close="modalOpen=false" @save="save" />
  </section>
</template>
