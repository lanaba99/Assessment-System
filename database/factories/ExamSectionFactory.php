<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\ExamEngine\Models\ExamSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExamSection>
 */
class ExamSectionFactory extends Factory
{
    protected $model = ExamSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => (string) Str::uuid(),
            // Server-controlled; override with real ids when FKs are enforced.
            'tenant_id' => (string) Str::uuid(),
            'exam_id' => (string) Str::uuid(),
            'section_name' => ucwords($this->faker->words(2, true)) . ' Section',
            'section_code' => 'SEC-' . strtoupper(Str::random(4)),
            'section_sequence' => $this->faker->numberBetween(1, 20),
            'questions_in_section' => $this->faker->numberBetween(5, 20),
            'time_limit_minutes' => $this->faker->optional(0.6)->numberBetween(10, 60),
            'branching_logic' => null,
            'section_metadata' => null,
        ];
    }

    public function forExam(string $examId): static
    {
        return $this->state(fn (): array => ['exam_id' => $examId]);
    }

    public function withSequence(int $sequence): static
    {
        return $this->state(fn (): array => ['section_sequence' => $sequence]);
    }
}
