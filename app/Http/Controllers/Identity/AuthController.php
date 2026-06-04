<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\DTOs\AuthenticationResult;
use App\Domains\Identity\Exceptions\AuthenticationFailedException;
use App\Domains\Identity\Exceptions\InvalidInviteTokenException;
use App\Domains\Identity\Exceptions\MfaVerificationFailedException;
use App\Domains\Identity\Exceptions\PasswordPolicyViolationException;
use App\Domains\Identity\Repositories\UserRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\AcceptInviteRequest;
use App\Http\Requests\Identity\ForgotPasswordRequest;
use App\Http\Requests\Identity\LoginRequest;
use App\Http\Requests\Identity\LogoutRequest;
use App\Http\Requests\Identity\RefreshSessionRequest;
use App\Http\Requests\Identity\ResetForgottenPasswordRequest;
use App\Http\Requests\Identity\VerifyMfaRequest;
use App\Http\Resources\AuthenticationResultResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authService,
        private readonly UserRepository $users,
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

        if ($result->status === AuthenticationResult::STATUS_AUTHENTICATED && $result->user !== null) {
            $token = $result->user->createToken('api')->plainTextToken;
            $payload = $response->getData(true);
            $payload['data']['token'] = $token;
            $response->setData($payload);
        }

        return $response;
    }

    public function verifyMfa(VerifyMfaRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->verifyMfaForSession(
                tenantId: (string) tenant()->getKey(),
                sessionId: $request->sessionIdValue(),
                oneTimeCode: $request->oneTimeCodeValue(),
            );
        } catch (MfaVerificationFailedException $e) {
            return $this->authError($e->reasonCode, $e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (AuthenticationFailedException $e) {
            return $this->authError($e->reasonCode, $e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }

        $response = AuthenticationResultResource::make($result)
            ->response()
            ->setStatusCode(Response::HTTP_OK);

        if ($result->status === AuthenticationResult::STATUS_AUTHENTICATED && $result->user !== null) {
            $token = $result->user->createToken('api')->plainTextToken;
            $payload = $response->getData(true);
            $payload['data']['token'] = $token;
            $response->setData($payload);
        }

        return $response;
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->authError('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $this->authService->logout(
            tenantId: (string) tenant()->getKey(),
            sessionId: $request->sessionIdValue(),
            userId: (string) $actor->id,
        );

        // Containment: the bearer token that just authenticated this request
        // must not survive logout. Without this the session row closes but
        // the token keeps working until natural expiry.
        $currentToken = $actor->currentAccessToken();
        if ($currentToken !== null && method_exists($currentToken, 'delete')) {
            $currentToken->delete();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function refresh(RefreshSessionRequest $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->authError('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $tenantId = (string) tenant()->getKey();

        $this->authService->refreshSessionActivity(
            tenantId: $tenantId,
            sessionId: $request->sessionIdValue(),
            userId: (string) $actor->id,
        );

        // Bearer-token rotation: invalidate the inbound token and issue a
        // fresh one. Short-lived rotation narrows the blast radius of a
        // leaked token versus an indefinitely valid one.
        $currentToken = $actor->currentAccessToken();
        if ($currentToken !== null && method_exists($currentToken, 'delete')) {
            $currentToken->delete();
        }

        $newToken = $actor->createToken('api')->plainTextToken;

        return new JsonResponse([
            'data' => [
                'token' => $newToken,
                'session_id' => $request->sessionIdValue(),
            ],
        ], Response::HTTP_OK);
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
            return $this->authError(
                'password_policy_violation',
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['violations' => $e->violations],
            );
        }

        $user = $this->users->findById($tenantId, $userId);
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
            return $this->authError(
                'password_policy_violation',
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['violations' => $e->violations],
            );
        }

        if (! $reset) {
            return $this->authError(
                code: 'invalid_reset_token',
                message: 'The password reset token is invalid or expired.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Unified API error envelope: { "error": { "code", "message", ...extras } }.
     * Every error path in this controller routes through here so login,
     * password-reset, invite-accept, etc. respond with the same shape.
     *
     * @param  array<string, mixed>  $extras
     */
    private function authError(string $code, string $message, int $status, array $extras = []): JsonResponse
    {
        return new JsonResponse([
            'error' => array_merge([
                'code' => $code,
                'message' => $message,
            ], $extras),
        ], $status);
    }
}
