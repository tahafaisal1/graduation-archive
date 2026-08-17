<?php

use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
});

test('a new project defaults to status 2 (مقترح) with both approvals null', function () {
    $department = Department::factory()->create();
    $specialization = Specialization::factory()->create(['department_id' => $department->id]);
    $supervisor = userWithRole('supervisor');

    $project = Project::factory()->create([
        'department_id' => $department->id,
        'specialization_id' => $specialization->id,
        'supervisor_id' => $supervisor->id,
        'current_status_id' => 2,
    ]);

    expect($project->current_status_id)->toBe(2)
        ->and($project->currentStatus->status_name)->toBe('مقترح')
        ->and($project->supervisor_approved_by)->toBeNull()
        ->and($project->supervisor_approved_at)->toBeNull()
        ->and($project->department_approved_by)->toBeNull()
        ->and($project->department_approved_at)->toBeNull()
        ->and($project->isSupervisorApproved())->toBeFalse()
        ->and($project->isDepartmentApproved())->toBeFalse();
});

test('a project with both approvals filled can be moved to status 1 (مؤرشف)', function () {
    $department = Department::factory()->create();
    $specialization = Specialization::factory()->create(['department_id' => $department->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager');

    $project = Project::factory()->create([
        'department_id' => $department->id,
        'specialization_id' => $specialization->id,
        'supervisor_id' => $supervisor->id,
        'current_status_id' => 2,
    ]);

    $project->update([
        'supervisor_approved_by' => $supervisor->id,
        'supervisor_approved_at' => now(),
        'department_approved_by' => $manager->id,
        'department_approved_at' => now(),
    ]);
    $project->refresh();

    expect($project->isSupervisorApproved())->toBeTrue()
        ->and($project->isDepartmentApproved())->toBeTrue()
        ->and($project->supervisorApprovedBy->id)->toBe($supervisor->id)
        ->and($project->departmentApprovedBy->id)->toBe($manager->id);

    $project->update(['current_status_id' => 1]);
    $project->refresh();

    expect($project->current_status_id)->toBe(1)
        ->and($project->currentStatus->status_name)->toBe('مؤرشف');
});
