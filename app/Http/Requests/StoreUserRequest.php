<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:100'],
            'email'               => ['required', 'email', 'unique:users,email'],
            'password'            => ['required', 'string', 'min:8'],
            'registration_number' => ['nullable', 'string', 'unique:users,registration_number'],
            'role'                => ['required', 'in:super_admin,dept_manager,supervisor,dept_staff,viewer'],
            'department_id'       => ['nullable', 'exists:departments,id'],
            'is_active'           => ['boolean'],
        ];
    }
}
