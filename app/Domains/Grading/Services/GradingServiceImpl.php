<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\Grading\Contracts\GradingService;
use App\Domains\Grading\DTOs\GradingRequest;
use App\Domains\Grading\DTOs\GradingResult;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Repositories\AnswerEvaluationRepository;
use App\Domains\Grading\Strategies\GradingStrategyResolver;
use App\Domains\QuestionBank\Models\QuestionVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GradingServiceImpl implements GradingService
{
    public function __construct(
        private readonly GradingStrategyResolver $resolver,
        private readonly QuestionVersion $versionModel,
        private readonly AnswerEvaluationRepository $evaluations,
    ) {
    }

    public function gradeFromEvent(ResponseSubmitted $event): AnswerEvaluation
    {
        return DB::transaction(function () use ($event): AnswerEvaluation {
            $version = $this->loadVersion($event->questionVersionId);

            $request = $this->buildRequest($event, $version);

            $strategy = $this->resolver->resolve((string) $version->question_type);

            $result = $strategy->grade($request);

            return $this->evaluations->record($result, $event->command->tenantId);
        });
    }

    private function loadVersion(string $versionId): QuestionVersion
    {
        $version = $this->versionModel
            ->newQuery()
            ->where('version_id', $versionId)
            ->first();

        if ($version === null) {
            throw new RuntimeException("Question version {$versionId} not found for grading.");
        }

        return $version;
    }

    private function buildRequest(ResponseSubmitted $event, QuestionVersion $version): GradingRequest
    {
        $command = $event->command;

        return new GradingRequest(
            tenantId: $command->tenantId,
            sessionId: $command->sessionId,
            sessionItemId: $command->sessionItemId,
            candidateId: $command->candidateId,
            questionId: (string) $version->question_id,
            questionVersionId: (string) $version->version_id,
            questionType: (string) $version->question_type,
            correctAnswerKey: $version->correct_answer_json,
            responseType: $command->responseType,
            responseData: $command->responseData,
            responseText: $command->responseText,
            selectedOptions: $command->selectedOptions,
            maxScore: 1.0,
        );
    }
}
