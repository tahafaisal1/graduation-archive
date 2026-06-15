<?php

use App\Models\ProjectStatus;
use Database\Seeders\ProjectStatusSeeder;

beforeEach(function () {
    $this->seed(ProjectStatusSeeder::class);
});

test('10 statuses exist in database', function () {
    expect(ProjectStatus::count())->toBe(10);
});

test('archived status has is_active true', function () {
    $archived = ProjectStatus::where('status_name', 'archived')->first();

    expect($archived)->not->toBeNull()
        ->and($archived->is_active)->toBeTrue();
});

test('all other statuses have is_active false', function () {
    $inactiveCount = ProjectStatus::where('status_name', '!=', 'archived')
        ->where('is_active', false)
        ->count();

    expect($inactiveCount)->toBe(9);
});
