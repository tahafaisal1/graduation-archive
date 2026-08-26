<?php

use App\Models\ProjectStatus;
use Database\Seeders\ProjectStatusSeeder;

beforeEach(function () {
    $this->seed(ProjectStatusSeeder::class);
});

test('exactly 2 statuses exist in database', function () {
    expect(ProjectStatus::count())->toBe(2);
});

test('مؤرشف status is id 1 and is active', function () {
    $archived = ProjectStatus::find(1);

    expect($archived)->not->toBeNull()
        ->and($archived->status_name)->toBe('مؤرشف')
        ->and($archived->is_active)->toBeTrue();
});

test('مقترح status is id 2 and is active', function () {
    $proposal = ProjectStatus::find(2);

    expect($proposal)->not->toBeNull()
        ->and($proposal->status_name)->toBe('مقترح')
        ->and($proposal->is_active)->toBeTrue();
});
