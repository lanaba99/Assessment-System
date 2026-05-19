<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExamFactory extends Factory
{
    protected $model = \App\Models\Exam::class;

    public function definition(): array
    {
        $totalQuestions = $this->faker->numberBetween(20, 80);
        $duration = $totalQuestions * 2;

        return [
            'exam_id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'created_by_user_id' => (string) Str::uuid(),
            'exam_name' => ucwords($this->faker->words(3, true)) . ' Assessment',
            'exam_code' => 'EXAM-' . strtoupper(Str::random(8)),
            'exam_description' => $this->faker->paragraph(),
            'exam_type' => $this->faker->randomElement(['certification', 'placement', 'training', 'evaluation']),
            'assessment_mode' => $this->faker->randomElement(['online', 'hybrid', 'paper']),
            'total_questions' => $totalQuestions,
            'total_duration_minutes' => $duration,
            'pass_mark_percentage' => 60,
            'difficulty_tier_level' => $this->faker->numberBetween(1, 5),
            'is_adaptive_exam' => $this->faker->boolean(30),
            'is_randomized' => true,
            'allow_review_after_submit' => true,
            'allow_flagging_for_review' => true,
            'timer_visible_to_candidate' => true,
            'show_correct_answers_after' => false,
            'security_protocols' => json_encode([
                'lockdown_browser' => true,
                'webcam_required' => true,
                'screen_recording' => true,
            ]),
            'exam_metadata' => json_encode(['source' => 'seeded']),
            'is_published' => false,
            'exam_status' => 'draft',
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'archived_at' => null,
        ];
    }

    public function adaptive(): static
    {
        return $this->state(fn () => ['is_adaptive_exam' => true]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'is_published' => true,
            'exam_status' => 'published',
            'published_at' => now(),
        ]);
    }
}
