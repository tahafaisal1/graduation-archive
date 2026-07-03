<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'total_projects'       => Project::where('current_status_id', 1)->where('is_deleted', false)->count(),
            'total_departments'    => Department::count(),
            'total_specializations'=> Specialization::count(),
        ];

        return Inertia::render('Welcome', ['stats' => $stats]);
    }

    public function browse(Request $request): Response
    {
        $query = Project::query()
            ->where('current_status_id', 1)
            ->where('is_deleted', false)
            ->with(['department', 'specialization', 'supervisor', 'students']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('project_title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($deptId = $request->input('department_id')) {
            $query->where('department_id', $deptId);
        }

        if ($specId = $request->input('specialization_id')) {
            $query->where('specialization_id', $specId);
        }

        if ($year = $request->input('academic_year')) {
            $query->where('academic_year', $year);
        }

        $projects = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $specializations = Specialization::orderBy('name')->get(['id', 'name', 'department_id']);
        $years = Project::where('current_status_id', 1)
            ->where('is_deleted', false)
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year');

        return Inertia::render('Public/Browse', [
            'projects'        => $projects,
            'departments'     => $departments,
            'specializations' => $specializations,
            'years'           => $years,
            'filters'         => $request->only(['search', 'department_id', 'specialization_id', 'academic_year']),
        ]);
    }

    public function show(int $id): Response
    {
        $project = Project::where('id', $id)
            ->where('current_status_id', 1)
            ->where('is_deleted', false)
            ->with([
                'department',
                'specialization',
                'supervisor',
                'students',
                'documents',
                'examiners',
                'evaluations',
            ])
            ->firstOrFail();

        Project::where('id', $id)->increment('visit_count');
        $project->visit_count += 1;

        $related = Project::where('specialization_id', $project->specialization_id)
            ->where('current_status_id', 1)
            ->where('is_deleted', false)
            ->where('id', '!=', $id)
            ->with(['department', 'specialization', 'supervisor', 'students'])
            ->limit(3)
            ->get();

        return Inertia::render('Public/Show', [
            'project' => $project,
            'related' => $related,
        ]);
    }
}
