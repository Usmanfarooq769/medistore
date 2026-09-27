<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'contact_person'  => $this->contact_person,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'address'         => $this->address,
            'opening_balance' => (float) $this->opening_balance,
            'balance'         => (float) $this->balance,
            'purchases_count' => $this->whenCounted('purchases'),
        ];
    }
}
