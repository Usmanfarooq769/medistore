<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'purchase_price'  => (float) $this->purchase_price,
            'sale_price'      => (float) $this->sale_price,
            'reorder_level'   => (int) $this->reorder_level,
            'rack'            => $this->rack,
            'total_stock'     => (int) $this->total_stock,
            'is_low'          => (int) $this->total_stock <= (int) $this->reorder_level,
            'is_active'       => (bool) $this->is_active,
            // optional aggregates
            'sellable_stock'  => $this->when(isset($this->sellable_stock), fn () => (int) $this->sellable_stock),
            'nearest_expiry'  => $this->when(isset($this->nearest_expiry), fn () => $this->nearest_expiry),
            'cost_value'      => $this->when(isset($this->cost_value), fn () => (float) $this->cost_value),
            'sale_value'      => $this->when(isset($this->sale_value), fn () => (float) $this->sale_value),
        ];
    }
}
