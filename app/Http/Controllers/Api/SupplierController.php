<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Distributor / wholesaler the store BUYS stock from */
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        return SupplierResource::collection(
            Supplier::withCount('purchases')
                ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")->orWhere('contact_person', 'like', "%{$s}%")))
                ->when($request->boolean('due'), fn ($q) => $q->where('balance', '>', 0))
                ->orderBy('name')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function show(Supplier $supplier)
    {
        return response()->json([
            'data'    => new SupplierResource($supplier),
            'summary' => [
                'purchases' => (float) Purchase::where('supplier_id', $supplier->id)->sum('total'),
                'paid'      => (float) Payment::where('party_type', 'supplier')->where('party_id', $supplier->id)->sum('amount'),
                'returns'   => (float) PurchaseReturn::where('supplier_id', $supplier->id)->sum('total'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['balance'] = $data['opening_balance'] ?? 0;

        return response()->json(['message' => 'Supplier added.', 'data' => new SupplierResource(Supplier::create($data))], 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validated($request);
        $data['balance'] = (float) $supplier->balance + ((float) ($data['opening_balance'] ?? 0) - (float) $supplier->opening_balance);
        $supplier->update($data);

        return response()->json(['message' => 'Supplier updated.']);
    }

    public function destroy(Supplier $supplier)
    {
        abort_if($supplier->purchases()->exists(), 422, 'Supplier has purchase history and cannot be deleted.');
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted.']);
    }

    /** Ledger: purchases (Dr), payments & returns (Cr) */
    public function ledger(Supplier $supplier)
    {
        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->selectRaw("id, purchase_date as date, 'purchase' as type, reference_no as ref, supplier_invoice_no as note, total as debit, 0 as credit");
        $payments = Payment::where('party_type', 'supplier')->where('party_id', $supplier->id)
            ->selectRaw("id, payment_date as date, 'payment' as type, method as ref, note, 0 as debit, amount as credit");
        $returns = PurchaseReturn::where('supplier_id', $supplier->id)->where('refund_type', 'adjust_balance')
            ->selectRaw("id, return_date as date, 'return' as type, return_no as ref, reason as note, 0 as debit, total as credit");

        return $purchases->unionAll($payments)->unionAll($returns)
            ->orderByDesc('date')->orderByDesc('id')->paginate(20);
    }

    /** Pay supplier – applied to oldest unpaid purchases first */
    public function payment(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01|max:' . max((float) $supplier->balance, 0.01),
            'method'       => 'required|in:cash,bank,cheque,online',
            'payment_date' => 'required|date',
            'note'         => 'nullable|string|max:255',
        ], ['amount.max' => 'Amount cannot be more than due balance (' . money($supplier->balance) . ').']);

        DB::transaction(function () use ($data, $supplier) {
            Payment::create($data + ['party_type' => 'supplier', 'party_id' => $supplier->id, 'user_id' => auth()->id()]);
            $supplier->decrement('balance', $data['amount']);

            $left = (float) $data['amount'];
            foreach (Purchase::where('supplier_id', $supplier->id)->where('due', '>', 0)->orderBy('purchase_date')->lockForUpdate()->get() as $p) {
                if ($left <= 0) {
                    break;
                }
                $pay = min($left, (float) $p->due);
                $p->paid += $pay;
                $p->due -= $pay;
                $p->payment_status = payment_status((float) $p->total, (float) $p->paid);
                $p->save();
                $left -= $pay;
            }
        });

        return response()->json(['message' => 'Payment of ' . money($data['amount']) . ' saved.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'            => 'required|string|max:150',
            'contact_person'  => 'nullable|string|max:120',
            'phone'           => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:150',
            'address'         => 'nullable|string|max:255',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);
    }
}
