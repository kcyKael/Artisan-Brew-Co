<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActiveAccount() ?? false;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'remove_avatar' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
        ];
    }
}
