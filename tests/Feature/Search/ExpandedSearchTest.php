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

test('internal search finds a project by a student name only', function () {
    $project = makeArchivedProject(['title' => 'Autonomous Rover']);
    $project->proposal->students()->create([
        'full_name' => 'Khaled Bouzid', 'registration_number' => 'S-9001', 'status' => 'active',
    ]);
    makeArchivedProject(['title' => 'Decoy Project']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => 'Bouzid']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->has('results.data', 1)
            ->where('results.data.0.title', 'Autonomous Rover')
            ->where('results.data.0.type', 'project')
        );
});

test('internal search finds a project by an examiner name only', function () {
    $project = makeArchivedProject(['title' => 'Thermal Camera']);
    $examiner = \App\Models\Examiner::factory()->create(['full_name' => 'Dr Munir Alraqi']);
    $project->examiners()->attach($examiner->id);
    makeArchivedProject(['title' => 'Decoy']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => 'Munir']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 1)
            ->where('results.data.0.type', 'project'));
});

test('internal search shows a pending proposal that public browse hides', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    Proposal::factory()->create([
        'title' => 'Pending Idea About Drones',
        'department_id' => $dept->id, 'specialization_id' => $spec->id,
        'supervisor_id' => userWithRole('supervisor')->id,
        'status_id' => Proposal::STATUS_PENDING, 'is_deleted' => false,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => 'Drones']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 1)
            ->where('results.data.0.type', 'proposal')
            ->where('results.data.0.entity_label', 'مقترح'));

    $this->get(route('public.browse', ['search' => 'Drones']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 0));
});

test('an archived project appears in both internal search and public browse', function () {
    makeArchivedProject(['title' => 'Shared Visible Project']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => 'Shared Visible']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 1)
            ->where('results.data.0.entity_label', 'مشروع مؤرشف'));

    $this->get(route('public.browse', ['search' => 'Shared Visible']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

test('internal search is case-insensitive on Arabic input', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    Proposal::factory()->create([
        'title' => 'نظام إدارة المكتبة الذكية',
        'department_id' => $dept->id, 'specialization_id' => $spec->id,
        'supervisor_id' => userWithRole('supervisor')->id,
        'status_id' => Proposal::STATUS_PENDING, 'is_deleted' => false,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => '  المكتبة  ']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 1));
});

test('empty internal search returns all reachable proposals and projects', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    Proposal::factory()->count(2)->create([
        'department_id' => $dept->id, 'specialization_id' => $spec->id,
        'supervisor_id' => userWithRole('supervisor')->id,
        'status_id' => Proposal::STATUS_PENDING, 'is_deleted' => false,
    ]);
    makeArchivedProject(['title' => 'Archived One']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 3));
});
