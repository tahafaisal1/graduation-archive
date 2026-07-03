<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['dept_staff', 'dept_manager', 'super_admin']);
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
