<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->hasAnyRole(['dept_staff', 'dept_manager', 'super_admin'])) {
            return false;
        }

        // dept_staff may only submit projects for their own department
        if ($user->hasRole('dept_staff') && (int) $this->department_id !== $user->department_id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'project_title'                  => ['required', 'string', 'max:255'],
            'description'                    => ['required', 'string'],
            'academic_year'                  => ['required', 'string', 'max:20'],
            'department_id'                  => ['required', 'integer', 'exists:departments,id'],
            'specialization_id'              => ['required', 'integer', 'exists:specializations,id'],
            'supervisor_id'                  => ['required', 'integer', 'exists:users,id'],
            'pdf_file'                       => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
            'students'                       => ['required', 'array', 'min:1'],
            'students.*.full_name'           => ['required', 'string'],
            'students.*.registration_number' => ['required', 'string'],
        ];
    }
}
