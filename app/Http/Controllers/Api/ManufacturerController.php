<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManufacturerResource;
use App\Models\Manufacturer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Company that MAKES the medicine (GSK, Abbott ...) */
class ManufacturerController extends Controller
{
    public function index(Request $request)
    {
        return ManufacturerResource::collection(
            Manufacturer::withCount('medicines')
                ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
                ->orderBy('name')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $m = Manufacturer::create($this->validated($request));

        return response()->json(['message' => 'Company added.', 'data' => new ManufacturerResource($m)], 201);
    }

    public function update(Request $request, Manufacturer $manufacturer)
    {
        $manufacturer->update($this->validated($request, $manufacturer->id));

        return response()->json(['message' => 'Company updated.']);
    }

    public function destroy(Manufacturer $manufacturer)
    {
        abort_if($manufacturer->medicines()->exists(), 422, 'This company has medicines. Change them first.');
        $manufacturer->delete();

        return response()->json(['message' => 'Company deleted.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:150', Rule::unique('manufacturers', 'name')->ignore($id)],
            'phone'   => 'nullable|string|max:30',
            'email'   => 'nullable|email|max:150',
            'address' => 'nullable|string|max:255',
        ]);
    }
}
