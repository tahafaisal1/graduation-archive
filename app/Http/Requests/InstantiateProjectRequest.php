<?php

namespace App\Http\Requests;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

class InstantiateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The instantiate route is defined as `proposals/{id}/instantiate`
        // (not `{proposal}`, unlike the resource routes below) — its route
        // parameter is named `id`, matching ProposalController::instantiate's
        // `int $id` signature.
        $id = $this->route('id');

        if ($id === null) {
            return false;
        }

        $proposal = Proposal::where('is_deleted', false)->find($id);

        if (! $proposal) {
            return true;
        }

        return $proposal->canBeInstantiatedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
