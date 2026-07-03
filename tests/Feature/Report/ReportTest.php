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

// ── Helper ──────────────────────────────────────────────────────────────────────

function makeReportProject(
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
        'is_deleted'        => false,
        'current_status_id' => 1,
    ], $overrides));
}

// ── 1. Dashboard — super_admin ─────────────────────────────────────────────────

test('super_admin can view full dashboard stats', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    Project::factory()->count(3)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
        'is_deleted'        => false,
        'current_status_id' => 1,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.total_projects')
            ->has('stats.total_departments')
            ->has('stats.projects_this_year')
            ->has('stats.pending_approvals')
            ->has('stats.recent_projects', 3)
            ->has('stats.by_status')
        );
});

// ── 2. Dashboard — dept_manager ────────────────────────────────────────────────

test('dept_manager sees only their department stats', function () {
    $dept    = Department::factory()->create();
    $spec    = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup     = userWithRole('supervisor');
    $manager = userWithRole('dept_manager');
    $manager->update(['department_id' => $dept->id]);

    Project::factory()->count(2)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
        'is_deleted'        => false,
        'current_status_id' => 1,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.project_count')
            ->has('stats.specializations')
            ->has('stats.supervisors')
            ->where('stats.project_count', 2)
        );
});

// ── 3. Dashboard — dept_staff ──────────────────────────────────────────────────

test('dept_staff sees limited dashboard view', function () {
    makeReportProject();

    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.total_projects')
        );
});

// ── 4. Department report — dept_manager sees own dept ─────────────────────────

test('dept_manager can view department report', function () {
    $dept    = Department::factory()->create();
    $manager = userWithRole('dept_manager');
    $manager->update(['department_id' => $dept->id]);

    $this->actingAs($manager)
        ->get(route('reports.department'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Department')
            ->where('filter.department_id', $dept->id)
        );
});

// ── 5. Department report — dept_manager forced to own dept ────────────────────

test('dept_manager cannot view other department report', function () {
    $deptA   = Department::factory()->create();
    $deptB   = Department::factory()->create();
    $manager = userWithRole('dept_manager');
    $manager->update(['department_id' => $deptA->id]);

    // Passing deptB in query string — controller must ignore it and use manager's dept
    $this->actingAs($manager)
        ->get(route('reports.department', ['department_id' => $deptB->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Department')
            ->where('filter.department_id', $deptA->id)
        );
});

// ── 6. Department report — super_admin can filter by any dept ─────────────────

test('super_admin can view any department report', function () {
    $dept = Department::factory()->create();

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('reports.department', ['department_id' => $dept->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Department')
            ->where('filter.department_id', $dept->id)
        );
});

// ── 7. Access control — dept_staff gets 403 ───────────────────────────────────

test('dept_staff cannot access reports', function () {
    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('reports.department'))
        ->assertForbidden();
});

// ── 8. Specialization report — ordering ───────────────────────────────────────

test('specialization report returns top 10 correctly', function () {
    $dept = Department::factory()->create();
    $sup  = userWithRole('supervisor');

    // specA: 3 projects, specB: 1 project, specC: 0 projects
    $specA = Specialization::factory()->create(['department_id' => $dept->id]);
    $specB = Specialization::factory()->create(['department_id' => $dept->id]);
    $specC = Specialization::factory()->create(['department_id' => $dept->id]);

    Project::factory()->count(3)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $specA->id,
        'supervisor_id'     => $sup->id,
        'is_deleted'        => false,
        'current_status_id' => 1,
    ]);
    Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $specB->id,
        'supervisor_id'     => $sup->id,
        'is_deleted'        => false,
        'current_status_id' => 1,
    ]);

    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('reports.specializations'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Specializations')
            ->where('report.top_specializations.0.id', $specA->id)
            ->where('report.rare_specializations.0.id', $specC->id)
        );
});

// ── 9. Supervisor report — average score ──────────────────────────────────────

test('supervisor report calculates average score correctly', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    makeReportProject($dept, $spec, $sup, ['final_score' => 80]);
    makeReportProject($dept, $spec, $sup, ['final_score' => 90]);

    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('reports.supervisors'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Supervisors')
            ->has('report', 1)
            ->where('report.0.project_count', 2)
            ->where('report.0.avg_score', fn ($v) => abs((float) $v - 85.0) < 0.1)
        );
});

// ── 10. Yearly report — growth percentage ─────────────────────────────────────

test('yearly report shows correct growth percentage', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    // 4 projects in 2022/2023 → 8 in 2023/2024 → growth = 100%
    Project::factory()->count(4)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
        'is_deleted'        => false,
        'current_status_id' => 1,
        'academic_year'     => '2022/2023',
    ]);
    Project::factory()->count(8)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
        'is_deleted'        => false,
        'current_status_id' => 1,
        'academic_year'     => '2023/2024',
    ]);

    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('reports.yearly'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Yearly')
            ->where('report.yearly.0.year', '2022/2023')
            ->where('report.yearly.0.count', fn ($v) => (int) $v === 4)
            ->where('report.yearly.0.growth_pct', null)
            ->where('report.yearly.1.year', '2023/2024')
            ->where('report.yearly.1.count', fn ($v) => (int) $v === 8)
            ->where('report.yearly.1.growth_pct', fn ($v) => $v !== null && abs((float) $v - 100.0) < 0.01)
        );
});

// ── 11. Export — PDF ──────────────────────────────────────────────────────────

test('export pdf returns valid pdf file', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('reports.export.pdf', ['type' => 'department']))
        ->assertOk()
        ->assertDownload('department-report.pdf');
});

// ── 12. Export — Excel ────────────────────────────────────────────────────────

test('export excel returns valid xlsx file', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('reports.export.excel', ['type' => 'department']))
        ->assertOk()
        ->assertDownload('department-report.xlsx');
});

// ── 13. Unauthenticated redirect ──────────────────────────────────────────────

test('unauthenticated user redirected from reports', function () {
    $this->get(route('reports.department'))
        ->assertRedirect(route('login'));
});
