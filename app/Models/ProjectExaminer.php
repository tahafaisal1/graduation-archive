<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectExaminer extends Pivot
{
    protected $table = 'project_examiners';

    protected $fillable = ['project_id', 'examiner_id', 'assigned_by'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(Examiner::class);
    }
}
