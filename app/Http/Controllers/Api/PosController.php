<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\MedicineResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Sale;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** SELL: cart -> FEFO stock deduction -> sale + receipt */
class PosController extends Controller
{
    public function __construct(private StockService $stock) {}

    /** Product grid – only sellable (non-expired) stock, simplePaginate = no COUNT query */
    public function products(Request $request)
    {
        return MedicineResource::collection(
            Medicine::query()
                ->where('is_active', true)
                ->sellable()
                ->withSellableStock()
                ->withNearestExpiry()
                ->search($request->q)
                ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
                ->orderBy('name')
                ->simplePaginate(24)
        );
    }

    public function checkout(CheckoutRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $allowPriceEdit = $user->isAdmin() || setting('cashier_price_edit', '0') === '1';

        $lines = collect($data['items'])->groupBy('id')->map(fn ($g) => [
            'qty'   => (int) $g->sum('quantity'),
            'price' => $g->first()['price'] ?? null,
        ]);

        $sale = DB::transaction(function () use ($data, $lines, $allowPriceEdit, $user) {
            $sale = Sale::create(['sale_date' => today(), 'user_id' => $user->id, 'payment_method' => $data['payment_method']]);
            $invoice = 'INV-' . now()->format('Ymd') . '-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT);
            $subtotal = 0;

            foreach ($lines as $medicineId => $line) {
                $medicine = Medicine::lockForUpdate()->findOrFail($medicineId);

                foreach ($this->stock->deductFefo($medicine, $line['qty'], $invoice) as [$batch, $qty]) {
                    $price = $allowPriceEdit && $line['price'] !== null ? (float) $line['price'] : (float) $batch->sale_price;
                    $total = round($price * $qty, 2);
                    $subtotal += $total;

                    $sale->items()->create([
                        'medicine_id'       => $medicine->id,
                        'medicine_batch_id' => $batch->id,
                        'medicine_name'     => $medicine->display_name,
                        'batch_no'          => $batch->batch_no,
                        'expiry_date'       => $batch->expiry_date,
                        'quantity'          => $qty,
                        'price'             => $price,
                        'cost_price'        => $batch->purchase_price,
                        'total'             => $total,
                    ]);
                }
            }

            $discount = min((float) ($data['discount'] ?? 0), $subtotal);
            $maxDisc = (float) setting('max_discount_percent', 100);
            if (! $user->isAdmin() && $subtotal > 0 && ($discount / $subtotal * 100) > $maxDisc + 0.001) {
                throw ValidationException::withMessages(['discount' => "Maximum allowed discount is {$maxDisc}%."]);
            }

            $taxPercent = (float) ($data['tax_percent'] ?? 0);
            $tax   = round(($subtotal - $discount) * $taxPercent / 100, 2);
            $total = round($subtotal - $discount + $tax, 2);
            $paid  = (float) $data['paid'];
            $due   = max($total - $paid, 0);

            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::lockForUpdate()->find($data['customer_id']);
            } elseif (! empty($data['customer_phone'])) {
                $customer = Customer::firstOrCreate(['phone' => $data['customer_phone']], ['name' => $data['customer_name'] ?: 'Customer']);
            }
            if ($due > 0 && ! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'Select or add a customer to sell on credit (due).']);
            }

            $sale->update([
                'invoice_no'     => $invoice,
                'customer_id'    => $customer?->id,
                'customer_name'  => $customer?->name ?? ($data['customer_name'] ?? null),
                'customer_phone' => $customer?->phone ?? ($data['customer_phone'] ?? null),
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'tax_percent'    => $taxPercent,
                'tax'            => $tax,
                'total'          => $total,
                'paid'           => min($paid, $total),
                'due'            => $due,
                'change_amount'  => max($paid - $total, 0),
                'payment_status' => payment_status($total, min($paid, $total)),
                'note'           => $data['note'] ?? null,
            ]);

            if ($due > 0) {
                $customer->increment('balance', $due);
            }

            return $sale;
        });

        StockService::flushCache();

        return response()->json([
            'message'   => 'Sale completed.',
            'data'      => new SaleResource($sale),
            'low_stock' => Medicine::whereIn('id', $lines->keys())->lowStock()->get(['id', 'name', 'strength', 'total_stock']),
        ], 201);
    }
}
