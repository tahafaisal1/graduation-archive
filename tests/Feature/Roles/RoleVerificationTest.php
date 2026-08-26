<?php

use App\Models\Department;
use App\Models\Examiner;
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

/** Minimal FK chain: department + specialization + supervisor user. */
function rvSetup(): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = User::factory()->create(['department_id' => $dept->id]);
    $supervisor->assignRole('supervisor');

    return compact('dept', 'spec', 'supervisor');
}

function staffInDept(int $deptId): User
{
    $u = User::factory()->create(['department_id' => $deptId]);
    $u->assignRole('dept_staff');
    return $u;
}

function managerInDept(int $deptId): User
{
    $u = User::factory()->create(['department_id' => $deptId]);
    $u->assignRole('dept_manager');
    return $u;
}

/** A مقترح (pending) proposal — optionally owned by $createdBy. */
function pendingRvProposal(int $deptId, int $specId, int $supervisorId, ?int $createdBy = null): Proposal
{
    return Proposal::create([
        'title'             => 'نظام تجريبي لاختبار الصلاحيات',
        'description'       => 'وصف المشروع التجريبي لاختبار صلاحيات المستخدمين',
        'academic_year'     => '2025/2026',
        'department_id'     => $deptId,
        'specialization_id' => $specId,
        'supervisor_id'     => $supervisorId,
        'status_id'         => Proposal::STATUS_PENDING,
        'created_by'        => $createdBy,
        'is_deleted'        => false,
    ]);
}

/**
 * A مؤرشف proposal with its linked (in-progress) Project already
 * instantiated — mirrors ReportTest.php::makeReportProject(). Used
 * wherever the old flat archivedRvProject() fed an examiner-assignment /
 * final-score / show / department-guard test, since those all need a real
 * Project row, not just an archived Proposal.
 */
function archivedRvProject(int $deptId, int $specId, int $supervisorId): Project
{
    $proposal = Proposal::create([
        'title'             => 'نظام تجريبي لاختبار الصلاحيات',
        'description'       => 'وصف المشروع التجريبي لاختبار صلاحيات المستخدمين',
        'academic_year'     => '2025/2026',
        'department_id'     => $deptId,
        'specialization_id' => $specId,
        'supervisor_id'     => $supervisorId,
        'status_id'         => Proposal::STATUS_ARCHIVED,
        'is_deleted'        => false,
    ]);

    $supervisor = User::find($supervisorId);

    return $proposal->instantiateProject($supervisor);
}

function validProposalPayload(int $deptId, int $specId, int $supervisorId): array
{
    return [
        'title'             => 'نظام تجريبي لاختبار الصلاحيات',
        'description'       => 'وصف المشروع التجريبي لاختبار صلاحيات المستخدمين',
        'academic_year'     => '2025/2026',
        'department_id'     => $deptId,
        'specialization_id' => $specId,
        'supervisor_id'     => $supervisorId,
        'students'          => [
            ['full_name' => 'أحمد محمد', 'registration_number' => '2025001'],
        ],
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// super_admin — full access to everything
// ═══════════════════════════════════════════════════════════════════════════════

test('super_admin can access dashboard', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('dashboard'))
        ->assertOk();
});

test('super_admin can access departments index', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('departments.index'))
        ->assertOk();
});

test('super_admin can create department', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->post(route('departments.store'), [
            'name' => 'قسم الاختبار', 'code' => 'TST', 'description' => 'قسم تجريبي',
        ])
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseHas('departments', ['code' => 'TST']);
});

test('super_admin can access projects index', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('projects.index'))
        ->assertOk();
});

test('super_admin can access project create page', function () {
    // Project creation lives under proposals now — projects.* is thin
    // (index/show only).
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.create'))
        ->assertOk();
});

test('super_admin can create project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();

    $this->actingAs(userWithRole('super_admin'))
        ->post(route('proposals.store'), validProposalPayload($dept->id, $spec->id, $sv->id))
        ->assertRedirect();

    $this->assertDatabaseHas('proposals', ['title' => 'نظام تجريبي لاختبار الصلاحيات']);
});

test('super_admin can archive a pending project', function () {
    // "Archiving" now means instantiating the proposal into a Project —
    // there is no standalone archive action on Project itself.
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $proposal = pendingRvProposal($dept->id, $spec->id, $sv->id);

    $this->actingAs(userWithRole('super_admin'))
        ->post(route('proposals.instantiate', $proposal->id))
        ->assertRedirect();

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'status_id' => Proposal::STATUS_ARCHIVED]);
    $this->assertDatabaseHas('projects', ['proposal_id' => $proposal->id]);
});

test('super_admin can soft-delete a project', function () {
    // Deleting now targets the Proposal (Project has no destroy route);
    // super_admin bypasses the pending-status lock that blocks everyone else.
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $project = archivedRvProject($dept->id, $spec->id, $sv->id);

    $this->actingAs(userWithRole('super_admin'))
        ->delete(route('proposals.destroy', $project->proposal_id))
        ->assertRedirect(route('proposals.index'));

    $this->assertDatabaseHas('proposals', ['id' => $project->proposal_id, 'is_deleted' => true]);
});

test('super_admin can access import', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('import.index'))
        ->assertOk();
});

test('super_admin can access department report', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('reports.department'))
        ->assertOk();
});

test('super_admin can access supervisors report', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('reports.supervisors'))
        ->assertOk();
});

test('super_admin can access yearly report', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('reports.yearly'))
        ->assertOk();
});

test('super_admin can access user management', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('admin.users.index'))
        ->assertOk();
});

// ═══════════════════════════════════════════════════════════════════════════════
// dept_manager — manage own department; cannot import
// ═══════════════════════════════════════════════════════════════════════════════

test('dept_manager can access dashboard', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('dashboard'))
        ->assertOk();
});

test('dept_manager can access departments index', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('departments.index'))
        ->assertOk();
});

test('dept_manager can create project in their department', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $mgr = managerInDept($dept->id);

    $this->actingAs($mgr)
        ->post(route('proposals.store'), validProposalPayload($dept->id, $spec->id, $sv->id))
        ->assertRedirect();

    // Under the split, creation never auto-archives for any role — proposals
    // always start مقترح; only instantiate() (the old "archive" action)
    // ever moves a proposal to مؤرشف.
    $this->assertDatabaseHas('proposals', [
        'title'     => 'نظام تجريبي لاختبار الصلاحيات',
        'status_id' => Proposal::STATUS_PENDING,
    ]);
});

test('dept_manager can archive a pending project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $proposal = pendingRvProposal($dept->id, $spec->id, $sv->id);
    $mgr      = managerInDept($dept->id);

    $this->actingAs($mgr)
        ->post(route('proposals.instantiate', $proposal->id))
        ->assertRedirect();

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'status_id' => Proposal::STATUS_ARCHIVED]);
});

test('dept_manager can soft-delete a pending project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $proposal = pendingRvProposal($dept->id, $spec->id, $sv->id);
    $mgr      = managerInDept($dept->id);

    $this->actingAs($mgr)
        ->delete(route('proposals.destroy', $proposal->id))
        ->assertRedirect(route('proposals.index'));

    $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'is_deleted' => true]);
});

test('dept_manager can access department report', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('reports.department'))
        ->assertOk();
});

test('dept_manager can assign examiner to project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $project  = archivedRvProject($dept->id, $spec->id, $sv->id);
    $examiner = Examiner::factory()->create(['department_id' => $dept->id]);
    $mgr      = managerInDept($dept->id);

    $this->actingAs($mgr)
        ->post(route('projects.assign-examiner', $project->id), ['examiner_id' => $examiner->id])
        ->assertRedirect();

    $this->assertDatabaseHas('project_examiners', [
        'project_id'  => $project->id,
        'examiner_id' => $examiner->id,
    ]);
});

test('dept_manager can enter final score', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $project = archivedRvProject($dept->id, $spec->id, $sv->id);
    $mgr     = managerInDept($dept->id);

    $this->actingAs($mgr)
        ->patch(route('projects.score', $project->id), ['final_score' => 85])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'final_score' => 85]);
});

test('dept_manager cannot access import', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('import.index'))
        ->assertForbidden();
});

test('dept_manager cannot delete any department', function () {
    ['dept' => $dept] = rvSetup();
    $mgr = managerInDept($dept->id);

    $this->actingAs($mgr)
        ->delete(route('departments.destroy', $dept->id))
        ->assertForbidden();

    $this->assertDatabaseHas('departments', ['id' => $dept->id]);
});

test('dept_manager cannot create a department', function () {
    managerInDept(Department::factory()->create()->id);
    $mgr = managerInDept(Department::factory()->create()->id);

    $this->actingAs($mgr)
        ->post(route('departments.store'), [
            'name' => 'قسم جديد', 'code' => 'NEW', 'description' => null,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('departments', ['code' => 'NEW']);
});

test('dept_manager can update their own department', function () {
    $dept = Department::factory()->create(['name' => 'القسم الأصلي', 'code' => 'ORI']);
    $mgr  = managerInDept($dept->id);

    $this->actingAs($mgr)
        ->put(route('departments.update', $dept->id), [
            'name' => 'القسم المحدث', 'code' => 'ORI', 'description' => null,
        ])
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'القسم المحدث']);
});

test('dept_manager cannot update another department', function () {
    $ownDept   = Department::factory()->create();
    $otherDept = Department::factory()->create(['name' => 'قسم آخر', 'code' => 'OTH']);
    $mgr       = managerInDept($ownDept->id);

    $this->actingAs($mgr)
        ->put(route('departments.update', $otherDept->id), [
            'name' => 'محاولة تعديل', 'code' => 'OTH', 'description' => null,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('departments', ['id' => $otherDept->id, 'name' => 'قسم آخر']);
});

test('super_admin can delete department without linked projects', function () {
    $dept = Department::factory()->create();

    $this->actingAs(userWithRole('super_admin'))
        ->delete(route('departments.destroy', $dept->id))
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
});

test('super_admin cannot delete department that has linked projects', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    archivedRvProject($dept->id, $spec->id, $sv->id);

    $this->actingAs(userWithRole('super_admin'))
        ->delete(route('departments.destroy', $dept->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('departments', ['id' => $dept->id]);
});

// ═══════════════════════════════════════════════════════════════════════════════
// supervisor — read-only on projects; no CRUD, no reports, no departments
// ═══════════════════════════════════════════════════════════════════════════════

test('supervisor can access dashboard', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('dashboard'))
        ->assertOk();
});

test('supervisor can view projects index', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('projects.index'))
        ->assertOk();
});

test('supervisor can view project detail', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $project = archivedRvProject($dept->id, $spec->id, $sv->id);

    $this->actingAs($sv)
        ->get(route('projects.show', $project->id))
        ->assertOk();
});

test('supervisor cannot access project create page', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('proposals.create'))
        ->assertForbidden();
});

test('supervisor cannot store a new project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $actor = userWithRole('supervisor'); // separate from the FK supervisor

    $this->actingAs($actor)
        ->post(route('proposals.store'), validProposalPayload($dept->id, $spec->id, $sv->id))
        ->assertForbidden();
});

test('supervisor cannot edit a project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $proposal = pendingRvProposal($dept->id, $spec->id, $sv->id);

    $this->actingAs($sv)
        ->get(route('proposals.edit', $proposal->id))
        ->assertForbidden();
});

test('supervisor cannot delete a project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $proposal = pendingRvProposal($dept->id, $spec->id, $sv->id);

    $this->actingAs($sv)
        ->delete(route('proposals.destroy', $proposal->id))
        ->assertForbidden();
});

test('supervisor cannot access import', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('import.index'))
        ->assertForbidden();
});

test('supervisor cannot access department report', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('reports.department'))
        ->assertForbidden();
});

test('supervisor cannot access departments management', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('departments.index'))
        ->assertForbidden();
});

test('supervisor cannot assign examiners', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $project  = archivedRvProject($dept->id, $spec->id, $sv->id);
    $examiner = Examiner::factory()->create(['department_id' => $dept->id]);

    $this->actingAs($sv)
        ->post(route('projects.assign-examiner', $project->id), ['examiner_id' => $examiner->id])
        ->assertForbidden();
});

// ═══════════════════════════════════════════════════════════════════════════════
// dept_staff — add pending projects in own dept; edit pending own-dept only
// ═══════════════════════════════════════════════════════════════════════════════

test('dept_staff can access dashboard', function () {
    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('dashboard'))
        ->assertOk();
});

test('dept_staff can view all projects', function () {
    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('projects.index'))
        ->assertOk();
});

test('dept_staff can access project create page', function () {
    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('proposals.create'))
        ->assertOk();
});

test('dept_staff can create project in their own department', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $staff = staffInDept($dept->id);

    $this->actingAs($staff)
        ->post(route('proposals.store'), validProposalPayload($dept->id, $spec->id, $sv->id))
        ->assertRedirect();

    // dept_staff proposals land as مقترح (pending)
    $this->assertDatabaseHas('proposals', [
        'title'     => 'نظام تجريبي لاختبار الصلاحيات',
        'status_id' => Proposal::STATUS_PENDING,
    ]);
});

test('dept_staff can edit their own pending project in same department', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $staff    = staffInDept($dept->id);
    $proposal = pendingRvProposal($dept->id, $spec->id, $sv->id, $staff->id);

    $this->actingAs($staff)
        ->get(route('proposals.edit', $proposal->id))
        ->assertOk();
});

test('dept_staff cannot create project in another department', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $otherDept = Department::factory()->create();
    $staff     = staffInDept($otherDept->id); // staff belongs to otherDept, not $dept

    $this->actingAs($staff)
        ->post(route('proposals.store'), validProposalPayload($dept->id, $spec->id, $sv->id))
        ->assertForbidden();
});

test('dept_staff cannot edit pending project from a different department', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $proposal  = pendingRvProposal($dept->id, $spec->id, $sv->id);
    $otherDept = Department::factory()->create();
    $staff     = staffInDept($otherDept->id); // staff is in otherDept, proposal is in $dept

    $this->actingAs($staff)
        ->get(route('proposals.edit', $proposal->id))
        ->assertForbidden();
});

test('dept_staff cannot edit approved (archived) project even in same department', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $project = archivedRvProject($dept->id, $spec->id, $sv->id); // proposal is now مؤرشف
    $staff   = staffInDept($dept->id);

    $this->actingAs($staff)
        ->get(route('proposals.edit', $project->proposal_id))
        ->assertForbidden();
});

test('dept_staff cannot delete a project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $project = archivedRvProject($dept->id, $spec->id, $sv->id);
    $staff   = staffInDept($dept->id);

    $this->actingAs($staff)
        ->delete(route('proposals.destroy', $project->proposal_id))
        ->assertForbidden();
});

test('dept_staff cannot archive a project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $proposal = pendingRvProposal($dept->id, $spec->id, $sv->id);
    $staff    = staffInDept($dept->id);

    $this->actingAs($staff)
        ->post(route('proposals.instantiate', $proposal->id))
        ->assertForbidden();
});

test('dept_staff cannot access import', function () {
    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('import.index'))
        ->assertForbidden();
});

test('dept_staff cannot access department report', function () {
    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('reports.department'))
        ->assertForbidden();
});

test('dept_staff cannot access departments management', function () {
    $this->actingAs(userWithRole('dept_staff'))
        ->get(route('departments.index'))
        ->assertForbidden();
});

// ═══════════════════════════════════════════════════════════════════════════════
// viewer — dashboard only; cannot write, import, report, or manage departments
// ═══════════════════════════════════════════════════════════════════════════════

test('viewer can access dashboard', function () {
    $this->actingAs(userWithRole('viewer'))
        ->get(route('dashboard'))
        ->assertOk();
});

test('viewer cannot access project create page', function () {
    $this->actingAs(userWithRole('viewer'))
        ->get(route('proposals.create'))
        ->assertForbidden();
});

test('viewer cannot store a project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();

    $this->actingAs(userWithRole('viewer'))
        ->post(route('proposals.store'), validProposalPayload($dept->id, $spec->id, $sv->id))
        ->assertForbidden();
});

test('viewer cannot access departments management', function () {
    $this->actingAs(userWithRole('viewer'))
        ->get(route('departments.index'))
        ->assertForbidden();
});

test('viewer cannot access import', function () {
    $this->actingAs(userWithRole('viewer'))
        ->get(route('import.index'))
        ->assertForbidden();
});

test('viewer cannot access department report', function () {
    $this->actingAs(userWithRole('viewer'))
        ->get(route('reports.department'))
        ->assertForbidden();
});
