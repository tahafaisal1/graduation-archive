<?php

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

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Creates a Proposal and instantiates its linked Project — mirrors
 * ReportTest.php::makeReportProject(). $overrides keyed 'status_id' or
 * 'is_deleted' are applied to the Project (after instantiation, since those
 * describe the Project's own lifecycle/soft-delete, not the Proposal's);
 * every other key is passed through to the Proposal (e.g. 'title',
 * 'academic_year'). The resulting Project defaults to Project::STATUS_ARCHIVED
 * (PublicController only ever surfaces archived projects) unless overridden.
 */
function makePublicProject(
    ?Department $dept = null,
    ?Specialization $spec = null,
    ?User $supervisor = null,
    array $overrides = [],
): Project {
    $dept       ??= Department::factory()->create();
    $spec       ??= Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor ??= userWithRole('supervisor');

    $projectKeys      = ['status_id', 'is_deleted'];
    $projectOverrides = array_intersect_key($overrides, array_flip($projectKeys));
    $proposalOverrides = array_diff_key($overrides, $projectOverrides);

    $proposal = Proposal::factory()->create(array_merge([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
        'is_deleted'        => false,
    ], $proposalOverrides));

    $project = $proposal->instantiateProject($supervisor);

    $project->update(array_merge(['status_id' => Project::STATUS_ARCHIVED], $projectOverrides));

    return $project->fresh();
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
    makePublicProject(overrides: ['title' => 'Robot Arm Controller']);
    makePublicProject(overrides: ['title' => 'Database Management System']);

    $this->get(route('public.browse', ['search' => 'Robot Arm']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Browse')
            ->has('projects.data', 1)
            ->where('projects.data.0.proposal.title', 'Robot Arm Controller')
        );
});

// ── 4. Filter by department ───────────────────────────────────────────────────

test('guest can filter browse by department_id', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    makePublicProject(dept: $deptA, overrides: ['title' => 'Project A']);
    makePublicProject(dept: $deptB, overrides: ['title' => 'Project B']);

    $this->get(route('public.browse', ['department_id' => $deptA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.proposal.title', 'Project A')
        );
});

// ── 5. Filter by academic_year ────────────────────────────────────────────────

test('guest can filter browse by academic_year', function () {
    makePublicProject(overrides: ['academic_year' => '2023-2024', 'title' => 'Old Project']);
    makePublicProject(overrides: ['academic_year' => '2024-2025', 'title' => 'New Project']);

    $this->get(route('public.browse', ['academic_year' => '2024-2025']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.proposal.title', 'New Project')
        );
});

// ── 6. View archived project detail ──────────────────────────────────────────

test('guest can view browse show for archived project', function () {
    $project = makePublicProject(overrides: ['title' => 'Archived Project Detail']);

    $this->get(route('public.show', $project->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Show')
            ->where('project.proposal.title', 'Archived Project Detail')
        );
});

// ── 7. Non-archived project returns 404 ──────────────────────────────────────

test('guest gets 404 for non-archived project on browse show', function () {
    // No UI exists yet to move a project from قيد التنفيذ (in-progress, the
    // default instantiateProject() lands it at) to مؤرشف — PublicController
    // only ever surfaces مؤرشف projects, so leaving status_id at its default
    // reproduces the "not archived yet" case.
    $project = makePublicProject(overrides: ['status_id' => Project::STATUS_IN_PROGRESS]);

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
    makePublicProject(overrides: ['title' => 'Visible Archived']);

    $this->get(route('public.browse'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.proposal.title', 'Visible Archived')
        );
});

// ── 14. Non-archived projects hidden from browse ──────────────────────────────

test('non-archived projects do not appear in browse results', function () {
    makePublicProject(overrides: ['status_id' => Project::STATUS_IN_PROGRESS, 'title' => 'Pending Project']);

    $this->get(route('public.browse'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 0));
});
