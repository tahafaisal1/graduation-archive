<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    protected $fillable = ['project_id', 'examiner_id', 'notes'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(Examiner::class);
    }
}
