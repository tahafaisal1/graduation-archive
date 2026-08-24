<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Proposal extends Model
{
    use HasFactory;

    public const STATUS_ARCHIVED = 1;
    public const STATUS_PENDING  = 2;

    protected $fillable = [
        'title',
        'description',
        'academic_year',
        'department_id',
        'specialization_id',
        'supervisor_id',
        'created_by',
        'draft_file_path',
        'status_id',
        'based_on_project_id',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'is_deleted' => 'boolean',
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

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'status_id');
    }

    public function basedOnProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'based_on_project_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(ProposalStudent::class);
    }

    public function instantiatedProject(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function isEditable(): bool
    {
        return $this->status_id === self::STATUS_PENDING;
    }

    public function isArchived(): bool
    {
        return $this->status_id === self::STATUS_ARCHIVED;
    }

    public function canBeModifiedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->status_id !== self::STATUS_PENDING) {
            return false;
        }

        if ($user->hasRole('dept_manager') && $user->department_id === $this->department_id) {
            return true;
        }

        return $this->created_by === $user->id && $user->department_id === $this->department_id;
    }

    public function canBeInstantiatedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasRole('dept_manager')
            && $user->department_id === $this->department_id
            && $this->status_id === self::STATUS_PENDING;
    }

    /**
     * Single atomic action: locks this proposal (مقترح → مؤرشف) and creates
     * its linked Project row. Either both happen or neither does.
     */
    public function instantiateProject(User $actor): Project
    {
        return DB::transaction(function () use ($actor) {
            $this->update(['status_id' => self::STATUS_ARCHIVED]);

            return $this->instantiatedProject()->create([
                'status_id'       => Project::STATUS_IN_PROGRESS,
                'instantiated_by' => $actor->id,
                'instantiated_at' => now(),
            ]);
        });
    }
}
