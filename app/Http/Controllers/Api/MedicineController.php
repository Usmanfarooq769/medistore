<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineRequest;
use App\Http\Resources\BatchResource;
use App\Http\Resources\MedicineResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Medicine;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function index(Request $request)
    {
        $days = (int) setting('expiry_alert_days', 60);
        $sort = in_array($request->sort, ['name', 'total_stock', 'sale_price', 'created_at'], true) ? $request->sort : 'name';

        $q = Medicine::query()
            ->with(['category:id,name', 'manufacturer:id,name'])
            ->withMin(['batches as nearest_expiry' => fn ($b) => $b->where('quantity', '>', 0)], 'expiry_date')
            ->search($request->search)
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->manufacturer_id, fn ($q, $v) => $q->where('manufacturer_id', $v))
            ->when($request->status, function ($q, $status) use ($days) {
                match ($status) {
                    'low'      => $q->lowStock(),
                    'out'      => $q->where('total_stock', '<=', 0),
                    'expiring' => $q->whereHas('batches', fn ($b) => $b->where('quantity', '>', 0)->whereBetween('expiry_date', [today(), today()->addDays($days)])),
                    'expired'  => $q->whereHas('batches', fn ($b) => $b->where('quantity', '>', 0)->where('expiry_date', '<', today())),
                    'inactive' => $q->where('is_active', false),
                    default    => null,
                };
            })
            ->orderBy($sort, $request->dir === 'desc' ? 'desc' : 'asc');

        return MedicineResource::collection($q->paginate($request->integer('per_page', 15)));
    }

    /** Autocomplete (purchase form, returns, adjustments). ?sellable=1 => non-expired stock only */
    public function search(Request $request)
    {
        $q = Medicine::query()->where('is_active', true)->search($request->q)->orderBy('name')->limit(20);

        if ($request->boolean('sellable')) {
            $q->sellable()->withSellableStock();
        }

        return MedicineResource::collection($q->get());
    }

    public function show(Medicine $medicine)
    {
        return new MedicineResource($medicine->load(['category:id,name', 'manufacturer:id,name']));
    }

    public function store(MedicineRequest $request)
    {
        $medicine = Medicine::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return response()->json(['message' => 'Medicine added. Add stock via Purchase.', 'data' => new MedicineResource($medicine)], 201);
    }

    public function update(MedicineRequest $request, Medicine $medicine)
    {
        $medicine->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return response()->json(['message' => 'Medicine updated.', 'data' => new MedicineResource($medicine)]);
    }

    public function destroy(Medicine $medicine)
    {
        if ($medicine->total_stock > 0 || $medicine->movements()->exists()) {
            $medicine->update(['is_active' => false]);

            return response()->json(['message' => 'Medicine has history, so it was deactivated instead of deleted.']);
        }
        $medicine->delete();

        return response()->json(['message' => 'Medicine deleted.']);
    }

    /**
     * Batches of a medicine.
     *  default   : batches with stock
     *  ?all=1    : include empty batches (customer return without invoice)
     *  ?supplier_id= : batches bought from that supplier first
     */
    public function batches(Request $request, Medicine $medicine)
    {
        $q = $medicine->batches()->with('supplier:id,name')
            ->when(! $request->boolean('all'), fn ($b) => $b->where('quantity', '>', 0))
            ->when($request->supplier_id, fn ($b, $s) => $b->orderByRaw('supplier_id = ? DESC', [$s]))
            ->orderByRaw('expiry_date IS NULL, expiry_date');

        return BatchResource::collection($q->limit(50)->get());
    }

    /** Stock card ledger */
    public function movements(Medicine $medicine)
    {
        return StockMovementResource::collection(StockMovement::where('medicine_id', $medicine->id)->latest('id')->paginate(20));
    }
}
