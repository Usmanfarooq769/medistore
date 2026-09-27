<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'medicine'    => $this->whenLoaded('medicine', fn () => $this->medicine ? trim($this->medicine->name . ' ' . ($this->medicine->strength ?? '')) : null),
            'batch_no'    => $this->whenLoaded('batch', fn () => $this->batch?->batch_no),
            'expiry_date' => $this->whenLoaded('batch', fn () => $this->batch?->expiry_date?->toDateString()),
            'type'        => $this->type,
            'quantity'    => (int) $this->quantity,
            'reason'      => $this->reason,
            'note'        => $this->note,
            'user'        => $this->whenLoaded('user', fn () => $this->user?->name),
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
