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

/** Build an archived Project whose proposal carries the given attributes. */
function makeArchivedProject(array $proposalOverrides = [], ?User $supervisor = null): Project {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor ??= userWithRole('supervisor');

    $proposal = Proposal::factory()->create(array_merge([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
        'is_deleted'        => false,
    ], $proposalOverrides));

    $project = $proposal->instantiateProject($supervisor);
    $project->update(['status_id' => Project::STATUS_ARCHIVED]);

    return $project->fresh();
}

test('public browse matches a supervisor name', function () {
    $sup = userWithRole('supervisor', ['name' => 'Dr Farouk Almansour']);
    makeArchivedProject(['title' => 'Unrelated Title A'], $sup);
    makeArchivedProject(['title' => 'Unrelated Title B']);

    $this->get(route('public.browse', ['search' => 'Farouk']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

test('public browse matches an examiner name', function () {
    $project = makeArchivedProject(['title' => 'Signal Processing Rig']);
    $examiner = Examiner::factory()->create(['full_name' => 'Prof Widad Alkhattabi']);
    $project->examiners()->attach($examiner->id);
    makeArchivedProject(['title' => 'Other Project']);

    $this->get(route('public.browse', ['search' => 'Widad']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});
