<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;

class ReportService
{
    public function getDashboardStats(): array
    {
        $currentYear = now()->year;

        return [
            'total_projects'     => Proposal::where('is_deleted', false)->count(),
            'total_departments'  => Department::count(),
            'projects_this_year' => Proposal::where('is_deleted', false)
                ->where('academic_year', 'like', "%{$currentYear}%")
                ->count(),
            'pending_approvals'  => Proposal::where('is_deleted', false)
                ->where('status_id', Proposal::STATUS_PENDING)
                ->count(),
            'recent_projects'    => Proposal::with([
                    'department:id,name',
                    'specialization:id,name',
                    'status:id,status_name',
                ])
                ->where('is_deleted', false)
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'academic_year', 'department_id', 'specialization_id', 'status_id', 'created_at']),
            'by_status'          => Proposal::where('is_deleted', false)
                ->join('project_status', 'proposals.status_id', '=', 'project_status.id')
                ->groupBy('project_status.id', 'project_status.status_name')
                ->selectRaw('project_status.status_name, COUNT(proposals.id) as count')
                ->get(),
        ];
    }

    public function getDepartmentReport(?int $departmentId = null): array
    {
        $departments = Department::withCount([
                'proposals as project_count' => fn ($q) => $q->where('is_deleted', false),
            ])
            ->with([
                'specializations' => fn ($q) => $q->withCount([
                    'proposals as project_count' => fn ($q2) => $q2->where('is_deleted', false),
                ]),
            ])
            ->when($departmentId, fn ($q) => $q->where('id', $departmentId))
            ->get(['id', 'name', 'code']);

        $avgScores = Project::query()
            ->join('proposals', 'projects.proposal_id', '=', 'proposals.id')
            ->where('projects.is_deleted', false)
            ->whereNotNull('projects.final_score')
            ->when($departmentId, fn ($q) => $q->where('proposals.department_id', $departmentId))
            ->groupBy('proposals.department_id')
            ->selectRaw('proposals.department_id, ROUND(AVG(projects.final_score), 2) as avg_score, COUNT(*) as scored_count')
            ->get()
            ->keyBy('department_id');

        $supervisors = User::role('supervisor')
            ->withCount([
                'supervisedProposals as project_count' => fn ($q) => $q
                    ->where('is_deleted', false)
                    ->when($departmentId, fn ($q2) => $q2->where('department_id', $departmentId)),
            ])
            ->with('department:id,name')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderByDesc('project_count')
            ->get(['id', 'name', 'department_id']);

        return [
            'departments' => $departments->map(fn ($dept) => [
                'id'              => $dept->id,
                'name'            => $dept->name,
                'code'            => $dept->code,
                'project_count'   => $dept->project_count,
                'avg_score'       => $avgScores->get($dept->id)?->avg_score,
                'scored_count'    => (int) ($avgScores->get($dept->id)?->scored_count ?? 0),
                'specializations' => $dept->specializations->map(fn ($spec) => [
                    'id'            => $spec->id,
                    'name'          => $spec->name,
                    'project_count' => $spec->project_count,
                ])->values(),
            ])->values(),
            'supervisors' => $supervisors->map(fn ($sup) => [
                'id'            => $sup->id,
                'name'          => $sup->name,
                'department'    => $sup->department?->name,
                'project_count' => $sup->project_count,
            ])->values(),
        ];
    }

    public function getSpecializationTrends(): array
    {
        $top = Specialization::withCount([
                'proposals as project_count' => fn ($q) => $q->where('is_deleted', false),
            ])
            ->with('department:id,name')
            ->orderByDesc('project_count')
            ->limit(10)
            ->get(['id', 'name', 'department_id']);

        $rare = Specialization::withCount([
                'proposals as project_count' => fn ($q) => $q->where('is_deleted', false),
            ])
            ->with('department:id,name')
            ->orderBy('project_count')
            ->limit(5)
            ->get(['id', 'name', 'department_id']);

        $byYear = Proposal::where('is_deleted', false)
            ->join('specializations', 'proposals.specialization_id', '=', 'specializations.id')
            ->groupBy('specializations.id', 'specializations.name', 'proposals.academic_year')
            ->selectRaw('specializations.id, specializations.name as spec_name, proposals.academic_year, COUNT(proposals.id) as count')
            ->orderBy('proposals.academic_year')
            ->get()
            ->groupBy('id')
            ->map(fn ($items) => $items->values());

        return [
            'top_specializations'  => $top,
            'rare_specializations' => $rare,
            'by_year'              => $byYear,
        ];
    }

    public function getSupervisorReport(): array
    {
        $supervisors = User::role('supervisor')
            ->with('department:id,name')
            ->withCount([
                'supervisedProposals as project_count' => fn ($q) => $q->where('is_deleted', false),
            ])
            ->orderByDesc('project_count')
            ->get(['id', 'name', 'department_id']);

        $avgScores = Project::query()
            ->join('proposals', 'projects.proposal_id', '=', 'proposals.id')
            ->where('projects.is_deleted', false)
            ->whereNotNull('projects.final_score')
            ->groupBy('proposals.supervisor_id')
            ->selectRaw('proposals.supervisor_id, ROUND(AVG(projects.final_score), 2) as avg_score, COUNT(*) as scored_count')
            ->get()
            ->keyBy('supervisor_id');

        $byYear = Proposal::where('is_deleted', false)
            ->groupBy('supervisor_id', 'academic_year')
            ->selectRaw('supervisor_id, academic_year, COUNT(*) as count')
            ->orderBy('academic_year')
            ->get()
            ->groupBy('supervisor_id')
            ->map(fn ($items) => $items->values());

        return $supervisors->map(function ($sup) use ($avgScores, $byYear) {
            $score = $avgScores->get($sup->id);

            return [
                'id'            => $sup->id,
                'name'          => $sup->name,
                'department'    => $sup->department?->name,
                'project_count' => $sup->project_count,
                'avg_score'     => $score?->avg_score,
                'scored_count'  => (int) ($score?->scored_count ?? 0),
                'by_year'       => ($byYear->get($sup->id) ?? collect())->map(fn ($row) => [
                    'year'  => $row->academic_year,
                    'count' => $row->count,
                ])->values(),
            ];
        })->values()->all();
    }

    public function getYearlyComparisonReport(): array
    {
        $perYear = Proposal::where('is_deleted', false)
            ->groupBy('academic_year')
            ->selectRaw('academic_year, COUNT(*) as count')
            ->orderBy('academic_year')
            ->get();

        $yearly = $perYear->values()->map(function ($row, $index) use ($perYear) {
            $prev   = $index > 0 ? $perYear->get($index - 1) : null;
            $growth = ($prev && $prev->count > 0)
                ? round((($row->count - $prev->count) / $prev->count) * 100, 2)
                : null;

            return [
                'year'       => $row->academic_year,
                'count'      => $row->count,
                'growth_pct' => $growth,
            ];
        });

        $deptByYear = Proposal::where('is_deleted', false)
            ->join('departments', 'proposals.department_id', '=', 'departments.id')
            ->groupBy('proposals.academic_year', 'departments.id', 'departments.name')
            ->selectRaw('proposals.academic_year, departments.id as department_id, departments.name as department_name, COUNT(proposals.id) as count')
            ->orderBy('proposals.academic_year')
            ->get()
            ->groupBy('academic_year')
            ->map(fn ($items) => $items->values());

        return [
            'yearly'             => $yearly->values(),
            'department_by_year' => $deptByYear,
        ];
    }
}
