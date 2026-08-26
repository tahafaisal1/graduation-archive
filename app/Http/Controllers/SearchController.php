<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search', 'department_id', 'specialization_id',
            'academic_year', 'supervisor_id', 'status', 'sort',
        ]);

        $projects = $this->search->searchProposals($filters);
        $projects->getCollection()->load([
            'students' => fn ($q) => $q->select('id', 'full_name', 'proposal_id'),
        ]);

        return Inertia::render('Search/Index', [
            'projects'      => $projects,
            'filterOptions' => $this->search->getFilterOptions(),
            'filters'       => $filters,
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $suggestions = Proposal::where('is_deleted', false)
            ->where('title', 'like', "%{$q}%")
            ->latest()
            ->limit(5)
            ->pluck('title');

        return response()->json($suggestions);
    }
}
