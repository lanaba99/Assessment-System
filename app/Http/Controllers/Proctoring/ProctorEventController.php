<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proctoring;

use App\Domains\ExamSession\Exceptions\SessionNotFoundException;
use App\Domains\Proctoring\Contracts\ProctoringService;
use App\Domains\Proctoring\Exceptions\SessionNotProctorableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proctoring\LogProctorEventRequest;
use App\Http\Resources\Proctoring\ProctorLogResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @group Proctoring
 */

class ProctorEventController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProctoringService $proctoring,
    ) {
    }

    /**
     * Ingest a single proctoring event for an active session.
     *
     * Called from the candidate's browser agent or a dedicated proctoring tool.
     * All business logic (session state validation, candidate vs. proctor
     * attribution) lives in ProctoringService — this method stays thin.
     */
    public function store(LogProctorEventRequest $request, string $sessionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $log = $this->proctoring->logEvent(
                $request->toCommand($tenantId, $sessionId),
            );
        } catch (SessionNotFoundException $e) {
            return $this->errorResponse('session_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (SessionNotProctorableException $e) {
            return $this->errorResponse('session_not_proctorable', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(
            ['data' => new ProctorLogResource($log)],
            Response::HTTP_CREATED,
        );
    }

    /**
     * List all proctoring events for a session.
     *
     * Intended for proctor/admin dashboards — the proctoring.view permission
     * is enforced by the policy gate in the form request or caller.
     */
    public function index(Request $request, string $sessionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $logs = $this->proctoring->listForSession($tenantId, $sessionId);
        } catch (SessionNotFoundException $e) {
            return $this->errorResponse('session_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            ['data' => ProctorLogResource::collection($logs)->resolve()],
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
