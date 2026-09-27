<template>
    <div class="row g-3 pos-wrap">
        <!-- ============ LEFT: products ============ -->
        <div class="col-lg-7 col-xl-8 h-100">
            <div class="card h-100 d-flex flex-column">
                <div class="card-header">
                    <div class="input-group input-group-lg mb-2">
                        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                        <input ref="searchEl" v-model="q" class="form-control" placeholder="Search name / generic or scan barcode (F2)" autocomplete="off" @keydown="onSearchKey">
                        <button v-if="q" class="btn btn-outline-secondary" @click="q = ''"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="chips">
                        <button class="chip" :class="{ active: !category }" @click="category = ''">All</button>
                        <button v-for="c in categories" :key="c.id" class="chip" :class="{ active: category === c.id }" @click="category = c.id">{{ c.name }}</button>
                    </div>
                </div>
                <div ref="gridEl" class="pos-grid flex-grow-1">
                    <template v-if="loading && !products.length"><div v-for="i in 12" :key="i" class="skeleton" style="height:105px"></div></template>
                    <div v-else-if="!products.length" class="text-center text-muted p-5" style="grid-column:1/-1">
                        <i class="bi bi-search fs-1 d-block"></i>No sellable medicine found<br><small>Out-of-stock and expired batches are hidden</small>
                    </div>
                    <div v-for="(m, i) in products" :key="m.id" class="pos-card" :class="{ active: i === active, low: m.sellable_stock <= m.reorder_level }"
                         :title="m.generic_name" @click="active = i; add(m)">
                        <span v-if="inCart[m.id]" class="badge rounded-pill bg-success in-cart">{{ inCart[m.id] }}</span>
                        <div class="name">{{ m.display_name }}</div>
                        <div class="small text-muted text-truncate">{{ m.generic_name || '\u00a0' }}</div>
                        <div class="d-flex justify-content-between align-items-end mt-2">
                            <span class="price">{{ money(m.sale_price) }}</span>
                            <span class="badge" :class="m.sellable_stock <= m.reorder_level ? 'bg-warning text-dark' : 'bg-success-subtle text-success'">{{ m.sellable_stock }} {{ m.unit }}</span>
                        </div>
                        <div class="small text-muted" style="font-size:.7rem"><span v-if="m.rack"><i class="bi bi-geo-alt"></i> {{ m.rack }} · </span>Exp {{ date(m.nearest_expiry) }}</div>
                    </div>
                </div>
                <div class="card-footer small text-muted d-flex flex-wrap gap-3 align-items-center">
                    <span><kbd>F2</kbd> search</span><span><kbd>↑↓←→</kbd> select</span><span><kbd>Enter</kbd> add</span>
                    <span><kbd>F4</kbd> hold</span><span><kbd>F8</kbd> clear</span><span><kbd>F9</kbd> pay</span>
                    <button v-if="hasMore" class="btn btn-sm btn-light ms-auto" @click="loadProducts(false)">Load more</button>
                </div>
            </div>
        </div>

        <!-- ============ RIGHT: cart ============ -->
        <div class="col-lg-5 col-xl-4 h-100">
            <div class="card h-100 d-flex flex-column">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-cart3"></i> Cart <span class="badge bg-success">{{ totals.units }}</span></span>
                    <div class="d-flex gap-1 position-relative">
                        <button class="btn btn-sm btn-outline-warning" title="Held sales" @click="heldOpen = !heldOpen"><i class="bi bi-pause-circle"></i> {{ held.length }}</button>
                        <div v-if="heldOpen" class="dropdown-menu show shadow" style="right:0; left:auto; top:110%; width:260px" @mouseleave="heldOpen = false">
                            <div v-for="(h, i) in held" :key="h.at + i" class="d-flex align-items-center px-2">
                                <a class="dropdown-item small" href="#" @click.prevent="resume(i)"><b>{{ h.customer?.name || 'Walk-in' }}</b> · {{ h.at }}<br>
                                    <span class="text-muted">{{ h.cart.length }} items · {{ money(h.cart.reduce((s, c) => s + c.price * c.qty, 0)) }}</span></a>
                                <button class="btn btn-sm text-danger" @click="held.splice(i, 1)"><i class="bi bi-x"></i></button>
                            </div>
                            <div v-if="!held.length" class="px-3 py-2 small text-muted">No held sales</div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" title="Hold (F4)" @click="hold"><i class="bi bi-pause"></i> Hold</button>
                        <button class="btn btn-sm btn-outline-danger" title="Clear (F8)" @click="clearCart"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <div class="px-3 pt-2">
                    <Autocomplete v-if="!customer" url="/customers" query-key="search" :params="{ per_page: 8 }" size="sm" icon="bi-person"
                                  placeholder="Customer name / phone (optional)" empty-text="Not found – add in Customers" @select="customer = $event">
                        <template #item="{ item }"><b>{{ item.name }}</b> <small class="text-muted">{{ item.phone }}</small>
                            <span v-if="item.balance > 0" class="badge bg-danger ms-1">{{ money(item.balance) }}</span></template>
                    </Autocomplete>
                    <div v-else class="d-flex justify-content-between align-items-center small border rounded px-2 py-1">
                        <span><i class="bi bi-person-check text-success"></i> <b>{{ customer.name }}</b> {{ customer.phone }}
                            <span v-if="customer.balance > 0" class="badge bg-danger">Due {{ money(customer.balance) }}</span></span>
                        <button class="btn btn-sm text-muted p-0" @click="customer = null"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                <div class="cart-list mt-2">
                    <div v-if="!cart.length" class="text-center text-muted py-5"><i class="bi bi-basket fs-1 d-block opacity-50"></i>Cart is empty<br><small>Click a medicine or scan a barcode</small></div>
                    <div v-for="(c, i) in cart" :key="c.id" class="cart-line">
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold small text-truncate">{{ c.name }}</div>
                            <input v-if="priceEdit" v-model.number="c.price" type="number" step="0.01" min="0" class="form-control form-control-sm py-0" style="width:90px">
                            <span v-else class="small text-muted">{{ money(c.price) }}</span>
                        </div>
                        <div class="qty-box">
                            <button @click="dec(i)"><i v-if="c.qty <= 1" class="bi bi-trash text-danger"></i><span v-else>−</span></button>
                            <input :value="c.qty" type="number" min="1" :max="c.stock" @change="setQty(i, $event.target.value)">
                            <button :disabled="c.qty >= c.stock" @click="setQty(i, c.qty + 1)">+</button>
                        </div>
                        <div class="text-end fw-semibold small" style="width:80px">{{ money(c.price * c.qty) }}</div>
                    </div>
                </div>

                <div class="card-body border-top pt-2">
                    <div class="d-flex justify-content-between small"><span>Subtotal</span><b>{{ money(totals.sub) }}</b></div>
                    <div class="d-flex justify-content-between align-items-center gap-2 mt-1">
                        <span class="small">Discount</span>
                        <div class="input-group input-group-sm" style="max-width:170px">
                            <input v-model.number="discount" type="number" min="0" step="0.01" class="form-control text-end" placeholder="0">
                            <button class="btn btn-secondary" type="button" @click="discPct = !discPct">{{ discPct ? '%' : getCurrency() }}</button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2 mt-1">
                        <span class="small">Tax %</span>
                        <input v-model.number="taxPercent" type="number" min="0" max="100" step="0.01" class="form-control form-control-sm text-end" style="max-width:170px">
                    </div>
                    <div class="d-flex justify-content-between fs-3 fw-bold text-success border-top mt-2 pt-1"><span>Total</span><span>{{ money(totals.total) }}</span></div>
                    <div class="btn-group w-100 my-2">
                        <button v-for="m in methods" :key="m.key" class="btn btn-sm" :class="method === m.key ? 'btn-success' : 'btn-outline-secondary'" @click="setMethod(m.key)">
                            <i :class="['bi', m.icon]"></i> {{ m.label }}</button>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="small">Paid</span>
                        <input v-model="paid" type="number" min="0" step="0.01" class="form-control text-end fw-semibold" :placeholder="method === 'credit' ? '0' : 'exact'">
                    </div>
                    <div v-if="method === 'cash' && totals.total > 0" class="d-flex gap-1 mt-1">
                        <button class="btn btn-sm btn-outline-secondary flex-fill" @click="paid = ''">Exact</button>
                        <button v-for="v in quickCash" :key="v" class="btn btn-sm btn-outline-secondary flex-fill" @click="paid = v">{{ v.toLocaleString() }}</button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-2 rounded mt-2" :class="totals.change < 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'">
                        <span class="fw-semibold">{{ totals.change < 0 ? (customer ? 'Due (credit)' : 'Short by') : 'Change to return' }}</span>
                        <span class="fw-bold fs-5">{{ money(Math.abs(totals.change)) }}</span>
                    </div>
                    <button class="btn btn-success btn-lg w-100 mt-2 fw-semibold" :disabled="busy || !cart.length" @click="checkout">
                        <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bi bi-check2-circle"></i> Pay {{ money(totals.total) }} (F9)
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api, { isCancel } from '../api';
import Autocomplete from '../components/Autocomplete.vue';
import { useAuth } from '../stores/auth';
import { date, debounce, getCurrency, money, stockChanged } from '../utils/format';
import { confirm, esc, Swal, toast } from '../utils/swal';

const auth = useAuth();
const priceEdit = computed(() => auth.isAdmin || auth.can('cashier_price_edit'));
const methods = [
    { key: 'cash', label: 'Cash', icon: 'bi-cash' }, { key: 'card', label: 'Card', icon: 'bi-credit-card' },
    { key: 'online', label: 'Online', icon: 'bi-phone' }, { key: 'credit', label: 'Credit', icon: 'bi-journal' },
];

const categories = ref([]);
const products = ref([]);
const q = ref('');
const category = ref('');
const page = ref(1);
const hasMore = ref(false);
const loading = ref(false);
const active = ref(0);
const searchEl = ref(null);
const gridEl = ref(null);

const cart = ref(JSON.parse(localStorage.getItem('pos_cart') || '[]'));
const held = ref(JSON.parse(localStorage.getItem('pos_held') || '[]'));
const heldOpen = ref(false);
const customer = ref(null);
const discount = ref('');
const discPct = ref(false);
const taxPercent = ref(Number(auth.settings.default_tax || 0));
const method = ref('cash');
const paid = ref('');
const busy = ref(false);
let controller = null;

watch(cart, (v) => localStorage.setItem('pos_cart', JSON.stringify(v)), { deep: true });
watch(held, (v) => localStorage.setItem('pos_held', JSON.stringify(v)), { deep: true });

/* ---------- products (AJAX, paginated, cancellable) ---------- */
async function loadProducts(reset = true) {
    if (reset) page.value = 1; else page.value++;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    try {
        const { data } = await api.get('/pos/products', { params: { q: q.value.trim() || undefined, category_id: category.value || undefined, page: page.value }, signal: controller.signal, silent: true });
        products.value = reset ? data.data : products.value.concat(data.data);
        hasMore.value = !!data.links?.next;
        if (reset) active.value = 0;
        const term = q.value.trim();
        if (term && data.data.length === 1 && data.data[0].barcode === term) { add(data.data[0]); q.value = ''; }
    } catch (e) {
        if (!isCancel(e)) products.value = [];
    } finally {
        loading.value = false;
    }
}
const debouncedLoad = debounce(() => loadProducts(), 250);
watch(q, debouncedLoad);
watch(category, () => loadProducts());

const inCart = computed(() => Object.fromEntries(cart.value.map((c) => [c.id, c.qty])));

function add(m) {
    const line = cart.value.find((c) => c.id === m.id);
    if (line) {
        if (line.qty + 1 > m.sellable_stock) return Swal.fire({ icon: 'warning', title: 'Stock limit', text: `Only ${m.sellable_stock} ${m.unit} available`, timer: 2000 });
        line.qty++;
        line.stock = m.sellable_stock;
    } else {
        cart.value.push({ id: m.id, name: m.display_name, price: m.sale_price, stock: m.sellable_stock, unit: m.unit, qty: 1 });
    }
    toast.fire({ icon: 'success', title: `${m.display_name} added`, timer: 700 });
}
function setQty(i, v) {
    const c = cart.value[i];
    let n = Math.floor(Number(v)) || 1;
    if (n > c.stock) { toast.fire({ icon: 'warning', title: `Only ${c.stock} available` }); n = c.stock; }
    c.qty = Math.max(n, 1);
}
function dec(i) { if (cart.value[i].qty <= 1) cart.value.splice(i, 1); else cart.value[i].qty--; }

function onSearchKey(e) {
    const cols = Math.max(1, Math.floor((gridEl.value?.clientWidth || 600) / 175));
    const max = products.value.length - 1;
    const moves = { ArrowDown: cols, ArrowUp: -cols, ArrowRight: q.value ? 0 : 1, ArrowLeft: q.value ? 0 : -1 };
    if (e.key in moves && moves[e.key]) {
        e.preventDefault();
        active.value = Math.max(0, Math.min(max, active.value + moves[e.key]));
        nextTick(() => gridEl.value?.querySelector('.pos-card.active')?.scrollIntoView({ block: 'nearest' }));
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (products.value[active.value]) { add(products.value[active.value]); if (q.value) q.value = ''; }
    }
}

/* ---------- totals ---------- */
const totals = computed(() => {
    const sub = Math.round(cart.value.reduce((s, c) => s + c.price * c.qty, 0) * 100) / 100;
    const dv = Number(discount.value) || 0;
    const disc = Math.round(Math.min(discPct.value ? (sub * Math.min(dv, 100)) / 100 : dv, sub) * 100) / 100;
    const tp = Number(taxPercent.value) || 0;
    const tax = Math.round((sub - disc) * tp) / 100;
    const total = Math.round((sub - disc + tax) * 100) / 100;
    const p = method.value === 'credit' ? Number(paid.value) || 0 : paid.value === '' ? total : Number(paid.value);
    return { sub, disc, tp, tax, total, paid: p, change: Math.round((p - total) * 100) / 100, units: cart.value.reduce((s, c) => s + c.qty, 0) };
});
const quickCash = computed(() => [...new Set([100, 500, 1000, 5000].map((s) => Math.ceil(totals.value.total / s) * s).filter((v) => v > totals.value.total))].slice(0, 3));

function setMethod(m) {
    method.value = m;
    if (m === 'credit') { paid.value = 0; if (!customer.value) toast.fire({ icon: 'info', title: 'Select a customer for credit sale' }); }
    else if (Number(paid.value) === 0) paid.value = '';
}

/* ---------- hold / clear ---------- */
function resetSale() { cart.value = []; customer.value = null; discount.value = ''; paid.value = ''; method.value = 'cash'; }
function hold() {
    if (!cart.value.length) return toast.fire({ icon: 'info', title: 'Cart is empty' });
    held.value.unshift({ cart: cart.value, customer: customer.value, at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) });
    held.value = held.value.slice(0, 10);
    resetSale();
    toast.fire({ icon: 'success', title: 'Sale on hold' });
}
function resume(i) {
    const h = held.value.splice(i, 1)[0];
    if (cart.value.length) held.value.unshift({ cart: cart.value, customer: customer.value, at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) });
    cart.value = h.cart; customer.value = h.customer; heldOpen.value = false;
}
async function clearCart() {
    if (!cart.value.length) return;
    if (await confirm({ title: 'Clear cart?', confirmText: 'Yes, clear' })) resetSale();
}

/* ---------- checkout ---------- */
async function checkout() {
    if (busy.value || !cart.value.length) return;
    const t = totals.value;
    if (t.change < 0 && !customer.value) {
        return Swal.fire({ icon: 'warning', title: 'Payment short', html: `Total <b>${money(t.total)}</b>, paid <b>${money(t.paid)}</b>.<br>Select a customer to sell on credit.` });
    }
    busy.value = true;
    try {
        const { data } = await api.post('/pos/checkout', {
            items: cart.value.map((c) => ({ id: c.id, quantity: c.qty, price: priceEdit.value ? c.price : null })),
            customer_id: customer.value?.id || null,
            discount: t.disc, tax_percent: t.tp, paid: t.paid, payment_method: method.value,
        });
        resetSale();
        loadProducts();
        stockChanged();
        const s = data.data;
        const low = data.low_stock?.length ? `<div class="alert alert-warning small py-2 mt-2 mb-0 text-start"><i class="bi bi-exclamation-triangle"></i> Low stock: ${data.low_stock.map((m) => `${esc(m.name)} (${m.total_stock})`).join(', ')}</div>` : '';
        const r = await Swal.fire({
            icon: 'success', title: 'Sale completed',
            html: `<div class="text-muted small">${esc(s.invoice_no)}</div><div class="display-6 fw-bold text-success">${money(s.change_amount)}</div>
                   <div class="small text-muted">change to return</div>${s.due > 0 ? `<div class="text-danger fw-semibold mt-1">Due added: ${money(s.due)}</div>` : ''}${low}`,
            showDenyButton: true, confirmButtonText: '<i class="bi bi-printer"></i> Print receipt', denyButtonText: 'New sale', confirmButtonColor: '#198754', denyButtonColor: '#6c757d',
        });
        if (r.isConfirmed) window.open(`/print/sale/${s.id}?print=1`, '_blank', 'width=420,height=720');
    } catch (e) {
        loadProducts();
    } finally {
        busy.value = false;
        searchEl.value?.focus();
    }
}

const onKey = (e) => {
    if (Swal.isVisible()) return;
    const map = { F2: () => { searchEl.value?.focus(); searchEl.value?.select(); }, F4: hold, F8: clearCart, F9: checkout };
    if (map[e.key]) { e.preventDefault(); map[e.key](); }
};

onMounted(async () => {
    window.addEventListener('keydown', onKey);
    searchEl.value?.focus();
    loadProducts();
    categories.value = (await api.get('/lookups')).data.categories;
});
onBeforeUnmount(() => { window.removeEventListener('keydown', onKey); controller?.abort(); });
</script>
