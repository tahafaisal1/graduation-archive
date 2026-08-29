<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SearchService
{
    public function searchProposals(array $filters): LengthAwarePaginator
    {
        $query = Proposal::with(['department', 'specialization', 'supervisor', 'status'])
            ->withCount('students')
            ->where('is_deleted', false);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['specialization_id'])) {
            $query->where('specialization_id', $filters['specialization_id']);
        }

        if (! empty($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        if (! empty($filters['supervisor_id'])) {
            $query->where('supervisor_id', $filters['supervisor_id']);
        }

        if (! empty($filters['status']) && $filters['status'] === 'active') {
            $query->where('status_id', Proposal::STATUS_ARCHIVED);
        }

        $sort = $filters['sort'] ?? 'created_at';
        match ($sort) {
            'title' => $query->orderBy('title'),
            default => $query->latest(),
        };

        return $query->paginate(15)->withQueryString();
    }

    /** @return Collection<int, Proposal> */
    public function detectSimilarity(string $title, ?int $excludeId = null): Collection
    {
        $query = Proposal::where('is_deleted', false)
            ->where('title', 'like', '%' . $title . '%')
            ->with('department:id,name')
            ->select(['id', 'title', 'academic_year', 'department_id']);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->limit(5)->get();
    }

    /**
     * OR-match a Proposal query across title, description, academic_year,
     * student names, supervisor name, department name, specialization name.
     * $term must already be trimmed + mb_strtolower'd.
     */
    public function proposalTextMatch(Builder $query, string $term): Builder
    {
        $like = '%' . $term . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(proposals.title) LIKE ?', [$like])
              ->orWhereRaw('LOWER(proposals.description) LIKE ?', [$like])
              ->orWhereRaw('LOWER(proposals.academic_year) LIKE ?', [$like])
              ->orWhereHas('students', fn (Builder $s) => $s->whereRaw('LOWER(proposal_students.full_name) LIKE ?', [$like]))
              ->orWhereHas('supervisor', fn (Builder $u) => $u->whereRaw('LOWER(users.name) LIKE ?', [$like]))
              ->orWhereHas('department', fn (Builder $d) => $d->whereRaw('LOWER(departments.name) LIKE ?', [$like]))
              ->orWhereHas('specialization', fn (Builder $sp) => $sp->whereRaw('LOWER(specializations.name) LIKE ?', [$like]));
        });
    }

    /**
     * OR-match a Project query across everything proposalTextMatch covers
     * (via the linked proposal) plus assigned examiner names.
     * $term must already be trimmed + mb_strtolower'd.
     */
    public function projectTextMatch(Builder $projectQuery, string $term): void
    {
        $like = '%' . $term . '%';

        $projectQuery->where(function (Builder $q) use ($term, $like) {
            $q->whereHas('proposal', fn (Builder $p) => $this->proposalTextMatch($p, $term))
              ->orWhereHas('examiners', fn (Builder $e) => $e->whereRaw('LOWER(examiners.full_name) LIKE ?', [$like]));
        });
    }

    public function getFilterOptions(): array
    {
        return [
            'departments'    => Department::with('specializations:id,name,department_id')
                                          ->orderBy('name')
                                          ->get(['id', 'name']),
            'academic_years' => Proposal::where('is_deleted', false)
                                        ->distinct()
                                        ->orderByDesc('academic_year')
                                        ->pluck('academic_year'),
            'supervisors'    => User::role('supervisor')
                                    ->orderBy('name')
                                    ->get(['id', 'name']),
        ];
    }
}
