<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\DTOs\AuthenticationResult;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Exceptions\InvalidInviteTokenException;
use App\Domains\Identity\Exceptions\MfaVerificationFailedException;
use App\Domains\Identity\Exceptions\PasswordPolicyViolationException;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\AcceptInviteRequest;
use App\Http\Requests\Identity\ForgotPasswordRequest;
use App\Http\Requests\Identity\LoginRequest;
use App\Http\Requests\Identity\ResetForgottenPasswordRequest;
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

    public function acceptInvite(AcceptInviteRequest $request): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        try {
            $userId = $this->authService->acceptInvite(
                tenantId: $tenantId,
                email: $request->emailValue(),
                token: $request->tokenValue(),
                plaintextPassword: $request->passwordValue(),
            );
        } catch (InvalidInviteTokenException $e) {
            return $this->authError('invalid_invite_token', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (PasswordPolicyViolationException $e) {
            return new JsonResponse([
                'error' => [
                    'code' => 'password_policy_violation',
                    'message' => $e->getMessage(),
                    'violations' => $e->violations,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::query()->where('tenant_id', $tenantId)->find($userId);
        if ($user === null) {
            return $this->authError('user_not_found', 'Activated user could not be loaded.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $token = $user->createToken('api')->plainTextToken;

        return new JsonResponse([
            'data' => [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'status' => 'active',
                'token' => $token,
            ],
        ], Response::HTTP_OK);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->requestPasswordReset((string) tenant()->getKey(), $request->emailValue());

        return new JsonResponse([
            'data' => [
                'message' => 'If the account exists, a password reset link has been sent.',
            ],
        ], Response::HTTP_ACCEPTED);
    }

    public function resetPassword(ResetForgottenPasswordRequest $request): JsonResponse
    {
        try {
            $reset = $this->authService->resetPasswordWithToken(
                tenantId: (string) tenant()->getKey(),
                email: $request->emailValue(),
                token: $request->tokenValue(),
                newPassword: $request->passwordValue(),
            );
        } catch (PasswordPolicyViolationException $e) {
            return new JsonResponse([
                'error' => [
                    'code' => 'password_policy_violation',
                    'message' => $e->getMessage(),
                    'violations' => $e->violations,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $reset) {
            return $this->authError(
                code: 'invalid_reset_token',
                message: 'The password reset token is invalid or expired.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

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
