<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // controlled / narcotic / psychotropic classes always get register tracking
        if (in_array($this->input('drug_class'), ['controlled', 'narcotic', 'psychotropic'], true)) $this->merge(['requires_controlled_tracking' => true]);
    }

    public function rules(): array
    {
        $id = $this->route('medicine')?->id;

        return [
            'name'            => 'required|string|max:180',
            'generic_name'    => 'nullable|string|max:180',
            'strength'        => 'nullable|string|max:60',
            'category_id'     => 'nullable|exists:categories,id',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'barcode'         => ['nullable', 'string', 'max:100', Rule::unique('medicines', 'barcode')->ignore($id)],
            'unit'            => 'required|string|max:30',
            'pack_size'       => 'required|integer|min:1|max:100000',
            'purchase_price'  => 'required|numeric|min:0',
            'sale_price'      => 'required|numeric|min:0|gte:purchase_price',
            'reorder_level'   => 'required|integer|min:0',
            'rack'            => 'nullable|string|max:50',
            'is_active'       => 'boolean',
            'requires_prescription' => 'boolean',
            'requires_controlled_tracking' => 'boolean',
            'dosage_form'     => 'nullable|string|max:40',
            'storage_condition' => 'nullable|in:room,cool,cold_chain,protect_light',
            'drug_class'      => 'nullable|in:otc,prescription,controlled,narcotic,psychotropic,supplement,device',
            'hs_code'         => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return ['sale_price.gte' => 'Sale price must be greater than or equal to purchase price.'];
    }
}
