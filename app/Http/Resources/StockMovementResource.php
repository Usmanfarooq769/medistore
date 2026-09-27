<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'quantity'      => (int) $this->quantity,
            'balance_after' => (int) $this->balance_after,
            'reference'     => $this->reference,
            'note'          => $this->note,
            'created_at'    => $this->created_at?->toDateTimeString(),
        ];
    }
}
