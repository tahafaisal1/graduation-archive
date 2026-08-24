<?php

namespace App\Http\Requests;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('proposal');

        if ($id === null) {
            return false;
        }

        $proposal = Proposal::where('is_deleted', false)->find($id);

        if (! $proposal) {
            return true;
        }

        if (! $proposal->canBeModifiedBy($this->user())) {
            return false;
        }

        if (! $this->user()->hasRole('super_admin')
            && (int) $this->input('department_id') !== $proposal->department_id
        ) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'title'                           => ['required', 'string', 'max:255'],
            'description'                     => ['required', 'string'],
            'academic_year'                   => ['required', 'string', 'max:20'],
            'department_id'                   => ['required', 'integer', 'exists:departments,id'],
            'specialization_id'               => ['required', 'integer', 'exists:specializations,id'],
            'supervisor_id'                   => ['required', 'integer', 'exists:users,id'],
            'pdf_file'                        => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
            'students'                        => ['required', 'array', 'min:1'],
            'students.*.full_name'            => ['required', 'string'],
            'students.*.registration_number'  => ['required', 'string'],
        ];
    }
}
