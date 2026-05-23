<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Identity\Contracts\UserManagementService;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\UserRepository;
use App\Http\Requests\Identity\RegisterRequest;
use App\Http\Requests\Identity\ResetPasswordRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        $newUserId = $this->userService->createUser(
            tenantId: $tenantId,
            email: $request->emailValue(),
            plaintextPassword: $request->plaintextPassword(),
            profile: $request->profile(),
            createdByUserId: (string) $actor->id,
        );

        return new JsonResponse([
            'data' => [
                'user_id' => $newUserId,
                'tenant_id' => $tenantId,
            ],
        ], Response::HTTP_CREATED);
    }

    public function resetPassword(ResetPasswordRequest $request, string $userId): JsonResponse
    {
        $actor = $request->user();
        $target = $this->loadOwnedUserOr404($actor, $userId);
        if ($target instanceof JsonResponse) {
            return $target;
        }

        $this->authorize('resetPassword', $target);

        $this->userService->resetPassword(
            tenantId: (string) tenant()->getKey(),
            userId: $userId,
            newPlaintextPassword: $request->newPassword(),
            resetByUserId: (string) $actor->id,
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
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

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
