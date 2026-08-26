<?php

use App\Models\Department;
use App\Models\Examiner;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
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

function makeArchivedProjectWithExaminer(): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    $proposal = Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
    ]);

    $project  = $proposal->instantiateProject($supervisor);
    $examiner = Examiner::factory()->create(['department_id' => $dept->id]);
    $project->examiners()->attach($examiner->id, ['assigned_by' => null]);
    $project->update(['final_score' => 90, 'status_id' => Project::STATUS_ARCHIVED]);

    return compact('dept', 'project', 'examiner');
}

test('cannot assign examiner to a finalized project', function () {
    $data      = makeArchivedProjectWithExaminer();
    $manager   = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    $examiner2 = Examiner::factory()->create(['department_id' => $data['dept']->id]);

    $this->actingAs($manager)
        ->post(route('projects.assign-examiner', $data['project']->id), ['examiner_id' => $examiner2->id])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('project_examiners', [
        'project_id' => $data['project']->id, 'examiner_id' => $examiner2->id,
    ]);
});

test('cannot remove examiner from a finalized project', function () {
    $data    = makeArchivedProjectWithExaminer();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);

    $this->actingAs($manager)
        ->delete(route('projects.remove-examiner', [$data['project']->id, $data['examiner']->id]))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('project_examiners', [
        'project_id' => $data['project']->id, 'examiner_id' => $data['examiner']->id,
    ]);
});

test('cannot add evaluation notes to a finalized project', function () {
    $data    = makeArchivedProjectWithExaminer();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);

    $this->actingAs($manager)
        ->post(route('projects.evaluation', $data['project']->id), [
            'examiner_id' => $data['examiner']->id,
            'notes'       => 'محاولة تعديل بعد الأرشفة',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('evaluations', ['project_id' => $data['project']->id]);
});

test('cannot change score on a finalized project', function () {
    $data    = makeArchivedProjectWithExaminer();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);

    $this->actingAs($manager)
        ->patch(route('projects.score', $data['project']->id), ['final_score' => 50])
        ->assertRedirect()
        ->assertSessionHas('error');

    $data['project']->refresh();
    expect((float) $data['project']->final_score)->toBe(90.0);
});
