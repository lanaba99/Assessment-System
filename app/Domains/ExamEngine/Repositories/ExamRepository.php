<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Repositories;

use App\Domains\ExamEngine\Models\Exam;

class ExamRepository
{
    public function __construct(private readonly Exam $exam)
    {
    }

    public function findWithSectionsAndQuestions(string $examId): ?Exam
    {
        return $this->exam
            ->newQuery()
            ->with(['sections'])
            ->find($examId);
    }
}
