<?php

namespace App\Http\Requests;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

class DeleteProposalRequest extends FormRequest
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

        return $proposal->canBeModifiedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
