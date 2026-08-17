<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Concerns\BuildsApiResponses;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettingsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group TenantSettings
 */
class TenantSettingsController extends Controller
{
    use BuildsApiResponses;
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

        if (! app(\App\Domains\Identity\Policies\TenantSettingsPolicy::class)->manage($actor)) {
            return $this->error('forbidden', 'You do not have permission to view tenant settings.', Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(
            ['data' => new TenantSettingsResource(tenant())],
            Response::HTTP_OK,
        );
    }

    public function update(UpdateTenantSettingsRequest $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->error('not_authenticated', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if (! app(\App\Domains\Identity\Policies\TenantSettingsPolicy::class)->manage($actor)) {
            return $this->error('forbidden', 'You do not have permission to update tenant settings.', Response::HTTP_FORBIDDEN);
        }

        tenant()->update($request->validated());

        return new JsonResponse(
            ['data' => new TenantSettingsResource(tenant()->fresh())],
            Response::HTTP_OK,
        );
    }
}