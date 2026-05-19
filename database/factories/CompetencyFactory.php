<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompetencyFactory extends Factory
{
    protected $model = \App\Models\Competency::class;

    public function definition(): array
    {
        $name = ucwords($this->faker->unique()->words(2, true));

        return [
            'competency_id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'created_by_user_id' => (string) Str::uuid(),
            'competency_name' => $name,
            'competency_code' => 'COMP-' . strtoupper(Str::random(6)),
            'competency_type' => $this->faker->randomElement(['knowledge', 'skill', 'ability']),
            'competency_category' => $this->faker->randomElement(['cognitive', 'technical', 'behavioral']),
            'description' => $this->faker->sentence(),
            'competency_attributes' => json_encode([
                'framework' => 'KSA',
                'bloom_level' => $this->faker->randomElement(['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create']),
            ]),
            'is_mandatory' => $this->faker->boolean(40),
            'is_active' => true,
            'proficiency_level_count' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function mandatory(): static
    {
        return $this->state(fn () => ['is_mandatory' => true]);
    }
}
