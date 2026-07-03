<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignExaminerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'examiner_id' => ['required', 'integer', 'exists:examiners,id'],
        ];
    }
}
