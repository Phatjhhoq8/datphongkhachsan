<script setup>
import ResourcePage from '../../admin/ResourcePage.vue'
const columns=[{key:'id',label:'ID'},{key:'user.name',label:'Khách hàng'},{key:'hotel.name',label:'Khách sạn'},{key:'rating_overall',label:'Điểm'},{key:'content',label:'Nội dung'},{key:'status',label:'Trạng thái',format:'status'},{key:'created_at',label:'Ngày gửi',format:'date'}]
const statuses=['pending','published','rejected']
const fields=[{key:'status',label:'Trạng thái kiểm duyệt',type:'select',required:true,options:statuses}]
</script>
<template><ResourcePage title="Đánh giá" subtitle="Kiểm duyệt nhận xét khách hàng" endpoint="/admin/reviews" item-key="reviews" :columns="columns" :fields="fields" :allow-create="false" :can-delete="false" :filters="[{key:'status',label:'Trạng thái',options:statuses},{key:'rating_overall',label:'Số sao',options:[1,2,3,4,5]}]" /></template>
