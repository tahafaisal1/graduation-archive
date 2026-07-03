<?php

use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
});

// ── Helper ────────────────────────────────────────────────────────────────────

function makePublicProject(
    ?Department $dept = null,
    ?Specialization $spec = null,
    ?User $supervisor = null,
    array $overrides = [],
): Project {
    $dept       ??= Department::factory()->create();
    $spec       ??= Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor ??= userWithRole('supervisor');

    return Project::factory()->create(array_merge([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'current_status_id' => 1,
        'is_deleted'        => false,
    ], $overrides));
}

// ── 1. Landing page ───────────────────────────────────────────────────────────

test('guest can access landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

// ── 2. Browse page ────────────────────────────────────────────────────────────

test('guest can access browse page', function () {
    $this->get(route('public.browse'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Public/Browse'));
});

// ── 3. Search on browse ───────────────────────────────────────────────────────

test('guest can search on browse with query parameter', function () {
    makePublicProject(overrides: ['project_title' => 'Robot Arm Controller']);
    makePublicProject(overrides: ['project_title' => 'Database Management System']);

    $this->get(route('public.browse', ['search' => 'Robot Arm']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Browse')
            ->has('projects.data', 1)
            ->where('projects.data.0.project_title', 'Robot Arm Controller')
        );
});

// ── 4. Filter by department ───────────────────────────────────────────────────

test('guest can filter browse by department_id', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    makePublicProject(dept: $deptA, overrides: ['project_title' => 'Project A']);
    makePublicProject(dept: $deptB, overrides: ['project_title' => 'Project B']);

    $this->get(route('public.browse', ['department_id' => $deptA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.project_title', 'Project A')
        );
});

// ── 5. Filter by academic_year ────────────────────────────────────────────────

test('guest can filter browse by academic_year', function () {
    makePublicProject(overrides: ['academic_year' => '2023-2024', 'project_title' => 'Old Project']);
    makePublicProject(overrides: ['academic_year' => '2024-2025', 'project_title' => 'New Project']);

    $this->get(route('public.browse', ['academic_year' => '2024-2025']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.project_title', 'New Project')
        );
});

// ── 6. View archived project detail ──────────────────────────────────────────

test('guest can view browse show for archived project', function () {
    $project = makePublicProject(overrides: ['project_title' => 'Archived Project Detail']);

    $this->get(route('public.show', $project->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Show')
            ->where('project.project_title', 'Archived Project Detail')
        );
});

// ── 7. Non-archived project returns 404 ──────────────────────────────────────

test('guest gets 404 for non-archived project on browse show', function () {
    $project = makePublicProject(overrides: ['current_status_id' => 2]); // proposal_submitted

    $this->get(route('public.show', $project->id))
        ->assertNotFound();
});

// ── 8. Deleted project returns 404 ───────────────────────────────────────────

test('guest gets 404 for deleted project on browse show', function () {
    $project = makePublicProject(overrides: ['is_deleted' => true]);

    $this->get(route('public.show', $project->id))
        ->assertNotFound();
});

// ── 9. visit_count increments ─────────────────────────────────────────────────

test('visit_count increments when guest views project', function () {
    $project = makePublicProject();
    $initial = $project->visit_count;

    $this->get(route('public.show', $project->id))->assertOk();

    expect(Project::find($project->id)->visit_count)->toBe($initial + 1);
});

// ── 10. Dashboard blocked for guests ─────────────────────────────────────────

test('guest cannot access dashboard and is redirected to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

// ── 11. Projects management blocked for guests ───────────────────────────────

test('guest cannot access projects management and is redirected to login', function () {
    $this->get(route('projects.index'))
        ->assertRedirect(route('login'));
});

// ── 12. Register redirects to login ──────────────────────────────────────────

test('/register redirects to login for guest', function () {
    $this->get(route('register'))
        ->assertRedirect(route('login'));
});

// ── 13. Only archived projects appear in browse ───────────────────────────────

test('archived projects appear in browse results', function () {
    makePublicProject(overrides: ['project_title' => 'Visible Archived']);

    $this->get(route('public.browse'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.project_title', 'Visible Archived')
        );
});

// ── 14. Non-archived projects hidden from browse ──────────────────────────────

test('non-archived projects do not appear in browse results', function () {
    makePublicProject(overrides: ['current_status_id' => 2, 'project_title' => 'Pending Project']);

    $this->get(route('public.browse'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 0));
});
