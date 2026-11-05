<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerReturnRequestController extends Controller
{
    public function index(Request $request)
    {
        $branchId = BranchContext::id();
        $rows = DB::table('customer_return_requests as r')
            ->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->join('sales as s', 's.id', '=', 'r.sale_id')
            ->where('r.branch_id', $branchId)
            ->when($request->filled('status'), fn ($q) => $q->where('r.status', $request->string('status')))
            ->orderByDesc('r.id')
            ->select('r.*', 'c.name as customer_name', 'c.phone as customer_phone', 's.invoice_no')
            ->paginate(25)->withQueryString();

        $rows->getCollection()->transform(function ($row) {
            $row->items = DB::table('customer_return_request_items as ri')
                ->join('sale_items as si', 'si.id', '=', 'ri.sale_item_id')
                ->where('ri.customer_return_request_id', $row->id)
                ->get(['ri.quantity', 'si.medicine_name', 'si.batch_no', 'si.quantity as sold_quantity', 'si.returned_qty']);
            return $row;
        });

        return response()->json($rows);
    }

    public function review(Request $request, int $returnRequest)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'staff_note' => ['nullable', 'string', 'max:1500'],
        ]);

        return DB::transaction(function () use ($data, $returnRequest) {
            $row = DB::table('customer_return_requests')->where('id', $returnRequest)
                ->where('branch_id', BranchContext::id())->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending return requests can be reviewed.']);
            }
            DB::table('customer_return_requests')->where('id', $row->id)->update([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'staff_note' => $data['staff_note'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['message' => 'Return request ' . $data['decision'] . 'd.']);
        });
    }

    /**
     * Staff first creates the actual return in Returns > New Customer Return.
     * This endpoint links that stock/accounting transaction to the customer's request.
     */
    public function complete(Request $request, int $returnRequest)
    {
        $data = $request->validate(['sale_return_id' => ['required', 'integer', 'exists:sale_returns,id']]);

        return DB::transaction(function () use ($data, $returnRequest) {
            $row = DB::table('customer_return_requests')->where('id', $returnRequest)
                ->where('branch_id', BranchContext::id())->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Approve the request before completing it.']);
            }
            $saleReturn = DB::table('sale_returns')->where('id', $data['sale_return_id'])
                ->where('sale_id', $row->sale_id)->where('branch_id', $row->branch_id)->first();
            if (! $saleReturn) {
                throw ValidationException::withMessages(['sale_return_id' => 'The selected return transaction does not belong to this invoice and branch.']);
            }
            if (DB::table('customer_return_requests')->where('sale_return_id', $saleReturn->id)->where('id', '!=', $row->id)->exists()) {
                throw ValidationException::withMessages(['sale_return_id' => 'This return transaction is already linked to another customer request.']);
            }

            $requested = DB::table('customer_return_request_items')->where('customer_return_request_id', $row->id)->get()->keyBy('sale_item_id');
            $processed = DB::table('sale_return_items')->where('sale_return_id', $saleReturn->id)
                ->whereNotNull('sale_item_id')->select('sale_item_id', DB::raw('SUM(quantity) as quantity'))
                ->groupBy('sale_item_id')->get()->keyBy('sale_item_id');
            foreach ($requested as $saleItemId => $line) {
                if ((int) ($processed[$saleItemId]->quantity ?? 0) < (int) $line->quantity) {
                    throw ValidationException::withMessages(['sale_return_id' => 'The actual sale return does not include all quantities requested by the customer.']);
                }
            }

            DB::table('customer_return_requests')->where('id', $row->id)->update([
                'status' => 'completed', 'sale_return_id' => $saleReturn->id, 'updated_at' => now(),
            ]);
            return response()->json(['message' => 'Customer return request linked to return ' . $saleReturn->return_no . '.']);
        });
    }
}
