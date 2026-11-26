<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isActiveAccount() ?? false) && ($this->user()?->isAdmin() ?? false);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($productId)],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'stock_version' => [$product ? 'required' : 'nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
        ];
    }
}
