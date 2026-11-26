<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActiveAccount() ?? false;
    }

    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'string', 'min:16', 'max:64'],
            'product_id' => ['required', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'addon_ids' => ['nullable', 'array', 'max:20'],
            'addon_ids.*' => ['integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'cash_received' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Please select a product to sell.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'cash_received.regex' => 'Cash received may have at most two decimal places.',
        ];
    }
}
