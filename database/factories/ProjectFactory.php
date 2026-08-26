<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'status_id'   => Project::STATUS_IN_PROGRESS,
            'final_score' => null,
            'is_deleted'  => false,
        ];
    }
}
