<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Domains\Identity\Contracts\UserManagementService;
use App\Domains\Identity\Exceptions\PasswordPolicyViolationException;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\UserRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\InviteUserRequest;
use App\Http\Requests\Identity\PaginatedIndexRequest;
use App\Http\Requests\Identity\RegisterRequest;
use App\Http\Requests\Identity\ResetPasswordRequest;
use App\Http\Requests\Identity\UpdateUserByAdminRequest; // added new 2nd/7 - lanaz 
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;


/**
 * @group User
 */

class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserManagementService $userService,
        private readonly UserRepository $users,
    ) {
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        // FormRequest's authorize() has already enforced UserPolicy@create;
        // controller-level authorize() repeats the check for defense in depth.
        $this->authorize('create', User::class);

        $actor = $request->user();
        $tenantId = (string) tenant()->getKey();

        try {
            $newUserId = $this->userService->createUser(
                tenantId: $tenantId,
                email: $request->emailValue(),
                plaintextPassword: $request->plaintextPassword(),
                profile: $request->profile(),
                createdByUserId: (string) $actor->id,
            );
        } catch (PasswordPolicyViolationException $e) {
            return $this->error(
                'password_policy_violation',
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['violations' => $e->violations],
            );
        }

        return new JsonResponse([
            'data' => [
                'user_id' => $newUserId,
                'tenant_id' => $tenantId,
            ],
        ], Response::HTTP_CREATED);
    }

    public function index(PaginatedIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $tenantId = (string) tenant()->getKey();
        $users = $this->userService->listUsers($tenantId, $request->perPage());

        return new JsonResponse([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(Request $request, string $userId): JsonResponse
    {
        $actor = $request->user();
        $target = $this->loadOwnedUserOr404($actor, $userId);
        if ($target instanceof JsonResponse) {
            return $target;
        }

        $this->authorize('view', $target);

        $profile = $this->userService->getProfile((string) tenant()->getKey(), $userId);

        return new JsonResponse(['data' => $profile], Response::HTTP_OK);
    }


    public function update(UpdateUserByAdminRequest $request, string $userId): JsonResponse
    {
        $actor = $request->user();
        $target = $this->loadOwnedUserOr404($actor, $userId);
        if ($target instanceof JsonResponse) {
            return $target;
        }

        // CRITICAL: UpdateUserByAdminRequest::authorize() always returns true —
        // this is the ONLY authorization check. Do not remove.
        $this->authorize('update', $target);

        $this->userService->updateProfileByAdmin(
            tenantId: (string) tenant()->getKey(),
            userId: $userId,
            changes: $request->changes(),
        );

        $profile = $this->userService->getProfile((string) tenant()->getKey(), $userId);

        return new JsonResponse(['data' => $profile], Response::HTTP_OK);
    }

    public function resetPassword(ResetPasswordRequest $request, string $userId): JsonResponse
    {
        $actor = $request->user();
        $target = $this->loadOwnedUserOr404($actor, $userId);
        if ($target instanceof JsonResponse) {
            return $target;
        }

        $this->authorize('resetPassword', $target);

        try {
            $this->userService->resetPassword(
                tenantId: (string) tenant()->getKey(),
                userId: $userId,
                newPlaintextPassword: $request->newPassword(),
                resetByUserId: (string) $actor->id,
            );
        } catch (PasswordPolicyViolationException $e) {
            return $this->error(
                'password_policy_violation',
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['violations' => $e->violations],
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function invite(InviteUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $actor = $request->user();
        $tenantId = (string) tenant()->getKey();

        try {
            $result = $this->userService->inviteUser(
                tenantId: $tenantId,
                email: $request->emailValue(),
                profile: $request->profile(),
                invitedByUserId: (string) $actor->id,
            );
        } catch (RuntimeException $e) {
            return $this->error('user_already_exists', $e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse([
            'data' => [
                'user_id' => $result->userId,
                'tenant_id' => $tenantId,
                'invite_token' => $result->inviteToken,
                'status' => 'pending',
            ],
        ], Response::HTTP_CREATED);
    }

    public function deactivate(Request $request, string $userId): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $target = $this->loadOwnedUserOr404($actor, $userId);
        if ($target instanceof JsonResponse) {
            return $target;
        }

        $this->authorize('deactivate', $target);

        $this->userService->deactivateUser(
            tenantId: (string) tenant()->getKey(),
            userId: $userId,
            deactivatedByUserId: (string) $actor->id,
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return User|JsonResponse  User if found in actor's tenant; 404 JsonResponse otherwise.
     */
    private function loadOwnedUserOr404(?User $actor, string $userId)
    {
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $target = $this->users->findById((string) tenant()->getKey(), $userId);

        if ($target === null) {
            return $this->error('user_not_found', "User {$userId} not found.", Response::HTTP_NOT_FOUND);
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    private function error(string $code, string $message, int $status, array $extras = []): JsonResponse
    {
        return new JsonResponse([
            'error' => array_merge(['code' => $code, 'message' => $message], $extras),
        ], $status);
    }
}
