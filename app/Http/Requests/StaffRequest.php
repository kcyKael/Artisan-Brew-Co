<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isActiveAccount() ?? false) && ($this->user()?->isAdmin() ?? false);
    }

    public function rules(): array
    {
        $staff = $this->route('staff');
        $staffId = $staff?->id;

        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($staffId),
            ],
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                Rule::requiredIf(fn () => ! $staff && $this->boolean('invite_mode')),
                'nullable',
                'string',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($staffId),
            ],
            'invite_mode' => ['nullable', 'boolean'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_CASHIER])],
            'is_active' => ['nullable', 'boolean'],
            'password' => [
                Rule::requiredIf(fn () => ! $staff && ! $this->boolean('invite_mode')),
                'nullable',
                'string',
                'min:8',
                'max:100',
                'confirmed',
            ],
            'remove_avatar' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }
}
