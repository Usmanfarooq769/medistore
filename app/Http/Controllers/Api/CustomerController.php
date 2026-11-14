<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\LoyaltyTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BranchContext;
use App\Services\JournalPostingService;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return CustomerResource::collection(
            Customer::withCount('sales')
                ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "{$s}%")))
                ->when($request->boolean('due'), fn ($q) => $q->where('balance', '>', 0))
                ->orderBy('name')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function show(Customer $customer)
    {
        return new CustomerResource($customer);
    }

    public function sales(Customer $customer)
    {
        return SaleResource::collection(Sale::where('customer_id', $customer->id)->where('branch_id', BranchContext::id())->latest('id')->paginate(15));
    }

    public function statement(Customer $customer)
    {
        return response()->json([
            'customer' => new CustomerResource($customer),
            'sales' => Sale::where('customer_id', $customer->id)->where('branch_id', BranchContext::id())->latest('sale_date')->limit(100)->get(['id','invoice_no','sale_date','total','paid','due','payment_status']),
            'payments' => Payment::where('party_type', 'customer')->where('party_id', $customer->id)->where('branch_id', BranchContext::id())->latest('payment_date')->limit(100)->get(),
            'loyalty' => LoyaltyTransaction::where('customer_id', $customer->id)->latest()->limit(100)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $c = Customer::create($this->validated($request));

        return response()->json(['message' => 'Customer added.', 'data' => new CustomerResource($c)], 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer->id));

        return response()->json(['message' => 'Customer updated.']);
    }

    public function destroy(Customer $customer)
    {
        abort_if($customer->balance > 0, 422, 'Customer still owes ' . money($customer->balance) . '.');
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    /** Receive due payment – applied to oldest credit invoices */
    public function payment(Request $request, Customer $customer)
    {
        $branchDue = (float) Sale::where('customer_id', $customer->id)->where('branch_id', BranchContext::id())->sum('due');
        abort_if($branchDue <= 0, 422, 'This customer has no outstanding balance in the active branch.');
        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01|max:' . max($branchDue, 0.01),
            'method'       => 'required|in:cash,card,online',
            'payment_date' => 'required|date',
            'note'         => 'nullable|string|max:255',
        ], ['amount.max' => 'Amount cannot exceed this branch customer due (' . money($branchDue) . ').']);

        $payment = DB::transaction(function () use ($data, $customer) {
            $payment = Payment::create($data + ['party_type' => 'customer', 'party_id' => $customer->id, 'user_id' => auth()->id(), 'branch_id' => BranchContext::id()]);
            $customer->decrement('balance', $data['amount']);

            $left = (float) $data['amount'];
            foreach (Sale::where('customer_id', $customer->id)->where('branch_id', BranchContext::id())->where('due', '>', 0)->orderBy('id')->lockForUpdate()->get() as $sale) {
                if ($left <= 0) {
                    break;
                }
                $pay = min($left, (float) $sale->due);
                $sale->paid += $pay;
                $sale->due -= $pay;
                $sale->payment_status = payment_status((float) $sale->total, (float) $sale->paid);
                $sale->save();

                // Award points as credit invoices are actually paid, without double-counting POS payments.
                $earnRate = max(0, (int) setting('loyalty_earn_per_100', 1));
                $eligiblePaid = min((float) $sale->paid, max(0, (float) $sale->total - (float) $sale->returned_amount));
                $targetEarned = $earnRate > 0 ? (int) floor($eligiblePaid / 100) * $earnRate : 0;
                $netEarned = max(0, (int) $sale->loyalty_points_earned - (int) $sale->loyalty_points_reversed);
                $newPoints = max(0, $targetEarned - $netEarned);
                if ($newPoints > 0) {
                    $customer->increment('loyalty_points', $newPoints);
                    $sale->increment('loyalty_points_earned', $newPoints);
                    LoyaltyTransaction::create(['customer_id'=>$customer->id,'sale_id'=>$sale->id,'user_id'=>auth()->id(),'type'=>'earned','points'=>$newPoints,'balance_after'=>(int)$customer->fresh()->loyalty_points,'note'=>'Earned from payment on invoice '.$sale->invoice_no]);
                }
                $left -= $pay;
            }
            app(JournalPostingService::class)->postPayment($payment);
            return $payment;
        });

        return response()->json(['message' => 'Received ' . money($data['amount']) . '.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:150',
            'phone'   => ['nullable', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($id)],
            'address' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0|max:999999999',
            'insurance_provider_id' => 'nullable|exists:insurance_providers,id',
            'insurance_plan_id' => 'nullable|exists:insurance_plans,id',
            'insurance_member_no' => 'nullable|string|max:100',
            'allergies' => 'nullable|string|max:1000',
            'chronic_conditions' => 'nullable|string|max:1000',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'gender' => 'nullable|in:male,female,other',
            'cnic' => ['nullable', 'regex:/^\d{5}-?\d{7}-?\d$/'],
            'email' => 'nullable|email|max:150',
        ], ['cnic.regex' => 'CNIC must look like 12345-1234567-1.']);
        if (! auth()->user()?->isAdmin()) unset($validated['credit_limit']);
        if (! in_array(auth()->user()?->role, ['admin','branch_manager'], true)) unset($validated['insurance_provider_id'], $validated['insurance_plan_id'], $validated['insurance_member_no']);
        if (!empty($validated['insurance_plan_id']) && empty($validated['insurance_provider_id'])) {
            abort(422, 'Select the insurance provider before assigning a plan.');
        }
        if (!empty($validated['insurance_plan_id']) && !empty($validated['insurance_provider_id'])) {
            abort_unless(\App\Models\InsurancePlan::whereKey($validated['insurance_plan_id'])->where('provider_id',$validated['insurance_provider_id'])->exists(), 422, 'Selected insurance plan does not belong to this provider.');
        }
        return $validated;
    }
}
