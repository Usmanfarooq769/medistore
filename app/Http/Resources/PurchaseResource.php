<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'reference_no'        => $this->reference_no,
            'supplier_id'         => $this->supplier_id,
            'supplier'            => new SupplierResource($this->whenLoaded('supplier')),
            'supplier_invoice_no' => $this->supplier_invoice_no,
            'purchase_date'       => $this->purchase_date?->toDateString(),
            'subtotal'            => (float) $this->subtotal,
            'discount'            => (float) $this->discount,
            'tax'                 => (float) $this->tax,
            'total'               => (float) $this->total,
            'paid'                => (float) $this->paid,
            'due'                 => (float) $this->due,
            'payment_status'      => $this->payment_status,
            'note'                => $this->note,
            'items_count'         => $this->whenCounted('items'),
            'user'                => $this->whenLoaded('user', fn () => $this->user?->name),
            'items'               => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'             => $i->id,
                'medicine'       => $i->relationLoaded('medicine') && $i->medicine ? trim($i->medicine->name . ' ' . ($i->medicine->strength ?? '')) : null,
                'batch_no'       => $i->batch_no,
                'expiry_date'    => $i->expiry_date?->toDateString(),
                'quantity'       => (int) $i->quantity,
                'bonus_qty'      => (int) $i->bonus_qty,
                'purchase_price' => (float) $i->purchase_price,
                'sale_price'     => (float) $i->sale_price,
                'total'          => (float) $i->total,
            ])),
            'created_at'          => $this->created_at?->toDateTimeString(),
        ];
    }
}
