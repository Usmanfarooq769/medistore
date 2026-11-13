<template>
    
<!-- Cashier summary cards: visible only when shift is open -->
<div class="row g-3 mb-3" v-if="session">
    <!-- Opening Cash -->
    <div class="col-xl-3 col-md-6">
        <div class="card custom-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-md avatar-rounded bg-primary shadow shadow-primary">
                        <i class="ti ti-shopping-bag fs-5"></i>
                    </span>
                    <div class="flex-fill">
                        <span class="d-block text-muted">Opening Cash</span>
                        <h4 class="fw-semibold mb-0 mt-1">
                            {{ money(session.opening_cash) }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cash Sales -->
    <div class="col-xl-3 col-md-6">
        <div class="card custom-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-md avatar-rounded bg-secondary shadow shadow-secondary">
                        <i class="ti ti-currency-dollar fs-5"></i>
                    </span>
                    <div class="flex-fill">
                        <span class="d-block text-muted">Cash Sales</span>
                        <h4 class="fw-semibold mb-0 mt-1">
                            {{ money(session.cash_sales) }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cash In -->
    <div class="col-xl-3 col-md-6">
        <div class="card custom-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-md avatar-rounded bg-success shadow shadow-success">
                        <i class="ti ti-box fs-5"></i>
                    </span>
                    <div class="flex-fill">
                        <span class="d-block text-muted">Cash In</span>
                        <h4 class="fw-semibold mb-0 mt-1">
                            {{ money(session.cash_in) }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cash Out -->
    <div class="col-xl-3 col-md-6">
        <div class="card custom-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-md avatar-rounded bg-danger shadow shadow-danger">
                        <i class="ti ti-moneybag fs-5"></i>
                    </span>
                    <div class="flex-fill">
                        <span class="d-block text-danger">Cash Out</span>
                        <h4 class="fw-semibold mb-0 mt-1 text-danger">
                            {{
                                money(
                                    session.cash_out +
                                    session.supplier_payments +
                                    session.cash_refunds
                                )
                            }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="row g-3">
        <div :class="session ? 'col-lg-8' : 'col-lg-12'">
            <div class="card custom-card">
                <div class="card-header justify-content-between flex-wrap">
                    <h5 class="card-title">
                        <i class="bi bi-safe me-2"></i>Cashier shift
                    </h5>
                    <span
                        class="badge"
                        :class="session ? 'bg-success' : 'bg-secondary'"
                        >{{ session ? "Shift open" : "No open shift" }}</span
                    >
                </div>
                <div class="card-body" v-if="!session">
                    <p class="text-muted">
                        Start your shift by entering the cash float currently in
                        the drawer.
                    </p>
                    <form class="row g-3" @submit.prevent="openShift">
                        <div class="col-md-12">
                            <label class="form-label">Opening cash</label>
                            <input
                                v-model.number="openingCash"
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control"
                                required
                            />
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" :disabled="busy">
                                Open shift
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-body" v-else>
                    
                    <div
                        class="rounded bg-body-tertiary p-3 d-flex justify-content-between align-items-center"
                    >
                        <span class="fw-semibold">Expected drawer cash</span
                        ><span class="fs-3 fw-bold text-success">{{
                            money(session.expected_cash)
                        }}</span>
                    </div>
                    <div class="row g-2 mt-3">
                        <div class="col-md-6">
                            <form
                                class="border rounded p-3 h-100"
                                @submit.prevent="cashMovement('in')"
                            >
                                <h6>Cash received / float added</h6>
                                <input
                                    v-model.number="cashIn.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="form-control mb-2"
                                    placeholder="Amount"
                                    required
                                /><input
                                    v-model="cashIn.category"
                                    class="form-control mb-2"
                                    placeholder="Reason / category"
                                    required
                                /><input
                                    v-model="cashIn.note"
                                    class="form-control mb-2"
                                    placeholder="Note (optional)"
                                /><button
                                    class="btn btn-outline-primary btn-sm"
                                >
                                    Record cash in
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form
                                class="border rounded p-3 h-100"
                                @submit.prevent="cashMovement('out')"
                            >
                                <h6>Cash expense / paid out</h6>
                                <input
                                    v-model.number="cashOut.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="form-control mb-2"
                                    placeholder="Amount"
                                    required
                                /><input
                                    v-model="cashOut.category"
                                    class="form-control mb-2"
                                    placeholder="Reason / category"
                                    required
                                /><input
                                    v-model="cashOut.note"
                                    class="form-control mb-2"
                                    placeholder="Note (optional)"
                                /><button class="btn btn-outline-danger btn-sm">
                                    Record cash out
                                </button>
                            </form>
                        </div>
                    </div>
                    <hr />
                    <form class="row g-2" @submit.prevent="closeShift">
                        <div class="col-md-5">
                            <label class="form-label"
                                >Counted cash in drawer</label
                            ><input
                                v-model.number="countedCash"
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control"
                                required
                            />
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Closing note</label
                            ><input
                                v-model="closingNote"
                                class="form-control"
                                placeholder="Optional note"
                            />
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button
                                class="btn btn-danger w-100"
                                :disabled="busy"
                            >
                                Close shift
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4" v-if="session">
            <div class="card custom-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="bi bi-receipt me-2"></i> Shift overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Invoices</span><b>{{ session.invoice_count }}</b>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Card / online sales</span
                        ><b>{{ money(session.non_cash_sales) }}</b>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Credit sales</span
                        ><b>{{ money(session.credit_sales) }}</b>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Customer cash receipts</span
                        ><b>{{ money(session.customer_receipts) }}</b>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Cash refunds</span
                        ><b class="text-danger">{{
                            money(session.cash_refunds)
                        }}</b>
                    </div>
                    <hr />
                    <div class="small text-muted">
                        Started
                        {{ new Date(session.opened_at).toLocaleString() }}
                    </div>
                    <h6 class="mt-3">Cash movements</h6>
                    <div
                        v-for="t in session.transactions"
                        :key="t.id"
                        class="d-flex justify-content-between small py-1 border-bottom"
                    >
                        <span>{{ t.category }}</span
                        ><b
                            :class="
                                t.type === 'in' ? 'text-success' : 'text-danger'
                            "
                            >{{ t.type === "in" ? "+" : "-"
                            }}{{ money(t.amount) }}</b
                        >
                    </div>
                    <div
                        v-if="!session.transactions?.length"
                        class="text-muted small"
                    >
                        No manual cash movements.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { onMounted, ref } from "vue";
import api from "../api";
import { money } from "../utils/format";
import { Swal } from "../utils/swal";
const session = ref(null),
    openingCash = ref(0),
    countedCash = ref(""),
    closingNote = ref(""),
    busy = ref(false);
const cashIn = ref({ amount: "", category: "Cash received", note: "" }),
    cashOut = ref({ amount: "", category: "Expense", note: "" });
async function load() {
    const { data } = await api.get("/cashier-session/current");
    session.value = data.data;
}
async function openShift() {
    busy.value = true;
    try {
        await api.post("/cashier-session/open", {
            opening_cash: openingCash.value,
        });
        await load();
    } finally {
        busy.value = false;
    }
}
async function cashMovement(type) {
    const form = type === "in" ? cashIn.value : cashOut.value;
    busy.value = true;
    try {
        await api.post("/cashier-session/transaction", { ...form, type });
        form.amount = "";
        form.note = "";
        await load();
    } finally {
        busy.value = false;
    }
}
async function closeShift() {
    const r = await Swal.fire({
        icon: "warning",
        title: "Close cashier shift?",
        text: "You will not be able to add sales to this shift after closing.",
        showCancelButton: true,
        confirmButtonText: "Close shift",
    });
    if (!r.isConfirmed) return;
    busy.value = true;
    try {
        const { data } = await api.post("/cashier-session/close", {
            counted_cash: countedCash.value,
            closing_note: closingNote.value,
        });
        session.value = null;
        await Swal.fire({
            icon: "success",
            title: "Shift closed",
            html: `Expected cash: <b>${money(data.data.expected_cash)}</b><br>Counted cash: <b>${money(data.data.counted_cash)}</b><br>Difference: <b>${money(data.data.difference)}</b>`,
        });
    } finally {
        busy.value = false;
    }
}
onMounted(load);
</script>
