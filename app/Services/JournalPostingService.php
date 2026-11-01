<?php
namespace App\Services;

use App\Models\{AccountingAccount,BranchStockTransfer,Expense,JournalEntry,Payment,Purchase,PurchaseReturn,Sale,SaleReturn,StockAdjustment,TaxProfile};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    private function account(string $code): int
    {
        $id = AccountingAccount::where('code', $code)->value('id');
        if (!$id) throw new \RuntimeException("Accounting account {$code} is missing. Re-run the pharmacy module migration or restore the chart of accounts.");
        return (int) $id;
    }

    private function line(string $code, float $debit = 0, float $credit = 0, ?string $description = null): array
    {
        return ['account_id' => $this->account($code), 'debit' => round($debit, 2), 'credit' => round($credit, 2), 'description' => $description];
    }

    private function post(string $sourceType, int $sourceId, int $branchId, string $date, string $narration, array $lines): ?JournalEntry
    {
        $lines = array_values(array_filter($lines, fn ($line) => ($line['debit'] ?? 0) > 0 || ($line['credit'] ?? 0) > 0));
        if (count($lines) < 2) return null;
        $debit = round(collect($lines)->sum('debit'), 2);
        $credit = round(collect($lines)->sum('credit'), 2);
        if ($debit <= 0 || abs($debit - $credit) > 0.01) {
            throw ValidationException::withMessages(['accounting' => "Automatic journal for {$sourceType} #{$sourceId} is not balanced ({$debit} / {$credit})."]);
        }

        return DB::transaction(function () use ($sourceType, $sourceId, $branchId, $date, $narration, $lines) {
            $existing = JournalEntry::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
            if ($existing) return $existing;
            $entry = JournalEntry::create([
                'entry_no' => 'AUTO-' . now()->format('Ymd') . '-' . strtoupper(str()->random(6)),
                'branch_id' => $branchId, 'entry_date' => $date, 'narration' => $narration,
                'source_type' => $sourceType, 'source_id' => $sourceId, 'status' => 'posted',
                'created_by' => auth()->id(), 'posted_at' => now(),
            ]);
            foreach ($lines as $line) $entry->lines()->create($line);
            return $entry;
        });
    }

    public function postSale(Sale $sale): ?JournalEntry
    {
        $sale->loadMissing('items');
        $lines = [];
        $paid = (float) ($sale->initial_paid ?? $sale->paid);
        $due = (float) $sale->due;
        if ($paid > 0) $lines[] = $this->line('1000', $paid, 0, 'Sale payment received');
        if ($due > 0) $lines[] = $this->line('1200', $due, 0, 'Customer receivable');
        $revenue = max(0, (float) $sale->subtotal - (float) $sale->discount);
        if ($sale->tax_profile_id && TaxProfile::whereKey($sale->tax_profile_id)->value('calculation') === 'inclusive') $revenue = max(0, $revenue - (float) $sale->tax);
        if ($revenue > 0) $lines[] = $this->line('4000', 0, $revenue, 'Medicine sales revenue');
        if ((float) $sale->tax > 0) $lines[] = $this->line('2100', 0, (float) $sale->tax, 'Sales tax payable');
        $cost = (float) $sale->items->sum(fn ($item) => (float) $item->cost_price * max(0, (int) $item->quantity - (int) $item->returned_qty));
        if ($cost > 0) { $lines[] = $this->line('5000', $cost, 0, 'Cost of goods sold'); $lines[] = $this->line('1100', 0, $cost, 'Inventory issued'); }
        return $this->post(Sale::class, $sale->id, (int) $sale->branch_id, $sale->sale_date->toDateString(), 'POS sale ' . $sale->invoice_no, $lines);
    }

    public function postPurchase(Purchase $purchase): ?JournalEntry
    {
        $inventory = max(0, (float) $purchase->subtotal - (float) $purchase->discount);
        $tax = (float) $purchase->tax;
        $lines = [];
        if ($inventory > 0) $lines[] = $this->line('1100', $inventory, 0, 'Inventory received');
        if ($tax > 0) $lines[] = $this->line('1300', $tax, 0, 'Purchase input tax');
        if ((float) $purchase->paid > 0) $lines[] = $this->line('1000', 0, (float) $purchase->paid, 'Supplier payment');
        if ((float) $purchase->due > 0) $lines[] = $this->line('2000', 0, (float) $purchase->due, 'Supplier payable');
        return $this->post(Purchase::class, $purchase->id, (int) $purchase->branch_id, $purchase->purchase_date->toDateString(), 'Purchase ' . $purchase->reference_no, $lines);
    }

    public function postExpense(Expense $expense): ?JournalEntry
    {
        return $this->post(Expense::class, $expense->id, (int) $expense->branch_id, $expense->expense_date->toDateString(), 'Expense: ' . $expense->description, [
            $this->line('6000', (float) $expense->amount, 0, $expense->category),
            $this->line('1000', 0, (float) $expense->amount, 'Expense paid from cash/bank'),
        ]);
    }

    public function postPayment(Payment $payment): ?JournalEntry
    {
        $amount = (float) $payment->amount;
        if ($amount <= 0) return null;
        if ($payment->party_type === 'customer') {
            $lines = [$this->line('1000', $amount, 0, 'Customer payment received'), $this->line('1200', 0, $amount, 'Customer receivable settled')];
        } else {
            $lines = [$this->line('2000', $amount, 0, 'Supplier payable settled'), $this->line('1000', 0, $amount, 'Supplier payment made')];
        }
        return $this->post(Payment::class, $payment->id, (int) $payment->branch_id, $payment->payment_date->toDateString(), ucfirst($payment->party_type) . ' payment #' . $payment->id, $lines);
    }

    public function postSaleReturn(SaleReturn $return): ?JournalEntry
    {
        $return->loadMissing('items');
        $lines = [];
        $amount = (float) $return->total;
        if ($amount > 0) {
            $taxReturned = 0;
            if ($return->sale_id) {
                $sale = Sale::find($return->sale_id);
                if ($sale && $sale->tax_profile_id && TaxProfile::whereKey($sale->tax_profile_id)->value('calculation') === 'inclusive' && (float)$sale->subtotal > 0) {
                    $taxReturned = min($amount, round((float)$sale->tax * $amount / (float)$sale->subtotal, 2));
                }
            }
            $salesReturnRevenue = max(0, $amount - $taxReturned);
            if ($salesReturnRevenue > 0) $lines[] = $this->line('4100', $salesReturnRevenue, 0, 'Sales return');
            if ($taxReturned > 0) $lines[] = $this->line('2100', $taxReturned, 0, 'Reverse sales tax on return');
            $dueAdjusted = min($amount, (float) $return->due_adjusted);
            $refunded = min(max(0, $amount - $dueAdjusted), (float) $return->refund_amount);
            if ($dueAdjusted > 0) $lines[] = $this->line('1200', 0, $dueAdjusted, 'Customer receivable reduced');
            if ($refunded > 0) $lines[] = $this->line('1000', 0, $refunded, 'Customer refund paid');
            $remaining = round($amount - $dueAdjusted - $refunded, 2);
            if ($remaining > 0) $lines[] = $this->line('1200', 0, $remaining, 'Customer balance adjustment');
        }
        $cost = 0;
        foreach ($return->items->where('restocked', true) as $item) {
            $batchCost = (float) \App\Models\MedicineBatch::whereKey($item->medicine_batch_id)->value('purchase_price');
            $cost += $batchCost * (int) $item->quantity;
        }
        if ($cost > 0) { $lines[] = $this->line('1100', $cost, 0, 'Returned stock restored'); $lines[] = $this->line('5000', 0, $cost, 'Reverse cost of goods sold'); }
        return $this->post(SaleReturn::class, $return->id, (int) $return->branch_id, $return->return_date->toDateString(), 'Sales return ' . $return->return_no, $lines);
    }

    public function postPurchaseReturn(PurchaseReturn $return): ?JournalEntry
    {
        $return->loadMissing('items');
        $amount = (float) $return->total;
        $account = $return->refund_type === 'cash' ? '1000' : '2000';
        $lines = [$this->line($account, $amount, 0, 'Supplier refund / payable adjustment'), $this->line('1100', 0, $amount, 'Inventory returned to supplier')];
        return $this->post(PurchaseReturn::class, $return->id, (int) $return->branch_id, $return->return_date->toDateString(), 'Purchase return ' . $return->return_no, $lines);
    }

    public function postAdjustment(StockAdjustment $adjustment): ?JournalEntry
    {
        $batch = \App\Models\MedicineBatch::find($adjustment->medicine_batch_id);
        $value = (float) ($batch?->purchase_price ?? 0) * (int) $adjustment->quantity;
        if ($value <= 0) return null;
        $lines = $adjustment->type === 'add'
            ? [$this->line('1100', $value, 0, 'Inventory adjustment in'), $this->line('4200', 0, $value, 'Inventory adjustment gain')]
            : [$this->line('6100', $value, 0, 'Inventory write-off / adjustment out'), $this->line('1100', 0, $value, 'Inventory adjustment out')];
        return $this->post(StockAdjustment::class, $adjustment->id, (int) $adjustment->branch_id, $adjustment->created_at->toDateString(), 'Stock adjustment #' . $adjustment->id, $lines);
    }


    public function postBranchTransfer(BranchStockTransfer $transfer): void
    {
        $transfer->loadMissing('items');
        $value = 0;
        foreach ($transfer->items as $item) {
            $unitCost = (float) \App\Models\MedicineBatch::whereKey($item->source_batch_id)->value('purchase_price');
            $value += $unitCost * (int) $item->quantity;
        }
        $value = round($value, 2);
        if ($value <= 0) return;
        $date = ($transfer->completed_at ?? now())->toDateString();
        $this->post(BranchStockTransfer::class . ':out', $transfer->id, (int) $transfer->from_branch_id, $date, 'Stock transfer out ' . $transfer->transfer_no, [
            $this->line('1400', $value, 0, 'Inter-branch stock receivable'),
            $this->line('1100', 0, $value, 'Inventory transferred out'),
        ]);
        $this->post(BranchStockTransfer::class . ':in', $transfer->id, (int) $transfer->to_branch_id, $date, 'Stock transfer in ' . $transfer->transfer_no, [
            $this->line('1100', $value, 0, 'Inventory transferred in'),
            $this->line('2200', 0, $value, 'Inter-branch stock payable'),
        ]);
    }

    public function reverseSource(string $sourceType, int $sourceId, int $branchId, string $reason): void
    {
        $original = JournalEntry::with('lines')->where('source_type', $sourceType)->where('source_id', $sourceId)->where('status', 'posted')->first();
        if (!$original) return;
        DB::transaction(function () use ($original, $branchId, $reason) {
            $reversal = JournalEntry::create([
                'entry_no' => 'REV-' . now()->format('Ymd') . '-' . strtoupper(str()->random(6)), 'branch_id' => $branchId,
                'entry_date' => today(), 'narration' => 'Reversal: ' . $reason, 'source_type' => 'reversal:' . JournalEntry::class,
                'source_id' => $original->id, 'status' => 'posted', 'created_by' => auth()->id(), 'posted_at' => now(),
            ]);
            foreach ($original->lines as $line) $reversal->lines()->create(['account_id' => $line->account_id, 'debit' => $line->credit, 'credit' => $line->debit, 'description' => 'Reversal of ' . $original->entry_no]);
            $original->update(['status' => 'reversed']);
        });
    }
}
