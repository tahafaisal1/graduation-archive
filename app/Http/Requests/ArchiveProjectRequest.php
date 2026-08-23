<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class ArchiveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::where('is_deleted', false)->find($this->route('id'));

        if (! $project) {
            return true;
        }

        return $project->canBeArchivedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
