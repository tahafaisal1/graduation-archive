<?php

use App\Models\Department;
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
});

/**
 * Creates a مؤرشف Proposal in the given dept/spec and instantiates its
 * linked Project — mirrors ReportTest.php::makeReportProject(). Used only
 * to give the "cannot delete department/specialization that has projects"
 * guards (Department::proposals()/Specialization::proposals()) something to
 * trip on; the guard itself checks the proposals table.
 */
function makeDeptLinkedProject(Department $dept, Specialization $spec, User $supervisor): void
{
    $proposal = Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
        'is_deleted'        => false,
    ]);

    $proposal->instantiateProject($supervisor);
}

// ── Department visibility ─────────────────────────────────────────────────────

test('super_admin can view all departments', function () {
    Department::factory()->count(3)->create();

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('departments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Departments/Index')
            ->has('departments', 3)
        );
});

test('dept_manager can view departments', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('departments.index'))
        ->assertOk();
});

test('dept_staff cannot access department management', function () {
    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('departments.index'))
        ->assertForbidden();
});

// ── Department CRUD ───────────────────────────────────────────────────────────

test('super_admin can create department', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->post(route('departments.store'), [
            'name'        => 'هندسة البرمجيات',
            'code'        => 'SE',
            'description' => 'قسم هندسة البرمجيات',
        ])
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseHas('departments', [
        'name' => 'هندسة البرمجيات',
        'code' => 'SE',
    ]);
});

test('dept_manager cannot create department', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('departments.store'), [
            'name' => 'قسم جديد',
            'code' => 'NEW',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('departments', ['code' => 'NEW']);
});

test('super_admin can update department', function () {
    $dept = Department::factory()->create(['name' => 'القديم', 'code' => 'OLD']);

    $this->actingAs(userWithRole('super_admin'))
        ->put(route('departments.update', $dept), [
            'name' => 'الجديد',
            'code' => 'NEW',
        ])
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'الجديد']);
});

test('super_admin can delete empty department', function () {
    $dept = Department::factory()->create();

    $this->actingAs(userWithRole('super_admin'))
        ->delete(route('departments.destroy', $dept))
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
});

test('cannot delete department that has projects', function () {
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);

    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    makeDeptLinkedProject($dept, $spec, $supervisor);

    $this->actingAs(userWithRole('super_admin'))
        ->delete(route('departments.destroy', $dept))
        ->assertRedirect();

    $this->assertDatabaseHas('departments', ['id' => $dept->id]);
});

// ── Validation ────────────────────────────────────────────────────────────────

test('department name must be unique', function () {
    Department::factory()->create(['name' => 'مكرر', 'code' => 'DUP']);

    $this->actingAs(userWithRole('super_admin'))
        ->post(route('departments.store'), [
            'name' => 'مكرر',
            'code' => 'NEW',
        ])
        ->assertSessionHasErrors('name');
});

test('department code must be unique', function () {
    Department::factory()->create(['name' => 'اسم', 'code' => 'DUP']);

    $this->actingAs(userWithRole('super_admin'))
        ->post(route('departments.store'), [
            'name' => 'اسم آخر',
            'code' => 'DUP',
        ])
        ->assertSessionHasErrors('code');
});

// ── Specializations ───────────────────────────────────────────────────────────

test('specialization belongs to correct department', function () {
    $dept = Department::factory()->create();

    $this->actingAs(userWithRole('super_admin'))
        ->post(route('specializations.store'), [
            'name'          => 'تخصص اختبار',
            'department_id' => $dept->id,
        ]);

    $this->assertDatabaseHas('specializations', [
        'name'          => 'تخصص اختبار',
        'department_id' => $dept->id,
    ]);
});

test('cannot delete specialization that has projects', function () {
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);

    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    makeDeptLinkedProject($dept, $spec, $supervisor);

    $this->actingAs(userWithRole('super_admin'))
        ->delete(route('specializations.destroy', $spec))
        ->assertRedirect();

    $this->assertDatabaseHas('specializations', ['id' => $spec->id]);
});
