<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Contracts;

use App\Domains\ExamEngine\DTOs\CreateExamCommand;
use App\Domains\ExamEngine\DTOs\UpdateExamCommand;
use App\Domains\ExamEngine\Exceptions\ExamNotFoundException;
use App\Domains\ExamEngine\Exceptions\InvalidExamStateException;
use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Support\Collection;

interface ExamEngineService
{
    /**
     * @return Collection<int, Exam>
     */
    public function listExams(string $tenantId): Collection;

    /**
     * @throws ExamNotFoundException
     */
    public function getExam(string $tenantId, string $examId): Exam;

    public function createExam(CreateExamCommand $command): Exam;

    /**
     * @throws ExamNotFoundException
     */
    public function updateExam(string $tenantId, string $examId, UpdateExamCommand $command): Exam;

    /**
     * @throws ExamNotFoundException
     * @throws InvalidExamStateException
     */
    public function publishExam(string $tenantId, string $examId): Exam;

    /**
     * @throws ExamNotFoundException
     * @throws InvalidExamStateException
     */
    public function archiveExam(string $tenantId, string $examId): Exam;

    /**
     * @throws ExamNotFoundException
     */
    public function deleteExam(string $tenantId, string $examId): void;
}
