<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUS_IN_PROGRESS = 1;
    public const STATUS_ARCHIVED    = 2;

    protected $fillable = [
        'proposal_id',
        'status_id',
        'final_score',
        'instantiated_by',
        'instantiated_at',
        'visit_count',
        'is_deleted',
    ];

    // Read-by-reference: supervisor/students are never duplicated onto
    // `projects` — they're proxied through the linked proposal and appended
    // to JSON so the frontend prop shape stays flat (project.supervisor,
    // project.students), unchanged from before the split.
    protected $appends = ['supervisor', 'students'];

    protected function casts(): array
    {
        return [
            'is_deleted'      => 'boolean',
            'final_score'     => 'decimal:2',
            'instantiated_at' => 'datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function instantiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instantiated_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectLifecycleStatus::class, 'status_id');
    }

    public function examiners(): BelongsToMany
    {
        return $this->belongsToMany(Examiner::class, 'project_examiners')
            ->using(ProjectExaminer::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * Proxy accessor — caller must eager-load 'proposal.supervisor' to avoid
     * an N+1 (see ProjectController::show()).
     */
    protected function supervisor(): Attribute
    {
        return Attribute::get(fn () => $this->proposal?->supervisor);
    }

    /**
     * Proxy accessor — caller must eager-load 'proposal.students'.
     */
    protected function students(): Attribute
    {
        return Attribute::get(fn () => $this->proposal?->students ?? collect());
    }
}
