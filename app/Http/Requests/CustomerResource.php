<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'phone'       => $this->phone,
            'address'     => $this->address,
            'balance'     => (float) $this->balance,
            'credit_limit' => $this->credit_limit === null ? null : (float) $this->credit_limit,
            'loyalty_points' => (int) ($this->loyalty_points ?? 0),
            'available_credit' => $this->credit_limit === null ? null : max(0, (float) $this->credit_limit - (float) $this->balance),
            'sales_count' => $this->whenCounted('sales'),
        ];
    }
}
