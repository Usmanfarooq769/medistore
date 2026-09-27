<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\MedicineBatch;
use App\Models\StockAdjustment;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        return StockAdjustmentResource::collection(
            StockAdjustment::with(['medicine:id,name,strength', 'batch:id,batch_no,expiry_date', 'user:id,name'])
                ->when($request->reason, fn ($q, $v) => $q->where('reason', $v))
                ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->latest('id')
                ->paginate(15)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'medicine_id'       => 'required|exists:medicines,id',
            'medicine_batch_id' => 'required|exists:medicine_batches,id',
            'type'              => 'required|in:add,subtract',
            'quantity'          => 'required|integer|min:1',
            'reason'            => 'required|in:expired,damaged,lost,correction,other',
            'note'              => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            $batch = MedicineBatch::lockForUpdate()->where('medicine_id', $data['medicine_id'])->findOrFail($data['medicine_batch_id']);
            $adj = StockAdjustment::create($data + ['user_id' => auth()->id()]);
            $delta = $data['type'] === 'add' ? (int) $data['quantity'] : -(int) $data['quantity'];
            $this->stock->moveBatch($batch, $delta, $delta > 0 ? 'adjustment_in' : 'adjustment_out', 'ADJ-' . $adj->id, $data['reason'] . ($data['note'] ? ': ' . $data['note'] : ''));
        });

        StockService::flushCache();

        return response()->json(['message' => 'Stock adjusted.'], 201);
    }

    /** Write off every expired batch in one click */
    public function writeOffExpired()
    {
        $count = DB::transaction(function () {
            $batches = MedicineBatch::where('quantity', '>', 0)->where('expiry_date', '<', today())->lockForUpdate()->get();
            foreach ($batches as $batch) {
                $adj = StockAdjustment::create([
                    'medicine_id' => $batch->medicine_id, 'medicine_batch_id' => $batch->id, 'type' => 'subtract',
                    'quantity' => $batch->quantity, 'reason' => 'expired', 'note' => 'Bulk expired write-off', 'user_id' => auth()->id(),
                ]);
                $this->stock->moveBatch($batch, -$batch->quantity, 'adjustment_out', 'ADJ-' . $adj->id, 'expired write-off');
            }

            return $batches->count();
        });

        StockService::flushCache();

        return response()->json(['message' => "{$count} expired batch(es) written off."]);
    }
}
