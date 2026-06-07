<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cohorts;

use App\Domains\Cohorts\Contracts\CohortManagementService;
use App\Domains\Cohorts\Exceptions\CohortNotFoundException;
use App\Domains\Cohorts\Exceptions\CohortNotEmptyException;
use App\Domains\Cohorts\Models\Cohort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cohorts\StoreCohortRequest;
use App\Http\Requests\Cohorts\UpdateCohortRequest;
use App\Http\Resources\Cohorts\CohortResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CohortController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CohortManagementService $cohorts,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Cohort::class);

        $tenantId = (string) tenant()->getKey();
        $cohorts = $this->cohorts->listCohorts($tenantId);

        return new JsonResponse(
            ['data' => CohortResource::collection($cohorts)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function store(StoreCohortRequest $request): JsonResponse
    {
        $this->authorize('create', Cohort::class);

        $tenantId = (string) tenant()->getKey();
        $cohort = $this->cohorts->createCohort(
            $request->toCommand($tenantId, (string) $request->user()->id),
        );

        return new JsonResponse(
            ['data' => new CohortResource($cohort)],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $cohortId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $cohort = $this->cohorts->getCohort($tenantId, $cohortId);
        } catch (CohortNotFoundException $e) {
            return $this->errorResponse('cohort_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $cohort);

        return new JsonResponse(
            ['data' => new CohortResource($cohort)],
            Response::HTTP_OK,
        );
    }

    public function update(UpdateCohortRequest $request, string $cohortId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $cohort = $this->cohorts->getCohort($tenantId, $cohortId);
        } catch (CohortNotFoundException $e) {
            return $this->errorResponse('cohort_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $cohort);

        $updated = $this->cohorts->updateCohort($tenantId, $cohortId, $request->toCommand());

        return new JsonResponse(
            ['data' => new CohortResource($updated)],
            Response::HTTP_OK,
        );
    }

    public function destroy(Request $request, string $cohortId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $cohort = $this->cohorts->getCohort($tenantId, $cohortId);
        } catch (CohortNotFoundException $e) {
            return $this->errorResponse('cohort_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('delete', $cohort);

        try {
            $this->cohorts->deleteCohort($tenantId, $cohortId);
        } catch (CohortNotEmptyException $e) {
            return $this->errorResponse('cohort_not_empty', $e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            $status,
        );
    }
}
