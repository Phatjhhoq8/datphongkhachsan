<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, apiError, responseData } from '../api'
import AdminState from './AdminState.vue'
import CrudModal from './CrudModal.vue'
import { useAuthStore } from '../stores/auth'

const props = defineProps({ title: String, subtitle: String, endpoint: String, itemKey: String, columns: Array, fields: Array, filters: { type: Array, default: () => [] }, createLabel: { type: String, default: 'Thêm mới' }, canWrite: { type: Boolean, default: true }, allowCreate: { type: Boolean, default: true }, canDelete: { type: Boolean, default: true }, updateMethod: { type: String, default: 'put' }, createRoles: { type: Array, default: () => [] } })
const items = ref([]), loading = ref(false), error = ref(''), search = ref(''), searchTemp = ref(''), filterValues = ref({}), filterValuesTemp = ref({}), modalOpen = ref(false), editing = ref(null), saving = ref(false), formError = ref(''), fieldOptions = ref({}), filterOptions = ref({})
const auth = useAuthStore()
const writable = computed(() => props.canWrite)
const canCreate = computed(() => writable.value && props.allowCreate && (!props.createRoles.length || auth.roles.some(role => props.createRoles.includes(role))))
const resolvedFields = computed(() => props.fields.map(field => field.optionsEndpoint ? { ...field, options: fieldOptions.value[field.optionsEndpoint] ?? [] } : field))

const resolvedFilters = computed(() => props.filters.map(f => {
  if (f.optionsEndpoint) {
    return {
      ...f,
      options: filterOptions.value[f.key] ?? []
    }
  }
  return {
    ...f,
    options: (f.options ?? []).map(opt => typeof opt === 'object' ? opt : { value: opt, label: opt })
  }
}))

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
  let value = valueAt(item, column.key)
  if (value === true || value === 'true') value = 'Có'
  if (value === false || value === 'false') value = 'Không'

  if (column.format === 'money') return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
  if (column.format === 'date' && value) return new Intl.DateTimeFormat('vi-VN').format(new Date(value))
  if (column.format === 'status') {
    const statusMap = {
      active: 'Hoạt động',
      inactive: 'Tạm dừng',
      available: 'Sẵn sàng',
      occupied: 'Có khách',
      cleaning: 'Đang dọn dẹp',
      maintenance: 'Bảo trì',
      out_of_service: 'Không sử dụng',
      confirmed: 'Đã xác nhận',
      cancelled: 'Đã hủy',
      pending: 'Chờ xử lý',
      completed: 'Hoàn thành',
      true: 'Hoạt động',
      false: 'Tạm dừng',
      '1': 'Hoạt động',
      '0': 'Tạm dừng',
      'có': 'Hoạt động',
      'không': 'Tạm dừng'
    }
    return statusMap[String(value).toLowerCase()] ?? value
  }

  const valueStr = String(value).toLowerCase()
  const dict = {
    per_booking: 'Mỗi lần đặt',
    per_night: 'Mỗi đêm',
    per_guest: 'Mỗi khách',
    per_unit: 'Mỗi đơn vị',
    fixed: 'Số tiền',
    percent: 'Phần trăm (%)',
    open: 'Đang mở',
    closed: 'Đã đóng'
  }
  if (dict[valueStr]) return dict[valueStr]

  return value ?? '—'
}
async function load() { loading.value = true; error.value = ''; try { items.value = listFrom(await api.get(props.endpoint)) } catch (err) { error.value = apiError(err) } finally { loading.value = false } }
async function loadFieldOptions() {
  const fields = props.fields.filter(field => field.optionsEndpoint)
  await Promise.all([...new Set(fields.map(field => field.optionsEndpoint))].map(async endpoint => {
    const field = fields.find(item => item.optionsEndpoint === endpoint)
    const data = responseData(await api.get(endpoint))
    const options = Array.isArray(data) ? data : data?.[field.optionsKey] ?? data?.items ?? data?.data ?? []
    fieldOptions.value[endpoint] = options.map(item => ({ value: String(item.id), label: valueAt(item, field.optionLabel ?? 'name') }))
  }))
}

async function loadFilterOptions() {
  const dynamicFilters = props.filters.filter(f => f.optionsEndpoint)
  await Promise.all(dynamicFilters.map(async f => {
    try {
      const res = await api.get(f.optionsEndpoint)
      const data = responseData(res)
      const list = Array.isArray(data) ? data : data?.items ?? data?.data ?? []
      filterOptions.value[f.key] = list.map(item => {
        const val = f.optionValue ? valueAt(item, f.optionValue) : (item.id ?? item.name ?? item)
        const lbl = f.optionLabel ? valueAt(item, f.optionLabel) : (item.name ?? item.title ?? item)
        return { value: String(val), label: String(lbl) }
      })
    } catch (err) {
      console.error(`Không thể tải bộ lọc động cho ${f.key}:`, err)
    }
  }))
}

const showSearchDropdown = ref(false)

const searchSuggestions = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return []
  return items.value.filter(item => {
    return props.columns.some(column => 
      String(valueAt(item, column.key) ?? '').toLowerCase().includes(query)
    )
  }).slice(0, 10).map(item => {
    const titleCol = props.columns[0]
    const title = display(item, titleCol)
    
    const subtext = props.columns.slice(1, 4).map(col => {
      const val = display(item, col)
      if (val === '—' || !val) return null
      return `${col.label}: ${val}`
    }).filter(Boolean).join(' - ')
    
    return {
      id: item.id || Math.random(),
      title,
      subtext,
      item
    }
  })
})

function selectSuggestion(sug) {
  search.value = sug.title
  showSearchDropdown.value = false
}

function handleSearchBlur() {
  setTimeout(() => {
    showSearchDropdown.value = false
  }, 200)
}

function handleAddOption({ endpoint, option }) {
  if (!fieldOptions.value[endpoint]) {
    fieldOptions.value[endpoint] = []
  }
  fieldOptions.value[endpoint].push(option)
}

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
onMounted(() => {
  if (props.filters) {
    props.filters.forEach(f => {
      filterValues.value[f.key] = ''
      filterValuesTemp.value[f.key] = ''
    })
  }
  load()
  loadFilterOptions().catch(err => { console.error('Lỗi tải bộ lọc động:', err) })
  loadFieldOptions().catch(err => { error.value ||= apiError(err, 'Không thể tải dữ liệu lựa chọn.') })
})
</script>

<template>
  <section>
    <header class="admin-page-head"><div><h1>{{ title }}</h1><p>{{ subtitle }}</p></div><button v-if="canCreate" class="admin-button" @click="openCreate">+ {{ createLabel }}</button></header>
    <p v-if="error && items.length" class="admin-alert">{{ error }}</p>
    <div class="admin-card">
      <div class="admin-toolbar">
        <div class="autocomplete-wrapper admin-search-wrapper">
          <input 
            v-model="search" 
            class="admin-input admin-search" 
            type="search" 
            placeholder="Tìm kiếm..." 
            @focus="showSearchDropdown = true"
            @blur="handleSearchBlur"
          />
          <transition name="fade">
            <ul v-if="showSearchDropdown && searchSuggestions.length" class="destinations-dropdown admin-search-dropdown">
              <li 
                v-for="sug in searchSuggestions" 
                :key="sug.id"
                class="dropdown-item"
                @mousedown="selectSuggestion(sug)"
              >
                <div class="dest-info">
                  <span class="dest-name">{{ sug.title }}</span>
                  <small class="dest-count">{{ sug.subtext }}</small>
                </div>
              </li>
            </ul>
          </transition>
        </div>
        <select v-for="filter in resolvedFilters" :key="filter.key" v-model="filterValues[filter.key]" class="admin-select">
          <option value="">{{ filter.label }}: Tất cả</option>
          <option v-for="option in filter.options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <AdminState :loading="loading" :error="error && !items.length ? error : ''" :empty="!loading && !error && !filtered.length" empty-text="Không tìm thấy bản ghi phù hợp." @retry="load" />
      <div v-if="!loading && filtered.length" class="admin-table-wrap"><table class="admin-table"><thead><tr><th v-for="column in columns" :key="column.key">{{ column.key === 'id' ? 'STT' : column.label }}</th><th v-if="writable">Thao tác</th></tr></thead><tbody><tr v-for="(item, index) in filtered" :key="item.id"><td v-for="column in columns" :key="column.key"><span v-if="column.key === 'id'">{{ index + 1 }}</span><span v-else-if="column.format === 'status'" class="admin-badge" :class="String(valueAt(item,column.key)).toLowerCase()">{{ display(item,column) }}</span><template v-else>{{ display(item,column) }}</template></td><td v-if="writable"><div class="admin-actions"><button class="admin-button secondary small" @click="openEdit(item)">Sửa</button><button v-if="canDelete" class="admin-button danger small" @click="remove(item)">Xóa</button></div></td></tr></tbody></table></div>
    </div>
    <CrudModal :open="modalOpen" :title="editing ? `Cập nhật ${title.toLowerCase()}` : createLabel" :fields="resolvedFields" :value="editing || {}" :saving="saving" :error="formError" @close="modalOpen=false" @save="save" @add-option="handleAddOption" />
  </section>
</template>

<style scoped>
.admin-search-wrapper {
  position: relative;
  width: 320px;
}
.admin-search-wrapper input {
  width: 100% !important;
}
@media (max-width: 760px) {
  .admin-search-wrapper {
    width: 100% !important;
  }
}
.admin-search-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  width: 100%;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--admin-line);
  list-style: none;
  margin: 0;
  padding: 6px 0;
  z-index: 999;
  max-height: 250px;
  overflow-y: auto;
}
.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 14px;
  cursor: pointer;
  transition: background 0.15s ease;
}
.dropdown-item:hover {
  background: #f1f5f9;
}
.dest-info {
  display: flex;
  flex-direction: column;
  text-align: left;
}
.dest-name {
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
}
.dest-count {
  font-size: 11px;
  color: #64748b;
  margin-top: 2px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
