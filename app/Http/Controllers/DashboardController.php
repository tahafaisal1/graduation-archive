<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $stats = match (true) {
            $user->hasRole('super_admin')  => $this->superAdminStats(),
            $user->hasRole('dept_manager') => $this->deptManagerStats($user),
            $user->hasRole('supervisor')   => $this->supervisorStats($user),
            default                        => $this->basicStats(),
        };

        return Inertia::render('Dashboard', [
            'stats' => $stats,
        ]);
    }

    private function superAdminStats(): array
    {
        return [
            'total_users'       => User::count(),
            'total_projects'    => Project::where('is_deleted', false)->count(),
            'total_departments' => Department::count(),
        ];
    }

    private function deptManagerStats(User $user): array
    {
        return [
            'dept_projects' => Project::where('department_id', $user->department_id)
                ->where('is_deleted', false)
                ->count(),
        ];
    }

    private function supervisorStats(User $user): array
    {
        return [
            'supervised_projects' => $user->supervisedProjects()
                ->where('is_deleted', false)
                ->count(),
        ];
    }

    private function basicStats(): array
    {
        return [
            'total_projects' => Project::where('is_deleted', false)->count(),
        ];
    }
}
