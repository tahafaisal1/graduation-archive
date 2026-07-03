<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        $departments = Department::withCount('specializations')
            ->with('specializations')
            ->orderBy('name')
            ->get();

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Departments/Create');
    }

    public function store(StoreDepartmentRequest $request)
    {
        Department::create($request->validated());

        return redirect()->route('departments.index')
            ->with('success', 'تم إنشاء القسم بنجاح');
    }

    public function edit(Department $department): Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->hasRole('dept_manager') && $user->department_id !== $department->id) {
            abort(403);
        }

        return Inertia::render('Departments/Edit', [
            'department' => $department->load('specializations'),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->hasRole('dept_manager') && $user->department_id !== $department->id) {
            abort(403);
        }

        $department->update($request->validated());

        return redirect()->route('departments.index')
            ->with('success', 'تم تحديث القسم بنجاح');
    }

    public function destroy(Department $department)
    {
        if ($department->projects()->exists()) {
            return back()->with('error', 'لا يمكن حذف القسم لوجود مشاريع مرتبطة به');
        }

        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', 'تم حذف القسم بنجاح');
    }
}
