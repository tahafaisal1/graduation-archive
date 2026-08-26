<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2020, 2026);

        return [
            'title'             => $this->faker->sentence(4),
            'description'       => $this->faker->paragraph(),
            'academic_year'     => $year . '/' . ($year + 1),
            'department_id'     => Department::factory(),
            'specialization_id' => Specialization::factory(),
            'supervisor_id'     => User::factory(),
            'status_id'         => Proposal::STATUS_ARCHIVED,
            'is_deleted'        => false,
        ];
    }
}
