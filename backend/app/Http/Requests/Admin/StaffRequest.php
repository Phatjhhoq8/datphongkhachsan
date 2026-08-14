<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$this->isMethod('post') ? 'required' : 'sometimes', 'email', Rule::unique('users')->ignore($user)],
            'password' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(['super_admin', 'hotel_manager', 'receptionist', 'accountant'])],
            'hotel_id' => ['nullable', 'exists:hotels,id'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
