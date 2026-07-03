<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SearchService
{
    private const STATUS_ARCHIVED = 1;

    public function searchProjects(array $filters): LengthAwarePaginator
    {
        $query = Project::with(['department', 'specialization', 'supervisor', 'currentStatus'])
            ->withCount('students')
            ->where('is_deleted', false);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('project_title', 'like', "%{$search}%")
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

        // 'active' restricts to published (archived) projects only
        if (! empty($filters['status']) && $filters['status'] === 'active') {
            $query->where('current_status_id', self::STATUS_ARCHIVED);
        }

        $sort = $filters['sort'] ?? 'created_at';
        match ($sort) {
            'title'       => $query->orderBy('project_title'),
            'visit_count' => $query->orderByDesc('visit_count'),
            default       => $query->latest(),
        };

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Find projects with a similar title using a LIKE query.
     * Excludes $excludeId when checking an existing project being edited.
     *
     * @return Collection<int, Project>
     */
    public function detectSimilarity(string $title, ?int $excludeId = null): Collection
    {
        $query = Project::where('is_deleted', false)
            ->where('project_title', 'like', '%' . $title . '%')
            ->with('department:id,name')
            ->select(['id', 'project_title', 'academic_year', 'department_id']);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->limit(5)->get();
    }

    /**
     * Return all data needed to populate the filter dropdowns.
     */
    public function getFilterOptions(): array
    {
        return [
            'departments'    => Department::with('specializations:id,name,department_id')
                                          ->orderBy('name')
                                          ->get(['id', 'name']),
            'academic_years' => Project::where('is_deleted', false)
                                        ->distinct()
                                        ->orderByDesc('academic_year')
                                        ->pluck('academic_year'),
            'supervisors'    => User::role('supervisor')
                                    ->orderBy('name')
                                    ->get(['id', 'name']),
        ];
    }
}
