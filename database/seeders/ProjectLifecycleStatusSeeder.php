<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectLifecycleStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('project_lifecycle_status')->updateOrInsert(
            ['id' => 1],
            ['status_name' => 'قيد التنفيذ', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('project_lifecycle_status')->updateOrInsert(
            ['id' => 2],
            ['status_name' => 'مؤرشف', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
