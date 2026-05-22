<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\ExamEngine\Services\ExamEngineService;
use App\Http\Requests\CreateExamRequest;
use App\Http\Resources\ExamResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamEngineService $examEngineService,
    ) {
    }

    public function store(CreateExamRequest $request): JsonResponse
    {
        $view = $this->examEngineService->createExam($request->toCommand());

        return ExamResource::make($view)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $examId): JsonResponse
    {
        $view = $this->examEngineService->getExam($examId);

        if ($view === null) {
            return response()->json([
                'error' => [
                    'code' => 'exam_not_found',
                    'message' => "Exam {$examId} not found.",
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        return ExamResource::make($view)->response();
    }

    public function publish(string $examId): JsonResponse
    {
        $view = $this->examEngineService->publishExam($examId);

        return ExamResource::make($view)->response();
    }

    public function archive(string $examId): JsonResponse
    {
        $view = $this->examEngineService->archiveExam($examId);

        return ExamResource::make($view)->response();
    }
}
