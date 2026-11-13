
<template>
    <div class="container-fluid">

        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <h5 class="card-title mb-0">Purchase Order List</h5>
                
                <router-link
                :to="{ name: 'purchase-orders.create' }"
                class="btn btn-primary"
            >
                <i class="bi bi-plus-lg me-1"></i>
                New Purchase Order
            </router-link>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-bordered">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Supplier</th>
                                <th>Branch</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="o in rows" :key="o.id">
                                <td><b>{{ o.order_no }}</b></td>
                                <td>{{ o.supplier?.name }}</td>
                                <td>{{ o.branch?.name }}</td>
                                <td>{{ o.order_date }}</td>
                                <td>{{ money(o.total) }}</td>

                                <td>
                                    <span
                                        class="badge"
                                        :class="
                                            o.status === 'approved'
                                                ? 'bg-success'
                                                : o.status === 'rejected'
                                                  ? 'bg-danger'
                                                  : 'bg-secondary'
                                        "
                                    >
                                        {{ o.status }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <button
                                        v-if="o.status === 'pending_approval'"
                                        class="btn btn-sm btn-outline-primary me-1"
                                        @click="review(o, 'approve')"
                                    >
                                        Approve
                                    </button>

                                    <button
                                        v-if="o.status === 'pending_approval'"
                                        class="btn btn-sm btn-outline-danger me-1"
                                        @click="review(o, 'reject')"
                                    >
                                        Reject
                                    </button>

                                    <button
                                        v-if="o.status === 'approved'"
                                        class="btn btn-sm btn-primary"
                                        @click="receive(o)"
                                    >
                                        Receive Stock
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!rows.length">
                                <td colspan="7" class="text-center text-muted py-4">
                                    No purchase orders found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer small text-muted">
                Receiving creates a normal purchase, updates supplier balance
                and creates FEFO batches. A purchase order alone does not change stock.
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import api from "../api";
import { money } from "../utils/format";
import { Swal } from "../utils/swal";

const router = useRouter();

const rows = ref([]);

async function load() {
    const { data } = await api.get("/purchase-orders");
    rows.value = data.data || [];
}

async function review(o, decision) {
    const r = await Swal.fire({
        title: `${decision} ${o.order_no}?`,
        input: "text",
        inputLabel: "Optional note",
        showCancelButton: true,
        confirmButtonText: "Confirm",
    });

    if (!r.isConfirmed) return;

    await api.post(`/purchase-orders/${o.id}/review`, {
        decision,
        note: r.value,
    });

    await load();
}

async function receive(o) {
    const r = await Swal.fire({
        title: "Receive purchase order",
        text: "Enter amount paid now. Remaining amount becomes supplier payable.",
        input: "number",
        inputValue: 0,
        inputAttributes: { min: 0, step: ".01" },
        showCancelButton: true,
        confirmButtonText: "Receive stock",
    });

    if (!r.isConfirmed) return;

    await api.post(`/purchase-orders/${o.id}/receive`, {
        paid: Number(r.value) || 0,
        payment_method: "cash",
    });

    await load();
}

onMounted(load);
</script>
