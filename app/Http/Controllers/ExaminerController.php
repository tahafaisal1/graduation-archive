<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExaminerRequest;
use App\Http\Requests\UpdateExaminerRequest;
use App\Models\Department;
use App\Models\Examiner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExaminerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Examiner::with('department')->withCount('projects');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        return Inertia::render('Examiners/Index', [
            'examiners'   => $query->orderBy('full_name')->get(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters'     => $request->only('department_id'),
        ]);
    }

    public function store(StoreExaminerRequest $request): RedirectResponse
    {
        Examiner::create($request->validated());

        return redirect()->route('examiners.index')
            ->with('success', 'تم إضافة الممتحن بنجاح');
    }

    public function update(UpdateExaminerRequest $request, Examiner $examiner): RedirectResponse
    {
        $examiner->update($request->validated());

        return redirect()->route('examiners.index')
            ->with('success', 'تم تحديث الممتحن بنجاح');
    }

    public function destroy(Examiner $examiner): RedirectResponse
    {
        if ($examiner->projects()->exists()) {
            return back()->with('error', 'لا يمكن حذف الممتحن لارتباطه بمشاريع');
        }

        $examiner->delete();

        return redirect()->route('examiners.index')
            ->with('success', 'تم حذف الممتحن بنجاح');
    }
}
