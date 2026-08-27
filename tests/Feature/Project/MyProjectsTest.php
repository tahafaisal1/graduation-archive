<?php
// tests/Feature/Project/MyProjectsTest.php
//
// Covers the "مشاريعي" (My Projects) supervisor route — confirmed broken
// by docs/analysis/current-system-behavior.md §2: the sidebar link pointed
// at /projects/my with no route, no controller method, and no
// supervisor-scoping query anywhere.

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

/** Creates an instantiated (مؤرشف proposal + قيد التنفيذ project) row supervised by $supervisor. */
function makeSupervisedProject(User $supervisor, ?Department $dept = null, ?Specialization $spec = null): Project
{
    $dept ??= Department::factory()->create();
    $spec ??= Specialization::factory()->create(['department_id' => $dept->id]);

    $proposal = Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
        'is_deleted'        => false,
    ]);

    return $proposal->instantiateProject($supervisor);
}

test('supervisor viewing my-projects sees only projects they supervise', function () {
    $supervisorA = userWithRole('supervisor');
    $supervisorB = userWithRole('supervisor');

    $ownProject   = makeSupervisedProject($supervisorA);
    $otherProject = makeSupervisedProject($supervisorB);

    $this->actingAs($supervisorA)
        ->get(route('projects.my'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $ownProject->id)
        );

    expect($otherProject->id)->not->toBe($ownProject->id);
});

test('non-supervisor roles are forbidden from my-projects route', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('projects.my'))
        ->assertForbidden();
})->with(['super_admin', 'dept_manager', 'dept_staff', 'viewer']);

test('my-projects route renders Projects/Index with مشاريعي heading and correct data', function () {
    $supervisor = userWithRole('supervisor');
    $project    = makeSupervisedProject($supervisor);

    $this->actingAs($supervisor)
        ->get(route('projects.my'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->where('heading', 'مشاريعي')
            ->has('projects.data', 1)
            ->where('projects.data.0.proposal.supervisor.id', $supervisor->id)
        );
});
