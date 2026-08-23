<?php

use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\PermissionRegistrar;

$expectedFillable = [
    'project_title', 'description', 'academic_year',
    'department_id', 'specialization_id', 'supervisor_id',
    'current_status_id', 'based_on_project_id', 'draft_file_path',
    'final_score', 'visit_count', 'is_deleted',
];

test('project model has all fillable fields', function () use ($expectedFillable) {
    $fillable = (new Project())->getFillable();

    foreach ($expectedFillable as $field) {
        expect($fillable)->toContain($field);
    }
});

test('is_deleted casts to boolean', function () {
    $casts = (new Project())->getCasts();

    expect($casts)->toHaveKey('is_deleted')
        ->and($casts['is_deleted'])->toBe('boolean');
});

test('project belongsTo department', function () {
    expect((new Project())->department())->toBeInstanceOf(BelongsTo::class);
});

test('project belongsTo specialization', function () {
    expect((new Project())->specialization())->toBeInstanceOf(BelongsTo::class);
});

test('project hasMany project students', function () {
    expect((new Project())->students())->toBeInstanceOf(HasMany::class);
});

test('project belongsToMany examiner via project_examiners', function () {
    expect((new Project())->examiners())->toBeInstanceOf(BelongsToMany::class);
});

describe('permission predicates', function () {
    beforeEach(function () {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        $this->seed(ProjectStatusSeeder::class);
    });

    test('canBeModifiedBy: super_admin can always modify regardless of status or ownership', function () {
        $dept = Department::factory()->create();
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_ARCHIVED,
            'created_by' => null,
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        expect($project->canBeModifiedBy($admin))->toBeTrue();
    });

    test('canBeModifiedBy: creator can modify their own pending project in their department', function () {
        $dept = Department::factory()->create();
        $creator = User::factory()->create(['department_id' => $dept->id]);
        $creator->assignRole('dept_staff');
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_PENDING,
            'created_by' => $creator->id,
        ]);

        expect($project->canBeModifiedBy($creator))->toBeTrue();
    });

    test('canBeModifiedBy: dept_manager of same department can modify a pending project even if not the creator', function () {
        $dept = Department::factory()->create();
        $manager = User::factory()->create(['department_id' => $dept->id]);
        $manager->assignRole('dept_manager');
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_PENDING,
            'created_by' => User::factory()->create()->id,
        ]);

        expect($project->canBeModifiedBy($manager))->toBeTrue();
    });

    test('canBeModifiedBy: non-creator dept_staff in same department cannot modify', function () {
        $dept = Department::factory()->create();
        $otherStaff = User::factory()->create(['department_id' => $dept->id]);
        $otherStaff->assignRole('dept_staff');
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_PENDING,
            'created_by' => User::factory()->create()->id,
        ]);

        expect($project->canBeModifiedBy($otherStaff))->toBeFalse();
    });

    test('canBeModifiedBy: creator cannot modify once archived', function () {
        $dept = Department::factory()->create();
        $creator = User::factory()->create(['department_id' => $dept->id]);
        $creator->assignRole('dept_staff');
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_ARCHIVED,
            'created_by' => $creator->id,
        ]);

        expect($project->canBeModifiedBy($creator))->toBeFalse();
    });

    test('canBeArchivedBy: dept_manager of same department can archive a pending project', function () {
        $dept = Department::factory()->create();
        $manager = User::factory()->create(['department_id' => $dept->id]);
        $manager->assignRole('dept_manager');
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_PENDING,
        ]);

        expect($project->canBeArchivedBy($manager))->toBeTrue();
    });

    test('canBeArchivedBy: dept_manager of a different department cannot archive', function () {
        $dept = Department::factory()->create();
        $otherDept = Department::factory()->create();
        $manager = User::factory()->create(['department_id' => $otherDept->id]);
        $manager->assignRole('dept_manager');
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_PENDING,
        ]);

        expect($project->canBeArchivedBy($manager))->toBeFalse();
    });

    test('canBeArchivedBy: cannot archive an already-archived project (idempotency guard)', function () {
        $dept = Department::factory()->create();
        $manager = User::factory()->create(['department_id' => $dept->id]);
        $manager->assignRole('dept_manager');
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_ARCHIVED,
        ]);

        expect($project->canBeArchivedBy($manager))->toBeFalse();
    });

    test('canBeArchivedBy: dept_staff can never archive', function () {
        $dept = Department::factory()->create();
        $staff = User::factory()->create(['department_id' => $dept->id]);
        $staff->assignRole('dept_staff');
        $project = Project::factory()->create([
            'department_id' => $dept->id,
            'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
            'supervisor_id' => User::factory()->create()->id,
            'current_status_id' => Project::STATUS_PENDING,
        ]);

        expect($project->canBeArchivedBy($staff))->toBeFalse();
    });
});
