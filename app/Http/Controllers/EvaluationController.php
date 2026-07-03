<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEvaluationRequest;
use App\Http\Requests\UpdateScoreRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class EvaluationController extends Controller
{
    public function store(int $projectId, StoreEvaluationRequest $request): RedirectResponse
    {
        $project = Project::where('is_deleted', false)->findOrFail($projectId);

        $project->evaluations()->create($request->validated());

        return back()->with('success', 'تم إضافة التقييم بنجاح');
    }

    public function updateScore(int $projectId, UpdateScoreRequest $request): RedirectResponse
    {
        $project = Project::where('is_deleted', false)->findOrFail($projectId);

        $project->update(['final_score' => $request->validated('final_score')]);

        return back()->with('success', 'تم تحديث الدرجة النهائية بنجاح');
    }
}
