<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CohortFactory extends Factory
{
    protected $model = \App\Models\Cohort::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'cohort_id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'parent_cohort_id' => null,
            'created_by_user_id' => (string) Str::uuid(),
            'cohort_name' => ucwords($name) . ' Cohort',
            'cohort_code' => 'COH-' . strtoupper(Str::random(8)),
            'cohort_type' => $this->faker->randomElement(['training', 'certification', 'department', 'project']),
            'cohort_description' => $this->faker->sentence(),
            'hierarchy_level' => 0,
            'cohort_attributes' => json_encode([
                'region' => $this->faker->randomElement(['NA', 'EU', 'APAC', 'MENA']),
                'cycle' => $this->faker->randomElement(['Q1', 'Q2', 'Q3', 'Q4']),
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
