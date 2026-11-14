<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\BranchContext;
use Illuminate\Http\Request;

/** Header "search everything" (Ctrl+K). Role aware: cashiers do not see suppliers. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) return response()->json(['data' => []]);
        $user = $request->user();
        $out = [];

        Medicine::query()->where("is_active", true)->where(fn ($w) => $w->search($q)->orWhere("barcode", $q))->orderBy('name')->limit(6)->get(['id', 'name', 'strength', 'generic_name', 'sale_price'])
            ->each(function ($m) use (&$out, $user) {
                $out[] = ['type' => 'Medicine', 'icon' => 'bi-capsule', 'title' => trim($m->name . ' ' . ($m->strength ?? '')), 'subtitle' => $m->generic_name ?: 'Medicine',
                    'to' => $user->hasPermission('medicines.manage') || $user->isAdmin() ? '/medicines/' . $m->id : '/pos?q=' . urlencode($m->name)];
            });

        Customer::query()->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "{$q}%"))->orderBy('name')->limit(5)->get(['id', 'name', 'phone'])
            ->each(function ($c) use (&$out) { $out[] = ['type' => 'Customer', 'icon' => 'bi-person', 'title' => $c->name, 'subtitle' => $c->phone ?: 'No phone', 'to' => '/customers/' . $c->id]; });

        Sale::query()->where('branch_id', BranchContext::id())->where(fn ($w) => $w->where('invoice_no', 'like', "%{$q}%")->orWhere('fbr_invoice_no', 'like', "%{$q}%"))->latest('id')->limit(5)->get(['id', 'invoice_no', 'customer_name', 'total'])
            ->each(function ($s) use (&$out) { $out[] = ['type' => 'Invoice', 'icon' => 'bi-receipt', 'title' => $s->invoice_no, 'subtitle' => ($s->customer_name ?: 'Walk-in') . ' · ' . money($s->total), 'to' => '/sales?search=' . urlencode($s->invoice_no)]; });

        if (in_array($user->role, ['admin', 'branch_manager'], true)) {
            Supplier::query()->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "{$q}%"))->orderBy('name')->limit(4)->get(['id', 'name', 'phone'])
                ->each(function ($s) use (&$out) { $out[] = ['type' => 'Supplier', 'icon' => 'bi-truck', 'title' => $s->name, 'subtitle' => $s->phone ?: 'No phone', 'to' => '/suppliers/' . $s->id]; });
        }

        return response()->json(['data' => $out]);
    }
}
