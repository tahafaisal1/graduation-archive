<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name'                => ['required', 'string', 'max:100'],
            'email'               => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'password'            => ['nullable', 'string', 'min:8'],
            'registration_number' => ['nullable', 'string', Rule::unique('users', 'registration_number')->ignore($user)],
            'role'                => ['required', 'in:super_admin,dept_manager,supervisor,dept_staff,viewer'],
            'department_id'       => ['nullable', 'exists:departments,id'],
        ];
    }
}
