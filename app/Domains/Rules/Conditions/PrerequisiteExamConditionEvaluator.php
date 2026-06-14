<?php

declare(strict_types=1);

namespace App\Domains\Rules\Conditions;

use App\Domains\Grading\Repositories\GradeRepository;
use App\Domains\Rules\Contracts\ConditionEvaluator;
use App\Domains\Rules\DTOs\EligibilityContext;
use App\Domains\Rules\Models\EligibilityChain;

class PrerequisiteExamConditionEvaluator implements ConditionEvaluator
{
    public const TYPE = 'prerequisite_exam';

    public function __construct(
        private readonly GradeRepository $grades,
    ) {
    }

    public function supports(string $conditionType): bool
    {
        return $conditionType === self::TYPE;
    }

    /**
     * @return array{passed: bool, reason: ?string}
     */
    public function evaluate(EligibilityChain $step, EligibilityContext $context): array
    {
        $prerequisiteExamId = $step->prerequisite_exam_id;

        if ($prerequisiteExamId === null) {
            return [
                'passed' => false,
                'reason' => "Step {$step->chain_step_number}: condition '{$step->condition_type}' is missing prerequisite_exam_id.",
            ];
        }

        $minScore = $step->min_score_required !== null ? (float) $step->min_score_required : null;

        $grade = $this->grades->findPassingGradeForCandidate(
            $context->tenantId,
            $context->candidateId,
            (string) $prerequisiteExamId,
            $minScore,
        );

        if ($grade === null) {
            $scoreClause = $minScore !== null ? " with score ≥ {$minScore}" : '';

            return [
                'passed' => false,
                'reason' => "Candidate has not passed prerequisite exam {$prerequisiteExamId}{$scoreClause}.",
            ];
        }

        return [
            'passed' => true,
            'reason' => null,
        ];
    }
}
