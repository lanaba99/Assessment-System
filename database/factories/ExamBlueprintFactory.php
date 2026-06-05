<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\ExamEngine\Models\ExamBlueprint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExamBlueprint>
 */
class ExamBlueprintFactory extends Factory
{
    protected $model = ExamBlueprint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minQuestions = $this->faker->numberBetween(3, 10);

        return [
            'blueprint_id' => (string) Str::uuid(),
            // Override with real FK ids when constraints are enforced.
            'exam_id' => (string) Str::uuid(),
            'section_id' => null,
            'competency_id' => (string) Str::uuid(),
            'min_questions_count' => $minQuestions,
            'max_questions_count' => $minQuestions + $this->faker->numberBetween(0, 5),
            'min_weight_percentage' => $this->faker->randomFloat(2, 5.0, 20.0),
            'max_weight_percentage' => $this->faker->randomFloat(2, 20.0, 40.0),
            'bloom_distribution' => [
                'remember' => 20,
                'understand' => 30,
                'apply' => 30,
                'analyze' => 20,
            ],
            'target_difficulty' => $this->faker->randomFloat(3, 0.300, 0.800),
            'min_discrimination' => 0.200,
            'resolution_strategy' => $this->faker->randomElement([
                'stratified', 'random', 'greedy', 'weighted',
            ]),
            'blueprint_metadata' => null,
        ];
    }

    public function forExam(string $examId): static
    {
        return $this->state(fn (): array => ['exam_id' => $examId]);
    }

    public function forSection(string $sectionId): static
    {
        return $this->state(fn (): array => ['section_id' => $sectionId]);
    }

    public function forCompetency(string $competencyId): static
    {
        return $this->state(fn (): array => ['competency_id' => $competencyId]);
    }

    public function stratified(): static
    {
        return $this->state(fn (): array => ['resolution_strategy' => 'stratified']);
    }

    public function adaptive(): static
    {
        return $this->state(fn (): array => ['resolution_strategy' => 'adaptive']);
    }
}
