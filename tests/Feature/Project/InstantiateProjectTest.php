<?php
// tests/Feature/Project/InstantiateProjectTest.php

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ProjectStatusSeeder::class);
    $this->seed(\Database\Seeders\ProjectLifecycleStatusSeeder::class);
});

function makeInstantiableProposal(): array
{
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'X', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_PENDING,
    ]);

    return compact('proposal', 'manager', 'dept');
}

test('dept_manager of the same department can instantiate and gets redirected to the project show page', function () {
    ['proposal' => $proposal, 'manager' => $manager] = makeInstantiableProposal();

    $response = $this->actingAs($manager)->post(route('proposals.instantiate', $proposal));

    $project = $proposal->fresh()->instantiatedProject()->firstOrFail();
    $response->assertRedirect(route('projects.show', $project));
});

test('dept_manager of a different department gets 403', function () {
    ['proposal' => $proposal] = makeInstantiableProposal();
    $otherManager = userWithRole('dept_manager', ['department_id' => Department::factory()->create()->id]);

    $this->actingAs($otherManager)->post(route('proposals.instantiate', $proposal))->assertForbidden();
});

test('super_admin can instantiate regardless of department', function () {
    ['proposal' => $proposal] = makeInstantiableProposal();
    $admin = userWithRole('super_admin');

    $this->actingAs($admin)->post(route('proposals.instantiate', $proposal))->assertRedirect();
});

test('an already-مؤرشف proposal cannot be instantiated again (double-click guard)', function () {
    ['proposal' => $proposal, 'manager' => $manager] = makeInstantiableProposal();
    $proposal->instantiateProject($manager);

    $this->actingAs($manager)->post(route('proposals.instantiate', $proposal))->assertForbidden();
    expect(Project::where('proposal_id', $proposal->id)->count())->toBe(1);
});

test('dept_staff cannot instantiate', function () {
    ['proposal' => $proposal, 'dept' => $dept] = makeInstantiableProposal();
    $staff = userWithRole('dept_staff', ['department_id' => $dept->id]);

    $this->actingAs($staff)->post(route('proposals.instantiate', $proposal))->assertForbidden();
});
