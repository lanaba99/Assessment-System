<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Domains\Identity\Contracts\RoleManagementService;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\RoleRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\PaginatedIndexRequest;
use App\Http\Requests\Identity\UpdateRoleRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RoleManagementService $roleService,
        private readonly RoleRepository $roles,
    ) {
    }

    public function update(UpdateRoleRequest $request, string $roleId): JsonResponse
    {
        $actor = $request->user();
        $role = $this->loadOwnedRoleOr404($actor, $roleId);
        if ($role instanceof JsonResponse) {
            return $role;
        }

        $this->authorize('update', $role);

        $changes = $request->changes();
        if ($changes === []) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $role->fill($changes)->save();

        return new JsonResponse([
            'data' => [
                'role_id' => (string) $role->role_id,
                'role_name' => (string) $role->role_name,
                'role_category' => (string) $role->role_category,
                'description' => $role->description,
            ],
        ], Response::HTTP_OK);
    }

    public function index(PaginatedIndexRequest $request): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $roles = $this->roleService->listRoles($tenantId, $request->perPage());

        return new JsonResponse([
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
                'last_page' => $roles->lastPage(),
            ],
        ], Response::HTTP_OK);
    }

    public function assignToUser(Request $request, string $roleId, string $userId): JsonResponse
    {
        $actor = $request->user();
        $role = $this->loadOwnedRoleOr404($actor, $roleId);
        if ($role instanceof JsonResponse) {
            return $role;
        }

        $this->authorize('assignToUser', $role);

        $this->roleService->assignRoleToUser(
            tenantId: (string) tenant()->getKey(),
            userId: $userId,
            roleId: $roleId,
            assignedByUserId: (string) $actor->id,
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function removeFromUser(Request $request, string $roleId, string $userId): JsonResponse
    {
        $actor = $request->user();
        $role = $this->loadOwnedRoleOr404($actor, $roleId);
        if ($role instanceof JsonResponse) {
            return $role;
        }

        $this->authorize('assignToUser', $role);

        $this->roleService->removeRoleFromUser(
            tenantId: (string) tenant()->getKey(),
            userId: $userId,
            roleId: $roleId,
            removedByUserId: (string) $actor->id,
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return Role|JsonResponse
     */
    private function loadOwnedRoleOr404(?User $actor, string $roleId)
    {
        if ($actor === null) {
            return new JsonResponse([
                'error' => ['code' => 'not_authenticated', 'message' => 'Authentication required.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $role = $this->roles->findById((string) tenant()->getKey(), $roleId);

        if ($role === null) {
            return new JsonResponse([
                'error' => ['code' => 'role_not_found', 'message' => "Role {$roleId} not found."],
            ], Response::HTTP_NOT_FOUND);
        }

        return $role;
    }
}
