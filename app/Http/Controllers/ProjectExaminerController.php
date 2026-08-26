<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignExaminerRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProjectExaminerController extends Controller
{
    public function assign(int $projectId, AssignExaminerRequest $request): RedirectResponse
    {
        $project = Project::where('is_deleted', false)->findOrFail($projectId);

        if ($project->status_id === Project::STATUS_ARCHIVED) {
            return back()->with('error', 'لا يمكن التعديل على مشروع مؤرشف نهائيًا');
        }

        if ($project->examiners()->count() >= 2) {
            return back()->with('error', 'لا يمكن إضافة أكثر من ممتحنين لكل مشروع');
        }

        $examinerId = $request->integer('examiner_id');

        if ($project->examiners()->where('examiners.id', $examinerId)->exists()) {
            return back()->with('error', 'الممتحن مرتبط بالمشروع بالفعل');
        }

        $project->examiners()->attach($examinerId, ['assigned_by' => Auth::id()]);

        return back()->with('success', 'تم تعيين الممتحن بنجاح');
    }

    public function remove(int $projectId, int $examinerId): RedirectResponse
    {
        $project = Project::where('is_deleted', false)->findOrFail($projectId);

        if ($project->status_id === Project::STATUS_ARCHIVED) {
            return back()->with('error', 'لا يمكن التعديل على مشروع مؤرشف نهائيًا');
        }

        $project->examiners()->detach($examinerId);

        return back()->with('success', 'تم إزالة الممتحن بنجاح');
    }
}
