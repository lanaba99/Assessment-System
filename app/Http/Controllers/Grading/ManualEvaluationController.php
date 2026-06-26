<?php

declare(strict_types=1);

namespace App\Http\Controllers\Grading;

use App\Domains\Grading\Contracts\ManualEvaluationService;
use App\Domains\Grading\Exceptions\EvaluationNotFoundException;
use App\Domains\Grading\Exceptions\InvalidEvaluationStateException;
use App\Domains\Grading\Exceptions\InvalidScoreRangeException;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grading\SubmitEvaluationRequest;
use App\Http\Resources\Grading\AnswerEvaluationResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @group ManualEvaluation
 */

class ManualEvaluationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ManualEvaluationService $service,
    ) {
    }

    /**
     * List all pending_review evaluations for the given session.
     *
     * Requires grading.evaluate OR grading.view permission (class-level gate).
     */
    public function pending(Request $request, string $sessionId): JsonResponse
    {
        $this->authorize('listPending', AnswerEvaluation::class);

        $tenantId = (string) tenant()->getKey();
        $evaluations = $this->service->listPendingForSession($tenantId, $sessionId);

        return new JsonResponse(
            ['data' => AnswerEvaluationResource::collection($evaluations)->resolve()],
            Response::HTTP_OK,
        );
    }

    /**
     * Submit a human evaluator's score for a single pending_review evaluation.
     *
     * Requires grading.evaluate permission (enforced in SubmitEvaluationRequest::authorize()).
     * If this was the last pending item for the session, grade re-finalization
     * is triggered synchronously and the final grade becomes queryable immediately.
     */
    public function score(SubmitEvaluationRequest $request, string $evaluationId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $updated = $this->service->submitScore(
                $request->toCommand($tenantId, $evaluationId),
            );
        } catch (EvaluationNotFoundException $e) {
            return $this->errorResponse('evaluation_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (InvalidEvaluationStateException $e) {
            return $this->errorResponse('invalid_evaluation_state', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidScoreRangeException $e) {
            return $this->errorResponse('invalid_score_range', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(
            ['data' => new AnswerEvaluationResource($updated)],
            Response::HTTP_OK,
        );
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            $status,
        );
    }
}
