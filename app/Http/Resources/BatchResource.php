<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'medicine_id'      => $this->medicine_id,
            'medicine'         => $this->whenLoaded('medicine', fn () => [
                'id' => $this->medicine->id, 'name' => trim($this->medicine->name . ' ' . ($this->medicine->strength ?? '')), 'unit' => $this->medicine->unit,
            ]),
            'supplier_id'      => $this->supplier_id,
            'supplier'         => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'batch_no'         => $this->batch_no,
            'expiry_date'      => $this->expiry_date?->toDateString(),
            'is_expired'       => $this->expiry_date !== null && $this->expiry_date->lt(today()),
            'days_left'        => $this->expiry_date ? (int) today()->diffInDays($this->expiry_date, false) : null,
            'purchase_price'   => (float) $this->purchase_price,
            'sale_price'       => (float) $this->sale_price,
            'initial_quantity' => (int) $this->initial_quantity,
            'quantity'         => (int) $this->quantity,
        ];
    }
}
