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

function makeFinalizeProject(array $overrides = []): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    $proposal = Proposal::factory()->create(array_merge([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
    ], $overrides));

    $project = $proposal->instantiateProject($supervisor);

    return compact('dept', 'spec', 'project');
}

test('canBeFinalizedBy: dept_manager of the project department can finalize while قيد التنفيذ', function () {
    $data    = makeFinalizeProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);

    expect($data['project']->canBeFinalizedBy($manager))->toBeTrue();
});

test('canBeFinalizedBy: dept_staff of the project department can finalize', function () {
    $data  = makeFinalizeProject();
    $staff = userWithRole('dept_staff', ['department_id' => $data['dept']->id]);

    expect($data['project']->canBeFinalizedBy($staff))->toBeTrue();
});

test('canBeFinalizedBy: dept_manager of a different department cannot finalize', function () {
    $data         = makeFinalizeProject();
    $otherDept    = Department::factory()->create();
    $otherManager = userWithRole('dept_manager', ['department_id' => $otherDept->id]);

    expect($data['project']->canBeFinalizedBy($otherManager))->toBeFalse();
});

test('canBeFinalizedBy: super_admin can always finalize', function () {
    $data  = makeFinalizeProject();
    $admin = userWithRole('super_admin');

    expect($data['project']->canBeFinalizedBy($admin))->toBeTrue();
});

test('canBeFinalizedBy: false once already مؤرشف', function () {
    $data    = makeFinalizeProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    $data['project']->update(['status_id' => Project::STATUS_ARCHIVED]);

    expect($data['project']->canBeFinalizedBy($manager))->toBeFalse();
});

test('canBeFinalizedBy: false for super_admin once already مؤرشف', function () {
    $data  = makeFinalizeProject();
    $admin = userWithRole('super_admin');
    $data['project']->update(['status_id' => Project::STATUS_ARCHIVED]);

    expect($data['project']->canBeFinalizedBy($admin))->toBeFalse();
});

test('finalizationBlockers: lists missing examiners and missing score when zero examiners assigned', function () {
    $data = makeFinalizeProject();

    expect($data['project']->finalizationBlockers())
        ->toBe(['بانتظار تعيين ممتحنين', 'بانتظار الدرجة']);
});

test('finalizationBlockers: lists "one more examiner needed" when exactly one examiner assigned', function () {
    $data = makeFinalizeProject();
    $examiner = \App\Models\Examiner::factory()->create(['department_id' => $data['dept']->id]);
    $data['project']->examiners()->attach($examiner->id, ['assigned_by' => null]);
    $data['project']->update(['final_score' => 88]);

    expect($data['project']->finalizationBlockers())->toBe(['بانتظار تعيين ممتحن آخر']);
});

test('finalizationBlockers: empty once exactly two examiners assigned and score set', function () {
    $data = makeFinalizeProject();
    $examinerA = \App\Models\Examiner::factory()->create(['department_id' => $data['dept']->id]);
    $examinerB = \App\Models\Examiner::factory()->create(['department_id' => $data['dept']->id]);
    $data['project']->examiners()->attach($examinerA->id, ['assigned_by' => null]);
    $data['project']->examiners()->attach($examinerB->id, ['assigned_by' => null]);
    $data['project']->update(['final_score' => 88]);

    expect($data['project']->finalizationBlockers())->toBe([]);
});
