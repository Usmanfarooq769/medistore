<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Two modes:
 *  1) With invoice   : sale_id + items[].sale_item_id + quantity + condition
 *  2) Without invoice: items[].medicine_id + medicine_batch_id + quantity + price + condition
 */
class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $withInvoice = $this->filled('sale_id');

        return [
            'sale_id'                   => 'nullable|exists:sales,id',
            'customer_id'               => 'nullable|exists:customers,id',
            'customer_name'             => 'nullable|string|max:150',
            'deduction'                 => 'nullable|numeric|min:0',
            'refund_method'             => 'required|in:cash,card,online,none',
            'reason'                    => 'nullable|string|max:255',
            'items'                     => 'required|array|min:1',
            'items.*.quantity'          => 'required|integer|min:0',
            'items.*.condition'         => 'required|in:good,damaged,expired',
            'items.*.sale_item_id'      => $withInvoice ? 'required|integer|exists:sale_items,id' : 'nullable',
            'items.*.medicine_id'       => $withInvoice ? 'nullable' : 'required|integer|exists:medicines,id',
            'items.*.medicine_batch_id' => $withInvoice ? 'nullable' : 'required|integer|exists:medicine_batches,id',
            'items.*.price'             => $withInvoice ? 'nullable' : 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                     => 'Add at least one medicine to return.',
            'items.*.medicine_batch_id.required' => 'Select the batch for every returned medicine.',
        ];
    }
}
