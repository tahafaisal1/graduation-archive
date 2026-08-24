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
