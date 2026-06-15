<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStudent extends Model
{
    protected $fillable = [
        'project_id',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
