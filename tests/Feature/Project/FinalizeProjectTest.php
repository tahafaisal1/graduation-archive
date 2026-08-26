<?php

use App\Models\Department;
use App\Models\Examiner;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use Database\Seeders\ProjectLifecycleStatusSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);
    Storage::fake('public');
});

function makeFinalizableProject(): array
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

    $project   = $proposal->instantiateProject($supervisor);
    $examinerA = Examiner::factory()->create(['department_id' => $dept->id]);
    $examinerB = Examiner::factory()->create(['department_id' => $dept->id]);

    return compact('dept', 'project', 'examinerA', 'examinerB');
}

function attachBothExaminers(array $data, ?int $assignedBy): void
{
    $data['project']->examiners()->attach($data['examinerA']->id, ['assigned_by' => $assignedBy]);
    $data['project']->examiners()->attach($data['examinerB']->id, ['assigned_by' => $assignedBy]);
}

test('cannot finalize with zero examiners assigned', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('final_file');

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_IN_PROGRESS)
        ->and($data['project']->final_file_path)->toBeNull();
});

test('cannot finalize with only one examiner assigned', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    $data['project']->examiners()->attach($data['examinerA']->id, ['assigned_by' => $manager->id]);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('final_file');

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_IN_PROGRESS);
});

test('cannot finalize without a score set', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $manager->id);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('final_file');

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_IN_PROGRESS);
});

test('cannot finalize without a file uploaded', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $manager->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [])
        ->assertSessionHasErrors('final_file');

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_IN_PROGRESS);
});

test('finalizing with exactly two examiners, score, and file succeeds and locks the project', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $manager->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('projects.show', $data['project']->id));

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_ARCHIVED)
        ->and($data['project']->final_file_path)->not->toBeNull();

    Storage::disk('public')->assertExists($data['project']->final_file_path);
});

test('dept_manager of a different department cannot finalize', function () {
    $data         = makeFinalizableProject();
    $otherDept    = Department::factory()->create();
    $otherManager = userWithRole('dept_manager', ['department_id' => $otherDept->id]);
    attachBothExaminers($data, $otherManager->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($otherManager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('dept_staff of the same department can finalize', function () {
    $data  = makeFinalizableProject();
    $staff = userWithRole('dept_staff', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $staff->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($staff)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('projects.show', $data['project']->id));
});

test('supervisor cannot finalize', function () {
    $data       = makeFinalizableProject();
    $supervisor = userWithRole('supervisor', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, null);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($supervisor)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('super_admin can finalize any project regardless of department', function () {
    $data  = makeFinalizableProject();
    $admin = userWithRole('super_admin');
    attachBothExaminers($data, $admin->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($admin)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('projects.show', $data['project']->id));
});

test('cannot finalize an already-finalized project', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $manager->id);
    $data['project']->update(['final_score' => 90, 'status_id' => Project::STATUS_ARCHIVED]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});
