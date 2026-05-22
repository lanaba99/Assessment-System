<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\ExamEngine\DTOs\ExamView;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ExamView $resource
 */
class ExamResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'exam_id' => $view->examId,
            'tenant_id' => $view->tenantId,
            'created_by_user_id' => $view->createdByUserId,
            'identity' => [
                'name' => $view->examName,
                'code' => $view->examCode,
                'description' => $view->examDescription,
                'type' => $view->examType,
                'assessment_mode' => $view->assessmentMode,
            ],
            'configuration' => [
                'total_questions' => $view->totalQuestions,
                'total_duration_minutes' => $view->totalDurationMinutes,
                'pass_mark_percentage' => $view->passMarkPercentage,
                'difficulty_tier_level' => $view->difficultyTierLevel,
                'is_adaptive_exam' => $view->isAdaptiveExam,
                'is_randomized' => $view->isRandomized,
                'allow_review_after_submit' => $view->allowReviewAfterSubmit,
                'allow_flagging_for_review' => $view->allowFlaggingForReview,
                'timer_visible_to_candidate' => $view->timerVisibleToCandidate,
                'show_correct_answers_after' => $view->showCorrectAnswersAfter,
            ],
            'security_protocols' => $view->securityProtocols,
            'metadata' => $view->examMetadata,
            'lifecycle' => [
                'status' => $view->examStatus,
                'is_published' => $view->isPublished,
                'published_at' => $view->publishedAt?->format(DateTimeInterface::ATOM),
                'archived_at' => $view->archivedAt?->format(DateTimeInterface::ATOM),
            ],
        ];
    }
}
