<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalQuestions = $this->faker->numberBetween(20, 80);

        return [
            'exam_id' => (string) Str::uuid(),
            // Server-controlled; override with real ids when FKs are enforced.
            'tenant_id' => (string) Str::uuid(),
            'created_by_user_id' => (string) Str::uuid(),
            'exam_name' => ucwords($this->faker->words(3, true)) . ' Assessment',
            'exam_code' => 'EXAM-' . strtoupper(Str::random(8)),
            'exam_description' => $this->faker->paragraph(),
            'exam_type' => $this->faker->randomElement(['certification', 'placement', 'training', 'evaluation']),
            'assessment_mode' => $this->faker->randomElement(['online', 'hybrid', 'paper']),
            'total_questions' => $totalQuestions,
            'total_duration_minutes' => $totalQuestions * 2,
            'pass_mark_percentage' => 60.0,
            'difficulty_tier_level' => $this->faker->numberBetween(1, 5),
            'is_adaptive_exam' => $this->faker->boolean(30),
            'is_randomized' => true,
            'allow_review_after_submit' => true,
            'allow_flagging_for_review' => true,
            'timer_visible_to_candidate' => true,
            'show_correct_answers_after' => false,
            'security_protocols' => [
                'lockdown_browser' => true,
                'webcam_required' => true,
                'screen_recording' => true,
            ],
            'exam_metadata' => ['source' => 'factory'],
            'is_published' => false,
            'exam_status' => 'draft',
            'published_at' => null,
            'archived_at' => null,
        ];
    }

    public function adaptive(): static
    {
        return $this->state(fn (): array => ['is_adaptive_exam' => true]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'exam_status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function forUser(string $userId): static
    {
        return $this->state(fn (): array => ['created_by_user_id' => $userId]);
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenantId]);
    }
}
