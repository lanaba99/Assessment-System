<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workflows;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Workflows\Models\ApprovalWorkflow;
use App\Domains\Workflows\Services\ApprovalWorkflowService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\ApproveWorkflowRequest;
use App\Http\Requests\Workflows\InitiateWorkflowRequest;
use App\Http\Requests\Workflows\RejectWorkflowRequest;
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
        private readonly AuthorizationService $auth,
    ) {
    }

    /**
     * GET /api/v1/workflows
     *
     * Single general-purpose list endpoint — filtered via query params, not
     * one endpoint per status/type. All filters are optional:
     *   ?status=pending          → workflows awaiting approval
     *   ?status=approved         → already-approved workflows
     *   ?status=rejected         → already-rejected workflows
     *   (no status)              → all workflows
     *   ?workflow_type=...        → result_publication | exam_publication
     *   ?resource_type=...        → filter by the resource's morph alias
     *   ?resource_id=...          → workflows for one specific resource
     *   ?per_page=...             → page size (default 15, max 100)
     *
     * Ownership scoping (Separation of Duties):
     *   - Actors with workflows.approve (Tenant Admin) see every workflow
     *     in the tenant — they are the independent reviewer and must be
     *     able to find anything awaiting a decision.
     *   - Actors WITHOUT workflows.approve, i.e. initiator-only roles like
     *     Technical Evaluator, are always scoped to workflows THEY
     *     initiated. This is enforced server-side (initiated_by_user_id is
     *     forced to the authenticated user's id) — it cannot be widened
     *     or bypassed via query params.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalWorkflow::class);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,approved,rejected',
            'workflow_type' => 'sometimes|string|in:result_publication,exam_publication',
            'resource_type' => 'sometimes|string',
            'resource_id' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $tenantId = (string) tenant()->getKey();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $canApprove = $this->auth->userHasPermission(
            $tenantId,
            (string) $request->user()->id,
            'workflows.approve',
        );

        $workflows = $this->service->listWorkflows(
            $tenantId,
            [
                'status' => $validated['status'] ?? null,
                'workflow_type' => $validated['workflow_type'] ?? null,
                'resource_type' => $validated['resource_type'] ?? null,
                'resource_id' => $validated['resource_id'] ?? null,
                // Server-controlled — never taken from client input, so an
                // initiator can never widen this to see other people's workflows.
                'initiated_by_user_id' => $canApprove ? null : (string) $request->user()->id,
            ],
            $perPage,
        );

        return new JsonResponse(
            [
                'data' => ApprovalWorkflowResource::collection($workflows->items()),
                'meta' => [
                    'current_page' => $workflows->currentPage(),
                    'per_page' => $workflows->perPage(),
                    'total' => $workflows->total(),
                    'last_page' => $workflows->lastPage(),
                ],
            ],
            Response::HTTP_OK,
        );
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

    public function history(Request $request, string $workflowId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $workflow = $this->service->find($tenantId, $workflowId);

        if ($workflow === null) {
            return $this->notFound($workflowId);
        }

        $this->authorize('view', $workflow);

        $history = $workflow->history()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return new JsonResponse(
            [
                'data' => \App\Http\Resources\Workflows\WorkflowHistoryResource::collection($history->items()),
                'meta' => [
                    'current_page' => $history->currentPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'last_page' => $history->lastPage(),
                ],
            ],
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
            return $this->workflowError('invalid_workflow_state', $e->getMessage());
        }

        return new JsonResponse(
            ['data' => new ApprovalWorkflowResource($workflow)],
            Response::HTTP_OK,
        );
    }

    public function reject(RejectWorkflowRequest $request, string $workflowId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $workflow = $this->service->reject(
                $tenantId,
                $workflowId,
                (string) $request->user()->id,
                $request->reason(),
            );
        } catch (RuntimeException $e) {
            return $this->workflowError('invalid_workflow_state', $e->getMessage());
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

    private function workflowError(string $code, string $message): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}