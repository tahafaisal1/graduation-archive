<?php
// tests/Feature/Project/ProjectTest.php
//
// Under the proposal/project split, ProjectController is thin — index/show
// only. All the old create/replace/delete/archive/search-filter assertions
// that used to live here have moved:
//   - create/replace/delete ownership matrix  → tests/Feature/Proposal/ProposalTest.php
//   - instantiate (the old "archive" action)  → tests/Feature/Project/InstantiateProjectTest.php
//   - examiner/evaluation/final-score          → tests/Feature/Examiner/ExaminerTest.php
//   - search/filter                            → tests/Feature/Search/SearchTest.php (now proposals.index)
// What survives here is what's still true about the thin Project surface
// itself: listing, showing (+ visit count), and its own (قيد التنفيذ/مؤرشف)
// status rendering.

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

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Creates a مؤرشف Proposal with the given dept/spec/supervisor and
 * instantiates its linked Project — mirrors
 * ReportTest.php::makeReportProject(). `status_id`, if present in
 * $overrides, is applied to the Project after instantiation (it describes
 * the Project's own قيد التنفيذ/مؤرشف lifecycle, not the Proposal's).
 */
function makeThinProject(?Department $dept = null, ?Specialization $spec = null, ?User $supervisor = null, array $overrides = []): Project
{
    $dept       ??= Department::factory()->create();
    $spec       ??= Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor ??= userWithRole('supervisor');

    $statusOverride = $overrides['status_id'] ?? null;
    unset($overrides['status_id']);

    $proposal = Proposal::factory()->create(array_merge([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
        'is_deleted'        => false,
    ], $overrides));

    $project = $proposal->instantiateProject($supervisor);

    if ($statusOverride !== null) {
        $project->update(['status_id' => $statusOverride]);
    }

    return $project;
}

// ── Visibility ────────────────────────────────────────────────────────────────

test('super_admin can view all projects', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    makeThinProject($dept, $spec, $sup);
    makeThinProject($dept, $spec, $sup);
    makeThinProject($dept, $spec, $sup);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 3)
        );
});

// ── Visit Count ───────────────────────────────────────────────────────────────

test('project visit count increments on each show', function () {
    $project = makeThinProject();
    $project->update(['visit_count' => 0]);

    $user = userWithRole('super_admin');
    $this->actingAs($user)->get(route('projects.show', $project->id));
    $this->actingAs($user)->get(route('projects.show', $project->id));

    $this->assertDatabaseHas('projects', [
        'id'          => $project->id,
        'visit_count' => 2,
    ]);
});

// ── Status Rendering ─────────────────────────────────────────────────────────

test('projects index never renders a status other than قيد التنفيذ or مؤرشف', function () {
    makeThinProject(overrides: ['status_id' => Project::STATUS_IN_PROGRESS]);
    makeThinProject(overrides: ['status_id' => Project::STATUS_ARCHIVED]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->where('projects.data', fn ($rows) => collect($rows)
                ->pluck('status.status_name')
                ->every(fn ($name) => in_array($name, ['قيد التنفيذ', 'مؤرشف'], true)))
        );
});
