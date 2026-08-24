<?php

namespace App\Http\Controllers;

use App\Models\Examiner;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        // proposal.supervisor and proposal.students must both be eager-loaded
        // here even though this view doesn't render students — Project's
        // $appends = ['supervisor', 'students'] (Task 2) fires both accessors
        // during JSON serialization for every row, and either one un-loaded
        // means an N+1 across the whole paginated page.
        $projects = Project::with(['proposal.department', 'proposal.specialization', 'proposal.supervisor', 'proposal.students', 'status'])
            ->where('is_deleted', false)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function show(int $id): Response
    {
        $project = Project::with([
            'proposal.department', 'proposal.specialization', 'proposal.supervisor', 'proposal.students',
            'status', 'examiners.department:id,name', 'evaluations',
        ])->where('is_deleted', false)->findOrFail($id);

        $project->increment('visit_count');

        $assignedIds        = $project->examiners->pluck('id');
        $availableExaminers = Examiner::whereNotIn('id', $assignedIds)
            ->with('department:id,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'title', 'department_id']);

        return Inertia::render('Projects/Show', [
            'project'            => $project,
            'availableExaminers' => $availableExaminers,
        ]);
    }
}
