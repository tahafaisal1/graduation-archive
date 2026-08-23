<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('project');

        if ($id === null) {
            return false;
        }

        $project = Project::where('is_deleted', false)->find($id);

        if (! $project) {
            return true; // valid id, but not found / already deleted — let the controller's findOrFail produce a 404
        }

        if (! $project->canBeModifiedBy($this->user())) {
            return false;
        }

        if (! $this->user()->hasRole('super_admin')
            && (int) $this->input('department_id') !== $project->department_id
        ) {
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
