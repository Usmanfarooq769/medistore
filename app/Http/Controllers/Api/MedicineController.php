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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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


    /** Download a clean CSV template with the exact supported import columns. */
    public function downloadImportTemplate()
    {
        $headers = [
            'name', 'generic_name', 'strength', 'category', 'manufacturer', 'barcode',
            'unit', 'pack_size', 'purchase_price', 'sale_price', 'reorder_level', 'rack',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            // UTF-8 BOM helps Microsoft Excel detect UTF-8 CSV files correctly.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            fclose($output);
        }, 'medistore_medicine_import_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** Import medicine master data from a CSV exported by another pharmacy system. */
    public function importCsv(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return response()->json(['message' => 'Unable to read the uploaded CSV.'], 422);
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            return response()->json(['message' => 'CSV file is empty.'], 422);
        }
        $headers = array_map(fn ($h) => Str::of(ltrim((string) $h, "\xEF\xBB\xBF"))->lower()->trim()->replace([' ', '-', '/'], '_')->toString(), $headers);
        $aliases = [
            'medicine_name' => 'name', 'brand_name' => 'name', 'product_name' => 'name',
            'generic' => 'generic_name', 'formula' => 'generic_name', 'manufacturer_name' => 'manufacturer',
            'company' => 'manufacturer', 'category_name' => 'category', 'pack' => 'pack_size',
            'units_per_pack' => 'pack_size', 'mrp' => 'sale_price', 'barcode_no' => 'barcode',
        ];
        $headers = array_map(fn ($h) => $aliases[$h] ?? $h, $headers);
        if (! in_array('name', $headers, true)) {
            fclose($handle);
            return response()->json(['message' => 'CSV must contain a medicine name column (name, medicine_name, brand_name or product_name).'], 422);
        }

        $created = 0; $updated = 0; $skipped = 0; $errors = []; $rowNo = 1;
        DB::beginTransaction();
        try {
            while (($values = fgetcsv($handle)) !== false) {
                $rowNo++;
                if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) continue;
                $row = [];
                foreach ($headers as $i => $key) $row[$key] = isset($values[$i]) ? trim((string) $values[$i]) : null;
                if (empty($row['name'])) { $skipped++; $errors[] = "Row {$rowNo}: medicine name is missing."; continue; }

                $categoryId = null;
                if (! empty($row['category'])) $categoryId = \App\Models\Category::firstOrCreate(['name' => mb_substr($row['category'], 0, 120)])->id;
                $manufacturerId = null;
                if (! empty($row['manufacturer'])) $manufacturerId = \App\Models\Manufacturer::firstOrCreate(['name' => mb_substr($row['manufacturer'], 0, 150)])->id;
                $barcode = ! empty($row['barcode']) ? mb_substr($row['barcode'], 0, 100) : null;
                $medicine = $barcode
                    ? Medicine::where('barcode', $barcode)->first()
                    : Medicine::where('name', mb_substr($row['name'], 0, 180))->where('strength', $row['strength'] ?? null)->first();
                $isNew = ! $medicine;
                $purchasePrice = is_numeric($row['purchase_price'] ?? null) ? max(0, (float) $row['purchase_price']) : 0;
                $salePrice = is_numeric($row['sale_price'] ?? null) ? max(0, (float) $row['sale_price']) : 0;
                if ($salePrice < $purchasePrice) { $skipped++; $errors[] = "Row {$rowNo}: sale_price cannot be less than purchase_price."; continue; }
                $payload = [
                    'name' => mb_substr($row['name'], 0, 180),
                    'generic_name' => ! empty($row['generic_name']) ? mb_substr($row['generic_name'], 0, 180) : null,
                    'strength' => ! empty($row['strength']) ? mb_substr($row['strength'], 0, 60) : null,
                    'category_id' => $categoryId, 'manufacturer_id' => $manufacturerId,
                    'barcode' => $barcode,
                    'unit' => ! empty($row['unit']) ? mb_substr($row['unit'], 0, 30) : 'strip',
                    'pack_size' => max(1, (int) ($row['pack_size'] ?? 1)),
                    'purchase_price' => $purchasePrice,
                    'sale_price' => $salePrice,
                    'reorder_level' => max(0, (int) ($row['reorder_level'] ?? 10)),
                    'rack' => ! empty($row['rack']) ? mb_substr($row['rack'], 0, 50) : null,
                    'is_active' => true,
                ];
                if ($medicine) {
                    // Blank/missing CSV columns must never erase existing master data.
                    foreach (['generic_name' => 'generic_name', 'strength' => 'strength', 'category_id' => 'category',
                        'manufacturer_id' => 'manufacturer', 'barcode' => 'barcode', 'rack' => 'rack',
                        'unit' => 'unit', 'pack_size' => 'pack_size', 'reorder_level' => 'reorder_level'] as $field => $source) {
                        if (! isset($row[$source]) || $row[$source] === '') unset($payload[$field]);
                    }
                    if (! is_numeric($row['purchase_price'] ?? null)) unset($payload['purchase_price']);
                    if (! is_numeric($row['sale_price'] ?? null)) unset($payload['sale_price']);
                    $medicine->update($payload);
                } else {
                    Medicine::create($payload);
                }
                $isNew ? $created++ : $updated++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            fclose($handle);
            return response()->json(['message' => 'CSV import failed. No rows were imported. Please verify the file and values.'], 422);
        }
        fclose($handle);
        return response()->json(['message' => 'CSV import completed.', 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
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
