<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Grading\Contracts\AssessmentResultService;
use App\Http\Resources\AssessmentResultResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AssessmentResultController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AssessmentResultService $resultService,
    ) {
    }

    public function index(string $sessionId): JsonResponse
    {
        // Tenant is resolved from the subdomain-based tenancy middleware — every
        // request on this route has already been verified to belong to a specific
        // tenant before this controller is reached.
        $tenantId = (string) tenant()->getKey();

        // TODO (Phase C): add a GradingPolicy check here once the policy is built.
        // Candidates should only see their own session's result; proctors/admins
        // with grading.view should be able to see any session's result within their
        // tenant. For now, tenant isolation is enforced at the service layer.

        $view = $this->resultService->getForSession($tenantId, $sessionId);

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
