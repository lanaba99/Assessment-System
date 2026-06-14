<?php

declare(strict_types=1);

namespace App\Http\Controllers\Penalties;

use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Penalties\Services\PenaltyRuleManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Penalties\StorePenaltyRuleRequest;
use App\Http\Requests\Penalties\UpdatePenaltyRuleRequest;
use App\Http\Resources\Penalties\PenaltyRuleResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PenaltyRuleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PenaltyRuleManagementService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PenaltyRule::class);

        $tenantId = (string) tenant()->getKey();
        $rules = $this->service->listForTenant($tenantId);

        return new JsonResponse(
            ['data' => PenaltyRuleResource::collection($rules)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function store(StorePenaltyRuleRequest $request): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $rule = $this->service->create(
            $tenantId,
            (string) $request->user()->id,
            $request->validated(),
        );

        return new JsonResponse(
            ['data' => new PenaltyRuleResource($rule)],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $ruleId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $rule = $this->service->find($tenantId, $ruleId);

        if ($rule === null) {
            return $this->notFound('penalty_rule_not_found', "Penalty rule {$ruleId} not found.");
        }

        $this->authorize('view', $rule);

        return new JsonResponse(
            ['data' => new PenaltyRuleResource($rule)],
            Response::HTTP_OK,
        );
    }

    public function update(UpdatePenaltyRuleRequest $request, string $ruleId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $rule = $this->service->find($tenantId, $ruleId);

        if ($rule === null) {
            return $this->notFound('penalty_rule_not_found', "Penalty rule {$ruleId} not found.");
        }

        $updated = $this->service->update($rule, $request->validated());

        return new JsonResponse(
            ['data' => new PenaltyRuleResource($updated)],
            Response::HTTP_OK,
        );
    }

    public function destroy(Request $request, string $ruleId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $rule = $this->service->find($tenantId, $ruleId);

        if ($rule === null) {
            return $this->notFound('penalty_rule_not_found', "Penalty rule {$ruleId} not found.");
        }

        $this->authorize('delete', $rule);
        $this->service->delete($rule);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function activate(Request $request, string $ruleId): JsonResponse
    {
        return $this->setActiveState($request, $ruleId, true);
    }

    public function deactivate(Request $request, string $ruleId): JsonResponse
    {
        return $this->setActiveState($request, $ruleId, false);
    }

    private function setActiveState(Request $request, string $ruleId, bool $active): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $rule = $this->service->find($tenantId, $ruleId);

        if ($rule === null) {
            return $this->notFound('penalty_rule_not_found', "Penalty rule {$ruleId} not found.");
        }

        $this->authorize('update', $rule);
        $updated = $this->service->setActive($rule, $active);

        return new JsonResponse(
            ['data' => new PenaltyRuleResource($updated)],
            Response::HTTP_OK,
        );
    }

    private function notFound(string $code, string $message): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            Response::HTTP_NOT_FOUND,
        );
    }
}
