<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExaminerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name'     => $this->faker->name(),
            'title'         => $this->faker->randomElement(['دكتور', 'أستاذ', 'أستاذ مساعد', 'مهندس']),
            'department_id' => Department::factory(),
        ];
    }
}
