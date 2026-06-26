<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Contracts\UserManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @group Identity
 */

class IdentityController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userService,
        private readonly AuthorizationService $authorizationService,
        private readonly AuthenticationService $authenticationService,
    ) {
    }

    public function profile(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $profile = $this->userService->getProfile((string) tenant()->getKey(), (string) $actor->id);
        if ($profile === null) {
            return $this->error('profile_not_found', 'Profile not found.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $profile], Response::HTTP_OK);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        // updateProfile is the SELF path — service strips any authorization-bearing
        // field (user_type, status, department_id) regardless of what the
        // FormRequest let through, as defense-in-depth.
        $this->userService->updateProfile(
            tenantId: (string) tenant()->getKey(),
            userId: (string) $actor->id,
            changes: $request->changes(),
        );

        $profile = $this->userService->getProfile((string) tenant()->getKey(), (string) $actor->id);

        return new JsonResponse(['data' => $profile], Response::HTTP_OK);
    }

    public function permissions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $tenantId = (string) tenant()->getKey();
        $userId = (string) $actor->id;

        return new JsonResponse([
            'data' => [
                'permissions' => $this->authorizationService->listPermissionNamesForUser($tenantId, $userId),
                'roles' => $this->authorizationService->listRoleNamesForUser($tenantId, $userId),
            ],
        ], Response::HTTP_OK);
    }

    public function sessions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $sessions = $this->authenticationService->listSessionsForUser((string) tenant()->getKey(), (string) $actor->id);

        return new JsonResponse(['data' => $sessions], Response::HTTP_OK);
    }

    public function deleteSession(Request $request, string $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $revoked = $this->authenticationService->revokeSessionForUser(
            tenantId: (string) tenant()->getKey(),
            userId: (string) $actor->id,
            sessionId: $id,
        );

        if (! $revoked) {
            return $this->error('session_not_found', 'Session not found.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function deleteAllSessions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $this->authenticationService->revokeAllSessionsForUser((string) tenant()->getKey(), (string) $actor->id);

        // Wholesale session revocation must end every bearer token too,
        // otherwise sessions are closed but holders of any prior token can
        // continue to authenticate.
        $actor->tokens()->delete();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
