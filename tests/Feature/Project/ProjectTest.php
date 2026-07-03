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

test('dept_manager can approve pending project', function () {
    $deps = makeProjectDeps();

    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => 2,
    ]);

    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('projects.approve', $project->id))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id'                => $project->id,
        'current_status_id' => 1,
    ]);
});

test('dept_staff cannot approve project', function () {
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
        ->post(route('projects.approve', $project->id))
        ->assertForbidden();
});

// ── Delete / Soft Delete ──────────────────────────────────────────────────────

test('dept_manager can soft delete project', function () {
    $deps = makeProjectDeps();

    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
    ]);

    $this->actingAs(userWithRole('dept_manager'))
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
