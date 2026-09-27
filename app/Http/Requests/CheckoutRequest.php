<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price'    => 'nullable|numeric|min:0',
            'customer_id'      => 'nullable|exists:customers,id',
            'customer_name'    => 'nullable|string|max:150',
            'customer_phone'   => 'nullable|string|max:30',
            'discount'         => 'nullable|numeric|min:0',
            'tax_percent'      => 'nullable|numeric|min:0|max:100',
            'paid'             => 'required|numeric|min:0',
            'payment_method'   => 'required|in:cash,card,online,credit',
            'note'             => 'nullable|string|max:255',
        ];
    }
}
