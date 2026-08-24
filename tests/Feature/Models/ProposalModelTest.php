<?php
// tests/Feature/Models/ProposalModelTest.php

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
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

function makeProposalDeps(): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    return compact('dept', 'spec', 'supervisor');
}

function makeProposal(array $deps, array $overrides = []): Proposal
{
    return Proposal::create(array_merge([
        'title'             => 'Test Proposal',
        'description'       => 'desc',
        'academic_year'     => '2024/2025',
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'status_id'         => Proposal::STATUS_PENDING,
        'is_deleted'        => false,
    ], $overrides));
}

test('dept_manager can be modified by dept_manager of the same department while مقترح', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps);

    expect($proposal->canBeModifiedBy($manager))->toBeTrue();
});

test('canBeModifiedBy is false once مؤرشف, even for the creator', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps, ['status_id' => Proposal::STATUS_ARCHIVED, 'created_by' => $manager->id]);

    expect($proposal->canBeModifiedBy($manager))->toBeFalse();
});

test('super_admin can modify regardless of status or department', function () {
    $deps = makeProposalDeps();
    $admin = userWithRole('super_admin');
    $proposal = makeProposal($deps, ['status_id' => Proposal::STATUS_ARCHIVED]);

    expect($proposal->canBeModifiedBy($admin))->toBeTrue();
});

test('canBeInstantiatedBy mirrors canBeArchivedBy: dept_manager of dept, مقترح only', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $otherDept = Department::factory()->create();
    $otherManager = userWithRole('dept_manager', ['department_id' => $otherDept->id]);
    $proposal = makeProposal($deps);

    expect($proposal->canBeInstantiatedBy($manager))->toBeTrue()
        ->and($proposal->canBeInstantiatedBy($otherManager))->toBeFalse();
});

test('instantiateProject creates exactly one Project row and flips proposal status', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps);

    $project = $proposal->instantiateProject($manager);

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->proposal_id)->toBe($proposal->id)
        ->and($project->status_id)->toBe(Project::STATUS_IN_PROGRESS)
        ->and($project->instantiated_by)->toBe($manager->id)
        ->and($project->instantiated_at)->not->toBeNull();

    $proposal->refresh();
    expect($proposal->status_id)->toBe(Proposal::STATUS_ARCHIVED)
        ->and(Project::where('proposal_id', $proposal->id)->count())->toBe(1);
});

test('instantiateProject rolls back the status flip if project creation fails', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps);

    // Force the unique(proposal_id) constraint to collide: pre-create a
    // project row for this proposal outside the transaction, bypassing the
    // model's own guard, to simulate an unexpected mid-transaction failure.
    Project::create([
        'proposal_id' => $proposal->id,
        'status_id'   => Project::STATUS_IN_PROGRESS,
    ]);
    // instantiateProject() itself would normally be blocked by
    // canBeInstantiatedBy() before this point in the real controller flow —
    // here we call it directly to prove the transaction itself is atomic,
    // independent of that outer guard.
    expect(fn () => $proposal->instantiateProject($manager))
        ->toThrow(\Illuminate\Database\QueryException::class);

    $proposal->refresh();
    expect($proposal->status_id)->toBe(Proposal::STATUS_PENDING);
});

test('a مؤرشف proposal cannot be instantiated again', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps);
    $proposal->instantiateProject($manager);

    expect($proposal->canBeInstantiatedBy($manager))->toBeFalse();
});
