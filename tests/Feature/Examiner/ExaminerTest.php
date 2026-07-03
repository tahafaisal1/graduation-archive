<?php

use App\Models\Department;
use App\Models\Examiner;
use App\Models\Project;
use App\Models\Specialization;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Creates a dept, spec, supervisor, project, and one examiner in that dept.
 */
function makeExaminerProject(): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $project    = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'current_status_id' => 1,
        'is_deleted'        => false,
    ]);
    $examiner = Examiner::factory()->create(['department_id' => $dept->id]);

    return compact('dept', 'spec', 'supervisor', 'project', 'examiner');
}

// ── ExaminerController ─────────────────────────────────────────────────────────

test('dept_manager can create examiner', function () {
    $dept = Department::factory()->create();

    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('examiners.store'), [
            'full_name'     => 'Dr. Ahmed Al-Rashid',
            'title'         => 'دكتور',
            'department_id' => $dept->id,
        ])
        ->assertRedirect(route('examiners.index'));

    $this->assertDatabaseHas('examiners', [
        'full_name' => 'Dr. Ahmed Al-Rashid',
        'title'     => 'دكتور',
    ]);
});

test('dept_staff cannot create examiner', function () {
    $dept = Department::factory()->create();

    $this->actingAs(userWithRole('dept_staff'))
        ->post(route('examiners.store'), [
            'full_name'     => 'Dr. Test',
            'title'         => 'دكتور',
            'department_id' => $dept->id,
        ])
        ->assertForbidden();
});

test('examiner requires full_name and title', function () {
    $dept = Department::factory()->create();

    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('examiners.store'), [
            'full_name'     => '',
            'title'         => '',
            'department_id' => $dept->id,
        ])
        ->assertSessionHasErrors(['full_name', 'title']);
});

test('cannot delete examiner linked to projects', function () {
    $data    = makeExaminerProject();
    $manager = userWithRole('dept_manager');

    $data['project']->examiners()->attach($data['examiner']->id, ['assigned_by' => $manager->id]);

    $this->actingAs($manager)
        ->delete(route('examiners.destroy', $data['examiner']->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('examiners', ['id' => $data['examiner']->id]);
});

test('examiners filtered by department correctly', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    Examiner::factory()->count(3)->create(['department_id' => $deptA->id]);
    Examiner::factory()->count(2)->create(['department_id' => $deptB->id]);

    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('examiners.index', ['department_id' => $deptA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examiners/Index')
            ->has('examiners', 3)
        );
});

// ── ProjectExaminerController ─────────────────────────────────────────────────

test('dept_manager can assign examiner to project', function () {
    $data    = makeExaminerProject();
    $manager = userWithRole('dept_manager');

    $this->actingAs($manager)
        ->post(route('projects.assign-examiner', $data['project']->id), [
            'examiner_id' => $data['examiner']->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('project_examiners', [
        'project_id'  => $data['project']->id,
        'examiner_id' => $data['examiner']->id,
    ]);
});

test('cannot assign more than 2 examiners to same project', function () {
    $data      = makeExaminerProject();
    $manager   = userWithRole('dept_manager');
    $examiner2 = Examiner::factory()->create(['department_id' => $data['dept']->id]);
    $examiner3 = Examiner::factory()->create(['department_id' => $data['dept']->id]);

    // Fill the 2-examiner cap via ORM
    $data['project']->examiners()->attach($data['examiner']->id, ['assigned_by' => $manager->id]);
    $data['project']->examiners()->attach($examiner2->id, ['assigned_by' => $manager->id]);

    // Third assignment must be rejected
    $this->actingAs($manager)
        ->post(route('projects.assign-examiner', $data['project']->id), [
            'examiner_id' => $examiner3->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('project_examiners', [
        'project_id'  => $data['project']->id,
        'examiner_id' => $examiner3->id,
    ]);
});

test('cannot assign same examiner twice to same project', function () {
    $data    = makeExaminerProject();
    $manager = userWithRole('dept_manager');

    // First assignment via ORM
    $data['project']->examiners()->attach($data['examiner']->id, ['assigned_by' => $manager->id]);

    // Second assignment of same examiner must be rejected
    $this->actingAs($manager)
        ->post(route('projects.assign-examiner', $data['project']->id), [
            'examiner_id' => $data['examiner']->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseCount('project_examiners', 1);
});

test('dept_manager can remove examiner from project', function () {
    $data    = makeExaminerProject();
    $manager = userWithRole('dept_manager');

    $data['project']->examiners()->attach($data['examiner']->id, ['assigned_by' => $manager->id]);

    $this->actingAs($manager)
        ->delete(route('projects.remove-examiner', [$data['project']->id, $data['examiner']->id]))
        ->assertRedirect();

    $this->assertDatabaseMissing('project_examiners', [
        'project_id'  => $data['project']->id,
        'examiner_id' => $data['examiner']->id,
    ]);
});

// ── EvaluationController ───────────────────────────────────────────────────────

test('dept_manager can add evaluation notes', function () {
    $data    = makeExaminerProject();
    $manager = userWithRole('dept_manager');

    $data['project']->examiners()->attach($data['examiner']->id, ['assigned_by' => $manager->id]);

    $this->actingAs($manager)
        ->post(route('projects.evaluation', $data['project']->id), [
            'examiner_id' => $data['examiner']->id,
            'notes'       => 'مشروع متميز يستحق التقدير',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('evaluations', [
        'project_id'  => $data['project']->id,
        'examiner_id' => $data['examiner']->id,
        'notes'       => 'مشروع متميز يستحق التقدير',
    ]);
});

test('dept_manager can set final score', function () {
    $data = makeExaminerProject();

    $this->actingAs(userWithRole('dept_manager'))
        ->patch(route('projects.score', $data['project']->id), ['final_score' => 85])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id'          => $data['project']->id,
        'final_score' => 85,
    ]);
});

test('dept_staff cannot set final score', function () {
    $data = makeExaminerProject();

    $this->actingAs(userWithRole('dept_staff'))
        ->patch(route('projects.score', $data['project']->id), ['final_score' => 75])
        ->assertForbidden();
});

test('score must be between 0 and 100', function () {
    $data    = makeExaminerProject();
    $manager = userWithRole('dept_manager');

    $this->actingAs($manager)
        ->patch(route('projects.score', $data['project']->id), ['final_score' => -1])
        ->assertSessionHasErrors('final_score');

    $this->actingAs($manager)
        ->patch(route('projects.score', $data['project']->id), ['final_score' => 101])
        ->assertSessionHasErrors('final_score');
});
