<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 1;
    public const STATUS_ARCHIVED    = 2;

    protected $fillable = [
        'proposal_id',
        'status_id',
        'final_score',
        'final_file_path',
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

    // Structural guarantee, not a per-call-site convention: every query that
    // retrieves a Project serializes it (Inertia props, JSON), which fires
    // both $appends accessors above. Without this default, every new call
    // site has to remember to eager-load these two exact relations or pay a
    // silent N+1 per row. Callers still eager-load department/specialization
    // explicitly where the view actually needs them.
    protected $with = ['proposal.supervisor', 'proposal.students'];

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

    public function canBeFinalizedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->status_id === self::STATUS_ARCHIVED) {
            return false;
        }

        $departmentId = $this->proposal->department_id;

        if ($user->hasRole('dept_manager') && $user->department_id === $departmentId) {
            return true;
        }

        return $user->hasRole('dept_staff') && $user->department_id === $departmentId;
    }

    /**
     * @return array<int, string>
     */
    public function finalizationBlockers(): array
    {
        $blockers = [];

        $examinerCount = $this->examiners()->count();

        if ($examinerCount === 0) {
            $blockers[] = 'بانتظار تعيين ممتحنين';
        } elseif ($examinerCount === 1) {
            $blockers[] = 'بانتظار تعيين ممتحن آخر';
        } elseif ($examinerCount > 2) {
            // Defensive only — ProjectExaminerController::assign() already blocks a 3rd examiner.
            $blockers[] = 'يجب أن يكون عدد الممتحنين اثنين بالضبط';
        }

        if ($this->final_score === null) {
            $blockers[] = 'بانتظار الدرجة';
        }

        return $blockers;
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
