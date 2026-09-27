<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'medicine_id'       => $this->medicine_id,
            'medicine_batch_id' => $this->medicine_batch_id,
            'medicine_name'     => $this->medicine_name,
            'batch_no'          => $this->batch_no,
            'expiry_date'       => $this->expiry_date?->toDateString(),
            'quantity'          => (int) $this->quantity,
            'returned_qty'      => (int) $this->returned_qty,
            'returnable_qty'    => (int) $this->quantity - (int) $this->returned_qty,
            'price'             => (float) $this->price,
            'total'             => (float) $this->total,
        ];
    }
}
