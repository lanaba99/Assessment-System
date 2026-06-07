<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cohorts;

use App\Domains\Cohorts\Contracts\CohortManagementService;
use App\Domains\Cohorts\Contracts\CohortMemberService;
use App\Domains\Cohorts\Exceptions\CohortNotFoundException;
use App\Domains\Cohorts\Exceptions\DuplicateMemberException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cohorts\AddMemberRequest;
use App\Http\Resources\Cohorts\CohortMemberResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CohortMemberController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CohortManagementService $cohorts,
        private readonly CohortMemberService $members,
    ) {
    }

    public function index(Request $request, string $cohortId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $cohort = $this->cohorts->getCohort($tenantId, $cohortId);
        } catch (CohortNotFoundException $e) {
            return $this->errorResponse('cohort_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $cohort);

        $members = $this->members->listMembers($cohortId);

        return new JsonResponse(
            ['data' => CohortMemberResource::collection($members)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function store(AddMemberRequest $request, string $cohortId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $cohort = $this->cohorts->getCohort($tenantId, $cohortId);
        } catch (CohortNotFoundException $e) {
            return $this->errorResponse('cohort_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('manageMembers', $cohort);

        try {
            $member = $this->members->addMember(
                $request->toCommand($tenantId, $cohortId),
            );
        } catch (DuplicateMemberException $e) {
            return $this->errorResponse('duplicate_member', $e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            ['data' => new CohortMemberResource($member)],
            Response::HTTP_CREATED,
        );
    }

    public function destroy(Request $request, string $cohortId, string $userId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $cohort = $this->cohorts->getCohort($tenantId, $cohortId);
        } catch (CohortNotFoundException $e) {
            return $this->errorResponse('cohort_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        $this->authorize('manageMembers', $cohort);

        try {
            $this->members->removeMember($tenantId, $cohortId, $userId);
        } catch (CohortNotFoundException $e) {
            return $this->errorResponse('member_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
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
