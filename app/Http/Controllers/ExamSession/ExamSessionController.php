<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExamSession;


use App\Domains\ExamSession\Contracts\ExamSessionService;
use App\Domains\ExamSession\Exceptions\EligibilityViolationException;
use App\Domains\ExamSession\Exceptions\EnrollmentNotFoundException;
use App\Domains\ExamSession\Exceptions\InvalidSessionStateException;
use App\Domains\ExamSession\Exceptions\SessionDurationExceededException;
use App\Domains\ExamSession\Exceptions\SessionNotFoundException;
use App\Domains\ExamSession\Exceptions\StaleVersionLockException;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamSession\StartSessionRequest;
use App\Http\Requests\ExamSession\SubmitResponseRequest;
use App\Http\Resources\ExamSessionListResource;
use App\Http\Resources\ExamSessionResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Domains\QuestionBank\Models\QuestionVersion;
use App\Http\Resources\CandidateQuestionResource;

/**
 * @group ExamSession
 */

class ExamSessionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ExamSessionService $sessionService,
    ) {
    }
    /**
     * GET /api/v1/exam-sessions
     *
     * Single general-purpose list endpoint — filtered via query params, not
     * one endpoint per status. All filters are optional:
     *   ?status=completed    → sessions in the 'completed' state (grading/publishing/certs)
     *   ?status=in_progress  → active sessions (live monitoring)
     *   ?status=terminated   → forcibly-ended sessions
     *   ?status=paused       → suspended sessions
     *   ?status=not_started  → sessions not yet begun
     *   (no status)          → all sessions
     *   ?exam_id=...          → scope to one exam
     *   ?candidate_id=...     → scope to one candidate
     *   ?per_page=...         → page size (default 15, max 100)
     *
     * Role-based visibility (see ExamSessionPolicy::viewAny):
     *   - Tenant Admin        → any status, or unfiltered.
     *   - Proctor              → in_progress / terminated only.
     *   - Technical Evaluator  → completed only.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:not_started,in_progress,paused,completed,terminated',
            'exam_id' => 'sometimes|string|uuid',
            'candidate_id' => 'sometimes|string|uuid',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $status = $validated['status'] ?? null;

        // Validation runs first so the ability check sees the real ?status=
        // value (or null) — visibility is role-and-status dependent.
        $this->authorize('viewAny', [CandidateExamStatus::class, $status]);

        $tenantId = (string) tenant()->getKey();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $sessions = $this->sessionService->listSessions(
            $tenantId,
            [
                'status' => $status,
                'exam_id' => $validated['exam_id'] ?? null,
                'candidate_id' => $validated['candidate_id'] ?? null,
            ],
            $perPage,
        );

        return new JsonResponse(
            [
                'data' => ExamSessionListResource::collection($sessions->items()),
                'meta' => [
                    'current_page' => $sessions->currentPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                    'last_page' => $sessions->lastPage(),
                ],
            ],
            Response::HTTP_OK,
        );
    }

    public function start(StartSessionRequest $request): JsonResponse
    {
        $this->authorize('start', CandidateExamStatus::class);

        $tenantId = (string) tenant()->getKey();
        // candidate_id is always the authenticated user — never from the request body.
        $candidateId = (string) $request->user()->id;

        try {
            $view = $this->sessionService->startSession($tenantId, $candidateId, $request->examId());
        } catch (EligibilityViolationException $e) {
            return $this->errorResponse('eligibility_violation', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (EnrollmentNotFoundException $e) {
            return $this->errorResponse('enrollment_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            ['data' => ExamSessionResource::make($view)->resolve()],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $sessionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $session = $this->sessionService->loadSessionModel($tenantId, $sessionId);
        } catch (SessionNotFoundException $e) {
            return $this->errorResponse('session_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $session);

        $view = $this->sessionService->getSession($tenantId, $sessionId);

        return new JsonResponse(
            ['data' => ExamSessionResource::make($view)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function currentQuestion(Request $request, string $sessionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $session = $this->sessionService->loadSessionModel($tenantId, $sessionId);
        } catch (SessionNotFoundException $e) {
            return $this->errorResponse('session_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        // Same ability as viewing/participating in the session: owner or exam_sessions.manage.
        $this->authorize('participate', $session);

        $view = $this->sessionService->getSession($tenantId, $sessionId);

        if ($view->currentQuestionVersionId === null) {
            return $this->errorResponse('no_current_question', 'This session has no active question right now.', Response::HTTP_NOT_FOUND);
        }

        $version = QuestionVersion::query()
            ->with('options')
            ->find($view->currentQuestionVersionId);

        if ($version === null) {
            return $this->errorResponse('question_version_not_found', 'The current question version could not be loaded.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            ['data' => CandidateQuestionResource::make($version)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function submitResponse(SubmitResponseRequest $request, string $sessionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $session = $this->sessionService->loadSessionModel($tenantId, $sessionId);
        } catch (SessionNotFoundException $e) {
            return $this->errorResponse('session_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('participate', $session);

        try {
            $view = $this->sessionService->submitResponse(
                $request->toCommand($tenantId, $sessionId, (string) $request->user()->id),
            );
        } catch (InvalidSessionStateException $e) {
            return $this->errorResponse('invalid_session_state', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (SessionDurationExceededException $e) {
            return $this->errorResponse('exam_duration_exceeded', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (StaleVersionLockException $e) {
            return $this->errorResponse('stale_version_lock', $e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            ['data' => ExamSessionResource::make($view)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function suspend(Request $request, string $sessionId): JsonResponse
    {
        return $this->performSessionTransition(
            $sessionId,
            // 'manage' (not 'participate'): suspend must NOT be self-service —
            // a candidate must not be able to pause their own timer at will.
            // Requires exam_sessions.manage (Proctor/Tenant Admin only).
            'manage',
            // actorId unused: suspend has no actor-aware service guards.
            fn (string $tenantId, string $actorId) => $this->sessionService->suspendSession($tenantId, $sessionId),
        );
    }

    public function resume(Request $request, string $sessionId): JsonResponse
    {
        return $this->performSessionTransition(
            $sessionId,
            // 'manage' (not 'participate'): same rationale as suspend —
            // only a Proctor/Tenant Admin can resume a paused session.
            'manage',
            // actorId unused: resume has no actor-aware service guards.
            fn (string $tenantId, string $actorId) => $this->sessionService->resumeSession($tenantId, $sessionId),
        );
    }
    public function complete(Request $request, string $sessionId): JsonResponse
    {
        return $this->performSessionTransition(
            $sessionId,
            'participate',
            fn (string $tenantId, string $actorId) => $this->sessionService->completeSession($tenantId, $sessionId, $actorId),
        );
    }

    public function terminate(Request $request, string $sessionId): JsonResponse
    {
        return $this->performSessionTransition(
            $sessionId,
            'manage',
            fn (string $tenantId, string $actorId) => $this->sessionService->terminateSession($tenantId, $sessionId, $actorId),
        );
    }

    /**
     * Receive a keep-alive heartbeat from the candidate's browser.
     *
     * Only the session owner can heartbeat their own session (participate ability).
     * version_lock is intentionally NOT incremented — see ExamSessionServiceImpl
     * and SessionRepository::updateHeartbeat for the concurrency rationale.
     */
    public function heartbeat(Request $request, string $sessionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $session = $this->sessionService->loadSessionModel($tenantId, $sessionId);
        } catch (\App\Domains\ExamSession\Exceptions\SessionNotFoundException $e) {
            return $this->errorResponse('session_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('participate', $session);

        try {
            $metadata = $request->input('metadata') !== null
                ? (array) $request->input('metadata')
                : null;

            $view = $this->sessionService->recordHeartbeat($tenantId, $sessionId, $metadata);
        } catch (\App\Domains\ExamSession\Exceptions\InvalidSessionStateException $e) {
            return $this->errorResponse('invalid_session_state', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(
            ['data' => ExamSessionResource::make($view)->resolve()],
            Response::HTTP_OK,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Shared flow for suspend / resume / complete / terminate:
     * load session → authorize → run callback(tenantId, actorId).
     *
     * The closure receives two arguments so actor-aware service methods
     * (completeSession, terminateSession) can apply the zero-response guard.
     * Closures that do not need the actorId simply declare it and ignore it.
     */
    private function performSessionTransition(
        string $sessionId,
        string $ability,
        \Closure $operation,
    ): JsonResponse {
        $tenantId = (string) tenant()->getKey();

        try {
            $session = $this->sessionService->loadSessionModel($tenantId, $sessionId);
        } catch (SessionNotFoundException $e) {
            return $this->errorResponse('session_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize($ability, $session);

        $actorId = (string) auth()->id();

        try {
            $view = $operation($tenantId, $actorId);
        } catch (InvalidSessionStateException $e) {
            return $this->errorResponse('invalid_session_state', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (StaleVersionLockException $e) {
            return $this->errorResponse(
                'stale_version_lock',
                'The session was modified by a concurrent request. Please refresh your session state and try again.',
                Response::HTTP_CONFLICT,
            );
        }

        return new JsonResponse(
            ['data' => ExamSessionResource::make($view)->resolve()],
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
