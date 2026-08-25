<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteProposalRequest;
use App\Http\Requests\InstantiateProjectRequest;
use App\Http\Requests\StoreProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Models\Department;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search', 'department_id', 'specialization_id',
            'academic_year', 'supervisor_id', 'status', 'sort',
        ]);

        return Inertia::render('Proposals/Index', [
            'proposals'     => $this->search->searchProposals($filters),
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

        return Inertia::render('Proposals/Create', [
            'departments'     => Department::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::orderBy('name')->get(['id', 'name', 'department_id']),
            'supervisors'     => User::role('supervisor')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProposalRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $similar = $this->search->detectSimilarity($data['title']);

        $pdfPath = null;
        if ($request->hasFile('pdf_file')) {
            $pdfPath = $request->file('pdf_file')->store('projects', 'public');
        }

        $proposal = Proposal::create([
            'title'             => $data['title'],
            'description'       => $data['description'],
            'academic_year'     => $data['academic_year'],
            'department_id'     => $data['department_id'],
            'specialization_id' => $data['specialization_id'],
            'supervisor_id'     => $data['supervisor_id'],
            'status_id'         => Proposal::STATUS_PENDING,
            'created_by'        => Auth::id(),
            'draft_file_path'   => $pdfPath,
            'is_deleted'        => false,
        ]);

        foreach ($data['students'] as $student) {
            $proposal->students()->create([
                'full_name'           => $student['full_name'],
                'registration_number' => $student['registration_number'],
                'status'              => 'active',
            ]);
        }

        $redirect = redirect()->route('proposals.show', $proposal)->with('success', 'تم إنشاء المقترح بنجاح');

        if ($similar->isNotEmpty()) {
            $redirect->with('similarity_warning', $this->formatSimilarityWarning($similar));
        }

        return $redirect;
    }

    public function show(int $id): Response
    {
        $proposal = Proposal::with([
            'department', 'specialization', 'supervisor', 'students', 'status', 'createdBy',
            'instantiatedProject',
        ])->where('is_deleted', false)->findOrFail($id);

        return Inertia::render('Proposals/Show', ['proposal' => $proposal]);
    }

    public function edit(int $id): Response
    {
        $proposal = Proposal::where('is_deleted', false)->findOrFail($id);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $proposal->canBeModifiedBy($user)) {
            abort(403);
        }

        return Inertia::render('Proposals/Edit', [
            'proposal'        => $proposal->load('students'),
            'departments'     => Department::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::orderBy('name')->get(['id', 'name', 'department_id']),
            'supervisors'     => User::role('supervisor')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProposalRequest $request, int $id): RedirectResponse
    {
        $proposal = Proposal::where('is_deleted', false)->findOrFail($id);
        $data     = $request->validated();
        $similar  = $this->search->detectSimilarity($data['title'], $id);
        $pdfPath  = $proposal->draft_file_path;

        if ($request->hasFile('pdf_file')) {
            if ($pdfPath) {
                Storage::disk('public')->delete($pdfPath);
            }
            $pdfPath = $request->file('pdf_file')->store('projects', 'public');
        }

        $proposal->update([
            'title'             => $data['title'],
            'description'       => $data['description'],
            'academic_year'     => $data['academic_year'],
            'department_id'     => $data['department_id'],
            'specialization_id' => $data['specialization_id'],
            'supervisor_id'     => $data['supervisor_id'],
            'draft_file_path'   => $pdfPath,
        ]);

        $proposal->students()->delete();
        foreach ($data['students'] as $student) {
            $proposal->students()->create([
                'full_name'           => $student['full_name'],
                'registration_number' => $student['registration_number'],
                'status'              => 'active',
            ]);
        }

        $redirect = redirect()->route('proposals.show', $proposal)->with('success', 'تم تحديث المقترح بنجاح');

        if ($similar->isNotEmpty()) {
            $redirect->with('similarity_warning', $this->formatSimilarityWarning($similar));
        }

        return $redirect;
    }

    public function destroy(DeleteProposalRequest $request, int $id): RedirectResponse
    {
        $proposal = Proposal::where('is_deleted', false)->findOrFail($id);

        // A proposal that was already instantiated carries a linked Project —
        // deleting only the proposal would leave that Project fully visible
        // everywhere (public browse, /projects, report stats), silently
        // defeating the delete. Both flags must flip together.
        DB::transaction(function () use ($proposal) {
            $proposal->update(['is_deleted' => true]);
            $proposal->instantiatedProject()->update(['is_deleted' => true]);
        });

        return redirect()->route('proposals.index')->with('success', 'تم حذف المقترح بنجاح');
    }

    public function instantiate(InstantiateProjectRequest $request, int $id): RedirectResponse
    {
        $proposal = Proposal::where('is_deleted', false)->findOrFail($id);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $project = $proposal->instantiateProject($user);

        return redirect()->route('projects.show', $project)->with('success', 'تم أرشفة المقترح وإنشاء المشروع بنجاح');
    }

    /**
     * @param  Collection<int, Proposal>  $similar
     * @return array<int, array{id: int, project_title: string, academic_year: string, department: ?string}>
     */
    private function formatSimilarityWarning(Collection $similar): array
    {
        return $similar->map(fn (Proposal $p) => [
            'id'             => $p->id,
            'project_title'  => $p->title,
            'academic_year'  => $p->academic_year,
            'department'     => $p->department?->name,
        ])->all();
    }
}
