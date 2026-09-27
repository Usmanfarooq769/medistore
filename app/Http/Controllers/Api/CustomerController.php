<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return SaleResource::collection(Sale::where('customer_id', $customer->id)->latest('id')->paginate(15));
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
        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01|max:' . max((float) $customer->balance, 0.01),
            'method'       => 'required|in:cash,card,online',
            'payment_date' => 'required|date',
            'note'         => 'nullable|string|max:255',
        ], ['amount.max' => 'Amount cannot be more than due balance (' . money($customer->balance) . ').']);

        DB::transaction(function () use ($data, $customer) {
            Payment::create($data + ['party_type' => 'customer', 'party_id' => $customer->id, 'user_id' => auth()->id()]);
            $customer->decrement('balance', $data['amount']);

            $left = (float) $data['amount'];
            foreach (Sale::where('customer_id', $customer->id)->where('due', '>', 0)->orderBy('id')->lockForUpdate()->get() as $sale) {
                if ($left <= 0) {
                    break;
                }
                $pay = min($left, (float) $sale->due);
                $sale->paid += $pay;
                $sale->due -= $pay;
                $sale->payment_status = payment_status((float) $sale->total, (float) $sale->paid);
                $sale->save();
                $left -= $pay;
            }
        });

        return response()->json(['message' => 'Received ' . money($data['amount']) . '.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'    => 'required|string|max:150',
            'phone'   => ['nullable', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($id)],
            'address' => 'nullable|string|max:255',
        ]);
    }
}
