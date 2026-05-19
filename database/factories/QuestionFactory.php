<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class QuestionFactory extends Factory
{
    protected $model = \App\Models\Question::class;

    public function definition(): array
    {
        return [
            'question_id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'category_id' => (string) Str::uuid(),
            'created_by_user_id' => (string) Str::uuid(),
            'current_version_id' => null,
            'question_title' => ucfirst($this->faker->sentence(6)),
            'question_type' => $this->faker->randomElement(['mcq', 'true_false', 'short_answer', 'essay']),
            'difficulty_level' => $this->faker->numberBetween(1, 5),
            'cognitive_level' => $this->faker->numberBetween(1, 6),
            'is_randomizable' => true,
            'requires_media_attachment' => false,
            'is_deprecated' => false,
            'is_archived' => false,
            'total_usage_count' => 0,
            'question_metadata' => json_encode([
                'source' => 'seeded',
                'review_state' => 'approved',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
            'archived_at' => null,
        ];
    }

    public function mcq(): static
    {
        return $this->state(fn () => ['question_type' => 'mcq']);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }
}
