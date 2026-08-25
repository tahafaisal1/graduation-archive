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
        // proposal.supervisor/proposal.students come from Project's own
        // $with default (see Project model) — every retrieval gets them
        // structurally, since Project's $appends accessors fire on every row
        // during JSON serialization. Only what this specific view renders
        // (department, not specialization — Projects/Index.vue's table never
        // shows it) is loaded explicitly here.
        $projects = Project::with(['proposal.department', 'status'])
            ->where('is_deleted', false)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function show(int $id): Response
    {
        $project = Project::with([
            'proposal.department', 'proposal.specialization',
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
