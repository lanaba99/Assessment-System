<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Grading\Contracts\AssessmentResultService;
use App\Domains\Grading\Models\AssessmentResult;
use App\Http\Resources\AssessmentResultResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;


/**
 * @group AssessmentResults
 */

class AssessmentResultController extends Controller
{
    public function __construct(
        private readonly AssessmentResultService $resultService,
    ) {
    }

    public function index(Request $request, string $sessionId): JsonResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            return response()->json([
                'error' => ['code' => 'not_authenticated', 'message' => 'Authentication required.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $tenantId = (string) tenant()->getKey();

        // Evaluators/admins with grading.view|grading.evaluate|grading.publish can
        // read any session's result within the tenant. Everyone else (candidates)
        // only gets their own result, and only once it has been published.
        $view = Gate::forUser($actor)->allows('viewResult', AssessmentResult::class)
            ? $this->resultService->getForSession($tenantId, $sessionId)
            : $this->resultService->getPublishedForCandidateSession(
                $tenantId,
                $sessionId,
                (string) $actor->id,
            );

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