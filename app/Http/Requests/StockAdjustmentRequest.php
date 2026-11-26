<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isActiveAccount() ?? false) && ($this->user()?->isAdmin() ?? false);
    }

    public function rules(): array
    {
        return [
            'delta' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'stock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
