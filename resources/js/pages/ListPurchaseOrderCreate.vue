
<template>
    <div class="container-fluid">

        <form @submit.prevent="create">

            <!-- Supplier Details -->
            <div class="card custom-card mb-3">
                <div class="card-header justify-content-between flex-wrap">
                    <h5 class="card-title ">Supplier Information</h5>

                    <router-link
                :to="{ name: 'purchase-orders' }"
                class="btn btn-primary"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Back to Purchase Orders
            </router-link>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">
                                Supplier <span class="text-danger">*</span>
                            </label>

                            <select
                                v-model.number="form.supplier_id"
                                class="form-select"
                                required
                            >
                                <option value="">Select supplier</option>
                                <option
                                    v-for="s in suppliers"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Order Date <span class="text-danger">*</span>
                            </label>

                            <input
                                v-model="form.order_date"
                                type="date"
                                class="form-control"
                                required
                            />
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Expected Delivery</label>

                            <input
                                v-model="form.expected_date"
                                type="date"
                                class="form-control"
                            />
                        </div>

                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card custom-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0">Purchase Order Items</h5>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        @click="addLine"
                    >
                        <i class="bi bi-plus-lg me-1"></i>
                        Add Medicine
                    </button>
                </div>

                <div class="card-body">

                    <div
                        v-for="(line, i) in form.items"
                        :key="i"
                        class="border rounded p-3 mb-3"
                    >
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                Medicine #{{ i + 1 }}
                            </h6>

                            <button
                                v-if="form.items.length > 1"
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="form.items.splice(i, 1)"
                            >
                                <i class="bi bi-trash"></i>
                                Remove
                            </button>
                        </div>

                        <div class="row g-3">

                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Medicine <span class="text-danger">*</span></label>

                                <select
                                    v-model.number="line.medicine_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="">Select medicine</option>

                                    <option
                                        v-for="m in medicines"
                                        :key="m.id"
                                        :value="m.id"
                                    >
                                        {{ m.name }} {{ m.strength }}
                                    </option>
                                </select>
                            </div>

                            <div class="col-lg-2 col-md-3">
                                <label class="form-label">Packs <span class="text-danger">*</span></label>

                                <input
                                    v-model.number="line.quantity"
                                    type="number"
                                    min="1"
                                    class="form-control"
                                    required
                                />
                            </div>

                            <div class="col-lg-2 col-md-3">
                                <label class="form-label">Bonus Qty</label>

                                <input
                                    v-model.number="line.bonus_qty"
                                    type="number"
                                    min="0"
                                    class="form-control"
                                />
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">
                                    Manufacturer Scheme
                                </label>

                                <select
                                    v-model="line.scheme_id"
                                    class="form-select"
                                >
                                    <option value="">No Scheme</option>

                                    <option
                                        v-for="s in schemes"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>

                                <small
                                    v-if="line.scheme_id"
                                    class="text-success"
                                >
                                    +{{ schemeFree(line) }} free
                                </small>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Purchase / Pack</label>

                                <input
                                    v-model.number="line.purchase_price"
                                    type="number"
                                    min="0"
                                    step=".01"
                                    class="form-control"
                                />
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Sale / Pack</label>

                                <input
                                    v-model.number="line.sale_price"
                                    type="number"
                                    min="0"
                                    step=".01"
                                    class="form-control"
                                />
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Batch Number</label>

                                <input
                                    v-model="line.batch_no"
                                    class="form-control"
                                />
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Expiry Date</label>

                                <input
                                    v-model="line.expiry_date"
                                    type="date"
                                    class="form-control"
                                />
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            <!-- Pricing and Notes -->
            <div class="card custom-card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Pricing & Additional Details</h5>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-md-3">
                            <label class="form-label">Discount</label>

                            <input
                                v-model.number="form.discount"
                                type="number"
                                min="0"
                                step=".01"
                                class="form-control"
                            />
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tax Amount</label>

                            <input
                                v-model.number="form.tax"
                                type="number"
                                min="0"
                                step=".01"
                                class="form-control"
                            />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Notes</label>

                            <input
                                v-model="form.notes"
                                class="form-control"
                                placeholder="Optional notes"
                            />
                        </div>

                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="card custom-card">
                <div class="card-body d-flex justify-content-end gap-2">

                    <button
                        type="button"
                        class="btn btn-light"
                        @click="goBack"
                        :disabled="saving"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="saving"
                    >
                        <i class="bi bi-check-circle me-1"></i>
                        {{ saving ? "Submitting..." : "Submit for Approval" }}
                    </button>

                </div>
            </div>

        </form>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import api from "../api";
import { Swal } from "../utils/swal";

const router = useRouter();

const suppliers = ref([]);
const medicines = ref([]);
const schemes = ref([]);
const saving = ref(false);

const blankLine = () => ({
    medicine_id: "",
    quantity: 1,
    bonus_qty: 0,
    scheme_id: "",
    purchase_price: 0,
    sale_price: 0,
    batch_no: "",
    expiry_date: "",
});

const form = reactive({
    supplier_id: "",
    order_date: new Date().toISOString().slice(0, 10),
    expected_date: "",
    discount: 0,
    tax: 0,
    notes: "",
    items: [blankLine()],
});

async function loadLookups() {
    const [l, m, s] = await Promise.all([
        api.get("/lookups"),
        api.get("/medicines", {
            params: { per_page: 100 },
        }),
        api.get("/stage4/schemes"),
    ]);

    suppliers.value = l.data.suppliers || [];
    medicines.value = m.data.data || [];

    const today = new Date().toISOString().slice(0, 10);

    schemes.value = (s.data.data || []).filter(
        (x) =>
            x.is_active &&
            x.scheme_type === "buy_x_get_y" &&
            String(x.valid_from).slice(0, 10) <= today &&
            String(x.valid_to).slice(0, 10) >= today
    );
}

function addLine() {
    form.items.push(blankLine());
}

function schemeFree(line) {
    const s = schemes.value.find(
        (x) => Number(x.id) === Number(line.scheme_id)
    );

    if (!s) return 0;

    const n =
        Math.floor(
            Number(line.quantity || 0) /
            Math.max(1, Number(s.buy_quantity) || 1)
        ) * Number(s.free_quantity || 0);

    return s.max_free_quantity
        ? Math.min(n, Number(s.max_free_quantity))
        : n;
}

function goBack() {
    router.push({ name: "purchase-orders" });
}

async function create() {
    if (!form.items.length) {
        await Swal.fire({
            icon: "warning",
            title: "Add at least one medicine",
        });
        return;
    }

    saving.value = true;

    try {
        await api.post("/purchase-orders", form);

        await Swal.fire({
            icon: "success",
            title: "Purchase Order Created",
            text: "Your purchase order has been submitted for approval.",
        });

        router.push({ name: "purchase-orders" });

    } catch (error) {
        await Swal.fire({
            icon: "error",
            title: "Unable to create purchase order",
            text:
                error.response?.data?.message ||
                "Something went wrong. Please try again.",
        });
    } finally {
        saving.value = false;
    }
}

onMounted(loadLookups);
</script>
