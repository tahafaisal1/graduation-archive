<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class DeleteProjectRequest extends FormRequest
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

        return $project->canBeModifiedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
