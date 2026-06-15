<?php

use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
