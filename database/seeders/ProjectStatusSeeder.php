<?php

namespace Database\Seeders;

use App\Models\ProjectStatus;
use Illuminate\Database\Seeder;

class ProjectStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 1,  'status_name' => 'archived',            'sort_order' => 8,  'is_active' => true],
            ['id' => 2,  'status_name' => 'proposal_submitted',  'sort_order' => 1,  'is_active' => false],
            ['id' => 3,  'status_name' => 'supervisor_approved', 'sort_order' => 2,  'is_active' => false],
            ['id' => 4,  'status_name' => 'hod_approved',        'sort_order' => 3,  'is_active' => false],
            ['id' => 5,  'status_name' => 'in_progress',         'sort_order' => 4,  'is_active' => false],
            ['id' => 6,  'status_name' => 'ready_for_defense',   'sort_order' => 5,  'is_active' => false],
            ['id' => 7,  'status_name' => 'under_defense',       'sort_order' => 6,  'is_active' => false],
            ['id' => 8,  'status_name' => 'revisions_required',  'sort_order' => 7,  'is_active' => false],
            ['id' => 9,  'status_name' => 'rejected',            'sort_order' => 9,  'is_active' => false],
            ['id' => 10, 'status_name' => 'cancelled',           'sort_order' => 10, 'is_active' => false],
        ];

        foreach ($statuses as $status) {
            ProjectStatus::updateOrCreate(['id' => $status['id']], $status);
        }
    }
}
