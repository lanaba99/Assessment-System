<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Competency\Models\Competency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competency>
 */
class CompetencyFactory extends Factory
{
    protected $model = Competency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competency_id' => (string) Str::uuid(),
            // Server-controlled. created_by_user_id is a FK to users — override
            // with a real id (e.g. ->forUser($user->id)) in tests that enforce
            // foreign keys. tenant_id is auto-filled by BelongsToTenant when a
            // tenant context is bound; the default here is for context-free use.
            'tenant_id' => (string) Str::uuid(),
            'created_by_user_id' => (string) Str::uuid(),
            'parent_competency_id' => null,
            'competency_name' => ucwords($this->faker->unique()->words(2, true)),
            'competency_code' => 'COMP-' . strtoupper(Str::random(6)),
            'competency_type' => $this->faker->randomElement([
                Competency::TYPE_KNOWLEDGE,
                Competency::TYPE_SKILL,
                Competency::TYPE_ABILITY,
            ]),
            'competency_category' => $this->faker->randomElement(['cognitive', 'technical', 'behavioral']),
            'description' => $this->faker->sentence(),
            'competency_attributes' => ['framework' => 'KSA'],
            'hierarchy_level' => 0,
            'is_active' => true,
        ];
    }

    public function forUser(string $userId): static
    {
        return $this->state(fn (): array => ['created_by_user_id' => $userId]);
    }

    public function childOf(Competency $parent): static
    {
        return $this->state(fn (): array => [
            'parent_competency_id' => (string) $parent->competency_id,
            'tenant_id' => (string) $parent->tenant_id,
            'hierarchy_level' => (int) $parent->hierarchy_level + 1,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
