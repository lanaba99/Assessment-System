<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rules;

use App\Domains\Rules\Models\EligibilityChain;
use App\Domains\Rules\Repositories\EligibilityChainRepository;
use App\Domains\Rules\Services\EligibilityChainManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rules\StoreEligibilityChainRequest;
use App\Http\Requests\Rules\UpdateEligibilityChainRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Eligibility
 */
class EligibilityChainController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EligibilityChainManagementService $chains,
        private readonly EligibilityChainRepository $chainRepository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EligibilityChain::class);

        $examId = (string) $request->query('exam_id');
        $tenantId = (string) tenant()->getKey();

        $chains = $this->chains->listForExam($tenantId, $examId);

        return new JsonResponse([
            'data' => $chains->values(),
        ], Response::HTTP_OK);
    }

    public function store(StoreEligibilityChainRequest $request): JsonResponse
    {
        // FormRequest::authorize() already enforced EligibilityPolicy@create;
        // repeated here for defense in depth, matching UserController convention.
        $this->authorize('create', EligibilityChain::class);

        $actor = $request->user();
        $tenantId = (string) tenant()->getKey();

        $chain = $this->chains->create(
            tenantId: $tenantId,
            createdByUserId: (string) $actor->id,
            data: $request->validated(),
        );

        return new JsonResponse(['data' => $chain], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $chainId): JsonResponse
    {
        $chain = $this->loadOwnedChainOr404($chainId);
        if ($chain instanceof JsonResponse) {
            return $chain;
        }

        $this->authorize('view', $chain);

        return new JsonResponse(['data' => $chain], Response::HTTP_OK);
    }

    public function update(UpdateEligibilityChainRequest $request, string $chainId): JsonResponse
    {
        $chain = $this->loadOwnedChainOr404($chainId);
        if ($chain instanceof JsonResponse) {
            return $chain;
        }

        // CRITICAL: UpdateEligibilityChainRequest::authorize() always returns true —
        // this is the ONLY authorization check for update. Do not remove.
        $this->authorize('update', $chain);

        $updated = $this->chains->update($chain, $request->validated());

        return new JsonResponse(['data' => $updated], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $chainId): JsonResponse
    {
        $chain = $this->loadOwnedChainOr404($chainId);
        if ($chain instanceof JsonResponse) {
            return $chain;
        }

        $this->authorize('delete', $chain);

        $this->chains->delete($chain);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return EligibilityChain|JsonResponse  Chain if found in actor's tenant; 404 otherwise.
     */
    private function loadOwnedChainOr404(string $chainId)
    {
        $tenantId = (string) tenant()->getKey();
        $chain = $this->chainRepository->findById($tenantId, $chainId);

        if ($chain === null) {
            return new JsonResponse([
                'error' => [
                    'code' => 'eligibility_chain_not_found',
                    'message' => "Eligibility chain {$chainId} not found.",
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        return $chain;
    }
}