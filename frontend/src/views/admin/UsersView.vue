<script setup>
import { computed } from 'vue'
import ResourcePage from '../../admin/ResourcePage.vue'
import { useAuthStore } from '../../stores/auth'

const auth=useAuthStore()
const columns=[{key:'id',label:'ID'},{key:'name',label:'Họ tên'},{key:'email',label:'Email'},{key:'phone',label:'Điện thoại'},{key:'role',label:'Vai trò',format:'status'},{key:'status',label:'Trạng thái',format:'status'}]
const roles=['super_admin','hotel_manager','receptionist','accountant']
const commonFields=[{key:'name',label:'Họ tên',required:true},{key:'email',label:'Email',type:'email',required:true},{key:'phone',label:'Điện thoại'}]
const assignmentFields=[{key:'hotel_id',label:'Khách sạn',type:'select',optionsEndpoint:'/admin/hotels'},{key:'role',label:'Vai trò',type:'select',required:true,options:roles}]
const fields=computed(()=>[...commonFields,...(auth.roles.includes('super_admin')?assignmentFields:[]),{key:'password',label:'Mật khẩu mới',type:'password',placeholder:'Để trống nếu không đổi'},{key:'status',label:'Trạng thái',type:'select',default:'active',options:['active','inactive']}])
</script>
<template><ResourcePage title="Người dùng" subtitle="Tài khoản, vai trò và trạng thái truy cập" endpoint="/admin/users" item-key="users" :columns="columns" :fields="fields" create-label="Thêm người dùng" update-method="patch" :can-delete="false" :create-roles="['super_admin']" :filters="[{key:'role',label:'Vai trò',options:roles},{key:'status',label:'Trạng thái',options:['active','inactive']}]" /></template>
