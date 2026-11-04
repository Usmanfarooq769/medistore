<template>
  <main class="min-vh-100 bg-light py-4">
    <div class="container" style="max-width:1180px">
      <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <div class="text-primary fw-bold small text-uppercase">MediStore Online Pharmacy</div>
          <h1 class="h3 mb-1">Order medicines online</h1>
          <p class="text-muted mb-0">Choose your pharmacy branch, add available products and submit your order.</p>
        </div>
        <a href="/customer-portal" class="btn btn-outline-primary">Customer account</a>
      </header>

      <div v-if="message" class="alert" :class="success ? 'alert-success' : 'alert-danger'">{{ message }}</div>

      <div class="row g-4">
        <section class="col-lg-8">
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
              <div class="row g-2">
                <div class="col-md-5">
                  <label class="form-label">Pharmacy branch</label>
                  <select v-model="branchId" class="form-select" @change="loadCatalog">
                    <option value="">Select branch</option>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}{{ branch.address ? ' — '+branch.address : '' }}</option>
                  </select>
                </div>
                <div class="col-md-7">
                  <label class="form-label">Search medicines</label>
                  <div class="input-group">
                    <input v-model="search" class="form-control" placeholder="Medicine name, generic or barcode" @keyup.enter="loadCatalog">
                    <button class="btn btn-primary" :disabled="loading || !branchId" @click="loadCatalog">Search</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
          <div v-else-if="!branchId" class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">Select a pharmacy branch to view available medicines.</div></div>
          <div v-else-if="!products.length" class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">No available medicines found. Try another search.</div></div>
          <div v-else class="row g-3">
            <div v-for="product in products" :key="product.id" class="col-sm-6 col-xl-4">
              <article class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                  <div class="fw-semibold">{{ product.name }} <span class="text-muted">{{ product.strength }}</span></div>
                  <div class="small text-muted mb-2">{{ product.generic_name || product.unit || 'Medicine' }}</div>
                  <div class="mt-auto d-flex align-items-center justify-content-between gap-2">
                    <div><div class="fw-bold">{{ money(product.sale_price) }}</div><div class="small text-muted">{{ product.available_stock }} units available</div></div>
                    <button class="btn btn-sm btn-primary" :disabled="Number(product.available_stock) < 1" @click="add(product)">Add</button>
                  </div>
                </div>
              </article>
            </div>
          </div>
        </section>

        <aside class="col-lg-4">
          <div class="card border-0 shadow-sm sticky-top" style="top:1rem">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Your cart</span><span class="badge text-bg-primary">{{ cartCount }}</span></div>
            <div class="card-body">
              <div v-if="!cart.length" class="text-muted text-center py-3">Your cart is empty.</div>
              <div v-for="line in cart" :key="line.id" class="d-flex justify-content-between gap-2 border-bottom py-2">
                <div class="min-w-0"><div class="fw-semibold small">{{ line.name }}</div><div class="text-muted small">{{ money(line.sale_price) }} each</div></div>
                <div class="d-flex align-items-center gap-1">
                  <button class="btn btn-sm btn-outline-secondary" @click="changeQty(line,-1)">−</button>
                  <span class="small px-1">{{ line.quantity }}</span>
                  <button class="btn btn-sm btn-outline-secondary" @click="changeQty(line,1)">+</button>
                  <button class="btn btn-sm btn-link text-danger" @click="remove(line.id)" aria-label="Remove">×</button>
                </div>
              </div>
              <div class="d-flex justify-content-between fw-bold mt-3"><span>Subtotal</span><span>{{ money(subtotal) }}</span></div>
              <hr>
              <form @submit.prevent="submitOrder">
                <div class="mb-2"><label class="form-label small">Full name</label><input v-model.trim="customer.name" class="form-control" required maxlength="150"></div>
                <div class="mb-2"><label class="form-label small">Mobile number</label><input v-model.trim="customer.phone" class="form-control" required maxlength="40" placeholder="03xxxxxxxxx"></div>
                <div class="mb-3"><label class="form-label small">Delivery address</label><textarea v-model.trim="customer.address" class="form-control" rows="2" required maxlength="1000"></textarea></div>
                <div class="mb-3"><label class="form-label small">Order note (optional)</label><input v-model.trim="customer.note" class="form-control" maxlength="1500"></div>
                <button class="btn btn-success w-100" type="submit" :disabled="sending || !cart.length || !branchId">{{ sending ? 'Submitting…' : 'Place order request' }}</button>
                <p class="small text-muted mt-2 mb-0">Your stock is reserved for 30 minutes while the pharmacy reviews your request. Payment is arranged with the pharmacy.</p>
              </form>
            </div>
          </div>
        </aside>
      </div>

      <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
          <h2 class="h6">Track an order</h2>
          <form class="row g-2" @submit.prevent="trackOrder">
            <div class="col-md-5"><input v-model.trim="tracking.order_no" class="form-control" placeholder="Order number" required></div>
            <div class="col-md-5"><input v-model.trim="tracking.phone" class="form-control" placeholder="Mobile number used for order" required></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" :disabled="trackingBusy">Track</button></div>
          </form>
          <div v-if="tracked" class="alert alert-info mt-3 mb-0"><strong>{{ tracked.order_no }}</strong> — {{ tracked.status.replaceAll('_',' ') }} · {{ money(tracked.total) }}</div>
        </div>
      </section>
      <footer class="text-center small text-muted py-4">For prescription-only medicines, please contact the pharmacy and provide a valid prescription.</footer>
    </div>
  </main>
</template>

<script setup>
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';

const branches=ref([]), products=ref([]), cart=ref([]), branchId=ref(''), search=ref('');
const loading=ref(false), sending=ref(false), trackingBusy=ref(false), message=ref(''), success=ref(false), tracked=ref(null);
const customer=reactive({name:'',phone:'',address:'',note:''});
const tracking=reactive({order_no:'',phone:''});
const cartCount=computed(()=>cart.value.reduce((n,x)=>n+x.quantity,0));
const subtotal=computed(()=>cart.value.reduce((n,x)=>n+(Number(x.sale_price)*x.quantity),0));
const money=v=>new Intl.NumberFormat('en-PK',{style:'currency',currency:'PKR',maximumFractionDigits:2}).format(Number(v||0));
async function loadBranches(){const {data}=await axios.get('/api/storefront/branches');branches.value=data;if(data.length)branchId.value=String(data[0].id);await loadCatalog();}
async function loadCatalog(){if(!branchId.value)return;loading.value=true;try{const {data}=await axios.get('/api/storefront/catalog',{params:{branch_id:branchId.value,search:search.value}});products.value=data.medicines?.data||data.medicines||[];}catch(e){message.value=e.response?.data?.message||'Unable to load medicines.';success.value=false;}finally{loading.value=false;}}
function add(p){const found=cart.value.find(x=>x.id===p.id);if(found){if(found.quantity<Number(p.available_stock))found.quantity++;}else cart.value.push({...p,quantity:1});}
function changeQty(line,delta){const p=products.value.find(x=>x.id===line.id);const max=Number(p?.available_stock||line.quantity);line.quantity=Math.max(1,Math.min(max,line.quantity+delta));}
function remove(id){cart.value=cart.value.filter(x=>x.id!==id);}
async function submitOrder(){message.value='';if(!cart.value.length)return; sending.value=true;try{const {data}=await axios.post('/api/storefront/orders',{branch_id:Number(branchId.value),customer_name:customer.name,customer_phone:customer.phone,delivery_address:customer.address,customer_note:customer.note,channel:'web',items:cart.value.map(x=>({medicine_id:x.id,quantity:x.quantity}))});message.value=`Order submitted successfully. Your order number is ${data.order_no}. Please save it to track your order.`;success.value=true;tracking.order_no=data.order_no;tracking.phone=customer.phone;cart.value=[];await loadCatalog();}catch(e){message.value=e.response?.data?.message||Object.values(e.response?.data?.errors||{}).flat().join(' ')||'Unable to submit order. Please check stock and try again.';success.value=false;}finally{sending.value=false;}}
async function trackOrder(){trackingBusy.value=true;tracked.value=null;try{const {data}=await axios.post('/api/storefront/track-order',tracking);tracked.value=data;}catch(e){message.value=e.response?.data?.message||'Order could not be found.';success.value=false;}finally{trackingBusy.value=false;}}
onMounted(loadBranches);
</script>
