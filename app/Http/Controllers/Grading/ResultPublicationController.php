<?php

declare(strict_types=1);

namespace App\Http\Controllers\Grading;

use App\Domains\Grading\Contracts\ResultPublicationService;
use App\Domains\Grading\Exceptions\AssessmentResultNotFoundException;
use App\Domains\Grading\Exceptions\PenaltyProcessingPendingException;
use App\Domains\Grading\Exceptions\WorkflowNotApprovedException;
use App\Domains\Grading\Exceptions\ResultNotFinalizedException;
use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Grading\Repositories\AssessmentResultRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grading\PublishResultRequest;
use App\Http\Resources\AssessmentResultResource;
use DateTimeInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @group ResultPublication
 */

class ResultPublicationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ResultPublicationService $publicationService,
        private readonly AssessmentResultRepository $results,
    ) {
    }

    public function publish(PublishResultRequest $request, string $sessionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $view = $this->publicationService->publishSessionResult(
                $tenantId,
                $sessionId,
                (string) $request->user()->id,
            );
        } catch (AssessmentResultNotFoundException $e) {
            return $this->errorResponse('result_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResultNotFinalizedException $e) {
            return $this->errorResponse('result_not_finalized', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (PenaltyProcessingPendingException $e) {
            return $this->errorResponse('penalty_processing_pending', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (WorkflowNotApprovedException $e) {
            return $this->errorResponse('workflow_not_approved', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return AssessmentResultResource::make($view)->response();
    }

    public function showPublicationStatus(Request $request, string $sessionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $result = $this->results->findBySession($tenantId, $sessionId);

        if ($result === null) {
            return $this->errorResponse(
                'result_not_found',
                "No assessment result exists for session {$sessionId}.",
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->authorize('viewPublicationStatus', $result);

        return new JsonResponse([
            'data' => $this->serializePublicationStatus($result),
        ], Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePublicationStatus(AssessmentResult $result): array
    {
        return [
            'session_id' => (string) $result->session_id,
            'result_id' => (string) $result->result_id,
            'result_status' => (string) $result->result_status,
            'publication_status' => (string) $result->publication_status,
            'published_at' => $result->published_at?->format(DateTimeInterface::ATOM),
            'result_calculated_at' => $result->result_calculated_at?->format(DateTimeInterface::ATOM),
        ];
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            $status,
        );
    }
}
