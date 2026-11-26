<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActiveAccount() ?? false;
    }

    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'string', 'min:16', 'max:64'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'cash_received' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.addon_ids' => ['nullable', 'array', 'max:20'],
            'items.*.addon_ids.*' => ['integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item to complete the checkout.',
            'items.min' => 'Add at least one item to complete the checkout.',
            'cash_received.regex' => 'Cash received may have at most two decimal places.',
        ];
    }
}
