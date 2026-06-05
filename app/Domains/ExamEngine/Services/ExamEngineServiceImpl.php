<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Services;

use App\Domains\ExamEngine\Contracts\ExamEngineService;
use App\Domains\ExamEngine\Contracts\QuestionSelectionService;
use App\Domains\ExamEngine\DTOs\CreateExamCommand;
use App\Domains\ExamEngine\DTOs\UpdateExamCommand;
use App\Domains\ExamEngine\Enums\ExamStatus;
use App\Domains\ExamEngine\Exceptions\BlueprintNotFeasibleException;
use App\Domains\ExamEngine\Exceptions\ExamNotFoundException;
use App\Domains\ExamEngine\Exceptions\InvalidExamStateException;
use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamEngine\Repositories\ExamRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExamEngineServiceImpl implements ExamEngineService
{
    public function __construct(
        private readonly ExamRepository $repository,
        private readonly QuestionSelectionService $questionSelection,
    ) {
    }

    /**
     * @return Collection<int, Exam>
     */
    public function listExams(string $tenantId): Collection
    {
        return $this->repository->allForTenant($tenantId);
    }

    /**
     * @throws ExamNotFoundException
     */
    public function getExam(string $tenantId, string $examId): Exam
    {
        return $this->loadOrFail($tenantId, $examId);
    }

    public function createExam(CreateExamCommand $command): Exam
    {
        return DB::transaction(function () use ($command): Exam {
            return $this->repository->create([
                'tenant_id' => $command->tenantId,
                'created_by_user_id' => $command->createdByUserId,
                'exam_name' => $command->examName,
                'exam_code' => $command->examCode,
                'exam_description' => $command->examDescription,
                'exam_type' => $command->examType,
                // assessment_mode is NOT NULL with no DB default; default to 'online'
                // when the caller omits it so inserts never violate the constraint.
                'assessment_mode' => $command->assessmentMode ?? 'online',
                'total_questions' => $command->totalQuestions,
                'total_duration_minutes' => $command->totalDurationMinutes,
                'pass_mark_percentage' => $command->passMarkPercentage,
                // DB DEFAULT 1 but explicit null overrides it; mirror the default here.
                'difficulty_tier_level' => $command->difficultyTierLevel ?? 1,
                'is_adaptive_exam' => $command->isAdaptiveExam,
                'is_randomized' => $command->isRandomized,
                'allow_review_after_submit' => $command->allowReviewAfterSubmit,
                'allow_flagging_for_review' => $command->allowFlaggingForReview,
                'timer_visible_to_candidate' => $command->timerVisibleToCandidate,
                'show_correct_answers_after' => $command->showCorrectAnswersAfter,
                'security_protocols' => $command->securityProtocols,
                'exam_metadata' => $command->examMetadata,
                'exam_status' => ExamStatus::Draft,
                'is_published' => false,
                'published_at' => null,
                'archived_at' => null,
            ]);
        });
    }

    /**
     * Only non-null command fields are applied, enabling true PATCH semantics.
     *
     * @throws ExamNotFoundException
     */
    public function updateExam(string $tenantId, string $examId, UpdateExamCommand $command): Exam
    {
        return DB::transaction(function () use ($tenantId, $examId, $command): Exam {
            $exam = $this->loadOrFail($tenantId, $examId);

            $attributes = array_filter([
                'exam_name' => $command->examName,
                'exam_code' => $command->examCode,
                'exam_description' => $command->examDescription,
                'exam_type' => $command->examType,
                'assessment_mode' => $command->assessmentMode,
                'total_questions' => $command->totalQuestions,
                'total_duration_minutes' => $command->totalDurationMinutes,
                'pass_mark_percentage' => $command->passMarkPercentage,
                'difficulty_tier_level' => $command->difficultyTierLevel,
                'is_adaptive_exam' => $command->isAdaptiveExam,
                'is_randomized' => $command->isRandomized,
                'allow_review_after_submit' => $command->allowReviewAfterSubmit,
                'allow_flagging_for_review' => $command->allowFlaggingForReview,
                'timer_visible_to_candidate' => $command->timerVisibleToCandidate,
                'show_correct_answers_after' => $command->showCorrectAnswersAfter,
                'security_protocols' => $command->securityProtocols,
                'exam_metadata' => $command->examMetadata,
            ], static fn (mixed $value): bool => $value !== null);

            if ($attributes !== []) {
                $exam = $this->repository->update($exam, $attributes);
            }

            return $exam;
        });
    }

    /**
     * @throws ExamNotFoundException
     * @throws InvalidExamStateException
     * @throws BlueprintNotFeasibleException
     */
    public function publishExam(string $tenantId, string $examId): Exam
    {
        // Feasibility check runs outside the transaction: a gap in the question
        // bank is a validation concern, not a data-integrity concern. Throwing
        // before the transaction also avoids a needless DB round-trip.
        $exam = $this->loadOrFail($tenantId, $examId);
        $this->assertTransition($exam, ExamStatus::Published);
        $this->questionSelection->assertBlueprintFeasible($exam);

        return DB::transaction(function () use ($tenantId, $examId): Exam {
            // Re-load inside the transaction so the status update is atomic.
            $exam = $this->loadOrFail($tenantId, $examId);

            return $this->repository->update($exam, [
                'exam_status' => ExamStatus::Published,
                'is_published' => true,
                'published_at' => $exam->published_at ?? now(),
            ]);
        });
    }

    /**
     * @throws ExamNotFoundException
     * @throws InvalidExamStateException
     */
    public function archiveExam(string $tenantId, string $examId): Exam
    {
        return DB::transaction(function () use ($tenantId, $examId): Exam {
            $exam = $this->loadOrFail($tenantId, $examId);

            $this->assertTransition($exam, ExamStatus::Archived);

            return $this->repository->update($exam, [
                'exam_status' => ExamStatus::Archived,
                'is_published' => false,
                'archived_at' => now(),
            ]);
        });
    }

    /**
     * @throws ExamNotFoundException
     */
    public function deleteExam(string $tenantId, string $examId): void
    {
        $this->repository->delete($this->loadOrFail($tenantId, $examId));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws ExamNotFoundException
     */
    private function loadOrFail(string $tenantId, string $examId): Exam
    {
        return $this->repository->findById($tenantId, $examId)
            ?? throw ExamNotFoundException::forId($examId);
    }

    /**
     * Delegates transition legality to the ExamStatus state machine.
     *
     * @throws InvalidExamStateException
     */
    private function assertTransition(Exam $exam, ExamStatus $target): void
    {
        $current = $exam->exam_status;

        if (! $current->canTransitionTo($target)) {
            throw InvalidExamStateException::forOperation($target->value, $current->value);
        }
    }
}
