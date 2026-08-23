<?php

use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
    Storage::fake('public');
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Creates the three foreign-key dependencies every project needs.
 * Pass an existing $dept to reuse a department across helpers.
 */
function makeProjectDeps(?Department $dept = null): array
{
    $dept       = $dept ?? Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    return compact('dept', 'spec', 'supervisor');
}

/**
 * Returns a valid project form payload, mergeable with overrides.
 */
function projectData(array $deps, array $overrides = []): array
{
    return array_merge([
        'project_title'     => 'Test Project Title',
        'description'       => 'Project description text',
        'academic_year'     => '2024/2025',
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'students'          => [
            ['full_name' => 'Ahmed Ali', 'registration_number' => 'ST001'],
        ],
    ], $overrides);
}

// ── Visibility ────────────────────────────────────────────────────────────────

test('super_admin can view all projects', function () {
    $deps = makeProjectDeps();

    Project::factory()->count(3)->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 3)
        );
});

// ── Create / Store ────────────────────────────────────────────────────────────

test('dept_manager can create project', function () {
    $deps = makeProjectDeps();

    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('projects.store'), projectData($deps))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'project_title'     => 'Test Project Title',
        'current_status_id' => 1, // archived — managers bypass approval
    ]);
});

test('dept_staff can create project with pending approval status', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);

    $staff = User::factory()->create(['department_id' => $dept->id]);
    $staff->assignRole('dept_staff');

    $this->actingAs($staff)
        ->post(route('projects.store'), projectData($deps))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'project_title'     => 'Test Project Title',
        'current_status_id' => 2, // proposal_submitted — awaits approval
    ]);
});

test('dept_staff cannot create project in other department', function () {
    $ownDept   = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $deps      = makeProjectDeps($otherDept);

    $staff = User::factory()->create(['department_id' => $ownDept->id]);
    $staff->assignRole('dept_staff');

    $this->actingAs($staff)
        ->post(route('projects.store'), projectData($deps))
        ->assertForbidden();
});

test('created project records the creating user as created_by', function () {
    $deps = makeProjectDeps();
    $manager = userWithRole('dept_manager');

    $this->actingAs($manager)
        ->post(route('projects.store'), projectData($deps))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'project_title' => 'Test Project Title',
        'created_by'    => $manager->id,
    ]);
});

// ── PDF Validation ────────────────────────────────────────────────────────────

test('project rejects non-PDF uploaded file', function () {
    $deps = makeProjectDeps();

    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('projects.store'), projectData($deps, [
            'pdf_file' => UploadedFile::fake()->create('document.txt', 100, 'text/plain'),
        ]))
        ->assertSessionHasErrors('pdf_file');
});

test('project PDF cannot exceed 15MB', function () {
    $deps = makeProjectDeps();

    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('projects.store'), projectData($deps, [
            'pdf_file' => UploadedFile::fake()->create('document.pdf', 16384, 'application/pdf'), // 16 MB
        ]))
        ->assertSessionHasErrors('pdf_file');
});

// ── Approve ───────────────────────────────────────────────────────────────────

test('dept_manager can archive pending project', function () {
    $deps = makeProjectDeps();

    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => 2,
    ]);

    $manager = User::factory()->create(['department_id' => $deps['dept']->id]);
    $manager->assignRole('dept_manager');

    $this->actingAs($manager)
        ->post(route('projects.archive', $project->id))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id'                => $project->id,
        'current_status_id' => 1,
    ]);
});

test('dept_staff cannot archive project', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);

    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => 2,
    ]);

    $staff = User::factory()->create(['department_id' => $dept->id]);
    $staff->assignRole('dept_staff');

    $this->actingAs($staff)
        ->post(route('projects.archive', $project->id))
        ->assertForbidden();
});

// ── Archive ───────────────────────────────────────────────────────────────────

test('dept_manager can archive a pending project in their own department', function () {
    $deps = makeProjectDeps();
    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);

    $manager = User::factory()->create(['department_id' => $deps['dept']->id]);
    $manager->assignRole('dept_manager');

    $this->actingAs($manager)
        ->post(route('projects.archive', $project->id))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'current_status_id' => Project::STATUS_ARCHIVED]);
});

test('dept_manager of a different department cannot archive a pending project', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $manager = User::factory()->create(['department_id' => $otherDept->id]);
    $manager->assignRole('dept_manager');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);

    $this->actingAs($manager)
        ->post(route('projects.archive', $project->id))
        ->assertForbidden();
});

test('cannot archive an already-archived project', function () {
    $deps = makeProjectDeps();
    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    $manager = User::factory()->create(['department_id' => $deps['dept']->id]);
    $manager->assignRole('dept_manager');

    $this->actingAs($manager)
        ->post(route('projects.archive', $project->id))
        ->assertForbidden();
});

test('dept_staff cannot archive a project', function () {
    $deps = makeProjectDeps();
    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);
    $staff = User::factory()->create(['department_id' => $deps['dept']->id]);
    $staff->assignRole('dept_staff');

    $this->actingAs($staff)
        ->post(route('projects.archive', $project->id))
        ->assertForbidden();
});

// ── Delete / Soft Delete ──────────────────────────────────────────────────────

test('dept_manager can soft delete project', function () {
    $deps = makeProjectDeps();

    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);

    $manager = User::factory()->create(['department_id' => $deps['dept']->id]);
    $manager->assignRole('dept_manager');

    $this->actingAs($manager)
        ->delete(route('projects.destroy', $project->id))
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'id'         => $project->id,
        'is_deleted' => true,
    ]);
});

test('dept_staff cannot delete project', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);

    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
    ]);

    $staff = User::factory()->create(['department_id' => $dept->id]);
    $staff->assignRole('dept_staff');

    $this->actingAs($staff)
        ->delete(route('projects.destroy', $project->id))
        ->assertForbidden();
});

// ── Replace / Delete Ownership Rules ─────────────────────────────────────────

test('creator can replace their own pending project details', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $staff = User::factory()->create(['department_id' => $dept->id]);
    $staff->assignRole('dept_staff');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by'        => $staff->id,
    ]);

    $this->actingAs($staff)
        ->put(route('projects.update', $project->id), projectData($deps, ['project_title' => 'Replaced Title']))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'project_title' => 'Replaced Title']);
});

test('non-creator dept_staff cannot replace another staff member pending project', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $creator = User::factory()->create(['department_id' => $dept->id]);
    $otherStaff = User::factory()->create(['department_id' => $dept->id]);
    $otherStaff->assignRole('dept_staff');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by'        => $creator->id,
    ]);

    $this->actingAs($otherStaff)
        ->put(route('projects.update', $project->id), projectData($deps))
        ->assertForbidden();
});

test('dept_manager cannot replace an archived project details', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $manager = User::factory()->create(['department_id' => $dept->id]);
    $manager->assignRole('dept_manager');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project->id), projectData($deps))
        ->assertForbidden();
});

test('super_admin can replace an archived project details', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->put(route('projects.update', $project->id), projectData($deps, ['project_title' => 'Admin Replaced']))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'project_title' => 'Admin Replaced']);
});

test('creator can delete their own pending project', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $staff = User::factory()->create(['department_id' => $dept->id]);
    $staff->assignRole('dept_staff');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by'        => $staff->id,
    ]);

    $this->actingAs($staff)
        ->delete(route('projects.destroy', $project->id))
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'is_deleted' => true]);
});

test('dept_manager cannot delete an archived project', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $manager = User::factory()->create(['department_id' => $dept->id]);
    $manager->assignRole('dept_manager');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    $this->actingAs($manager)
        ->delete(route('projects.destroy', $project->id))
        ->assertForbidden();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'is_deleted' => false]);
});

// ── Visit Count ───────────────────────────────────────────────────────────────

test('project visit count increments on each show', function () {
    $deps = makeProjectDeps();

    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'visit_count'       => 0,
    ]);

    $user = userWithRole('super_admin');
    $this->actingAs($user)->get(route('projects.show', $project->id));
    $this->actingAs($user)->get(route('projects.show', $project->id));

    $this->assertDatabaseHas('projects', [
        'id'          => $project->id,
        'visit_count' => 2,
    ]);
});

// ── Search & Filters ──────────────────────────────────────────────────────────

test('search returns only title-matching projects', function () {
    $deps = makeProjectDeps();

    Project::factory()->create([
        'project_title'     => 'Unique Alpha Title',
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
    ]);
    Project::factory()->create([
        'project_title'     => 'Something Completely Different',
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('projects.index', ['search' => 'Unique Alpha']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
        );
});

test('filter by department returns only that department projects', function () {
    $depsA = makeProjectDeps();
    $depsB = makeProjectDeps(); // separate department

    Project::factory()->count(2)->create([
        'department_id'     => $depsA['dept']->id,
        'specialization_id' => $depsA['spec']->id,
        'supervisor_id'     => $depsA['supervisor']->id,
    ]);
    Project::factory()->create([
        'department_id'     => $depsB['dept']->id,
        'specialization_id' => $depsB['spec']->id,
        'supervisor_id'     => $depsB['supervisor']->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('projects.index', ['department_id' => $depsA['dept']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 2)
        );
});

test('filter by academic year returns correct projects', function () {
    $deps = makeProjectDeps();

    Project::factory()->count(2)->create([
        'academic_year'     => '2023/2024',
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
    ]);
    Project::factory()->create([
        'academic_year'     => '2022/2023',
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('projects.index', ['academic_year' => '2023/2024']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 2)
        );
});

// ── Similarity Warning ────────────────────────────────────────────────────────

test('duplicate title projects both appear in search results', function () {
    $deps  = makeProjectDeps();
    $title = 'Identical Project Title';

    Project::factory()->count(2)->create([
        'project_title'     => $title,
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('projects.index', ['search' => $title]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 2)
        );
});
