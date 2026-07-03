<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Examiner extends Model
{
    use HasFactory;

    protected $fillable = ['full_name', 'title', 'department_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_examiners')
            ->using(ProjectExaminer::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
