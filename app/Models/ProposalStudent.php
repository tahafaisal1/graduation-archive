<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalStudent extends Model
{
    protected $fillable = [
        'proposal_id',
        'full_name',
        'registration_number',
        'status',
        'withdrawal_date',
    ];

    protected function casts(): array
    {
        return [
            'withdrawal_date' => 'date',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
