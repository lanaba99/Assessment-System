<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Repositories;

use App\Domains\ExamEngine\Models\Exam;

class ExamRepository
{
    public function __construct(
        private readonly Exam $exam,
    ) {
    }

    public function findById(string $examId): ?Exam
    {
        return $this->exam->newQuery()->find($examId);
    }

    public function findWithSectionsAndQuestions(string $examId): ?Exam
    {
        return $this->exam
            ->newQuery()
            ->with(['sections'])
            ->find($examId);
    }

    public function create(array $attributes): Exam
    {
        return $this->exam->newQuery()->create($attributes);
    }

    public function update(Exam $exam, array $attributes): Exam
    {
        $exam->fill($attributes)->save();

        return $exam;
    }
}
