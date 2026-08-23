<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    public const STATUS_ARCHIVED = 1;
    public const STATUS_PENDING  = 2;

    protected $fillable = [
        'project_title',
        'description',
        'academic_year',
        'department_id',
        'specialization_id',
        'supervisor_id',
        'current_status_id',
        'created_by',
        'based_on_project_id',
        'draft_file_path',
        'final_score',
        'visit_count',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'is_deleted'              => 'boolean',
            'final_score'             => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentStatus(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'current_status_id');
    }

    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'based_on_project_id');
    }

    public function canBeModifiedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->current_status_id !== self::STATUS_PENDING) {
            return false;
        }

        if ($user->hasRole('dept_manager') && $user->department_id === $this->department_id) {
            return true;
        }

        return $this->created_by === $user->id && $user->department_id === $this->department_id;
    }

    public function canBeArchivedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasRole('dept_manager')
            && $user->department_id === $this->department_id
            && $this->current_status_id === self::STATUS_PENDING;
    }

    public function students(): HasMany
    {
        return $this->hasMany(ProjectStudent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function examiners(): BelongsToMany
    {
        return $this->belongsToMany(Examiner::class, 'project_examiners')
            ->using(ProjectExaminer::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
