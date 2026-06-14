<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workflows;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use App\Domains\Workflows\Services\ApprovalWorkflowService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\ApproveWorkflowRequest;
use App\Http\Requests\Workflows\InitiateWorkflowRequest;
use App\Http\Resources\Workflows\ApprovalWorkflowResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ApprovalWorkflowController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ApprovalWorkflowService $service,
    ) {
    }

    public function initiate(InitiateWorkflowRequest $request): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $validated = $request->validated();

        $workflow = $this->service->initiate(
            $tenantId,
            (string) $request->user()->id,
            (string) $validated['resource_type'],
            (string) $validated['resource_id'],
            (string) $validated['workflow_type'],
        );

        return new JsonResponse(
            ['data' => new ApprovalWorkflowResource($workflow)],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $workflowId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $workflow = $this->service->find($tenantId, $workflowId);

        if ($workflow === null) {
            return $this->notFound($workflowId);
        }

        $this->authorize('view', $workflow);

        return new JsonResponse(
            ['data' => new ApprovalWorkflowResource($workflow)],
            Response::HTTP_OK,
        );
    }

    public function approve(ApproveWorkflowRequest $request, string $workflowId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $workflow = $this->service->approve(
                $tenantId,
                $workflowId,
                (string) $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_workflow_state', 'message' => $e->getMessage()]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse(
            ['data' => new ApprovalWorkflowResource($workflow)],
            Response::HTTP_OK,
        );
    }

    private function notFound(string $workflowId): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'workflow_not_found', 'message' => "Workflow {$workflowId} not found."]],
            Response::HTTP_NOT_FOUND,
        );
    }
}
