<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'order_type' => ['nullable', 'string', 'in:dine_in,take_out'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,gcash'],
            'payment_reference' => ['nullable', 'string', 'max:60'],
            'cup_name' => ['nullable', 'string', 'max:60'],
            'order_note' => ['nullable', 'string', 'max:200'],
            'discount_type' => ['nullable', 'string', 'in:none,percent,fixed,giftcard'],
            'discount_value' => ['nullable', 'required_if:discount_type,percent,fixed', 'numeric', 'min:0', 'max:99999999.99'],
            'gift_card_code' => ['nullable', 'required_if:discount_type,giftcard', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'receipt_email' => ['nullable', 'string', 'email', 'max:191'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'cash_received' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($this->input('payment_method'), [null, '', 'cash'], true)),
                'numeric',
                'min:0',
                'max:99999999.99',
                'regex:/^\d+(?:\.\d{1,2})?$/',
            ],
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
