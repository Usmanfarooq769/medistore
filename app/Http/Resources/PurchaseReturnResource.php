<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'return_no'   => $this->return_no,
            'supplier_id' => $this->supplier_id,
            'supplier'    => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'return_date' => $this->return_date?->toDateString(),
            'total'       => (float) $this->total,
            'refund_type' => $this->refund_type,
            'reason'      => $this->reason,
            'items_count' => $this->whenCounted('items'),
            'user'        => $this->whenLoaded('user', fn () => $this->user?->name),
            'items'       => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'          => $i->id,
                'medicine'    => $i->relationLoaded('medicine') && $i->medicine ? trim($i->medicine->name . ' ' . ($i->medicine->strength ?? '')) : null,
                'batch_no'    => $i->batch_no,
                'expiry_date' => $i->expiry_date?->toDateString(),
                'quantity'    => (int) $i->quantity,
                'price'       => (float) $i->price,
                'total'       => (float) $i->total,
            ])),
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
