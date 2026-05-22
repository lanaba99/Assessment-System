<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Grading\Services\AssessmentResultService;
use App\Http\Resources\AssessmentResultResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AssessmentResultController extends Controller
{
    public function __construct(
        private readonly AssessmentResultService $resultService,
    ) {
    }

    public function index(string $sessionId): JsonResponse
    {
        $view = $this->resultService->getForSession($sessionId);

        if ($view === null) {
            return response()->json([
                'error' => [
                    'code' => 'result_not_ready',
                    'message' => "No assessment result exists for session {$sessionId} yet.",
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        return AssessmentResultResource::make($view)->response();
    }
}
