<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function index(): Response
    {
        $stats = [
            'total_projects' => Project::where('status_id', Project::STATUS_ARCHIVED)->where('is_deleted', false)->count(),
            'total_departments' => Department::count(),
            'total_specializations' => Specialization::count(),
        ];

        return Inertia::render('Welcome', ['stats' => $stats]);
    }

    public function browse(Request $request): Response
    {
        $query = Project::query()
            ->where('status_id', Project::STATUS_ARCHIVED)
            ->where('is_deleted', false)
            ->with(['proposal.department', 'proposal.specialization']);

        if ($search = trim((string) $request->input('search'))) {
            $this->search->projectTextMatch($query, mb_strtolower($search));
        }

        if ($deptId = $request->input('department_id')) {
            $query->whereHas('proposal', fn ($q) => $q->where('department_id', $deptId));
        }

        if ($specId = $request->input('specialization_id')) {
            $query->whereHas('proposal', fn ($q) => $q->where('specialization_id', $specId));
        }

        if ($year = $request->input('academic_year')) {
            $query->whereHas('proposal', fn ($q) => $q->where('academic_year', $year));
        }

        $projects = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $specializations = Specialization::orderBy('name')->get(['id', 'name', 'department_id']);
        $years = Proposal::whereHas('instantiatedProject', fn ($q) => $q->where('status_id', Project::STATUS_ARCHIVED))
            ->where('is_deleted', false)
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year');

        return Inertia::render('Public/Browse', [
            'projects' => $projects,
            'departments' => $departments,
            'specializations' => $specializations,
            'years' => $years,
            'filters' => $request->only(['search', 'department_id', 'specialization_id', 'academic_year']),
        ]);
    }

    public function show(int $id): Response
    {
        $project = Project::where('id', $id)
            ->where('status_id', Project::STATUS_ARCHIVED)
            ->where('is_deleted', false)
            ->with(['proposal.department', 'proposal.specialization', 'examiners', 'evaluations'])
            ->firstOrFail();

        Project::where('id', $id)->increment('visit_count');
        $project->visit_count += 1;

        $related = Project::where('status_id', Project::STATUS_ARCHIVED)
            ->where('is_deleted', false)
            ->where('id', '!=', $id)
            ->whereHas('proposal', fn ($q) => $q->where('specialization_id', $project->proposal->specialization_id))
            ->with(['proposal.department', 'proposal.specialization'])
            ->limit(3)
            ->get();

        return Inertia::render('Public/Show', [
            'project' => $project,
            'related' => $related,
        ]);
    }
}
