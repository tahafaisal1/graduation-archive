<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::where('is_deleted', false)->find($this->route('id'));

        if (! $project) {
            return true;
        }

        return $project->canBeFinalizedBy($this->user());
    }

    public function rules(): array
    {
        return [
            'final_file' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $project = Project::where('is_deleted', false)->find($this->route('id'));

            if (! $project) {
                return;
            }

            foreach ($project->finalizationBlockers() as $blocker) {
                $validator->errors()->add('final_file', $blocker);
            }
        });
    }
}
