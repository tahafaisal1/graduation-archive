<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchiveProjectRequest;
use App\Http\Requests\DeleteProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Department;
use App\Models\Examiner;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search', 'department_id', 'specialization_id',
            'academic_year', 'supervisor_id', 'status', 'sort',
        ]);

        return Inertia::render('Projects/Index', [
            'projects'      => $this->search->searchProjects($filters),
            'filterOptions' => $this->search->getFilterOptions(),
            'filters'       => $filters,
        ]);
    }

    public function create(): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasAnyRole(['dept_staff', 'dept_manager', 'super_admin'])) {
            abort(403);
        }

        return Inertia::render('Projects/Create', [
            'departments'     => Department::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::orderBy('name')->get(['id', 'name', 'department_id']),
            'supervisors'     => User::role('supervisor')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $similar = $this->search->detectSimilarity($data['project_title']);

        $pdfPath = null;
        if ($request->hasFile('pdf_file')) {
            $pdfPath = $request->file('pdf_file')->store('projects', 'public');
        }

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $statusId = $user->hasAnyRole(['dept_manager', 'super_admin'])
            ? Project::STATUS_ARCHIVED
            : Project::STATUS_PENDING;

        $project = Project::create([
            'project_title'     => $data['project_title'],
            'description'       => $data['description'],
            'academic_year'     => $data['academic_year'],
            'department_id'     => $data['department_id'],
            'specialization_id' => $data['specialization_id'],
            'supervisor_id'     => $data['supervisor_id'],
            'current_status_id' => $statusId,
            'created_by'        => $user->id,
            'draft_file_path'   => $pdfPath,
            'is_deleted'        => false,
        ]);

        foreach ($data['students'] as $student) {
            $project->students()->create([
                'full_name'           => $student['full_name'],
                'registration_number' => $student['registration_number'],
                'status'              => 'active',
            ]);
        }

        if ($pdfPath) {
            $project->documents()->create([
                'document_type' => 'final_report',
                'file_path'     => $pdfPath,
                'is_final'      => $statusId === Project::STATUS_ARCHIVED,
            ]);
        }

        $redirect = redirect()->route('projects.show', $project)->with('success', 'تم إنشاء المشروع بنجاح');

        if ($similar->isNotEmpty()) {
            $redirect->with('similarity_warning', $similar->map(fn ($p) => [
                'id'            => $p->id,
                'project_title' => $p->project_title,
                'academic_year' => $p->academic_year,
                'department'    => $p->department?->name,
            ])->all());
        }

        return $redirect;
    }

    public function show(int $id): Response
    {
        $project = Project::with([
            'department',
            'specialization',
            'supervisor',
            'students',
            'documents',
            'examiners.department:id,name',
            'evaluations',
            'currentStatus',
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

    public function edit(int $id): Response
    {
        $project = Project::where('is_deleted', false)->findOrFail($id);

        if (! $project->canBeModifiedBy(Auth::user())) {
            abort(403);
        }

        return Inertia::render('Projects/Edit', [
            'project'         => $project->load(['students', 'documents']),
            'departments'     => Department::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::orderBy('name')->get(['id', 'name', 'department_id']),
            'supervisors'     => User::role('supervisor')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProjectRequest $request, int $id): RedirectResponse
    {
        $project = Project::where('is_deleted', false)->findOrFail($id);

        $data    = $request->validated();
        $similar = $this->search->detectSimilarity($data['project_title'], $id);
        $pdfPath = $project->draft_file_path;

        if ($request->hasFile('pdf_file')) {
            if ($pdfPath) {
                Storage::disk('public')->delete($pdfPath);
            }
            $pdfPath = $request->file('pdf_file')->store('projects', 'public');

            $project->documents()->create([
                'document_type' => 'final_report',
                'file_path'     => $pdfPath,
                'is_final'      => $project->current_status_id === Project::STATUS_ARCHIVED,
            ]);
        }

        $project->update([
            'project_title'     => $data['project_title'],
            'description'       => $data['description'],
            'academic_year'     => $data['academic_year'],
            'department_id'     => $data['department_id'],
            'specialization_id' => $data['specialization_id'],
            'supervisor_id'     => $data['supervisor_id'],
            'draft_file_path'   => $pdfPath,
        ]);

        $project->students()->delete();
        foreach ($data['students'] as $student) {
            $project->students()->create([
                'full_name'           => $student['full_name'],
                'registration_number' => $student['registration_number'],
                'status'              => 'active',
            ]);
        }

        $redirect = redirect()->route('projects.show', $project)->with('success', 'تم تحديث المشروع بنجاح');

        if ($similar->isNotEmpty()) {
            $redirect->with('similarity_warning', $similar->map(fn ($p) => [
                'id'            => $p->id,
                'project_title' => $p->project_title,
                'academic_year' => $p->academic_year,
                'department'    => $p->department?->name,
            ])->all());
        }

        return $redirect;
    }

    public function destroy(DeleteProjectRequest $request, int $id): RedirectResponse
    {
        $project = Project::where('is_deleted', false)->findOrFail($id);

        $project->update(['is_deleted' => true]);

        return redirect()->route('projects.index')
            ->with('success', 'تم حذف المشروع بنجاح');
    }

    public function archive(ArchiveProjectRequest $request, int $id): RedirectResponse
    {
        $project = Project::where('is_deleted', false)->findOrFail($id);

        $project->update(['current_status_id' => Project::STATUS_ARCHIVED]);

        return back()->with('success', 'تم أرشفة المشروع بنجاح');
    }
}
