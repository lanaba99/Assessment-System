<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Contracts;

use App\Domains\ExamSession\DTOs\EnrollCandidateCommand;
use App\Domains\ExamSession\Exceptions\EnrollmentAlreadyExistsException;
use App\Domains\ExamSession\Exceptions\EnrollmentNotFoundException;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use Illuminate\Support\Collection;

interface EnrollmentService
{
    /**
     * @throws EnrollmentAlreadyExistsException when a record already exists for this (exam, candidate) pair
     */
    public function enroll(EnrollCandidateCommand $command): ExamCandidateEligible;

    /**
     * @return Collection<int, ExamCandidateEligible>
     */
    public function listForExam(string $tenantId, string $examId): Collection;

    /**
     * @throws EnrollmentNotFoundException when the enrollment does not exist for this tenant
     */
    public function revoke(string $tenantId, string $enrollmentId): void;
}
