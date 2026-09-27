<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'            => 'required|exists:suppliers,id',
            'supplier_invoice_no'    => 'nullable|string|max:100',
            'purchase_date'          => 'required|date|before_or_equal:today',
            'discount'               => 'nullable|numeric|min:0',
            'tax'                    => 'nullable|numeric|min:0',
            'paid'                   => 'nullable|numeric|min:0',
            'payment_method'         => 'nullable|in:cash,bank,cheque,online',
            'note'                   => 'nullable|string|max:255',
            'items'                  => 'required|array|min:1',
            'items.*.medicine_id'    => 'required|exists:medicines,id',
            'items.*.batch_no'       => 'required|string|max:100',
            'items.*.expiry_date'    => 'required|date|after:today',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.bonus_qty'      => 'nullable|integer|min:0',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.sale_price'     => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'               => 'Add at least one medicine.',
            'items.*.batch_no.required'    => 'Batch no is required on every row.',
            'items.*.expiry_date.required' => 'Expiry date is required on every row.',
            'items.*.expiry_date.after'    => 'Expiry date must be in the future.',
        ];
    }
}
