<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleReturnRequest;
use App\Http\Resources\SaleReturnResource;
use App\Models\Customer;
use App\Models\MedicineBatch;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CUSTOMER RETURN
 * ------------------------------------------------------------
 * Condition per item:
 *   good    => stock goes BACK into the same batch (sellable again)
 *   damaged => refund given, NOT added to stock (loss)
 *   expired => refund given, NOT added to stock (loss)
 * Refund: total = value - deduction.
 *   If the invoice has credit due, the due is reduced first,
 *   the rest is refunded (cash / card / online).
 */
class SaleReturnController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        $q = SaleReturn::with(['sale:id,invoice_no', 'user:id,name'])->withCount('items')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('return_no', 'like', "%{$s}%")
                ->orWhere('customer_name', 'like', "%{$s}%")
                ->orWhereHas('sale', fn ($x) => $x->where('invoice_no', 'like', "%{$s}%"))))
            ->when($request->from, fn ($q, $d) => $q->where('return_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('return_date', '<=', $d))
            ->when($request->type === 'invoice', fn ($q) => $q->whereNotNull('sale_id'))
            ->when($request->type === 'no_invoice', fn ($q) => $q->whereNull('sale_id'));

        $totals = (clone $q)->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as total, COALESCE(SUM(refund_amount),0) as refunded')->first();

        return SaleReturnResource::collection($q->latest('id')->paginate($request->integer('per_page', 15)))
            ->additional(['totals' => $totals]);
    }

    public function show(SaleReturn $saleReturn)
    {
        return new SaleReturnResource($saleReturn->load(['items', 'sale:id,invoice_no', 'user:id,name']));
    }

    public function store(StoreSaleReturnRequest $request)
    {
        $data = $request->validated();
        $items = collect($data['items'])->filter(fn ($i) => (int) $i['quantity'] > 0)->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Enter a return quantity for at least one medicine.']);
        }

        $return = DB::transaction(fn () => ! empty($data['sale_id'])
            ? $this->returnWithInvoice($data, $items)
            : $this->returnWithoutInvoice($data, $items));

        StockService::flushCache();

        $restocked = $return->items->where('restocked', true)->sum('quantity');

        return response()->json([
            'message' => "Return {$return->return_no} saved. {$restocked} unit(s) added back to stock.",
            'data'    => new SaleReturnResource($return->load('items')),
        ], 201);
    }

    /* ------------------------------------------------------------ */

    private function returnWithInvoice(array $data, $items): SaleReturn
    {
        $sale = Sale::lockForUpdate()->findOrFail($data['sale_id']);
        $return = $this->createHeader($data, $sale->customer_id, $sale->customer_name, $sale->id);

        // refund at NET price (invoice discount + tax shared proportionally)
        $ratio = $sale->subtotal > 0 ? ((float) $sale->total / (float) $sale->subtotal) : 1;
        $subtotal = 0;

        foreach ($items as $row) {
            $item = SaleItem::lockForUpdate()->where('sale_id', $sale->id)->findOrFail($row['sale_item_id']);
            $can = $item->quantity - $item->returned_qty;
            if ($row['quantity'] > $can) {
                throw ValidationException::withMessages(['items' => "Only {$can} unit(s) of {$item->medicine_name} can be returned."]);
            }

            $price = round((float) $item->price * $ratio, 2);
            $lineTotal = round($price * $row['quantity'], 2);
            $subtotal += $lineTotal;

            $batch = $item->medicine_batch_id ? MedicineBatch::find($item->medicine_batch_id) : null;
            $restock = $this->shouldRestock($row['condition'], $batch);

            $return->items()->create([
                'sale_item_id'      => $item->id,
                'medicine_id'       => $item->medicine_id,
                'medicine_batch_id' => $item->medicine_batch_id,
                'medicine_name'     => $item->medicine_name,
                'batch_no'          => $item->batch_no,
                'expiry_date'       => $item->expiry_date,
                'quantity'          => $row['quantity'],
                'price'             => $price,
                'total'             => $lineTotal,
                'condition'         => $restock || $row['condition'] !== 'good' ? $row['condition'] : 'expired',
                'restocked'         => $restock,
            ]);
            $item->increment('returned_qty', $row['quantity']);

            if ($restock && $item->medicine_id) {
                $this->stock->restore($item->medicine_id, $item->medicine_batch_id, (int) $row['quantity'], 'sale_return', $return->return_no, 'Customer return ' . $sale->invoice_no);
            }
        }

        $total = $this->finalize($return, $subtotal, $data);
        $sale->increment('returned_amount', $total);

        // credit invoice => reduce customer's due first
        if ($sale->due > 0 && $sale->customer_id) {
            $adjust = min((float) $sale->due, $total);
            $sale->decrement('due', $adjust);
            Customer::whereKey($sale->customer_id)->decrement('balance', $adjust);
            $return->update(['due_adjusted' => $adjust, 'refund_amount' => round($total - $adjust, 2)]);
        }

        return $return;
    }

    private function returnWithoutInvoice(array $data, $items): SaleReturn
    {
        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
        $return = $this->createHeader($data, $customer?->id, $customer?->name ?? ($data['customer_name'] ?? null), null);
        $subtotal = 0;

        foreach ($items as $row) {
            $medicine = Medicine::findOrFail($row['medicine_id']);
            $batch = MedicineBatch::where('medicine_id', $medicine->id)->findOrFail($row['medicine_batch_id']);

            $lineTotal = round((float) $row['price'] * $row['quantity'], 2);
            $subtotal += $lineTotal;
            $restock = $this->shouldRestock($row['condition'], $batch);

            $return->items()->create([
                'medicine_id'       => $medicine->id,
                'medicine_batch_id' => $batch->id,
                'medicine_name'     => $medicine->display_name,
                'batch_no'          => $batch->batch_no,
                'expiry_date'       => $batch->expiry_date,
                'quantity'          => $row['quantity'],
                'price'             => $row['price'],
                'total'             => $lineTotal,
                'condition'         => $restock || $row['condition'] !== 'good' ? $row['condition'] : 'expired',
                'restocked'         => $restock,
            ]);

            if ($restock) {
                $this->stock->moveBatch(MedicineBatch::lockForUpdate()->find($batch->id), (int) $row['quantity'], 'sale_return', $return->return_no, 'Customer return (no invoice)');
            }
        }

        $this->finalize($return, $subtotal, $data);

        return $return;
    }

    /** Good condition AND batch not expired => back to stock */
    private function shouldRestock(string $condition, ?MedicineBatch $batch): bool
    {
        if ($condition !== 'good') {
            return false;
        }

        return ! $batch || ! $batch->expiry_date || $batch->expiry_date->gte(today());
    }

    private function createHeader(array $data, ?int $customerId, ?string $customerName, ?int $saleId): SaleReturn
    {
        $return = SaleReturn::create([
            'sale_id'       => $saleId,
            'customer_id'   => $customerId,
            'customer_name' => $customerName,
            'return_date'   => today(),
            'refund_method' => $data['refund_method'],
            'reason'        => $data['reason'] ?? null,
            'user_id'       => auth()->id(),
        ]);
        $return->update(['return_no' => 'RET-' . now()->format('Ymd') . '-' . str_pad($return->id, 5, '0', STR_PAD_LEFT)]);

        return $return;
    }

    private function finalize(SaleReturn $return, float $subtotal, array $data): float
    {
        $deduction = min((float) ($data['deduction'] ?? 0), $subtotal);
        $total = round($subtotal - $deduction, 2);

        $return->update([
            'subtotal'      => $subtotal,
            'deduction'     => $deduction,
            'total'         => $total,
            'refund_amount' => $data['refund_method'] === 'none' ? 0 : $total,
        ]);

        return $total;
    }
}
