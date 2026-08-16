<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettingsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantSettingsController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->auth->userHasPermission((string) tenant()->getKey(), (string) $actor->id, 'tenant.manage')) {
            return $this->error('forbidden', 'You do not have permission to view tenant settings.', Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(
            ['data' => new TenantSettingsResource(tenant())],
            Response::HTTP_OK,
        );
    }

    public function update(UpdateTenantSettingsRequest $request): JsonResponse
    {
        tenant()->update($request->validated());

        return new JsonResponse(
            ['data' => new TenantSettingsResource(tenant()->fresh())],
            Response::HTTP_OK,
        );
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}