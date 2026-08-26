<?php
// tests/Feature/Proposal/ProposalTest.php

use App\Models\Department;
use App\Models\Proposal;
use App\Models\Specialization;

// beforeEach + makeProposalDeps()/makeProposal() reused from Task 2's test file style — declared locally here too since Pest test files don't share helpers across files unless in Pest.php.

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ProjectStatusSeeder::class);
    $this->seed(\Database\Seeders\ProjectLifecycleStatusSeeder::class);
});

test('dept_staff can create a proposal in their own department', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $staff = userWithRole('dept_staff', ['department_id' => $dept->id]);

    $this->actingAs($staff)->post(route('proposals.store'), [
        'title' => 'My Proposal', 'description' => 'desc', 'academic_year' => '2024/2025',
        'department_id' => $dept->id, 'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'students' => [['full_name' => 'Ahmed', 'registration_number' => 'ST001']],
    ])->assertRedirect();

    $this->assertDatabaseHas('proposals', ['title' => 'My Proposal', 'status_id' => Proposal::STATUS_PENDING, 'created_by' => $staff->id]);
});

test('dept_manager creating a proposal is immediately مؤرشف and gets an instantiated project — no: creation always starts مقترح now, only the instantiate button archives', function () {
    // Under the split, creation NEVER auto-archives (that was the old
    // conflated-model behavior where managers' creates skipped straight to
    // مؤرشف). Now archiving only ever happens via instantiateProject().
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $this->actingAs($manager)->post(route('proposals.store'), [
        'title' => 'Manager Proposal', 'description' => 'desc', 'academic_year' => '2024/2025',
        'department_id' => $dept->id, 'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'students' => [['full_name' => 'Ahmed', 'registration_number' => 'ST001']],
    ])->assertRedirect();

    $this->assertDatabaseHas('proposals', ['title' => 'Manager Proposal', 'status_id' => Proposal::STATUS_PENDING]);
});

// ── Replace / Delete Ownership Rules ─────────────────────────────────────────
// Ported from the pre-split ProjectTest.php's ownership matrix — the same
// canBeModifiedBy() rules apply, just to Proposal now instead of a flat
// Project row (see UpdateProposalRequest/DeleteProposalRequest).

function proposalUpdatePayload(array $deps, array $overrides = []): array
{
    return array_merge([
        'title'             => 'Replaced Title',
        'description'       => 'Replaced description',
        'academic_year'     => '2024/2025',
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'students'          => [
            ['full_name' => 'Ahmed Ali', 'registration_number' => 'ST001'],
        ],
    ], $overrides);
}

test('creator can replace their own pending proposal details', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $staff = userWithRole('dept_staff', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'Original Title', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_PENDING, 'created_by' => $staff->id,
    ]);

    $deps = compact('dept', 'spec', 'supervisor');

    $this->actingAs($staff)
        ->put(route('proposals.update', $proposal->id), proposalUpdatePayload($deps))
        ->assertRedirect();

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'title' => 'Replaced Title']);
});

test('non-creator dept_staff cannot replace another staff member pending proposal', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $creator = userWithRole('dept_staff', ['department_id' => $dept->id]);
    $otherStaff = userWithRole('dept_staff', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'Original Title', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_PENDING, 'created_by' => $creator->id,
    ]);

    $deps = compact('dept', 'spec', 'supervisor');

    $this->actingAs($otherStaff)
        ->put(route('proposals.update', $proposal->id), proposalUpdatePayload($deps))
        ->assertForbidden();
});

test('dept_manager cannot replace an archived (مؤرشف) proposal details', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'Original Title', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_ARCHIVED,
    ]);

    $deps = compact('dept', 'spec', 'supervisor');

    $this->actingAs($manager)
        ->put(route('proposals.update', $proposal->id), proposalUpdatePayload($deps))
        ->assertForbidden();
});

test('super_admin can replace an archived (مؤرشف) proposal details', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    $proposal = Proposal::create([
        'title' => 'Original Title', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_ARCHIVED,
    ]);

    $deps = compact('dept', 'spec', 'supervisor');

    $this->actingAs(userWithRole('super_admin'))
        ->put(route('proposals.update', $proposal->id), proposalUpdatePayload($deps, ['title' => 'Admin Replaced']))
        ->assertRedirect();

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'title' => 'Admin Replaced']);
});

test('dept_manager cannot move a proposal to a different department via replace', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'Original Title', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_PENDING,
    ]);

    $deps = compact('dept', 'spec', 'supervisor');

    // Only super_admin may move a proposal across departments via update —
    // UpdateProposalRequest rejects any non-super_admin whose payload
    // department_id doesn't match the proposal's current department, even
    // a dept_manager who otherwise passes canBeModifiedBy().
    $this->actingAs($manager)
        ->put(route('proposals.update', $proposal->id), proposalUpdatePayload($deps, ['department_id' => $otherDept->id]))
        ->assertForbidden();

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'department_id' => $dept->id]);
});

test('super_admin can move a proposal to a different department via replace', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $otherSpec = Specialization::factory()->create(['department_id' => $otherDept->id]);
    $supervisor = userWithRole('supervisor');

    $proposal = Proposal::create([
        'title' => 'Original Title', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_PENDING,
    ]);

    $deps = compact('dept', 'spec', 'supervisor');

    $this->actingAs(userWithRole('super_admin'))
        ->put(route('proposals.update', $proposal->id), proposalUpdatePayload($deps, [
            'department_id'     => $otherDept->id,
            'specialization_id' => $otherSpec->id,
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'department_id' => $otherDept->id]);
});

test('creator can delete their own pending proposal', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $staff = userWithRole('dept_staff', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'Original Title', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_PENDING, 'created_by' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->delete(route('proposals.destroy', $proposal->id))
        ->assertRedirect(route('proposals.index'));

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'is_deleted' => true]);
});

test('dept_manager cannot delete an archived (مؤرشف) proposal', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'Original Title', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_ARCHIVED,
    ]);

    $this->actingAs($manager)
        ->delete(route('proposals.destroy', $proposal->id))
        ->assertForbidden();

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'is_deleted' => false]);
});
