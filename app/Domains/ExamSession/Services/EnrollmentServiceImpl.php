<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Services;

use App\Domains\ExamSession\Contracts\EnrollmentService;
use App\Domains\ExamSession\DTOs\EnrollCandidateCommand;
use App\Domains\ExamSession\Exceptions\EnrollmentAlreadyExistsException;
use App\Domains\ExamSession\Exceptions\EnrollmentNotFoundException;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use App\Domains\ExamSession\Repositories\EnrollmentRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnrollmentServiceImpl implements EnrollmentService
{
    public function __construct(
        private readonly EnrollmentRepository $repository,
    ) {
    }

    public function enroll(EnrollCandidateCommand $command): ExamCandidateEligible
    {
        return DB::transaction(function () use ($command): ExamCandidateEligible {
            $existing = $this->repository->findByCandidateAndExam(
                $command->tenantId,
                $command->candidateUserId,
                $command->examId,
            );

            if ($existing !== null) {
                throw EnrollmentAlreadyExistsException::forCandidateAndExam(
                    $command->candidateUserId,
                    $command->examId,
                );
            }

            return $this->repository->create([
                'tenant_id' => $command->tenantId,
                'exam_id' => $command->examId,
                'candidate_user_id' => $command->candidateUserId,
                'cohort_id' => $command->cohortId,
                'enrollment_status' => 'active',
                'enrollment_date' => now(),
                'start_window_date' => $command->startWindowDate,
                'end_window_date' => $command->endWindowDate,
                'can_retake_exam' => $command->maxAttemptsAllowed > 1,
                'max_attempts_allowed' => $command->maxAttemptsAllowed,
                'attempts_used' => 0,
                'attempts_remaining' => $command->maxAttemptsAllowed,
                'enrollment_notes' => $command->enrollmentNotes,
            ]);
        });
    }

    /**
     * @return Collection<int, ExamCandidateEligible>
     */
    public function listForExam(string $tenantId, string $examId): Collection
    {
        return $this->repository->listForExam($tenantId, $examId);
    }

    public function revoke(string $tenantId, string $enrollmentId): void
    {
        $enrollment = $this->repository->findById($tenantId, $enrollmentId);

        if ($enrollment === null) {
            throw EnrollmentNotFoundException::forCandidate('unknown', $enrollmentId);
        }

        $this->repository->update($enrollment, [
            'enrollment_status' => 'revoked',
        ]);
    }
}
