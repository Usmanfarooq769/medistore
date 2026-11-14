<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'invoice_no'      => $this->invoice_no,
            'customer_id'     => $this->customer_id,
            'customer_name'   => $this->customer_name,
            'customer_phone'  => $this->customer_phone,
            'sale_date'       => $this->sale_date?->toDateString(),
            'subtotal'        => (float) $this->subtotal,
            'discount'        => (float) $this->discount,
            'tax_percent'     => (float) $this->tax_percent,
            'tax'             => (float) $this->tax,
            'total'           => (float) $this->total,
            'paid'            => (float) $this->paid,
            'initial_paid'    => (float) $this->initial_paid,
            'due'             => (float) $this->due,
            'change_amount'   => (float) $this->change_amount,
            'returned_amount' => (float) $this->returned_amount,
            'payment_method'  => $this->payment_method,
            'payment_status'  => $this->payment_status,
            'items_count'     => $this->whenCounted('items'),
            'user'            => $this->whenLoaded('user', fn () => $this->user?->name),
            'items'           => SaleItemResource::collection($this->whenLoaded('items')),
            'returns'         => $this->whenLoaded('returns', fn () => $this->returns->map(fn ($r) => [
                'id' => $r->id, 'return_no' => $r->return_no, 'total' => (float) $r->total, 'return_date' => $r->return_date?->toDateString(),
            ])),
            'fbr_invoice_no'  => $this->fbr_invoice_no,
            'fbr_status'      => $this->fbr_status,
            'fbr_error'       => $this->fbr_error,
            'loyalty_points_earned' => (int) $this->loyalty_points_earned,
            'loyalty_points_redeemed' => (int) $this->loyalty_points_redeemed,
            'created_at'      => $this->created_at?->toDateTimeString(),
        ];
    }
}
