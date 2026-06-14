<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Domains\Central\Services\CentralAuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\CentralLoginRequest;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly CentralAuthService $authService,
    ) {
    }

    public function login(CentralLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                (string) $request->validated('email'),
                (string) $request->validated('password'),
            );
        } catch (RuntimeException $e) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_credentials', 'message' => $e->getMessage()]],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new JsonResponse([
            'data' => [
                'scope' => 'central',
                'token' => $result->token,
                'admin_user_id' => $result->adminUserId,
                'email' => $result->email,
                'first_name' => $result->firstName,
                'last_name' => $result->lastName,
            ],
        ], Response::HTTP_OK);
    }
}
