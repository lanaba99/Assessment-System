<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Identity\Contracts\SecurityPolicyService;
use App\Http\Requests\Identity\UpdateSecurityPolicyRequest;
use App\Http\Resources\SecurityPolicyResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SecurityPolicyService $securityPolicyService,
    ) {
    }

    public function policies(Request $request): JsonResponse
    {
        $policy = $this->securityPolicyService->getActivePolicy((string) tenant()->getKey());

        if ($policy === null) {
            return new JsonResponse([
                'error' => ['code' => 'policy_not_found', 'message' => 'No active security policy for this tenant.'],
            ], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $policy);

        return SecurityPolicyResource::make($policy)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function updatePolicies(UpdateSecurityPolicyRequest $request): JsonResponse
    {
        $policy = $this->securityPolicyService->getActivePolicy((string) tenant()->getKey());

        if ($policy === null) {
            return new JsonResponse([
                'error' => ['code' => 'policy_not_found', 'message' => 'No active security policy for this tenant.'],
            ], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $policy);

        $changes = $request->changes();
        if ($changes === []) {
            return SecurityPolicyResource::make($policy)
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        }

        $updated = $this->securityPolicyService->updatePolicy(
            tenantId: (string) tenant()->getKey(),
            changes: $changes,
            updatedByUserId: (string) $request->user()->id,
        );

        return SecurityPolicyResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
