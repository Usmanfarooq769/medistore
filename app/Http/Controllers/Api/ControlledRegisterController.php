<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Narcotics / psychotropic / controlled-medicine register (printable, inspector-ready).
 * Built from controlled_medicine_register: opening balance, receipts, dispensed, adjustments, closing – per medicine and as a ledger.
 */
class ControlledRegisterController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->hasPermission('controlled.register'), 403, 'You are not allowed to view the controlled register.');
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'medicine_id' => 'nullable|integer|exists:medicines,id']);
        $from = $data['from'] ?? today()->startOfMonth()->toDateString();
        $to = $data['to'] ?? today()->toDateString();
        $branch = BranchContext::id();

        $base = fn () => DB::table('controlled_medicine_register as r')->where('r.branch_id', $branch)
            ->when($data['medicine_id'] ?? null, fn ($q, $id) => $q->where('r.medicine_id', $id));

        $opening = $base()->whereDate('r.recorded_at', '<', $from)->groupBy('r.medicine_id')->selectRaw('r.medicine_id, SUM(r.quantity_change) as qty')->pluck('qty', 'medicine_id');
        $period = $base()->whereDate('r.recorded_at', '>=', $from)->whereDate('r.recorded_at', '<=', $to)
            ->groupBy('r.medicine_id')
            ->selectRaw("r.medicine_id,
                SUM(CASE WHEN r.quantity_change > 0 THEN r.quantity_change ELSE 0 END) as received,
                SUM(CASE WHEN r.transaction_type = 'dispense' THEN -r.quantity_change ELSE 0 END) as dispensed,
                SUM(CASE WHEN r.transaction_type <> 'dispense' AND r.quantity_change < 0 THEN -r.quantity_change ELSE 0 END) as other_out")->get()->keyBy('medicine_id');

        $ids = collect($opening->keys())->merge($period->keys())->unique()->values();
        $names = Medicine::whereIn('id', $ids)->get(['id', 'name', 'strength', 'generic_name'])->keyBy('id');
        $summary = $ids->map(function ($id) use ($opening, $period, $names) {
            $p = $period[$id] ?? null; $open = (int) ($opening[$id] ?? 0);
            $in = (int) ($p->received ?? 0); $disp = (int) ($p->dispensed ?? 0); $oth = (int) ($p->other_out ?? 0);
            $m = $names[$id] ?? null;
            return ['medicine_id' => $id, 'medicine' => $m ? trim($m->name . ' ' . ($m->strength ?? '')) : "#{$id}", 'generic_name' => $m?->generic_name,
                'opening' => $open, 'received' => $in, 'dispensed' => $disp, 'other_out' => $oth, 'closing' => $open + $in - $disp - $oth];
        })->sortBy('medicine')->values();

        $ledger = $base()->leftJoin('medicines as m', 'm.id', '=', 'r.medicine_id')->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('prescriptions as p', 'p.id', '=', 'r.prescription_id')->leftJoin('users as u', 'u.id', '=', 'r.recorded_by')->leftJoin('medicine_batches as b', 'b.id', '=', 'r.batch_id')
            ->whereDate('r.recorded_at', '>=', $from)->whereDate('r.recorded_at', '<=', $to)->orderBy('r.recorded_at')->orderBy('r.id')->limit(2000)
            ->get(['r.id', 'r.recorded_at', 'r.transaction_type', 'r.quantity_change', 'r.balance_after', 'r.reference_no', 'r.reason',
                'm.name as medicine', 'm.strength', 'b.batch_no', 'c.name as customer', 'c.cnic as customer_cnic', 'p.prescription_no', 'p.doctor_name', 'p.doctor_license', 'u.name as recorded_by']);

        return response()->json(['from' => $from, 'to' => $to, 'summary' => $summary, 'ledger' => $ledger,
            'store' => ['name' => setting('store_name'), 'license' => setting('license_no'), 'address' => setting('store_address')]]);
    }
}
