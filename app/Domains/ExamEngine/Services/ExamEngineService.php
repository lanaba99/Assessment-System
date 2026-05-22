<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Services;

use App\Domains\ExamEngine\DTOs\CreateExamCommand;
use App\Domains\ExamEngine\DTOs\ExamView;
use App\Domains\ExamEngine\Exceptions\InvalidExamStateException;
use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamEngine\Repositories\ExamRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExamEngineService
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    private const PUBLISHABLE_FROM = [self::STATUS_DRAFT];

    private const ARCHIVABLE_FROM = [self::STATUS_DRAFT, self::STATUS_PUBLISHED];

    public function __construct(
        private readonly ExamRepository $repository,
    ) {
    }

    public function getExam(string $examId): ?ExamView
    {
        $exam = $this->repository->findById($examId);

        if ($exam === null) {
            return null;
        }

        return $this->toView($exam);
    }

    public function createExam(CreateExamCommand $command): ExamView
    {
        return DB::transaction(function () use ($command): ExamView {
            $exam = $this->repository->create([
                'tenant_id' => $command->tenantId,
                'created_by_user_id' => $command->createdByUserId,
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
                'exam_status' => self::STATUS_DRAFT,
                'is_published' => false,
                'published_at' => null,
                'archived_at' => null,
            ]);

            return $this->toView($exam);
        });
    }

    public function publishExam(string $examId): ExamView
    {
        return DB::transaction(function () use ($examId): ExamView {
            $exam = $this->loadOrFail($examId);
            $this->assertTransition($exam, self::PUBLISHABLE_FROM, 'publish');

            $exam = $this->repository->update($exam, [
                'exam_status' => self::STATUS_PUBLISHED,
                'is_published' => true,
                'published_at' => $exam->published_at ?? now(),
            ]);

            return $this->toView($exam);
        });
    }

    public function archiveExam(string $examId): ExamView
    {
        return DB::transaction(function () use ($examId): ExamView {
            $exam = $this->loadOrFail($examId);
            $this->assertTransition($exam, self::ARCHIVABLE_FROM, 'archive');

            $exam = $this->repository->update($exam, [
                'exam_status' => self::STATUS_ARCHIVED,
                'is_published' => false,
                'archived_at' => now(),
            ]);

            return $this->toView($exam);
        });
    }

    private function loadOrFail(string $examId): Exam
    {
        $exam = $this->repository->findById($examId);

        if ($exam === null) {
            throw new RuntimeException("Exam {$examId} not found.");
        }

        return $exam;
    }

    /**
     * @param  array<int, string>  $allowedFromStates
     */
    private function assertTransition(Exam $exam, array $allowedFromStates, string $operation): void
    {
        if (! in_array((string) $exam->exam_status, $allowedFromStates, true)) {
            throw InvalidExamStateException::forOperation($operation, (string) $exam->exam_status);
        }
    }

    private function toView(Exam $exam): ExamView
    {
        return new ExamView(
            examId: (string) $exam->exam_id,
            tenantId: (string) $exam->tenant_id,
            createdByUserId: (string) $exam->created_by_user_id,
            examName: (string) $exam->exam_name,
            examCode: (string) $exam->exam_code,
            examDescription: $exam->exam_description,
            examType: (string) $exam->exam_type,
            assessmentMode: $exam->assessment_mode,
            totalQuestions: (int) $exam->total_questions,
            totalDurationMinutes: (int) $exam->total_duration_minutes,
            passMarkPercentage: (float) $exam->pass_mark_percentage,
            difficultyTierLevel: $exam->difficulty_tier_level !== null ? (int) $exam->difficulty_tier_level : null,
            isAdaptiveExam: (bool) $exam->is_adaptive_exam,
            isRandomized: (bool) $exam->is_randomized,
            allowReviewAfterSubmit: (bool) $exam->allow_review_after_submit,
            allowFlaggingForReview: (bool) $exam->allow_flagging_for_review,
            timerVisibleToCandidate: (bool) $exam->timer_visible_to_candidate,
            showCorrectAnswersAfter: (bool) $exam->show_correct_answers_after,
            securityProtocols: is_array($exam->security_protocols) ? $exam->security_protocols : null,
            examMetadata: is_array($exam->exam_metadata) ? $exam->exam_metadata : null,
            examStatus: (string) $exam->exam_status,
            isPublished: (bool) $exam->is_published,
            publishedAt: $this->toDateTime($exam->published_at),
            archivedAt: $this->toDateTime($exam->archived_at),
        );
    }

    private function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
