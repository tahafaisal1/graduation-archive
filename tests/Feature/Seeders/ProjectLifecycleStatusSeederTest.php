<?php

use Database\Seeders\ProjectLifecycleStatusSeeder;

test('exactly 2 project lifecycle statuses exist', function () {
    $this->seed(ProjectLifecycleStatusSeeder::class);

    expect(DB::table('project_lifecycle_status')->count())->toBe(2);
});

test('قيد التنفيذ status is id 1 and مؤرشف is id 2', function () {
    $this->seed(ProjectLifecycleStatusSeeder::class);

    $inProgress = DB::table('project_lifecycle_status')->find(1);
    $archived   = DB::table('project_lifecycle_status')->find(2);

    expect($inProgress->status_name)->toBe('قيد التنفيذ')
        ->and($archived->status_name)->toBe('مؤرشف');
});
