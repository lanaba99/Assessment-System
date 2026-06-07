<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExamSession;

use App\Domains\ExamSession\Contracts\EnrollmentService;
use App\Domains\ExamSession\Exceptions\EnrollmentAlreadyExistsException;
use App\Domains\ExamSession\Exceptions\EnrollmentNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamSession\EnrollCandidateRequest;
use App\Http\Resources\ExamSession\EnrollmentResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnrollmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EnrollmentService $enrollmentService,
    ) {
    }

    public function index(Request $request, string $examId): JsonResponse
    {
        // Uses the exam_sessions.manage permission — enrollment lists are admin-only.
        $this->authorize('manage-enrollments', \App\Domains\ExamSession\Models\ExamCandidateEligible::class);

        $tenantId = (string) tenant()->getKey();
        $enrollments = $this->enrollmentService->listForExam($tenantId, $examId);

        return new JsonResponse(
            ['data' => EnrollmentResource::collection($enrollments)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function store(EnrollCandidateRequest $request, string $examId): JsonResponse
    {
        $this->authorize('manage-enrollments', \App\Domains\ExamSession\Models\ExamCandidateEligible::class);

        $tenantId = (string) tenant()->getKey();

        try {
            $enrollment = $this->enrollmentService->enroll(
                $request->toCommand($tenantId, $examId),
            );
        } catch (EnrollmentAlreadyExistsException $e) {
            return $this->errorResponse('enrollment_already_exists', $e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            ['data' => new EnrollmentResource($enrollment)],
            Response::HTTP_CREATED,
        );
    }

    public function destroy(Request $request, string $examId, string $enrollmentId): JsonResponse
    {
        $this->authorize('manage-enrollments', \App\Domains\ExamSession\Models\ExamCandidateEligible::class);

        $tenantId = (string) tenant()->getKey();

        try {
            $this->enrollmentService->revoke($tenantId, $enrollmentId);
        } catch (EnrollmentNotFoundException $e) {
            return $this->errorResponse('enrollment_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            $status,
        );
    }
}
