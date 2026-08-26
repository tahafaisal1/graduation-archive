<?php

namespace Database\Seeders;

use App\Models\ProjectStatus;
use Illuminate\Database\Seeder;

class ProjectStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 1, 'status_name' => 'مؤرشف', 'sort_order' => 2, 'is_active' => true],
            ['id' => 2, 'status_name' => 'مقترح', 'sort_order' => 1, 'is_active' => true],
        ];

        foreach ($statuses as $status) {
            ProjectStatus::updateOrCreate(['id' => $status['id']], $status);
        }
    }
}
