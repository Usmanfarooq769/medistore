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
                  <div class="small text-muted">Pack contains {{ Math.max(1, Number(product.pack_size || 1)) }} {{ product.unit || 'units' }}</div>
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
                <div class="min-w-0 flex-grow-1"><div class="fw-semibold small">{{ line.name }}</div>
                  <select v-model="line.sale_unit" class="form-select form-select-sm my-1" style="max-width:145px" @change="changeUnit(line)"><option value="pack" :disabled="Number(line.available_stock) < Number(line.pack_size)">Complete pack ({{ line.pack_size }})</option><option value="unit">Individual tablet / unit</option></select>
                  <div class="text-muted small">{{ money(lineUnitPrice(line)) }} per {{ line.sale_unit === 'pack' ? 'pack' : 'unit' }}</div></div>
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
                <div class="mb-2"><label class="form-label small">Payment method</label><select v-model="customer.payment_method" class="form-select"><option value="cash_on_delivery">Cash on delivery</option><option value="pay_at_store">Pay at pharmacy counter</option></select></div><div class="mb-3"><label class="form-label small">Order note (optional)</label><input v-model.trim="customer.note" class="form-control" maxlength="1500"></div>
                <button class="btn btn-primary w-100" type="submit" :disabled="sending || !cart.length || !branchId">{{ sending ? 'Submitting…' : 'Place order request' }}</button>
                <p class="small text-muted mt-2 mb-0">Your stock is reserved for 30 minutes while the pharmacy reviews your request. Payment is collected using your selected method when the order is fulfilled. Online card payments are not enabled in this release.</p>
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
          <div v-if="tracked" class="card mt-3"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2"><div><strong>{{ tracked.order_no }}</strong><div class="small text-muted">{{ tracked.status.replaceAll('_',' ') }} · {{ tracked.created_at }}</div></div><div class="fw-bold">{{ money(tracked.total) }}</div></div>
            <div class="table-responsive mt-3"><table class="table table-sm align-middle"><thead><tr><th>Medicine</th><th>Unit</th><th class="text-end">Qty</th><th class="text-end">Amount</th></tr></thead><tbody><tr v-for="(item,i) in tracked.items" :key="i"><td>{{ item.name }} <small class="text-muted">{{ item.strength }}</small></td><td>{{ item.sale_unit === 'pack' ? `Pack (${item.pack_size})` : 'Individual unit' }}</td><td class="text-end">{{ item.sale_quantity }}</td><td class="text-end">{{ money(item.line_total) }}</td></tr></tbody></table></div>
            <div v-if="tracked.invoice_no" class="small text-success">POS invoice: {{ tracked.invoice_no }}</div>
            <div v-if="tracked.delivery" class="small text-muted">Delivery: {{ tracked.delivery.status }} · {{ tracked.delivery.driver_name || '' }}</div>
          </div></div>
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
const loading=ref(false), sending=ref(false), trackingBusy=ref(false), message=ref(''), success=ref(false), tracked=ref(null), checkoutKey=ref('');
const customer=reactive({name:'',phone:'',address:'',note:'',payment_method:'cash_on_delivery'});
const tracking=reactive({order_no:'',phone:''});
const cartCount=computed(()=>cart.value.reduce((n,x)=>n+x.quantity,0));
const subtotal=computed(()=>cart.value.reduce((n,x)=>n+lineUnitPrice(x)*x.quantity,0));
const money=v=>new Intl.NumberFormat('en-PK',{style:'currency',currency:'PKR',maximumFractionDigits:2}).format(Number(v||0));
async function loadBranches(){const {data}=await axios.get('/api/storefront/branches');branches.value=data;if(data.length)branchId.value=String(data[0].id);await loadCatalog();}
async function loadCatalog(){if(!branchId.value)return;loading.value=true;try{const {data}=await axios.get('/api/storefront/catalog',{params:{branch_id:branchId.value,search:search.value}});products.value=data.medicines?.data||data.medicines||[];}catch(e){message.value=e.response?.data?.message||'Unable to load medicines.';success.value=false;}finally{loading.value=false;}}
function factor(line){return line.sale_unit === 'pack' ? Math.max(1,Number(line.pack_size||1)) : 1;}
function lineUnitPrice(line){const packPrice=Number(line.sale_price||0);return line.sale_unit==='pack'?packPrice:packPrice/Math.max(1,Number(line.pack_size||1));}
function add(p){const found=cart.value.find(x=>x.id===p.id);if(found){changeQty(found,1);}else {const packSize=Math.max(1,Number(p.pack_size||1));cart.value.push({...p,quantity:1,sale_unit:Number(p.available_stock)>=packSize?'pack':'unit',pack_size:packSize});}}
function changeUnit(line){const max=Math.floor(Number(line.available_stock||0)/factor(line));line.quantity=Math.max(1,Math.min(line.quantity,max||1));}
function changeQty(line,delta){const max=Math.floor(Number(line.available_stock||line.quantity*factor(line))/factor(line));line.quantity=Math.max(1,Math.min(max,line.quantity+delta));}
function remove(id){cart.value=cart.value.filter(x=>x.id!==id);}
async function submitOrder(){message.value='';if(!cart.value.length)return; sending.value=true;if(!checkoutKey.value)checkoutKey.value=(globalThis.crypto?.randomUUID?.()||`web-${Date.now()}-${Math.random().toString(36).slice(2)}`);try{const {data}=await axios.post('/api/storefront/orders',{branch_id:Number(branchId.value),customer_name:customer.name,customer_phone:customer.phone,delivery_address:customer.address,customer_note:customer.note,payment_method:customer.payment_method,channel:'web',items:cart.value.map(x=>({medicine_id:x.id,quantity:x.quantity,sale_unit:x.sale_unit}))},{headers:{'Idempotency-Key':checkoutKey.value}});message.value=`Order submitted successfully. Your order number is ${data.order_no}. Please save it to track your order.`;success.value=true;tracking.order_no=data.order_no;tracking.phone=customer.phone;cart.value=[];checkoutKey.value='';await loadCatalog();}catch(e){message.value=e.response?.data?.message||Object.values(e.response?.data?.errors||{}).flat().join(' ')||'Unable to submit order. Please check stock and try again.';success.value=false;}finally{sending.value=false;}}
async function trackOrder(){trackingBusy.value=true;tracked.value=null;try{const {data}=await axios.post('/api/storefront/track-order',tracking);tracked.value=data;}catch(e){message.value=e.response?.data?.message||'Order could not be found.';success.value=false;}finally{trackingBusy.value=false;}}
onMounted(loadBranches);
</script>
