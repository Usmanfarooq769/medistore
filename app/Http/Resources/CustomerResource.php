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
            'insurance_provider_id' => $this->insurance_provider_id,
            'insurance_plan_id' => $this->insurance_plan_id,
            'insurance_member_no' => $this->insurance_member_no,
            'balance'     => (float) $this->balance,
            'sales_count' => $this->whenCounted('sales'),
        ];
    }
}
