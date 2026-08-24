<?php
// tests/Feature/Models/ProjectModelTest.php

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalStudent;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\ProjectLifecycleStatusSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);
});

test('project supervisor and students are read by reference through the proposal', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'X', 'academic_year' => '2024/2025',
        'department_id' => $dept->id, 'specialization_id' => $spec->id,
        'supervisor_id' => $supervisor->id, 'status_id' => Proposal::STATUS_PENDING,
    ]);
    ProposalStudent::create(['proposal_id' => $proposal->id, 'full_name' => 'Ahmed', 'registration_number' => 'ST001']);

    $project = $proposal->instantiateProject($manager);
    $project->load('proposal.supervisor', 'proposal.students');

    expect($project->supervisor->id)->toBe($supervisor->id)
        ->and($project->students)->toHaveCount(1)
        ->and($project->students->first()->full_name)->toBe('Ahmed');
});
