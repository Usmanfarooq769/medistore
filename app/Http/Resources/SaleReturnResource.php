<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'return_no'     => $this->return_no,
            'sale_id'       => $this->sale_id,
            'invoice_no'    => $this->whenLoaded('sale', fn () => $this->sale?->invoice_no),
            'customer_id'   => $this->customer_id,
            'customer_name' => $this->customer_name,
            'return_date'   => $this->return_date?->toDateString(),
            'subtotal'      => (float) $this->subtotal,
            'deduction'     => (float) $this->deduction,
            'total'         => (float) $this->total,
            'due_adjusted'  => (float) $this->due_adjusted,
            'refund_amount' => (float) $this->refund_amount,
            'refund_method' => $this->refund_method,
            'reason'        => $this->reason,
            'items_count'   => $this->whenCounted('items'),
            'user'          => $this->whenLoaded('user', fn () => $this->user?->name),
            'items'         => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'            => $i->id,
                'medicine_name' => $i->medicine_name,
                'batch_no'      => $i->batch_no,
                'expiry_date'   => $i->expiry_date?->toDateString(),
                'quantity'      => (int) $i->quantity,
                'price'         => (float) $i->price,
                'total'         => (float) $i->total,
                'condition'     => $i->condition,
                'restocked'     => (bool) $i->restocked,
            ])),
            'created_at'    => $this->created_at?->toDateTimeString(),
        ];
    }
}
