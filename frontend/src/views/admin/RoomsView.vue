<script setup>
import ResourcePage from '../../admin/ResourcePage.vue'
const statuses=[
  {value:'available',label:'Trống (Sẵn sàng)'},
  {value:'cleaning',label:'Đang dọn dẹp'},
  {value:'maintenance',label:'Bảo trì'},
  {value:'out_of_service',label:'Không sử dụng'}
]
const columns=[{key:'room_number',label:'Số phòng'},{key:'floor',label:'Tầng'},{key:'room_type.name',label:'Loại phòng'},{key:'hotel.name',label:'Khách sạn'},{key:'operational_status',label:'Trạng thái',format:'status'}]
const fields=[{key:'hotel_id',label:'Khách sạn',type:'select',optionsEndpoint:'/admin/hotels',required:true},{key:'room_type_id',label:'Loại phòng',type:'select',optionsEndpoint:'/admin/room-types',required:true},{key:'room_number',label:'Số phòng',required:true},{key:'floor',label:'Tầng',type:'number',min:0},{key:'operational_status',label:'Trạng thái',type:'select',default:'available',options:statuses},{key:'active',label:'Hoạt động',type:'select',default:1,options:[{value:1,label:'Có'},{value:0,label:'Không'}]}]
</script>
<template><ResourcePage title="Phòng" subtitle="Theo dõi danh mục và tình trạng phòng" endpoint="/admin/rooms" item-key="rooms" :columns="columns" :fields="fields" create-label="Thêm phòng" :filters="[{key:'hotel_id',label:'Khách sạn',optionsEndpoint:'/admin/hotels',optionLabel:'name',optionValue:'id'},{key:'operational_status',label:'Trạng thái',options:statuses},{key:'floor',label:'Tầng',options:[1,2,3,4,5]}]" /></template>
