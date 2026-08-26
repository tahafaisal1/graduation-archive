<?php
// tests/Feature/Console/MigrateProposalProjectDataTest.php

use App\Models\Department;
use App\Models\Examiner;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\ProjectLifecycleStatusSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);
});

function seedLegacyProjectRow(array $overrides = []): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    $id = DB::table('projects_legacy')->insertGetId(array_merge([
        'project_title'      => 'Legacy row',
        'description'        => 'desc',
        'academic_year'      => '2024/2025',
        'department_id'      => $dept->id,
        'specialization_id'  => $spec->id,
        'supervisor_id'      => $supervisor->id,
        'current_status_id'  => 1, // مؤرشف
        'final_score'        => null,
        'visit_count'        => 0,
        'is_deleted'         => false,
        'created_at'         => now(),
        'updated_at'         => now(),
    ], $overrides));

    return compact('id', 'dept', 'spec', 'supervisor');
}

test('migrates a graded مؤرشف row into a proposal plus an instantiated, graded project', function () {
    $row = seedLegacyProjectRow(['final_score' => 91.00]);
    $examiner1 = Examiner::factory()->create(['department_id' => $row['dept']->id]);
    $examiner2 = Examiner::factory()->create(['department_id' => $row['dept']->id]);
    DB::table('project_examiners')->insert([
        ['project_id' => $row['id'], 'examiner_id' => $examiner1->id, 'created_at' => now(), 'updated_at' => now()],
        ['project_id' => $row['id'], 'examiner_id' => $examiner2->id, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('evaluations')->insert([
        'project_id' => $row['id'], 'examiner_id' => $examiner1->id, 'notes' => 'good',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposal = Proposal::findOrFail($row['id']);
    expect($proposal->status_id)->toBe(Proposal::STATUS_ARCHIVED);

    $project = $proposal->instantiatedProject()->first();
    expect($project)->not->toBeNull()
        ->and((float) $project->final_score)->toBe(91.00)
        ->and($project->examiners)->toHaveCount(2)
        ->and($project->evaluations)->toHaveCount(1);
});

test('migrates an ungraded مؤرشف row into an in-progress project with no examiners', function () {
    $row = seedLegacyProjectRow();

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposal = Proposal::findOrFail($row['id']);
    $project  = $proposal->instantiatedProject()->first();

    expect($project)->not->toBeNull()
        ->and($project->status_id)->toBe(\App\Models\Project::STATUS_IN_PROGRESS)
        ->and($project->final_score)->toBeNull()
        ->and($project->examiners)->toHaveCount(0);
});

test('a مقترح row with no examiners stays proposal-only', function () {
    $row = seedLegacyProjectRow(['current_status_id' => 2]);

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposal = Proposal::findOrFail($row['id']);
    expect($proposal->status_id)->toBe(Proposal::STATUS_PENDING)
        ->and($proposal->instantiatedProject()->exists())->toBeFalse();
});

test('cleans erroneous grading data from a مقترح row before migrating it', function () {
    $row = seedLegacyProjectRow(['current_status_id' => 2, 'final_score' => 87.50]);
    $examiner = Examiner::factory()->create(['department_id' => $row['dept']->id]);
    DB::table('project_examiners')->insert([
        'project_id' => $row['id'], 'examiner_id' => $examiner->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('evaluations')->insert([
        'project_id' => $row['id'], 'examiner_id' => $examiner->id, 'notes' => 'bad state',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposal = Proposal::findOrFail($row['id']);
    expect($proposal->status_id)->toBe(Proposal::STATUS_PENDING)
        ->and($proposal->instantiatedProject()->exists())->toBeFalse();
    expect(DB::table('project_examiners')->where('project_id', $row['id'])->count())->toBe(0);
    expect(DB::table('evaluations')->where('project_id', $row['id'])->count())->toBe(0);
});

test('re-points based_on_project_id chains at the new project ids', function () {
    $rowA = seedLegacyProjectRow(); // becomes project X
    $rowB = seedLegacyProjectRow(['based_on_project_id' => $rowA['id']]); // مؤرشف, references rowA

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposalA = Proposal::findOrFail($rowA['id']);
    $proposalB = Proposal::findOrFail($rowB['id']);
    $projectA  = $proposalA->instantiatedProject()->firstOrFail();

    expect($proposalB->based_on_project_id)->toBe($projectA->id);
});
