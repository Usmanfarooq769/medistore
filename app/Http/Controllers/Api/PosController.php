<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\MedicineResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\Prescription;
use App\Models\TaxProfile;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\LoyaltyTransaction;
use App\Services\BranchContext;
use App\Services\JournalPostingService;
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
                ->where(fn ($w) => $w->where('stock_units_are_base', true)->orWhere('pack_size', '<=', 1))
                ->sellable()
                ->withSellableStock()
                ->withNearestExpiry()
                ->search($request->q)
                ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
                ->orderBy('name')
                ->simplePaginate(24)
        );
    }

    /** Exact barcode lookup used by USB/Bluetooth HID scanners. */
    public function scan(string $barcode)
    {
        $medicine = Medicine::where('barcode', $barcode)->where('is_active', true)->withSellableStock()->withNearestExpiry()->first();
        if (! $medicine) return response()->json(['message' => 'No active medicine found for this barcode.'], 404);
        if (! $medicine->stock_units_are_base && (int) $medicine->pack_size > 1) return response()->json(["message" => "Convert this medicine's legacy stock to individual units before selling."], 422);
        if ((int) ($medicine->sellable_stock ?? 0) < 1) return response()->json(['message' => 'This medicine has no sellable stock.'], 422);
        return new MedicineResource($medicine);
    }

    public function checkout(CheckoutRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $allowPriceEdit = $user->isAdmin() || setting('cashier_price_edit', '0') === '1';
        $medicineIds = collect($data['items'])->pluck('id')->unique();
        $restrictedIds = Medicine::whereIn('id', $medicineIds)->where('requires_prescription', true)->pluck('id');
        $prescription = null;
        if ($restrictedIds->isNotEmpty()) {
            if (empty($data['prescription_id'])) throw ValidationException::withMessages(['prescription_id' => 'An approved prescription is required for one or more medicines in this sale.']);
            $prescription = Prescription::with('items')->whereKey($data['prescription_id'])->where('status', 'approved')->first();
            if (!$prescription) throw ValidationException::withMessages(['prescription_id' => 'Selected prescription is not approved or is unavailable.']);
            $covered = $prescription->items->pluck('medicine_id')->filter()->map(fn($id)=>(int)$id);
            if ($restrictedIds->diff($covered)->isNotEmpty()) throw ValidationException::withMessages(['prescription_id' => 'The selected prescription does not include every restricted medicine in this cart.']);
            if ($prescription->customer_id && (int) ($data['customer_id'] ?? 0) !== (int) $prescription->customer_id) throw ValidationException::withMessages(['prescription_id' => 'Select the customer linked to this prescription before checkout.']);
            foreach ($restrictedIds as $restrictedId) {
                $medicine = Medicine::find($restrictedId); $prescribedQty = (float) $prescription->items->where('medicine_id', $restrictedId)->sum('quantity');
                $requestedQty = collect($data['items'])->where('id', $restrictedId)->sum(fn($row) => (float)$row['quantity'] * (($row['sale_unit'] ?? 'pack') === 'pack' ? max(1,(int)$medicine->pack_size) : 1));
                if ($prescribedQty > 0 && $requestedQty > $prescribedQty) throw ValidationException::withMessages(['prescription_id' => "Sale quantity for {$medicine->name} exceeds the prescribed individual-unit quantity ({$prescribedQty})."]);
            }
        }
        $taxProfile = !empty($data['tax_profile_id']) ? TaxProfile::where('is_active', true)->findOrFail($data['tax_profile_id']) : null;

        $lines = collect($data['items'])->groupBy(fn ($row) => $row['id'].'|'.($row['sale_unit'] ?? 'pack'))->map(fn ($g) => [
            'id' => (int) $g->first()['id'],
            'sale_unit' => $g->first()['sale_unit'] ?? 'pack',
            'qty' => (int) $g->sum('quantity'),
            'price' => $g->first()['price'] ?? null,
        ]);

        $sale = DB::transaction(function () use ($data, $lines, $allowPriceEdit, $user, $prescription, $taxProfile) {
            $onlineOrder = null;
            $onlineOrderItems = collect();
            if (! empty($data['online_order_id'])) {
                $onlineOrder = DB::table('online_orders')->where('id', $data['online_order_id'])->where('branch_id', BranchContext::id())->lockForUpdate()->first();
                if (! $onlineOrder || $onlineOrder->status !== 'confirmed' || $onlineOrder->sale_id) {
                    throw ValidationException::withMessages(['online_order_id' => 'This online order is not available for POS fulfilment.']);
                }
                $requested = DB::table('online_order_items')->where('online_order_id', $onlineOrder->id)->get()->keyBy('medicine_id');
                $onlineOrderItems = $requested;
                $entered = $lines->groupBy('id')->map(function ($group, $medicineId) {
                    $medicine = Medicine::findOrFail($medicineId);
                    return (int) $group->sum(fn ($line) => (int) $line['qty'] * ($line['sale_unit'] === 'pack' ? max(1, (int) $medicine->pack_size) : 1));
                });
                if ($requested->count() !== $entered->count()) throw ValidationException::withMessages(['online_order_id' => 'POS cart must exactly match the confirmed online order.']);
                foreach ($requested as $medicineId => $row) {
                    if ((int) ($entered[$medicineId] ?? 0) !== (int) $row->quantity) throw ValidationException::withMessages(['online_order_id' => 'POS quantities must exactly match the online order.']);
                }
                if (! empty($onlineOrder->customer_id)) $data['customer_id'] = $onlineOrder->customer_id;
                $data['customer_name'] = $onlineOrder->customer_name;
                $data['customer_phone'] = $onlineOrder->customer_phone;
            }
            if ($prescription) { $prescription = Prescription::with('items')->lockForUpdate()->findOrFail($prescription->id); if ($prescription->status !== 'approved') throw ValidationException::withMessages(['prescription_id' => 'Prescription has already been dispensed or is no longer approved.']); }
            $session = \App\Models\CashierSession::where('branch_id', BranchContext::id())->where('user_id', $user->id)->whereNull('closed_at')->latest('id')->first();
            if ($user->role === 'cashier' && ! $session) throw ValidationException::withMessages(['cashier_session' => 'Open your cashier shift before taking sales.']);
            $sale = Sale::create(['sale_date' => today(), 'user_id' => $user->id, 'branch_id' => BranchContext::id(), 'cashier_session_id' => $session?->id, 'payment_method' => $data['payment_method'], 'prescription_id' => $prescription?->id, 'tax_profile_id' => $taxProfile?->id]);
            $invoice = 'INV-' . now()->format('Ymd') . '-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT);
            $subtotal = 0;

            foreach ($lines as $line) {
                $medicine = Medicine::lockForUpdate()->findOrFail($line['id']);
                $packSize = max(1, (int) ($medicine->pack_size ?? 1));
                $factor = $line['sale_unit'] === 'pack' ? $packSize : 1;
                $baseQty = $line['qty'] * $factor;
                foreach ($this->stock->deductFefo($medicine, $baseQty, $invoice, $onlineOrder?->id) as [$batch, $qty]) {
                    $enteredPrice = $onlineOrder
                        ? (float) ($onlineOrderItems[$medicine->id]->unit_price ?? $batch->sale_price) * $factor
                        : ($allowPriceEdit && $line['price'] !== null ? (float) $line['price'] : (float) $batch->sale_price * $factor);
                    $price = $enteredPrice / $factor;
                    $total = round($price * $qty, 2);
                    $subtotal += $total;

                    $sale->items()->create([
                        'medicine_id'       => $medicine->id,
                        'medicine_batch_id' => $batch->id,
                        'medicine_name'     => $medicine->display_name,
                        'batch_no'          => $batch->batch_no,
                        'expiry_date'       => $batch->expiry_date,
                        'quantity'          => $qty,
                        'sale_unit'         => $line['sale_unit'] === 'pack' ? 'pack' : 'unit',
                        'sale_quantity'     => round($qty / $factor, 3),
                        'pack_size'         => $packSize,
                        'price'             => $price,
                        'cost_price'        => $batch->purchase_price,
                        'total'             => $total,
                    ]);
                }
            }

            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::lockForUpdate()->find($data['customer_id']);
            } elseif (! empty($data['customer_phone'])) {
                $customer = Customer::firstOrCreate(['phone' => $data['customer_phone']], ['name' => $data['customer_name'] ?: 'Customer']);
                $customer = Customer::lockForUpdate()->find($customer->id);
            }

            $manualDiscount = min((float) ($data['discount'] ?? 0), $subtotal);
            $maxDisc = (float) setting('max_discount_percent', 100);
            if (! $user->isAdmin() && $subtotal > 0 && ($manualDiscount / $subtotal * 100) > $maxDisc + 0.001) {
                throw ValidationException::withMessages(['discount' => "Maximum allowed manual discount is {$maxDisc}%."]);
            }

            $promotion = null;
            $promotionDiscount = 0.0;
            if (! empty($data['promotion_code'])) {
                $promotion = Promotion::where('code', strtoupper(trim($data['promotion_code'])))->lockForUpdate()->first();
                if (! $promotion || ! $promotion->is_active || ($promotion->starts_at && $promotion->starts_at->isFuture()) || ($promotion->ends_at && $promotion->ends_at->isPast())) {
                    throw ValidationException::withMessages(['promotion_code' => 'This promotion code is invalid or inactive.']);
                }
                if ($subtotal < (float) $promotion->minimum_subtotal) throw ValidationException::withMessages(['promotion_code' => 'Minimum purchase for this promotion is ' . money($promotion->minimum_subtotal) . '.']);
                if ($promotion->usage_limit !== null && $promotion->used_count >= $promotion->usage_limit) throw ValidationException::withMessages(['promotion_code' => 'This promotion has reached its usage limit.']);
                if ($promotion->per_customer_limit && ! $customer) throw ValidationException::withMessages(['promotion_code' => 'Select a customer to use this coupon.']);
                if ($promotion->per_customer_limit && $customer) {
                    $used = PromotionRedemption::where('promotion_id', $promotion->id)->where('customer_id', $customer->id)->count();
                    if ($used >= $promotion->per_customer_limit) throw ValidationException::withMessages(['promotion_code' => 'This customer has already used this promotion the maximum number of times.']);
                }
                $promotionDiscount = $promotion->discount_type === 'percentage'
                    ? $subtotal * ((float) $promotion->discount_value / 100)
                    : (float) $promotion->discount_value;
                if ($promotion->maximum_discount !== null) $promotionDiscount = min($promotionDiscount, (float) $promotion->maximum_discount);
                $promotionDiscount = min($promotionDiscount, max(0, $subtotal - $manualDiscount));
            }

            $redeemPoints = (int) ($data['redeem_points'] ?? 0);
            $pointValue = max(0.01, (float) setting('loyalty_point_value', 1));
            if ($redeemPoints > 0 && ! $customer) throw ValidationException::withMessages(['redeem_points' => 'Select a customer to redeem loyalty points.']);
            if ($customer && $redeemPoints > 0 && $redeemPoints > (int) $customer->loyalty_points) throw ValidationException::withMessages(['redeem_points' => 'Customer does not have enough loyalty points.']);
            $availableForPoints = max(0, $subtotal - $manualDiscount - $promotionDiscount);
            if (($redeemPoints * $pointValue) > $availableForPoints + 0.001) throw ValidationException::withMessages(['redeem_points' => 'Loyalty redemption cannot exceed the remaining item subtotal.']);
            $loyaltyDiscount = $redeemPoints * $pointValue;
            $discount = min($subtotal, $manualDiscount + $promotionDiscount + $loyaltyDiscount);

            $taxPercent = $taxProfile ? (float) $taxProfile->rate : (float) ($data['tax_percent'] ?? 0);
            $taxable = $subtotal - $discount;
            if ($taxProfile && $taxProfile->calculation === 'inclusive') {
                $tax = $taxPercent > 0 ? round($taxable - ($taxable / (1 + ($taxPercent / 100))), 2) : 0;
                $total = round($taxable, 2);
            } else {
                $tax = round($taxable * $taxPercent / 100, 2);
                $total = round($taxable + $tax, 2);
            }
            $paid  = (float) $data['paid'];
            $due   = max($total - $paid, 0);

            if ($due > 0 && ! $customer) throw ValidationException::withMessages(['customer_id' => 'Select or add a customer to sell on credit (due).']);
            if ($due > 0 && $customer && $customer->credit_limit !== null && ((float) $customer->balance + $due) > (float) $customer->credit_limit + 0.001) {
                throw ValidationException::withMessages(['customer_id' => 'Credit limit exceeded. Available credit: ' . money(max(0, (float) $customer->credit_limit - (float) $customer->balance)) . '.']);
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
                'initial_paid'   => min($paid, $total),
                'due'            => $due,
                'change_amount'  => max($paid - $total, 0),
                'payment_status' => payment_status($total, min($paid, $total)),
                'note'           => $data['note'] ?? null,
                'prescription_id' => $prescription?->id,
                'tax_profile_id' => $taxProfile?->id,
                'promotion_id' => $promotion?->id,
                'loyalty_points_redeemed' => $redeemPoints,
            ]);

            if (\Illuminate\Support\Facades\Schema::hasTable('controlled_medicine_register')) {
                $controlledItems = DB::table('sale_items as si')->join('medicines as m','m.id','=','si.medicine_id')
                    ->where('si.sale_id',$sale->id)->where('m.requires_controlled_tracking',true)->select('si.*')->get();
                foreach ($controlledItems as $controlledItem) {
                    DB::table('controlled_medicine_register')->insert([
                        'branch_id'=>BranchContext::id(),'medicine_id'=>$controlledItem->medicine_id,'batch_id'=>$controlledItem->medicine_batch_id,
                        'transaction_type'=>'dispense','quantity_change'=>-((int)$controlledItem->quantity),'reference_no'=>$invoice,
                        'customer_id'=>$customer?->id,'prescription_id'=>$prescription?->id,'reason'=>'Dispensed on POS invoice '.$invoice,
                        'recorded_by'=>$user->id,'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }

            if ($due > 0) {
                $customer->increment('balance', $due);
            }
            if ($promotion) {
                $promotion->increment('used_count');
                PromotionRedemption::create(['promotion_id' => $promotion->id, 'sale_id' => $sale->id, 'customer_id' => $customer?->id, 'discount_amount' => $promotionDiscount]);
            }
            if ($customer && $redeemPoints > 0) {
                $customer->decrement('loyalty_points', $redeemPoints);
                LoyaltyTransaction::create(['customer_id' => $customer->id, 'sale_id' => $sale->id, 'user_id' => $user->id, 'type' => 'redeemed', 'points' => $redeemPoints, 'balance_after' => (int) $customer->fresh()->loyalty_points, 'note' => 'Redeemed on invoice ' . $invoice]);
            }
            $earnRate = max(0, (int) setting('loyalty_earn_per_100', 1));
            $earnedPoints = $customer && $earnRate > 0 ? (int) floor(min($paid, $total) / 100) * $earnRate : 0;
            if ($customer && $earnedPoints > 0) {
                $customer->increment('loyalty_points', $earnedPoints);
                $balanceAfter = (int) $customer->fresh()->loyalty_points;
                $sale->update(['loyalty_points_earned' => $earnedPoints]);
                LoyaltyTransaction::create(['customer_id' => $customer->id, 'sale_id' => $sale->id, 'user_id' => $user->id, 'type' => 'earned', 'points' => $earnedPoints, 'balance_after' => $balanceAfter, 'note' => 'Earned on invoice ' . $invoice]);
            }
            if ($prescription) {
                $prescription->update(['status' => 'dispensed']);
                if (\Illuminate\Support\Facades\Schema::hasTable('prescription_refills')) {
                    DB::table('prescription_refills')->where('approved_prescription_id',$prescription->id)->where('status','approved')->update(['status'=>'dispensed','updated_at'=>now()]);
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('prescription_events')) {
                    DB::table('prescription_events')->insert(['prescription_id'=>$prescription->id,'event'=>'dispensed_on_pos','notes'=>'Dispensed on invoice '.$invoice,'user_id'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
                }
            }
            app(JournalPostingService::class)->postSale($sale->load('items'));

            if ($onlineOrder) {
                DB::table('online_order_reservations')->where('online_order_id', $onlineOrder->id)->where('status', 'active')->update(['status' => 'consumed', 'quantity' => 0, 'updated_at' => now()]);
                DB::table('online_orders')->where('id', $onlineOrder->id)->update(['sale_id' => $sale->id, 'status' => 'ready', 'updated_at' => now()]);
            }

            return $sale;
        });

        StockService::flushCache();

        return response()->json([
            'message'   => 'Sale completed.',
            'data'      => new SaleResource($sale),
            'low_stock' => Medicine::whereIn('id', $lines->pluck('id'))->withSellableStock()->withSum(['batches as branch_stock' => fn($b) => $b->where('branch_id', BranchContext::id())], 'quantity')->lowStock()->get(['id', 'name', 'strength', 'total_stock', 'branch_stock', 'sellable_stock'])->map(function($m){$m->total_stock=(int)$m->branch_stock;return $m;}),
        ], 201);
    }
}
