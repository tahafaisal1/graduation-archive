<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class DeleteProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::where('is_deleted', false)->find($this->route('project'));

        if (! $project) {
            return true;
        }

        return $project->canBeModifiedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
