<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\CashierSession;
use App\Models\Payment;
use App\Models\SaleReturn;
use App\Models\PurchaseReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashierSessionController extends Controller
{
    public function current(Request $request)
    {
        $session = CashierSession::where('user_id', $request->user()->id)->whereNull('closed_at')->latest('id')->first();
        return response()->json(['data' => $session ? $this->summary($session) : null]);
    }

    public function open(Request $request)
    {
        $data = $request->validate(['opening_cash' => 'required|numeric|min:0|max:999999999', 'note' => 'nullable|string|max:500']);
        $session = DB::transaction(function () use ($request, $data) {
            if (CashierSession::where('user_id', $request->user()->id)->whereNull('closed_at')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['session' => 'You already have an open cashier shift.']);
            }
            $session = CashierSession::create(['user_id' => $request->user()->id, 'opened_at' => now(), 'opening_cash' => $data['opening_cash'], 'closing_note' => $data['note'] ?? null]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'cashier_shift_opened', 'subject_type' => CashierSession::class, 'subject_id' => $session->id, 'new_values' => ['opening_cash' => $data['opening_cash']], 'ip_address' => $request->ip()]);
            return $session;
        });
        return response()->json(['message' => 'Cashier shift opened.', 'data' => $this->summary($session)], 201);
    }

    public function transaction(Request $request)
    {
        $data = $request->validate(['type' => 'required|in:in,out', 'category' => 'required|string|max:60', 'amount' => 'required|numeric|gt:0|max:999999999', 'note' => 'nullable|string|max:255']);
        $session = CashierSession::where('user_id', $request->user()->id)->whereNull('closed_at')->firstOrFail();
        $row = $session->transactions()->create($data + ['user_id' => $request->user()->id]);
        return response()->json(['message' => 'Cash movement recorded.', 'data' => $row], 201);
    }

    public function close(Request $request)
    {
        $data = $request->validate(['counted_cash' => 'required|numeric|min:0|max:999999999', 'closing_note' => 'nullable|string|max:1000']);
        $session = DB::transaction(function () use ($request, $data) {
            $session = CashierSession::where('user_id', $request->user()->id)->whereNull('closed_at')->lockForUpdate()->latest('id')->firstOrFail();
            $totals = $this->totals($session);
            $expected = $totals['expected_cash'];
            $session->update(['closed_at' => now(), 'expected_cash' => $expected, 'counted_cash' => $data['counted_cash'], 'difference' => round((float) $data['counted_cash'] - $expected, 2), 'closing_note' => $data['closing_note'] ?? null]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'cashier_shift_closed', 'subject_type' => CashierSession::class, 'subject_id' => $session->id, 'new_values' => ['expected_cash' => $expected, 'counted_cash' => $data['counted_cash'], 'difference' => $session->difference], 'ip_address' => $request->ip()]);
            return $session->fresh();
        });
        return response()->json(['message' => 'Cashier shift closed.', 'data' => $this->summary($session)]);
    }

    private function summary(CashierSession $session): array
    {
        return array_merge($session->toArray(), $this->totals($session));
    }

    private function totals(CashierSession $session): array
    {
        $sales = $session->sales()->get(['id','payment_method','paid','change_amount','total','due']);
        $cashSales = (float) $sales->where('payment_method', 'cash')->sum('paid');
        $nonCashSales = (float) $sales->whereIn('payment_method', ['card','online'])->sum('paid');
        $creditSales = (float) $sales->where('payment_method', 'credit')->sum('total');
        $cashIn = (float) $session->transactions()->where('type', 'in')->sum('amount');
        $cashOut = (float) $session->transactions()->where('type', 'out')->sum('amount');
        $period = [$session->opened_at, $session->closed_at ?? now()];
        $customerReceipts = (float) Payment::where('party_type', 'customer')->where('method', 'cash')->where('user_id', $session->user_id)->whereBetween('created_at', $period)->sum('amount');
        $supplierPayments = (float) Payment::where('party_type', 'supplier')->where('method', 'cash')->where('user_id', $session->user_id)->whereBetween('created_at', $period)->sum('amount');
        $cashRefunds = (float) SaleReturn::where('refund_method', 'cash')->where('user_id', $session->user_id)->whereBetween('created_at', $period)->sum('refund_amount');
        $supplierCashBack = (float) PurchaseReturn::where('refund_type', 'cash')->where('user_id', $session->user_id)->whereBetween('created_at', $period)->sum('total');
        $opening = (float) $session->opening_cash;
        $expected = round($opening + $cashSales + $cashIn + $customerReceipts + $supplierCashBack - $cashOut - $supplierPayments - $cashRefunds, 2);
        return ['invoice_count' => $sales->count(), 'cash_sales' => $cashSales, 'non_cash_sales' => $nonCashSales, 'credit_sales' => $creditSales,
            'cash_in' => $cashIn, 'cash_out' => $cashOut, 'customer_receipts' => $customerReceipts, 'supplier_payments' => $supplierPayments,
            'cash_refunds' => $cashRefunds, 'supplier_cash_back' => $supplierCashBack, 'opening_cash' => $opening,
            'expected_cash' => $expected, 'transactions' => $session->transactions()->latest()->get()];
    }
}
