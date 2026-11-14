<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();
        $stock = array_key_exists('branch_stock', $attributes) ? (int) ($this->branch_stock ?? 0) : (int) $this->total_stock;
        $sellable = array_key_exists('sellable_stock', $attributes) ? (int) ($this->sellable_stock ?? 0) : $stock;
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'strength'        => $this->strength,
            'display_name'    => trim($this->name . ' ' . ($this->strength ?? '')),
            'generic_name'    => $this->generic_name,
            'category_id'     => $this->category_id,
            'manufacturer_id' => $this->manufacturer_id,
            'category'        => $this->whenLoaded('category', fn () => $this->category?->name),
            'manufacturer'    => $this->whenLoaded('manufacturer', fn () => $this->manufacturer?->name),
            'barcode'         => $this->barcode,
            'unit'            => $this->unit,
            'pack_size'       => (int) ($this->pack_size ?: 1),
            'stock_units_are_base' => (bool) ($this->stock_units_are_base ?? false),
            'has_stock_history' => (bool) ($this->has_stock_history ?? false),
            'purchase_price'  => (float) $this->purchase_price,
            'sale_price'      => (float) $this->sale_price,
            'reorder_level'   => (int) $this->reorder_level,
            'rack'            => $this->rack,
            'total_stock'     => $stock,
            'is_low'          => $sellable <= (int) $this->reorder_level,
            'is_active'       => (bool) $this->is_active,
            'requires_prescription' => (bool) ($this->requires_prescription ?? false),
            'requires_controlled_tracking' => (bool) ($this->requires_controlled_tracking ?? false),
            'dosage_form'     => $this->dosage_form,
            'storage_condition' => $this->storage_condition,
            'drug_class'      => $this->drug_class,
            'hs_code'         => $this->hs_code,
            // optional aggregates
            'sellable_stock'  => array_key_exists('sellable_stock', $attributes) ? (int) ($this->sellable_stock ?? 0) : null,
            'nearest_expiry'  => $this->when(isset($this->nearest_expiry), fn () => $this->nearest_expiry),
            'cost_value'      => $this->when(isset($this->cost_value), fn () => (float) $this->cost_value),
            'sale_value'      => $this->when(isset($this->sale_value), fn () => (float) $this->sale_value),
        ];
    }
}
