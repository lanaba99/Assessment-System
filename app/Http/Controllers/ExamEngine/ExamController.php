<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExamEngine;

use App\Domains\ExamEngine\Contracts\ExamEngineService;
use App\Domains\ExamEngine\Exceptions\BlueprintNotFeasibleException;
use App\Domains\ExamEngine\Exceptions\ExamNotFoundException;
use App\Domains\ExamEngine\Exceptions\InvalidExamStateException;
use App\Domains\ExamEngine\Models\Exam;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamEngine\StoreExamRequest;
use App\Http\Requests\ExamEngine\UpdateExamRequest;
use App\Http\Resources\ExamEngine\ExamResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExamController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ExamEngineService $examEngine,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Exam::class);

        $tenantId = (string) tenant()->getKey();
        $exams = $this->examEngine->listExams($tenantId);

        return new JsonResponse(
            ['data' => ExamResource::collection($exams)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function store(StoreExamRequest $request): JsonResponse
    {
        $this->authorize('create', Exam::class);

        $tenantId = (string) tenant()->getKey();
        $exam = $this->examEngine->createExam(
            $request->toCommand($tenantId, (string) $request->user()->id),
        );

        return new JsonResponse(
            ['data' => new ExamResource($exam)],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $examId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $exam = $this->examEngine->getExam($tenantId, $examId);
        } catch (ExamNotFoundException $e) {
            return $this->errorResponse('exam_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $exam);

        return new JsonResponse(
            ['data' => new ExamResource($exam)],
            Response::HTTP_OK,
        );
    }

    public function update(UpdateExamRequest $request, string $examId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $exam = $this->examEngine->getExam($tenantId, $examId);
        } catch (ExamNotFoundException $e) {
            return $this->errorResponse('exam_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $exam);

        $updated = $this->examEngine->updateExam($tenantId, $examId, $request->toCommand());

        return new JsonResponse(
            ['data' => new ExamResource($updated)],
            Response::HTTP_OK,
        );
    }

    public function destroy(Request $request, string $examId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $exam = $this->examEngine->getExam($tenantId, $examId);
        } catch (ExamNotFoundException $e) {
            return $this->errorResponse('exam_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('delete', $exam);
        $this->examEngine->deleteExam($tenantId, $examId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function publish(Request $request, string $examId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $exam = $this->examEngine->getExam($tenantId, $examId);
        } catch (ExamNotFoundException $e) {
            return $this->errorResponse('exam_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $exam);

        try {
            $published = $this->examEngine->publishExam($tenantId, $examId);
        } catch (InvalidExamStateException $e) {
            return $this->errorResponse('invalid_exam_state', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (BlueprintNotFeasibleException $e) {
            return $this->errorResponse('blueprint_not_feasible', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(
            ['data' => new ExamResource($published)],
            Response::HTTP_OK,
        );
    }

    public function archive(Request $request, string $examId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $exam = $this->examEngine->getExam($tenantId, $examId);
        } catch (ExamNotFoundException $e) {
            return $this->errorResponse('exam_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $exam);

        try {
            $archived = $this->examEngine->archiveExam($tenantId, $examId);
        } catch (InvalidExamStateException $e) {
            return $this->errorResponse('invalid_exam_state', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(
            ['data' => new ExamResource($archived)],
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
