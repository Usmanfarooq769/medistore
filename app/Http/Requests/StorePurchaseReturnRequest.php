<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'               => 'required|exists:suppliers,id',
            'refund_type'               => 'required|in:adjust_balance,cash',
            'reason'                    => 'nullable|string|max:255',
            'items'                     => 'required|array|min:1',
            'items.*.medicine_batch_id' => 'required|integer|exists:medicine_batches,id',
            'items.*.quantity'          => 'required|integer|min:1',
        ];
    }
}
