<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\ExamSession\Services\ExamSessionService;
use App\Http\Requests\StartSessionRequest;
use App\Http\Requests\SubmitResponseRequest;
use App\Http\Resources\ExamSessionResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExamSessionController extends Controller
{
    public function __construct(
        private readonly ExamSessionService $examSessionService,
    ) {
    }

    public function start(StartSessionRequest $request): JsonResponse
    {
        $view = $this->examSessionService->startSession(
            $request->candidateId(),
            $request->examId(),
        );

        return ExamSessionResource::make($view)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $sessionId): JsonResponse
    {
        $view = $this->examSessionService->getSession($sessionId);

        return ExamSessionResource::make($view)
            ->response();
    }

    public function submitResponse(SubmitResponseRequest $request, string $sessionId): JsonResponse
    {
        $view = $this->examSessionService->submitResponse($request->toCommand($sessionId));

        return ExamSessionResource::make($view)
            ->response();
    }

    public function terminate(string $sessionId): JsonResponse
    {
        $view = $this->examSessionService->terminateSession($sessionId);

        return ExamSessionResource::make($view)
            ->response();
    }
}
