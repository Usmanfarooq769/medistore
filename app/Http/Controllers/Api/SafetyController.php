<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MedicineResource;
use App\Models\Customer;
use App\Models\DrugInteraction;
use App\Models\Medicine;
use App\Services\SafetyCheckService;
use Illuminate\Http\Request;

/** Drug safety (allergy / interaction / duplicate), substitutes and the interaction reference list. */
class SafetyController extends Controller
{
    public function check(Request $request, SafetyCheckService $service)
    {
        $data = $request->validate(['medicine_ids' => 'required|array|min:1|max:60', 'medicine_ids.*' => 'integer|exists:medicines,id', 'customer_id' => 'nullable|integer|exists:customers,id']);
        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
        return response()->json($service->check(array_values(array_unique($data['medicine_ids'])), $customer));
    }

    /** Same generic name (and strength when known), sorted by price – with savings vs the selected brand. */
    public function substitutes(Medicine $medicine)
    {
        $generic = mb_strtolower(trim((string) $medicine->generic_name));
        if ($generic === '') return response()->json(['data' => [], 'message' => 'No generic name saved for this medicine.']);

        $rows = Medicine::query()->where('is_active', true)->where('id', '!=', $medicine->id)
            ->whereRaw('LOWER(TRIM(generic_name)) = ?', [$generic])
            ->withSellableStock()->withNearestExpiry()
            ->orderBy('sale_price')->limit(12)->get();

        $ownStrength = mb_strtolower(trim((string) $medicine->strength));
        $data = $rows->map(function ($m) use ($medicine, $ownStrength) {
            $r = (new MedicineResource($m))->resolve();
            $r['same_strength'] = $ownStrength !== '' && mb_strtolower(trim((string) $m->strength)) === $ownStrength;
            $r['price_diff'] = round((float) $m->sale_price - (float) $medicine->sale_price, 2);
            $r['in_stock'] = (int) ($m->sellable_stock ?? 0) > 0;
            return $r;
        })->sortBy([['in_stock', 'desc'], ['same_strength', 'desc'], ['sale_price', 'asc']])->values();

        return response()->json(['data' => $data, 'generic' => $medicine->generic_name]);
    }

    /* ---------- interaction reference list ---------- */

    public function interactions(Request $request)
    {
        return DrugInteraction::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('ingredient_a', 'like', "%{$s}%")->orWhere('ingredient_b', 'like', "%{$s}%")))
            ->when($request->severity, fn ($q, $v) => $q->where('severity', $v))
            ->orderByRaw("CASE severity WHEN 'contraindicated' THEN 0 WHEN 'major' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")->orderBy('ingredient_a')
            ->paginate($request->integer('per_page', 20));
    }

    public function storeInteraction(Request $request)
    {
        $d = $this->validated($request);
        abort_if(DrugInteraction::where('ingredient_a', $d['ingredient_a'])->where('ingredient_b', $d['ingredient_b'])->exists(), 422, 'This pair already exists.');
        return response()->json(['message' => 'Interaction added.', 'data' => DrugInteraction::create($d)], 201);
    }

    public function updateInteraction(Request $request, DrugInteraction $interaction)
    {
        $d = $this->validated($request);
        abort_if(DrugInteraction::where('ingredient_a', $d['ingredient_a'])->where('ingredient_b', $d['ingredient_b'])->where('id', '!=', $interaction->id)->exists(), 422, 'This pair already exists.');
        $interaction->update($d);
        return response()->json(['message' => 'Interaction updated.']);
    }

    public function destroyInteraction(DrugInteraction $interaction)
    {
        $interaction->delete();
        return response()->json(['message' => 'Interaction deleted.']);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'ingredient_a' => 'required|string|max:120', 'ingredient_b' => 'required|string|max:120|different:ingredient_a',
            'severity' => 'required|in:minor,moderate,major,contraindicated', 'effect' => 'required|string|max:255',
            'management' => 'nullable|string|max:255', 'is_active' => 'boolean',
        ]);
        $d['ingredient_a'] = mb_strtolower(trim($d['ingredient_a']));
        $d['ingredient_b'] = mb_strtolower(trim($d['ingredient_b']));
        if ($d['ingredient_a'] > $d['ingredient_b']) [$d['ingredient_a'], $d['ingredient_b']] = [$d['ingredient_b'], $d['ingredient_a']];
        $d['is_active'] = $request->boolean('is_active', true);
        return $d;
    }
}
