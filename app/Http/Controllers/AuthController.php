<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\DTOs\AuthenticationResult;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Exceptions\MfaVerificationFailedException;
use App\Domains\Identity\Models\User;
use App\Http\Requests\Identity\LoginRequest;
use App\Http\Resources\AuthenticationResultResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authService,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->attemptLogin(
                tenantId: $request->tenantId(),
                emailOrEmployeeId: $request->emailOrEmployeeId(),
                plaintextPassword: $request->password(),
                ipAddress: (string) $request->ip(),
                userAgent: (string) $request->userAgent(),
            );
        } catch (AuthenticationFailedException $e) {
            return $this->authError($e->reasonCode, $e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }

        $response = AuthenticationResultResource::make($result)
            ->response()
            ->setStatusCode(Response::HTTP_OK);

        if ($result->status === AuthenticationResult::STATUS_AUTHENTICATED && $result->userId !== null) {
            $user = User::query()->find($result->userId);
            if ($user !== null) {
                $token = $user->createToken('api')->plainTextToken;
                $payload = $response->getData(true);
                $payload['data']['token'] = $token;
                $response->setData($payload);
            }
        }

        return $response;
    }

    public function verifyMfa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'one_time_code' => ['required', 'string', 'max:32'],
        ]);

        try {
            $result = $this->authService->verifyMfaForSession(
                tenantId: (string) tenant()->getKey(),
                sessionId: (string) $validated['session_id'],
                oneTimeCode: (string) $validated['one_time_code'],
            );
        } catch (MfaVerificationFailedException $e) {
            return $this->authError($e->reasonCode, $e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (AuthenticationFailedException $e) {
            return $this->authError($e->reasonCode, $e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }

        return AuthenticationResultResource::make($result)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $sessionId = (string) $request->input('session_id', '');
        if ($sessionId === '') {
            return $this->authError('missing_session_id', 'session_id is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->authService->logout((string) tenant()->getKey(), $sessionId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function refresh(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->authError('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $sessionId = (string) $request->input('session_id', '');
        if ($sessionId === '') {
            return $this->authError('missing_session_id', 'session_id is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->authService->refreshSessionActivity((string) tenant()->getKey(), $sessionId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function authError(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
