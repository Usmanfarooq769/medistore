<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BatchResource;
use App\Http\Resources\MedicineResource;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AlertController extends Controller
{
    /** Navbar bell – cached 5 min, cleared on every stock change */
    public function counts()
    {
        return response()->json(Cache::remember('alerts.counts', 300, function () {
            $days = (int) setting('expiry_alert_days', 60);

            return [
                'low_stock' => Medicine::where('is_active', true)->lowStock()->count(),
                'expiring'  => MedicineBatch::where('quantity', '>', 0)->whereBetween('expiry_date', [today(), today()->addDays($days)])->count(),
                'expired'   => MedicineBatch::where('quantity', '>', 0)->where('expiry_date', '<', today())->count(),
                'low_items' => Medicine::where('is_active', true)->lowStock()->orderBy('total_stock')->limit(6)->get(['id', 'name', 'strength', 'total_stock']),
            ];
        }));
    }

    public function lowStock(Request $request)
    {
        return MedicineResource::collection(
            Medicine::with('manufacturer:id,name')->where('is_active', true)->lowStock()
                ->search($request->search)->orderBy('total_stock')->paginate(25)
        );
    }

    public function expiry(Request $request)
    {
        $days = (int) ($request->days ?: setting('expiry_alert_days', 60));

        return BatchResource::collection(
            MedicineBatch::with(['medicine:id,name,strength,unit', 'supplier:id,name'])
                ->where('quantity', '>', 0)
                ->when($request->type === 'expired',
                    fn ($q) => $q->where('expiry_date', '<', today()),
                    fn ($q) => $q->whereBetween('expiry_date', [today(), today()->addDays($days)]))
                ->orderBy('expiry_date')
                ->paginate(25)
        );
    }
}
