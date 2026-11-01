<template>
    <div>
        <div class="mb-3"><h4 class="fw-bold mb-1">Promotions & Coupons</h4><p class="text-muted small mb-0">Create time-limited discounts and control total and per-customer usage.</p></div>
        <CrudPage endpoint="/promotions" singular="Promotion" add-label="Add Promotion" search-placeholder="Name or coupon code…"
            :fields="fields" :columns="columns" :defaults="{discount_type:'percentage',minimum_subtotal:0,is_active:true}">
            <template #cell-code="{ item }"><span class="badge bg-primary-subtle text-primary font-monospace">{{ item.code }}</span></template>
            <template #cell-discount_value="{ item }">{{ item.discount_type === 'percentage' ? `${item.discount_value}%` : money(item.discount_value) }}</template>
            <template #cell-usage="{ item }">{{ item.used_count }} / {{ item.usage_limit ?? '∞' }}</template>
            <template #cell-is_active="{ item }"><span class="badge" :class="item.is_active ? 'bg-success' : 'bg-secondary'">{{ item.is_active ? 'Active' : 'Inactive' }}</span></template>
        </CrudPage>
        <div class="alert alert-info small mt-3 mb-0"><b>POS usage:</b> Enter the coupon code during checkout. Promotions are validated again on the server before a sale is saved.</div>
    </div>
</template>
<script setup>
import CrudPage from '../components/CrudPage.vue';
import { money } from '../utils/format';
const fields = [
    {key:'name',label:'Promotion name',required:true,col:'col-md-6'},
    {key:'code',label:'Coupon code',required:true,col:'col-md-6',hint:'Code is stored in uppercase.'},
    {key:'discount_type',label:'Discount type',type:'select',required:true,col:'col-md-6',options:[{value:'percentage',label:'Percentage (%)'},{value:'fixed',label:'Fixed amount'}]},
    {key:'discount_value',label:'Discount value',type:'number',step:'0.01',min:0.01,required:true,col:'col-md-6'},
    {key:'minimum_subtotal',label:'Minimum cart subtotal',type:'number',step:'0.01',min:0,col:'col-md-6'},
    {key:'maximum_discount',label:'Maximum discount (optional)',type:'number',step:'0.01',min:0,col:'col-md-6'},
    {key:'usage_limit',label:'Total usage limit (optional)',type:'number',min:1,col:'col-md-6'},
    {key:'per_customer_limit',label:'Per-customer limit (optional)',type:'number',min:1,col:'col-md-6'},
    {key:'starts_at',label:'Starts at',type:'datetime-local',col:'col-md-6'},
    {key:'ends_at',label:'Ends at',type:'datetime-local',col:'col-md-6'},
    {key:'is_active',label:'Active',type:'switch'},
];
const columns = [
    {key:'name',label:'Promotion'}, {key:'code',label:'Code'}, {key:'discount_value',label:'Discount'},
    {key:'minimum_subtotal',label:'Minimum'}, {key:'usage',label:'Used'}, {key:'is_active',label:'Status'},
];
</script>
