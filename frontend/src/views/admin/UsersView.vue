<script setup>
import ResourcePage from '../../admin/ResourcePage.vue'
const columns=[{key:'id',label:'ID'},{key:'name',label:'Họ tên'},{key:'email',label:'Email'},{key:'phone',label:'Điện thoại'},{key:'role',label:'Vai trò',format:'status'},{key:'status',label:'Trạng thái',format:'status'}]
const roles=['super_admin','hotel_manager','receptionist','accountant']
const fields=[{key:'name',label:'Họ tên',required:true},{key:'email',label:'Email',type:'email',required:true},{key:'phone',label:'Điện thoại'},{key:'hotel_id',label:'ID khách sạn',type:'number'},{key:'role',label:'Vai trò',type:'select',required:true,options:roles},{key:'password',label:'Mật khẩu',type:'password'},{key:'status',label:'Trạng thái',type:'select',default:'active',options:['active','inactive']}]
</script>
<template><ResourcePage title="Người dùng" subtitle="Tài khoản, vai trò và trạng thái truy cập" endpoint="/admin/users" item-key="users" :columns="columns" :fields="fields" create-label="Thêm người dùng" update-method="patch" :can-delete="false" :create-roles="['super_admin']" :filters="[{key:'role',label:'Vai trò',options:roles},{key:'status',label:'Trạng thái',options:['active','inactive']}]" /></template>
