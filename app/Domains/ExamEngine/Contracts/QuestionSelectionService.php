<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Contracts;

use App\Domains\ExamEngine\Exceptions\BlueprintNotFeasibleException;
use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Support\Collection;

interface QuestionSelectionService
{
    /**
     * Validate that the QuestionBank can satisfy every section blueprint in
     * this exam. Called at publish time as a hard gate.
     *
     * @throws BlueprintNotFeasibleException  when one or more sections have
     *         insufficient coverage in the bank.
     */
    public function assertBlueprintFeasible(Exam $exam): void;

    /**
     * Resolve the ordered list of question_version_ids for a candidate's
     * session, one draw per section. Called at session-start time.
     *
     * @param  array<int, string>  $excludedVersionIds  versions already seen
     *         by this candidate (exposure-cooldown list).
     * @return Collection<int, \App\Domains\ExamEngine\DTOs\ResolvedSessionItem>
     */
    public function resolveQuestionsForSession(
        Exam $exam,
        string $candidateId,
        array $excludedVersionIds = [],
    ): Collection;
}
