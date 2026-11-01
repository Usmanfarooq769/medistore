<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BatchResource;
use App\Http\Resources\MedicineResource;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AlertController extends Controller
{
    /** Navbar bell – cached 5 min, cleared on every stock change */
    public function counts()
    {
        return response()->json(Cache::remember('alerts.counts.'.BranchContext::id(), 300, function () {
            $days = (int) setting('expiry_alert_days', 60);

            return [
                'low_stock' => Medicine::where('is_active', true)->lowStock()->count(),
                'expiring'  => MedicineBatch::where('branch_id', BranchContext::id())->where('quantity', '>', 0)->whereBetween('expiry_date', [today(), today()->addDays($days)])->count(),
                'expired'   => MedicineBatch::where('branch_id', BranchContext::id())->where('quantity', '>', 0)->where('expiry_date', '<', today())->count(),
                'low_items' => Medicine::where('is_active', true)->withSellableStock()->withSum(['batches as branch_stock' => fn($b) => $b->where('branch_id', BranchContext::id())], 'quantity')->lowStock()->orderBy('branch_stock')->limit(6)->get(['id', 'name', 'strength', 'total_stock', 'branch_stock', 'sellable_stock'])->map(function($m){$m->total_stock=(int)$m->branch_stock;return $m;}),
            ];
        }));
    }

    public function lowStock(Request $request)
    {
        return MedicineResource::collection(
            Medicine::with('manufacturer:id,name')->withSellableStock()->withSum(['batches as branch_stock' => fn($b) => $b->where('branch_id', BranchContext::id())], 'quantity')->where('is_active', true)->lowStock()
                ->search($request->search)->orderBy('branch_stock')->paginate(25)
        );
    }

    public function expiry(Request $request)
    {
        $days = (int) ($request->days ?: setting('expiry_alert_days', 60));

        return BatchResource::collection(
            MedicineBatch::with(['medicine:id,name,strength,unit', 'supplier:id,name'])
                ->where('branch_id', BranchContext::id())->where('quantity', '>', 0)
                ->when($request->type === 'expired',
                    fn ($q) => $q->where('expiry_date', '<', today()),
                    fn ($q) => $q->whereBetween('expiry_date', [today(), today()->addDays($days)]))
                ->orderBy('expiry_date')
                ->paginate(25)
        );
    }
}
